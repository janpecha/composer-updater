<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;

	use Nette;
	use Nette\Utils\Arrays;


	class MemoryBridge implements IComposerBridge
	{
		/** @var array<string, array<string, array<string, string>>> */
		private $repository = [];

		/** @var string */
		private $type;

		/** @var array<string, string> */
		private $composerFile = [];

		/** @var array<string, string> */
		private $lockFile;

		/** @var array<string, string> */
		private $latestVersions = [];


		/**
		 * @param array<string, array<string, array<string, string>>> $repository
		 * @param array<string, string> $composerFile
		 * @param array<string, string>|NULL $lockFile
		 */
		public function __construct(
			array $repository,
			string $type,
			array $composerFile,
			?array $lockFile
		)
		{
			foreach ($repository as $packageName => $versions) {
				if (count($versions) === 0) {
					throw new \RuntimeException("Missing versions for package '$packageName' in repository.");
				}
			}

			$this->repository = $repository;
			$this->type = $type;

			foreach ($composerFile as $packageName => $constraint) {
				if (!isset($this->repository[$packageName])) {
					throw new \RuntimeException("Package '$packageName' is not in package repository.");
				}

				$this->composerFile[$packageName] = $constraint;
			}

			if ($lockFile !== NULL) {
				foreach ($lockFile as $packageName => $currentVersion) {
					if (!isset($this->repository[$packageName])) {
						throw new \RuntimeException("Package '$packageName' is not in package repository.");
					}

					if (!isset($this->repository[$packageName][$currentVersion])) {
						throw new \RuntimeException("Version '$currentVersion' of package '$packageName' is not in package repository.");
					}

					$this->lockFile[$packageName] = $currentVersion;
				}
			}
		}


		public function getType(): string
		{
			return $this->type;
		}


		public function existsLockFile(): bool
		{
			return $this->lockFile !== NULL;
		}


		public function getOutdated(): array
		{
			if ($this->lockFile === NULL) {
				return [];
			}

			$result = [];

			foreach ($this->lockFile as $packageName => $currentVersion) {
				$latestVersion = $this->getLatestVersion($packageName);

				if ($currentVersion !== $latestVersion) {
					$result[] = new Package($packageName, $this->getPackageConstraint($packageName), $currentVersion, $latestVersion);
				}
			}

			return $result;
		}


		public function getVersions(string $package): array
		{
			return array_keys($this->repository[$package]);
		}


		public function runComposerInstall(): void
		{
			$this->lockFile = $this->getOrInstallLockFile();
		}


		public function runComposerUpdate(bool $withAllDependencies): bool
		{
			$composerFile = $this->composerFile;
			$lockFile = $this->getOrInstallLockFile();
			$newLockFile = $this->tryUpdatePackages($composerFile, $lockFile, $withAllDependencies);

			if ($newLockFile === NULL) {
				return FALSE;
			}

			$this->composerFile = $composerFile;
			$this->lockFile = $newLockFile;
			return $this->wasLockUpdated($lockFile, $newLockFile);;
		}


		public function requirePackageWithoutUpdate(string $package, string $constraint): void
		{
			if (!isset($this->repository[$package])) {
				throw new \RuntimeException("Missing package '$package' in repository.");
			}

			$this->composerFile[$package] = $constraint;
		}


		public function tryRequirePackage(string $package, string $constraint, bool $dryRun): bool
		{
			$composerFile = $this->composerFile;
			$composerFile[$package] = $constraint;
			$lockFile = $this->lockFile !== NULL ? $this->lockFile : [];
			$newLockFile = $this->tryUpdatePackages($composerFile, $lockFile, TRUE);

			if ($newLockFile === NULL) {
				return FALSE;
			}

			if (!$dryRun) {
				$this->composerFile = $composerFile;
				$this->lockFile = $newLockFile;
			}

			return $this->wasLockUpdated($lockFile, $newLockFile);
		}


		public function getPackageConstraint(string $package): string
		{
			$constraint = Arrays::get($this->composerFile, $package);
			assert(is_string($constraint));
			return $constraint;
		}


		/**
		 * @return array<string, string>
		 */
		public function getInstalledVersions(): array
		{
			if ($this->lockFile === NULL) {
				throw new \RuntimeException('Missing lock file.');
			}

			$lockFile = $this->lockFile;
			ksort($lockFile, SORT_STRING);
			return $lockFile;
		}


		/**
		 * @param  array<string, string> $composerFile
		 * @param  array<string, string> $lockFile
		 * @return array<string, string>
		 */
		private function tryUpdatePackages(
			array $composerFile,
			array $lockFile,
			bool $withAllDependencies
		): ?array
		{
			$posibilities = [];
			$packagesToUpdate = array_keys($composerFile);

			// fill from lock file
			if (!$withAllDependencies) {
				foreach ($lockFile as $packageName => $version) {
					$posibilities[$packageName] = [$version];
				}
			}

			// fill from composer.json
			foreach ($composerFile as $packageName => $constraint) {
				$posibilities[$packageName] = \Composer\Semver\Semver::rsort(array_keys($this->repository[$packageName]));
			}

			$posibilities = $this->filterPosibilities($posibilities, $composerFile);

			foreach ($packagesToUpdate as $packageName) {
				if (!isset($posibilities[$packageName]) || count($posibilities[$packageName]) === 0) {
					return NULL;
				}

				$wasFound = FALSE;

				foreach ($posibilities[$packageName] as $candidate) {
					$installed = $this->canBeInstalled($packageName, $candidate, $posibilities);

					if ($installed !== NULL) {
						$wasFound = TRUE;
						$posibilities[$packageName] = [$candidate];

						foreach ($installed as $dependend => $dependendPosibilities) {
							$posibilities[$dependend] = $dependendPosibilities;
						}
					}
				}

				if (!$wasFound) {
					return NULL;
				}
			}

			$newLockFile = [];

			foreach ($posibilities as $packageName => $candidates) {
				if (count($candidates) === 0) {
					return NULL;
				}

				foreach ($candidates as $candidate) {
					$newLockFile[$packageName] = $candidate;
					break;
				}
			}

			return $newLockFile;
		}


		/**
		 * @param  array<string, string[]> $posibilities
		 * @return array<string, string[]>|NULL
		 */
		private function canBeInstalled(string $packageName, string $version, array $posibilities): ?array
		{
			if (!isset($this->repository[$packageName][$version])) {
				return NULL;
			}

			if (isset($posibilities[$packageName]) && !in_array($version, $posibilities[$packageName], TRUE)) {
				return NULL;
			}

			$dependencies = $this->repository[$packageName][$version];

			foreach ($dependencies as $dependency => $constraint) {
				if (!isset($posibilities[$dependency])) {
					$posibilities[$dependency] = \Composer\Semver\Semver::rsort(array_keys($this->repository[$dependency]));
				}
			}

			$posibilities = $this->filterPosibilities($posibilities, $dependencies);

			foreach ($dependencies as $dependency => $constraint) {
				if (!isset($posibilities[$dependency])) {
					return NULL;
				}

				if (count($posibilities[$dependency]) === 0) {
					return NULL;
				}
			}

			return $posibilities;
		}


		/**
		 * @param  array<string, string[]> $posibilities
		 * @param  array<string, string> $composerFile
		 * @return array<string, string[]>
		 */
		private function filterPosibilities(
			array $posibilities,
			array $composerFile
		): array
		{
			foreach ($composerFile as $packageName => $constraint) {
				if (!isset($posibilities[$packageName]) || count($posibilities[$packageName]) === 0) {
					continue;
				}

				$posibilities[$packageName] = \Composer\Semver\Semver::satisfiedBy($posibilities[$packageName], $constraint);
			}

			return $posibilities;
		}


		/**
		 * @param  array<string, string> $old
		 * @param  array<string, string> $new
		 */
		private function wasLockUpdated(array $old, array $new): bool
		{
			$diff = array_diff_assoc($old, $new);
			return count($diff) > 0;
		}


		/**
		 * @return array<string, string>
		 */
		private function getOrInstallLockFile(): array
		{
			if ($this->lockFile === NULL) {
				$lockFile = [];

				foreach ($this->composerFile as $packageName => $constrait) {
					$this->tryInstallToLock($lockFile, $packageName, $constrait);
				}

				$this->lockFile = $lockFile;
			}

			return $this->lockFile;

		}


		/**
		 * @param  array<string, string> &$lockFile
		 */
		private function tryInstallToLock(
			array &$lockFile,
			string $packageName,
			string $constraint
		): bool
		{
			if (isset($lockFile[$packageName])) {
				return \Composer\Semver\Semver::satisfies($lockFile[$packageName], $constraint);
			}

			$candidates = \Composer\Semver\Semver::satisfiedBy($this->getVersions($packageName), $constraint);
			$candidates = \Composer\Semver\Semver::rsort($candidates);

			if (count($candidates) === 0) {
				throw new \RuntimeException("Missing any version for package '$packageName'.");
			}

			$wasInstalled = FALSE;

			foreach ($candidates as $candidate) {
				$allOk = TRUE;

				foreach ($this->repository[$packageName][$candidate] as $dependency => $dependencyConstraint) {
					$allOk = $allOk && $this->tryInstallToLock($lockFile, $dependency, $dependencyConstraint);
				}

				if ($allOk) {
					$lockFile[$packageName] = $candidate;
					$wasInstalled = TRUE;
					break;
				}
			}

			return $wasInstalled;
		}


		private function getLatestVersion(string $package): string
		{
			if (!isset($this->latestVersions[$package])) {
				$versions = \Composer\Semver\Semver::rsort($this->getVersions($package));
				$latestVersion = Arrays::first($versions);

				if (!is_string($latestVersion)) {
					throw new \RuntimeException('Latest version not found.');
				}

				$this->latestVersions[$package] = $latestVersion;
			}

			return $this->latestVersions[$package];
		}
	}

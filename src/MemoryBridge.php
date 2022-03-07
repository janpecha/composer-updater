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
			$lockFile = $this->getOrInstallLockFile();
			$wasUpdated = FALSE;

			foreach ($this->composerFile as $packageName => $constraint) {
				$wasUpdated = $wasUpdated || $this->tryUpdatePackage($lockFile, $packageName, $constraint, $withAllDependencies);
			}

			$this->lockFile = $lockFile;
			return $wasUpdated;
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
			$lockFile = $this->lockFile !== NULL ? $this->lockFile : [];
			$wasUpdated = $this->tryUpdatePackage($lockFile, $package, $constraint, TRUE);

			if (!$dryRun) {
				$this->lockFile = $lockFile;
			}

			return $wasUpdated;
		}


		public function getPackageConstraint(string $package): string
		{
			$constraint = Arrays::get($this->composerFile, $package);
			assert(is_string($constraint));
			return $constraint;
		}


		public function getInstalledVersion(string $package): string
		{
			if ($this->lockFile === NULL) {
				throw new \RuntimeException('Missing lock file.');
			}

			$installedVersion = Arrays::get($this->lockFile, $package);
			assert(is_string($installedVersion));
			return $installedVersion;
		}


		/**
		 * @param  array<string, string> &$lockFile
		 */
		private function tryUpdatePackage(
			array &$lockFile,
			string $packageName,
			string $constraint,
			bool $withAllDependencies
		): bool
		{
			$candidates = \Composer\Semver\Semver::satisfiedBy($this->getVersions($packageName), $constraint);
			$candidates = \Composer\Semver\Semver::rsort($candidates);

			if (count($candidates) === 0) {
				return FALSE;
			}

			$wasUpdated = FALSE;

			foreach ($candidates as $candidate) {
				foreach ($this->repository[$packageName][$candidate] as $dependency => $dependencyConstraint) {
					if ($withAllDependencies) {
						$wasDepUpdated = $this->tryUpdatePackage($lockFile, $dependency, $dependencyConstraint, $withAllDependencies);

						if (!$wasDepUpdated) { // cannot be updated
							continue 2;
						}

					} elseif (!\Composer\Semver\Semver::satisfies($this->getInstalledVersion($dependency), $dependencyConstraint)) {
						continue;
					}
				}

				if (!isset($lockFile[$packageName]) || $candidate !== $lockFile[$packageName]) {
					$wasUpdated = TRUE;
				}

				$lockFile[$packageName] = $candidate;
				break;
			}

			return $wasUpdated;
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

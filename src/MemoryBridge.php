<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;

	use Nette;
	use Nette\Utils\Arrays;


	class MemoryBridge implements IComposerBridge
	{
		/** @var string */
		private $type;

		/** @var array<string, Package> */
		private $packages = [];

		/** @var bool */
		private $existsLockFile;

		/** @var array<string, array<string, array<string, string>>> */
		private $repository = [];


		/**
		 * @param Package[] $packages
		 * @param array<string, array<string, array<string, string>>> $repository
		 */
		public function __construct(
			string $type,
			array $packages,
			bool $existsLockFile,
			array $repository
		)
		{
			$this->type = $type;
			$this->existsLockFile = $existsLockFile;
			$this->repository = $repository;

			foreach ($packages as $package) {
				$packageName = $package->getName();

				if (!isset($this->repository[$packageName])) {
					throw new \RuntimeException("Package '$packageName' is not in package repository.");
				}

				if (!isset($this->repository[$packageName][$package->getCurrentVersion()])) {
					throw new \RuntimeException("Version '{$package->getCurrentVersion()}' of '$packageName' is not in repository.");
				}

				if (!isset($this->repository[$packageName][$package->getLatestVersion()])) {
					throw new \RuntimeException("Version '{$package->getLatestVersion()}' of '$packageName' is not in repository.");
				}

				$this->packages[$package->getName()] = $package;
			}
		}


		public function getType(): string
		{
			return $this->type;
		}


		public function getPackageConstraint(string $package): string
		{
			return $this->getPackage($package)->getConstraint();
		}


		public function existsLockFile(): bool
		{
			return $this->existsLockFile;
		}


		public function getOutdated(): array
		{
			$result = [];

			foreach ($this->packages as $package) {
				if ($package->getCurrentVersion() !== $package->getLatestVersion()) {
					$result[] = $package;
				}
			}

			return $result;
		}


		public function runComposerInstall(): void
		{
			$this->existsLockFile = TRUE;
		}


		public function runComposerUpdate(bool $withAllDependencies): bool
		{
			$this->existsLockFile = TRUE;
			$wasUpdated = FALSE;

			foreach ($this->packages as $package) {
				$wasUpdated = $wasUpdated || $this->tryUpdatePackage($package, $package->getConstraint(), $withAllDependencies, FALSE);
			}

			return $wasUpdated;
		}


		public function requirePackageWithoutUpdate(string $package, string $constraint): void
		{
			$p = $this->getPackage($package);
			$this->packages[$p->getName()] = new Package(
				$p->getName(),
				$constraint,
				$p->getCurrentVersion(),
				$p->getLatestVersion()
			);
		}


		public function tryRequirePackage(string $package, string $constraint, bool $dryRun): bool
		{
			return $this->tryUpdatePackage($this->getPackage($package), $constraint, TRUE, $dryRun);
		}


		public function getPackage(string $package): Package
		{
			$result = Arrays::get($this->packages, $package);
			assert($result instanceof Package);
			return $result;
		}


		private function tryUpdatePackage(
			Package $package,
			string $constraint,
			bool $withAllDependencies,
			bool $dryRun
		): bool
		{
			$packageName = $package->getName();
			$candidates = \Composer\Semver\Semver::satisfiedBy(array_keys($this->repository[$packageName]), $constraint);
			$candidates = \Composer\Semver\Semver::rsort($candidates);

			if (count($candidates) === 0) {
				return FALSE;
			}

			$wasUpdated = FALSE;

			foreach ($candidates as $candidate) {
				foreach ($this->repository[$packageName][$candidate] as $dependency => $dependencyConstraint) {
					if ($withAllDependencies) {
						$wasUpdated = $this->tryUpdatePackage($this->getPackage($dependency), $dependencyConstraint, $withAllDependencies, $dryRun);

						if (!$wasUpdated) { // cannot be updated
							continue;
						}

					} elseif (!\Composer\Semver\Semver::satisfies($this->getPackage($dependency)->getCurrentVersion(), $dependencyConstraint)) {
						continue;
					}
				}

				if (!$dryRun) {
					$this->packages[$packageName] = new Package(
						$packageName,
						$constraint,
						$candidate,
						$package->getLatestVersion()
					);
				}

				$wasUpdated = $candidate !== $package->getCurrentVersion();
				break;
			}

			return $wasUpdated;
		}
	}

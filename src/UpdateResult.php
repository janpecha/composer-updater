<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;


	class UpdateResult
	{
		/** @var array<string, string> */
		private $updatedPackages = [];

		/** @var array<string, string> */
		private $failedPackages = [];


		public function wasSomeUpdated(): bool
		{
			return count($this->updatedPackages) > 0;
		}


		/**
		 * @return array<string, string>
		 */
		public function getUpdatedPackages(): array
		{
			return $this->updatedPackages;
		}


		public function addUpdatedPackage(string $package, string $version): void
		{
			if (isset($this->updatedPackages[$package]) && $this->updatedPackages[$package] !== $version) {
				$old = $this->updatedPackages[$package];
				throw new \RuntimeException("Versions of updated package '$package' must be same ($old !== $version).");
			}

			$this->updatedPackages[$package] = $version;
		}


		public function hasFailedPackages(): bool
		{
			return count($this->failedPackages) > 0;
		}


		/**
		 * @return array<string, string>
		 */
		public function getFailedPackages(): array
		{
			return $this->failedPackages;
		}


		public function addFailedPackage(string $package, string $version): void
		{
			if (isset($this->failedPackages[$package]) && $this->failedPackages[$package] !== $version) {
				$old = $this->failedPackages[$package];
				throw new \RuntimeException("Versions of failed package '$package' must be same ($old !== $version).");
			}

			$this->failedPackages[$package] = $version;
		}


		public function mergeWith(self $subResult): void
		{
			foreach ($subResult->getFailedPackages() as $package => $version) {
				if (isset($this->updatedPackages[$package])) {
					continue;
				}

				$this->addFailedPackage($package, $version);
			}

			foreach ($subResult->getUpdatedPackages() as $package => $version) {
				unset($this->failedPackages[$package]);
				$this->addUpdatedPackage($package, $version);
			}
		}
	}

<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;

	use Nette\Utils\Arrays;
	use Nette\Utils\Strings;

	class Updater
	{
		/** @var IComposerBridge */
		private $composerBridge;

		/** @var \CzProject\PhpCli\Console */
		private $console;

		/** @var \Composer\Semver\VersionParser */
		private $versionParser;


		public function __construct(
			IComposerBridge $composerBridge,
			\CzProject\PhpCli\Console $console
		)
		{
			$this->composerBridge = $composerBridge;
			$this->console = $console;
			$this->versionParser = new \Composer\Semver\VersionParser;
		}


		public function run(bool $dryRun): bool
		{
			if ($dryRun) {
				$this->console->output('Dry-run mode')->nl();
			}

			$projectType = $this->composerBridge->getType();
			$result = FALSE;

			if ($projectType === 'library') {
				$result = $this->updateLibrary($dryRun);

			} elseif ($projectType === 'project') {
				$result = $this->updateProject($dryRun);

			} else {
				throw new \RuntimeException('Unknow "type: ' . $projectType . '" in composer.json.');
			}

			if ($result) {
				$this->console->output('Done.', \CzProject\PhpCli\Colors::GREEN)->nl();

			} else {
				$this->console->output('Failed.', \CzProject\PhpCli\Colors::RED)->nl();
			}

			return $result;
		}


		private function updateLibrary(bool $dryRun): bool
		{
			$this->tryComposerInstall($dryRun);

			$this->console->output('Updating library dependencies:')->nl();
			$outdated = $this->composerBridge->getOutdated();

			if (count($outdated) === 0) {
				$this->console->output(' - ', \CzProject\PhpCli\Colors::GRAY);
				$this->console->output('nothing to update.', \CzProject\PhpCli\Colors::YELLOW);
				$this->console->nl();
				return TRUE;
			}

			foreach ($outdated as $outdatedPackage) {
				if (Arrays::get($outdatedPackage, 'latest-status') !== 'update-possible') {
					continue;
				}

				$name = Arrays::get($outdatedPackage, 'name');
				$this->console->output(' - ', \CzProject\PhpCli\Colors::GRAY);
				$this->console->output($name, \CzProject\PhpCli\Colors::GREEN);

				$latestVersion = new \Composer\Semver\Constraint\Constraint('==', $this->versionParser->normalize(Arrays::get($outdatedPackage, 'latest')));

				$constraint = $this->versionParser->parseConstraints($this->composerBridge->getPackageConstraint($name));
				$newConstraint = $constraint;
				$upperBound = $constraint->getUpperBound();
				$retries = 5;

				while (!$newConstraint->matches($latestVersion)) {
					$newConstraint = $this->mergeConstraint($newConstraint, $this->createConstraintFromBound($upperBound));

					if ($newConstraint->matches($latestVersion)) {
						break;
					}

					$newUpperBound = $newConstraint->getUpperBound();

					if ($upperBound->getVersion() === $newUpperBound->getVersion()) {
						break;
					}

					$upperBound = $newUpperBound;
					$retries--;

					if ($retries <= 0) {
						break;
					}
				}

				$this->console->output(' => ', \CzProject\PhpCli\Colors::GRAY);
				$newVersion = $newConstraint->getPrettyString();

				if ($constraint->getPrettyString() === $newVersion) {
					$this->console->output('nothing to update', \CzProject\PhpCli\Colors::YELLOW);

				} else {
					$newVersion = Strings::replace($newVersion, '~\|\|?~', '||');
					$this->console->output($newVersion);

					if (!$dryRun) {
						$this->composerBridge->requirePackageWithoutUpdate($name, $newVersion);
					}
				}

				$this->console->nl();
			}

			$this->console->nl();
			return TRUE;
		}


		private function updateProject(bool $dryRun): bool
		{
			$this->tryComposerInstall($dryRun);

			$this->console->output('Updating project dependencies:')->nl();
			$outdated = $this->composerBridge->getOutdated();

			if (count($outdated) === 0) {
				$this->console->output(' - ', \CzProject\PhpCli\Colors::GRAY);
				$this->console->output('nothing to update.', \CzProject\PhpCli\Colors::YELLOW);
				$this->console->nl();
				return TRUE;
			}

			if (!$dryRun) {
				$this->console->output(' - ', \CzProject\PhpCli\Colors::GRAY);
				$this->console->output('running `composer update`', \CzProject\PhpCli\Colors::GREEN);
				$wasUpdated = FALSE;

				try {
					$wasUpdated = $this->composerBridge->runComposerUpdate(FALSE);

				} catch (\RuntimeException $e) {
					$this->console->output(' [FAILED]', \CzProject\PhpCli\Colors::RED);
				}


				if ($wasUpdated) {
					$this->console->output(' [UPDATED]')->nl();
					return TRUE;

				} else {
					$this->console->output(' [NOTHING TO UPDATE]')->nl();
				}
			}

			$this->console->output('Updating project constraints:')->nl();
			$packagesToUpdate = [];

			foreach ($outdated as $outdatedPackage) {
				$name = Arrays::get($outdatedPackage, 'name');
				assert(is_string($name));
				$this->console->output(' - ', \CzProject\PhpCli\Colors::GRAY);
				$this->console->output($name, \CzProject\PhpCli\Colors::GREEN);

				$latestVersion = $this->versionParser->normalize(Arrays::get($outdatedPackage, 'latest'));
				$latestVersionConstraint = $this->createConstraintFromVersion($latestVersion);
				$constraint = $this->versionParser->parseConstraints($this->composerBridge->getPackageConstraint($name));
				$newConstraint = $constraint;

				if (!$constraint->matches($latestVersionConstraint)) {
					$upperBound = $constraint->getUpperBound();

					if (!$upperBound->isInclusive() && \Composer\Semver\Comparator::lessThan($upperBound->getVersion(), $latestVersion)) {
						$newConstraint = $this->createConstraintFromBound($upperBound);

					} else {
						$newConstraint = $latestVersionConstraint;
					}
				}

				$this->console->output(' => ', \CzProject\PhpCli\Colors::GRAY);
				$newVersion = $newConstraint->getPrettyString();

				if ($constraint->getPrettyString() === $newVersion) {
					$this->console->output('nothing to update, stay on ' . $newVersion, \CzProject\PhpCli\Colors::GRAY);

				} else {
					$newVersion = Strings::replace($newVersion, '~\|\|?~', '||');
					$this->console->output($newVersion);
					$packagesToUpdate[$name] = $newVersion;
				}

				$this->console->nl();
			}

			if (count($packagesToUpdate) > 0) {
				$this->console->output('Apply updates:')->nl();
				$result = $this->tryUpdatePackages($packagesToUpdate, $dryRun);

				if ($result->hasFailedPackages()) {
					$this->console->output('Some packages was not updated due conflict issues:')->nl();

					foreach ($result->getFailedPackages() as $package => $newVersion) {
						$this->console->output(' - ', \CzProject\PhpCli\Colors::RED);
						$this->console->output($package, \CzProject\PhpCli\Colors::RED);
						$this->console->output(' => NOT updated to ', \CzProject\PhpCli\Colors::RED);
						$this->console->output($newVersion, \CzProject\PhpCli\Colors::RED);
						$this->console->nl();
					}
				}

				if (!$result->wasSomeUpdated()) {
					$this->console->nl();
					return FALSE;
				}
			}

			$this->console->nl();
			return TRUE;
		}


		private function tryComposerInstall(bool $dryRun): void
		{
			if (!$this->composerBridge->existsLockFile()) {
				if ($dryRun) {
					throw new \RuntimeException('Missing composer.lock & dry-run mode enabled. Run `composer install` manually');
				}

				$this->console->output('Missing composer.lock, running of `composer install`.', \CzProject\PhpCli\Colors::YELLOW)->nl();
				$this->composerBridge->runComposerInstall();
			}
		}


		/**
		 * @param  array<string, string> $packages
		 */
		private function tryUpdatePackages(array $packages, bool $dryRun): UpdateResult
		{
			$result = new UpdateResult;

			foreach ($packages as $package => $newVersion) {
				if ($this->composerBridge->tryRequirePackage($package, $newVersion, $dryRun)) {
					$result->addUpdatedPackage($package, $newVersion);
					$this->console->output(' - ', \CzProject\PhpCli\Colors::GRAY);
					$this->console->output($package, \CzProject\PhpCli\Colors::GREEN);
					$this->console->output(' => updated to ', \CzProject\PhpCli\Colors::GRAY);
					$this->console->output($newVersion);
					$this->console->nl();

				} else {
					$result->addFailedPackage($package, $newVersion);
				}
			}

			if ($result->wasSomeUpdated() && $result->hasFailedPackages()) { // try one round more
				$subResult = $this->tryUpdatePackages($result->getFailedPackages(), $dryRun);
				$result->mergeWith($subResult);
			}

			return $result;
		}


		private function createConstraintFromBound(\Composer\Semver\Constraint\Bound $bound): \Composer\Semver\Constraint\ConstraintInterface
		{
			return $this->createConstraintFromVersion($bound->getVersion());
		}


		private function createConstraintFromVersion(string $version): \Composer\Semver\Constraint\ConstraintInterface
		{
			$version = Strings::before($version, '.', 2);

			if (!is_string($version)) {
				throw new \RuntimeException('Invalid version string.');
			}

			return $this->versionParser->parseConstraints('^' . $version);
		}


		private function mergeConstraint(
			\Composer\Semver\Constraint\ConstraintInterface $a,
			\Composer\Semver\Constraint\ConstraintInterface $b
		): \Composer\Semver\Constraint\ConstraintInterface
		{
			$result = new \Composer\Semver\Constraint\MultiConstraint([$a, $b], FALSE);
			$result->setPrettyString($a->getPrettyString() . ' || ' . $b->getPrettyString());
			return $result;
		}
	}

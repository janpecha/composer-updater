<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;

	use Nette;
	use Nette\Utils\Arrays;
	use Nette\Utils\Strings;

	class Updater
	{
		/** @var ComposerFile */
		private $composerFile;

		/** @var string */
		private $composerExecutable;

		/** @var \CzProject\PhpCli\Console */
		private $console;

		/** @var \CzProject\Runner\Runner */
		private $runner;

		/** @var \Composer\Semver\VersionParser */
		private $versionParser;


		public function __construct(
			string $composerFile,
			string $composerExecutable,
			\CzProject\PhpCli\Console $console
		)
		{
			$this->composerFile = ComposerFile::open($composerFile);
			$this->composerExecutable = $composerExecutable;
			$this->console = $console;
			$this->runner = new \CzProject\Runner\Runner(dirname($this->composerFile->getPath()));
			$this->versionParser = new \Composer\Semver\VersionParser;
		}


		public function run(bool $dryRun): bool
		{
			if ($dryRun) {
				$this->console->output('Dry-run mode')->nl();
			}

			$projectType = $this->composerFile->getType();
			$result = FALSE;

			if ($projectType === 'library') {
				$result = $this->updateLibrary($dryRun);

			} else {
				throw new \RuntimeException('Unknow "type: ' . $projectType . '" in composer.json.');
			}

			$this->console->output('Done.', \CzProject\PhpCli\Colors::GREEN)
				->nl();
			return $result;
		}


		private function updateLibrary(bool $dryRun): bool
		{
			$this->tryComposerInstall($dryRun);

			$this->console->output('Updating library dependencies:')->nl();
			$outdated = $this->getOutdated();

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

				$constraint = $this->versionParser->parseConstraints($this->composerFile->getPackageConstraint($name));
				$newConstraint = $constraint;
				$upperBound = $constraint->getUpperBound();
				$retries = 5;

				while (!$newConstraint->matches($latestVersion)) {
					$newConstraint = $this->mergeConstraint($newConstraint, $this->createConstraintFromBound($upperBound));

					if ($newConstraint->matches($latestVersion)) {
						break;
					}

					$newUpperBound = $newConstraint->getUpperBound();

					if ($upperBound->getVersion() !== $newUpperBound->getVersion()) {
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
						$this->requirePackage($name, $newVersion);
					}
				}

				$this->console->nl();
			}

			$this->console->nl();
			return TRUE;
		}


		/**
		 * @return array<array<string, mixed>>
		 */
		private function getOutdated(): array
		{
			$result = $this->runner->run([
				$this->composerExecutable,
				'outdated',
				'-D',
				'--format=json'
			]);

			if (!$result->isOk()) {
				throw new \RuntimeException("Composer outdated failed.");
			}

			$outdated = Nette\Utils\Json::decode(implode("\n", $result->getOutput()), Nette\Utils\Json::FORCE_ARRAY);
			assert(is_array($outdated));
			return Arrays::get($outdated, 'installed');
		}


		private function tryComposerInstall(bool $dryRun): void
		{
			if (!$this->composerFile->existsLockFile()) {
				if ($dryRun) {
					throw new \RuntimeException('Missing composer.lock & dry-run mode enabled. Run `composer install` manually');
				}

				$this->console->output('Missing composer.lock, running of `composer install`.', \CzProject\PhpCli\Colors::YELLOW)->nl();
				$result = $this->runner->run([
					$this->composerExecutable,
					'install',
				]);

				if (!$result->isOk()) {
					throw new \RuntimeException("Composer install failed.");
				}
			}
		}


		/**
		 * @param  string $package
		 * @param  string $constraint
		 * @return void
		 */
		private function requirePackage($package, $constraint): void
		{
			$result = $this->runner->run([
				$this->composerExecutable,
				'--no-update',
				'--no-scripts',
				'require',
				$this->composerFile->isDevPackage($package) ? '--dev' : FALSE,
				$package . ':' . $constraint,
			]);

			if (!$result->isOk()) {
				throw new \RuntimeException("Composer require for package '$package' failed.");
			}
		}


		private function createConstraintFromBound(\Composer\Semver\Constraint\Bound $bound): \Composer\Semver\Constraint\ConstraintInterface
		{
			$version = Strings::before($bound->getVersion(), '.', 2);

			if (!is_string($version)) {
				throw new \RuntimeException('Invalid bound version.');
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

<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;

	use Nette;
	use Nette\Utils\Arrays;


	class CliBridge implements IComposerBridge
	{
		/** @var ComposerFile */
		private $composerFile;

		/** @var string */
		private $composerExecutable;

		/** @var \CzProject\Runner\Runner */
		private $runner;


		public function __construct(
			string $composerFile,
			string $composerExecutable
		)
		{
			$this->composerFile = ComposerFile::open($composerFile);
			$this->composerExecutable = $composerExecutable;
			$this->runner = new \CzProject\Runner\Runner(dirname($this->composerFile->getPath()));
		}


		public function getType(): string
		{
			return $this->composerFile->getType();
		}


		public function existsLockFile(): bool
		{
			return $this->composerFile->existsLockFile();
		}


		public function getOutdated(): array
		{
			$result = $this->runner->run([
				$this->composerExecutable,
				'outdated',
				'--no-plugins',
				'-D',
				'--format=json'
			]);

			if (!$result->isOk()) {
				throw new \RuntimeException("Composer outdated failed.");
			}

			$outdated = Nette\Utils\Json::decode(implode("\n", $result->getOutput()), Nette\Utils\Json::FORCE_ARRAY);
			assert(is_array($outdated));
			$result = [];

			foreach (Arrays::get($outdated, 'installed') as $package) {
				$packageName = Arrays::get($package, 'name');
				$result[] = new Package(
					$packageName,
					$this->composerFile->getPackageConstraint($packageName),
					Arrays::get($package, 'version'),
					Arrays::get($package, 'latest'),
					Arrays::get($package, 'latest-status')
				);
			}

			return $result;
		}


		public function runComposerInstall(): void
		{
			$result = $this->runner->run([
				$this->composerExecutable,
				'install',
				'--no-interaction',
				'--prefer-dist',
			]);

			if (!$result->isOk()) {
				throw new \RuntimeException("Composer install failed.");
			}
		}


		public function runComposerUpdate(bool $withAllDependencies): bool
		{
			$result = $this->runner->run([
				$this->composerExecutable,
				'update',
				'--no-interaction',
				'--prefer-dist',
				'--no-progress',
				$withAllDependencies ? '--with-all-dependencies' : FALSE,
			]);

			if (!$result->isOk()) {
				throw new \RuntimeException("Composer update failed.");
			}

			return strpos(implode("\n", $result->getOutput()), 'Nothing to install, update or remove') === FALSE;
		}


		public function requirePackageWithoutUpdate(string $package, string $constraint): void
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


		public function tryRequirePackage(string $package, string $constraint, bool $dryRun): bool
		{
			$result = $this->runner->run([
				$this->composerExecutable,
				$dryRun ? '--dry-run' : FALSE,
				'--update-with-all-dependencies',
				'require',
				$this->composerFile->isDevPackage($package) ? '--dev' : FALSE,
				$package . ':' . $constraint,
			]);

			return $result->isOk();
		}
	}

<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;

	use CzProject\Runner\RunnerResult;
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

		/** @var \CzProject\Runner\Runner */
		private $stdoutRunner;


		public function __construct(
			string $composerFile,
			string $composerExecutable
		)
		{
			$this->composerFile = ComposerFile::open($composerFile);
			$this->composerExecutable = $composerExecutable;
			$this->runner = new \CzProject\Runner\Runner(dirname($this->composerFile->getPath()), \CzProject\Runner\Runner::MERGE_OUTPUTS);
			$this->stdoutRunner = new \CzProject\Runner\Runner(dirname($this->composerFile->getPath()), \CzProject\Runner\Runner::ERR_DEV_NULL);
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
			$result = $this->stdoutRunner->run([
				$this->composerExecutable,
				'outdated',
				'--no-plugins',
				'-D',
				'--format=json'
			]);

			if (!$result->isOk()) {
				throw new CliRunnerException("Composer outdated failed.", $result);
			}

			$outdated = $this->decodeJsonFromResult($result);
			$result = [];

			foreach (Arrays::get($outdated, 'installed', []) as $package) {
				assert(is_array($package));
				$packageName = Arrays::get($package, 'name');
				$result[] = new Package(
					$packageName,
					$this->composerFile->getPackageConstraint($packageName),
					Arrays::get($package, 'version'),
					Arrays::get($package, 'latest')
				);
			}

			return $result;
		}


		public function getVersions(string $package): array
		{
			$result = $this->stdoutRunner->run([
				$this->composerExecutable,
				'show',
				$package,
				'--all',
				'--format=json',
			]);

			if (!$result->isOk()) {
				throw new CliRunnerException("Composer show failed.", $result);
			}

			$data = $this->decodeJsonFromResult($result);
			return Arrays::get($data, 'versions');
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
				throw new CliRunnerException("Composer install failed.", $result);
			}
		}


		public function runComposerUpdate(bool $withAllDependencies): bool
		{
			$result = $this->runner->run([
				$this->composerExecutable,
				'update',
				'--no-interaction',
				'--lock',
			]);

			$result = $this->runner->run([
				$this->composerExecutable,
				'update',
				'--no-interaction',
				'--prefer-dist',
				'--no-progress',
				$withAllDependencies ? '--with-all-dependencies' : FALSE,
			]);

			if (!$result->isOk()) {
				throw new CliRunnerException("Composer update failed.", $result);
			}

			return strpos(implode("\n", $result->getOutput()), 'Nothing to install, update or remove') === FALSE;
		}


		public function requirePackageWithoutUpdate(string $package, string $constraint): void
		{
			$result = $this->runner->run([
				$this->composerExecutable,
				'--no-update',
				'--no-scripts',
				'--no-interaction',
				'require',
				$this->composerFile->isDevPackage($package) ? '--dev' : FALSE,
				$package . ':' . $constraint,
			]);

			if (!$result->isOk()) {
				throw new CliRunnerException("Composer require for package '$package' failed.", $result);
			}
		}


		public function tryRequirePackage(string $package, string $constraint, bool $dryRun): bool
		{
			$result = $this->runner->run([
				$this->composerExecutable,
				$dryRun ? '--dry-run' : FALSE,
				'--update-with-all-dependencies',
				'--no-interaction',
				'require',
				$this->composerFile->isDevPackage($package) ? '--dev' : FALSE,
				$package . ':' . $constraint,
			]);

			return $result->isOk();
		}


		/**
		 * @return array<string, mixed>
		 */
		private function decodeJsonFromResult(RunnerResult $result)
		{
			try {
				$data = Nette\Utils\Json::decode(implode("\n", $result->getOutput()), Nette\Utils\Json::FORCE_ARRAY);

			} catch (\Nette\Utils\JsonException $e) {
				throw new CliRunnerException($e->getMessage(), $result);
			}

			return $data;
		}
	}

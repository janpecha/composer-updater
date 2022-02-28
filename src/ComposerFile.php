<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;

	use Nette\Utils\Arrays;


	class ComposerFile
	{
		/** @var string */
		private $path;

		/** @var array<string, mixed> */
		private $data;


		/**
		 * @param array<string, mixed> $data
		 */
		public function __construct(string $path, array $data)
		{
			$this->path = $path;
			$this->data = $data;
		}


		public function getPath(): string
		{
			return $this->path;
		}


		public function getType(): string
		{
			return Arrays::get($this->data, 'type', 'library');
		}


		/**
		 * @return array<string, string>
		 */
		public function getRequire(): array
		{
			return Arrays::get($this->data, 'require', []);
		}


		/**
		 * @return array<string, string>
		 */
		public function getRequireDev(): array
		{
			return Arrays::get($this->data, 'require-dev', []);
		}


		public function getPackageConstraint(string $package): string
		{
			$constraint = Arrays::get($this->data, ['require-dev', $package], Arrays::get($this->data, ['require', $package], NULL));

			if ($constraint === NULL) {
				throw new \RuntimeException("Missing package '$package' in composer.json.");
			}

			return $constraint;
		}


		public function isDevPackage(string $package): bool
		{
			return isset($this->data['require-dev'][$package]);
		}


		public function existsLockFile(): bool
		{
			return is_file(dirname($this->path) . '/composer.lock');
		}


		public static function open(string $path): self
		{
			$content = \Nette\Utils\FileSystem::read($path);
			$data = \Nette\Utils\Json::decode($content, \Nette\Utils\Json::FORCE_ARRAY);
			return new self($path, $data);
		}
	}

<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;


	class Package
	{
		/** @var string */
		private $name;

		/** @var string */
		private $constraint;

		/** @var string */
		private $currentVersion;

		/** @var string */
		private $latestVersion;


		public function __construct(
			string $name,
			string $constraint,
			string $currentVersion,
			string $latestVersion
		)
		{
			$this->name = $name;
			$this->constraint = $constraint;
			$this->currentVersion = $currentVersion;
			$this->latestVersion = $latestVersion;
		}


		public function getName(): string
		{
			return $this->name;
		}


		public function getConstraint(): string
		{
			return $this->constraint;
		}


		public function getCurrentVersion(): string
		{
			return $this->currentVersion;
		}


		public function getLatestVersion(): string
		{
			return $this->latestVersion;
		}
	}

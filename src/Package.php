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

		/** @var string */
		private $updateStatus;


		public function __construct(
			string $name,
			string $constraint,
			string $currentVersion,
			string $latestVersion,
			string $updateStatus
		)
		{
			$this->name = $name;
			$this->constraint = $constraint;
			$this->currentVersion = $currentVersion;
			$this->latestVersion = $latestVersion;
			$this->updateStatus = $updateStatus;
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


		public function getUpdateStatus(): string
		{
			return $this->updateStatus;
		}
	}

<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;


	class PackageToUpdate
	{
		/** @var Package */
		private $package;

		/** @var \Composer\Semver\Constraint\ConstraintInterface */
		private $constraint;

		/** @var string */
		private $latestVersion;

		/** @var \Composer\Semver\Constraint\ConstraintInterface */
		private $latestVersionConstraint;

		/** @var PackageVersion[] */
		private $versions;


		/**
		 * @param PackageVersion[] $versions
		 */
		public function __construct(
			Package $package,
			\Composer\Semver\Constraint\ConstraintInterface $constraint,
			string $latestVersion,
			\Composer\Semver\Constraint\ConstraintInterface $latestVersionConstraint,
			array $versions
		)
		{
			$this->package = $package;
			$this->constraint = $constraint;
			$this->latestVersion = $latestVersion;
			$this->latestVersionConstraint = $latestVersionConstraint;
			$this->versions = $versions;
		}


		public function getName(): string
		{
			return $this->package->getName();
		}


		public function getConstraint(): \Composer\Semver\Constraint\ConstraintInterface
		{
			return $this->constraint;
		}


		public function getLatestVersion(): string
		{
			return $this->latestVersion;
		}


		public function getLatestVersionConstraint(): \Composer\Semver\Constraint\ConstraintInterface
		{
			return $this->latestVersionConstraint;
		}


		public function needsToUpdate(): bool
		{
			return !$this->constraint->matches($this->latestVersionConstraint);
		}


		/**
		 * @return PackageVersion[]
		 */
		public function getVersions(): array
		{
			return $this->versions;
		}
	}

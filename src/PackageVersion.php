<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;


	class PackageVersion
	{
		/** @var string */
		private $normalizedVersion;

		/** @var \Composer\Semver\Constraint\ConstraintInterface */
		private $constraint;


		private function __construct(
			string $normalizedVersion,
			\Composer\Semver\Constraint\ConstraintInterface $constraint
		)
		{
			$this->normalizedVersion = $normalizedVersion;
			$this->constraint = $constraint;
		}


		public function satisfies(\Composer\Semver\Constraint\ConstraintInterface $constraints): bool
		{
			return $constraints->matches($this->constraint);
		}


		public function getNormalizedVersion(): string
		{
			return $this->normalizedVersion;
		}


		public static function create(
			string $version,
			\Composer\Semver\VersionParser $versionParser
		): self
		{
			$normalizedVersion = $versionParser->normalize($version);
			return new self(
				$normalizedVersion,
				new \Composer\Semver\Constraint\Constraint('==', $normalizedVersion)
			);
		}
	}

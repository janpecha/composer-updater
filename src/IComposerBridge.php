<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;


	interface IComposerBridge
	{
		function getType(): string;


		function getPackageConstraint(string $package): string;


		function existsLockFile(): bool;


		/**
		 * @return array<array<string, mixed>>
		 */
		function getOutdated(): array;


		function runComposerInstall(): void;


		function runComposerUpdate(bool $withAllDependencies): bool;


		function requirePackageWithoutUpdate(string $package, string $constraint): void;


		function tryRequirePackage(string $package, string $constraint, bool $dryRun): bool;
	}

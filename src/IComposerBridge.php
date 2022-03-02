<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;


	interface IComposerBridge
	{
		function getType(): string;


		function existsLockFile(): bool;


		/**
		 * @return Package[]
		 */
		function getOutdated(): array;


		function runComposerInstall(): void;


		function runComposerUpdate(bool $withAllDependencies): bool;


		function requirePackageWithoutUpdate(string $package, string $constraint): void;


		function tryRequirePackage(string $package, string $constraint, bool $dryRun): bool;
	}

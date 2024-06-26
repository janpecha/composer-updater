<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();


function test($description, callable $cb)
{
	$cb();
}


class Tests
{
	/**
	 * @param  string[] $expected
	 */
	public static function assertOutput(
		array $expected,
		\CzProject\PhpCli\Outputs\MemoryOutputProvider $outputProvider
	): void
	{
		Tester\Assert::same(trim(implode("\n", $expected), "\n"), trim($outputProvider->getOutput(), "\n"));
	}


	/**
	 * @param  \JP\ComposerUpdater\Package[] $packages
	 */
	public static function createUpdater(
		array $repository,
		string $type,
		array $composerFile,
		?array $lockFile,
		\CzProject\PhpCli\Outputs\MemoryOutputProvider $outputProvider
	): \JP\ComposerUpdater\Updater
	{
		return new \JP\ComposerUpdater\Updater(
			new \JP\ComposerUpdater\MemoryBridge($repository, $type, $composerFile, $lockFile),
			self::createConsole($outputProvider)
		);
	}


	public static function createConsoleOutput(): \CzProject\PhpCli\Outputs\MemoryOutputProvider
	{
		return new \CzProject\PhpCli\Outputs\MemoryOutputProvider;
	}


	public static function createConsole(\CzProject\PhpCli\Outputs\MemoryOutputProvider $outputProvider): \CzProject\PhpCli\Console
	{
		return new \CzProject\PhpCli\Console(
			$outputProvider,
			new \CzProject\PhpCli\Inputs\MemoryInputProvider,
			new \CzProject\PhpCli\Parameters\MemoryParametersProvider([])
		);
	}
}

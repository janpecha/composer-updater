<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('Conflicts', function () {
	$outputProvider = Tests::createConsoleOutput();
	$memoryBridge = new \JP\ComposerUpdater\MemoryBridge(
		[
			'inteve/types' => [
				'v0.5.0' => [
					'nette/utils' => '^2.4',
				],
				'v1.0.0' => [
					'nette/utils' => '^2.4',
				],
				'v1.1.0' => [
					'nette/utils' => '^2.4',
				],
			],
			'nette/application' => [
				'v2.4.0' => [
					'nette/utils' => '~2.4',
				],
				'v3.0.0' => [
					'nette/utils' => '^3.0',
				],
				'v3.1.0' => [
					'nette/utils' => '^3.2',
				],
			],
			'nette/caching' => [
				'v2.4.0' => [
					'nette/utils' => '~2.2',
				],
				'v2.5.0' => [
					'nette/utils' => '~2.4',
				],
				'v3.0.0' => [
					'nette/utils' => '^2.4 || ~3.0.0',
				],
				'v3.1.0' => [
					'nette/utils' => '^2.4 || ^3.0',
				],
			],
			'nette/robot-loader' => [
				'v2.4.0' => [
					'nette/caching' => '~2.2',
					'nette/utils' => '~2.4',
				],
				'v3.0.0' => [
					'nette/utils' => '^2.4 || ^3.0',
				],
				'v3.1.0' => [
					'nette/utils' => '^2.4 || ^3.0',
				],
			],
			'nette/utils' => [
				'v2.4.0' => [],
				'v3.0.0' => [],
				'v3.1.0' => [],
			],
		],
		'project',
		[
			'inteve/types' => '^0.5.0',
			'nette/application' => '^2.4',
			'nette/robot-loader' => '^2.4',
			'nette/caching' => '^2.4',
			'nette/utils' => '^2.4',
		],
		[
			'inteve/types' => 'v0.5.0',
			'nette/application' => 'v2.4.0',
			'nette/caching' => 'v2.4.0',
			'nette/robot-loader' => 'v2.4.0',
			'nette/utils' => 'v2.4.0',
		]
	);
	$updater = new \JP\ComposerUpdater\Updater($memoryBridge, Tests::createConsole($outputProvider));

	Assert::same([
		'inteve/types' => 'v0.5.0',
		'nette/application' => 'v2.4.0',
		'nette/caching' => 'v2.4.0',
		'nette/robot-loader' => 'v2.4.0',
		'nette/utils' => 'v2.4.0',
	], $memoryBridge->getInstalledVersions());

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Stabilization of project constraints:',
		' - inteve/types => ~0.5.0',
		' - nette/application => ~2.4.0',
		' - nette/caching => ~2.4.0',
		' - nette/robot-loader => ~2.4.0',
		' - nette/utils => ~2.4.0',
		'Done.',
	], $outputProvider);

	Assert::same([
		'inteve/types' => 'v0.5.0',
		'nette/application' => 'v2.4.0',
		'nette/caching' => 'v2.4.0',
		'nette/robot-loader' => 'v2.4.0',
		'nette/utils' => 'v2.4.0',
	], $memoryBridge->getInstalledVersions());

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - running `composer update` [NOTHING TO UPDATE]',
		'Updating project constraints:',
		' - inteve/types => ~1.0.0',
		' - nette/application => ~3.0.0',
		' - nette/caching => ~2.5.0',
		' - nette/robot-loader => ~3.0.0',
		' - nette/utils => ~3.0.0',
		'Apply updates:',
		' - inteve/types => updated to ~1.0.0',
		' - nette/caching => updated to ~2.5.0',
		' - nette/robot-loader => updated to ~3.0.0',
		'Some packages was not updated due conflict issues:',
		' - nette/application => NOT updated to ~3.0.0',
		' - nette/utils => NOT updated to ~3.0.0',
		'',
		'Done.',
	], $outputProvider);

	Assert::same([
		'inteve/types' => 'v1.0.0',
		'nette/application' => 'v2.4.0',
		'nette/caching' => 'v2.5.0',
		'nette/robot-loader' => 'v3.0.0',
		'nette/utils' => 'v2.4.0',
	], $memoryBridge->getInstalledVersions());

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - running `composer update` [NOTHING TO UPDATE]',
		'Updating project constraints:',
		' - inteve/types => ~1.1.0',
		' - nette/application => ~3.0.0',
		' - nette/robot-loader => ~3.1.0',
		' - nette/caching => ~3.0.0',
		' - nette/utils => ~3.0.0',
		'Apply updates:',
		' - inteve/types => updated to ~1.1.0',
		' - nette/robot-loader => updated to ~3.1.0',
		' - nette/caching => updated to ~3.0.0',
		'Some packages was not updated due conflict issues:',
		' - nette/application => NOT updated to ~3.0.0',
		' - nette/utils => NOT updated to ~3.0.0',
		'',
		'Done.',
	], $outputProvider);

	Assert::same([
		'inteve/types' => 'v1.1.0',
		'nette/application' => 'v2.4.0',
		'nette/caching' => 'v3.0.0',
		'nette/robot-loader' => 'v3.1.0',
		'nette/utils' => 'v2.4.0',
	], $memoryBridge->getInstalledVersions());

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - running `composer update` [NOTHING TO UPDATE]',
		'Updating project constraints:',
		' - nette/application => ~3.0.0',
		' - nette/caching => ~3.1.0',
		' - nette/utils => ~3.0.0',
		'Apply updates:',
		' - nette/caching => updated to ~3.1.0',
		'Some packages was not updated due conflict issues:',
		' - nette/application => NOT updated to ~3.0.0',
		' - nette/utils => NOT updated to ~3.0.0',
		'',
		'Done.',
	], $outputProvider);

	Assert::same([
		'inteve/types' => 'v1.1.0',
		'nette/application' => 'v2.4.0',
		'nette/caching' => 'v3.1.0',
		'nette/robot-loader' => 'v3.1.0',
		'nette/utils' => 'v2.4.0',
	], $memoryBridge->getInstalledVersions());

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - running `composer update` [NOTHING TO UPDATE]',
		'Updating project constraints:',
		' - nette/application => ~3.0.0',
		' - nette/utils => ~3.0.0',
		'Apply updates:',
		'Some packages was not updated due conflict issues:',
		' - nette/application => NOT updated to ~3.0.0',
		' - nette/utils => NOT updated to ~3.0.0',
		'',
		'FAILED.',
	], $outputProvider);

	Assert::same([
		'inteve/types' => 'v1.1.0',
		'nette/application' => 'v2.4.0',
		'nette/caching' => 'v3.1.0',
		'nette/robot-loader' => 'v3.1.0',
		'nette/utils' => 'v2.4.0',
	], $memoryBridge->getInstalledVersions());

});

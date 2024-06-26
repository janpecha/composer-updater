<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('Project update', function () {
	$outputProvider = Tests::createConsoleOutput();
	$memoryBridge = new \JP\ComposerUpdater\MemoryBridge(
		[
			'org/package1' => [
				'v2.4.0' => [],
				'v2.4.1' => [],
				'v2.4.2' => [],
				'v2.4.3' => [],
			],
			'org/package2' => [
				'v0.7.0' => [],
				'v0.8.0' => [],
				'v1.0.0' => [],
			],
		],
		'project',
		[
			'org/package1' => '^2.4',
			'org/package2' => '^0.7',
		],
		[
			'org/package1' => 'v2.4.2',
			'org/package2' => 'v0.7.0',
		]
	);
	$updater = new \JP\ComposerUpdater\Updater($memoryBridge, Tests::createConsole($outputProvider));

	$updater->run(FALSE);
	Tests::assertOutput([
		'Stabilization of project constraints:',
		' - org/package1 => ~2.4.2',
		' - org/package2 => ~0.7.0',
		'Done.',
	], $outputProvider);

	Assert::same([
		'org/package1' => 'v2.4.2',
		'org/package2' => 'v0.7.0',
	], $memoryBridge->getInstalledVersions());

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - running `composer update` [UPDATED]',
		'Bump of project constraints:',
		' - org/package1 => ~2.4.3',
		'Done.',
	], $outputProvider);

	Assert::same([
		'org/package1' => 'v2.4.3',
		'org/package2' => 'v0.7.0',
	], $memoryBridge->getInstalledVersions());

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - running `composer update` [NOTHING TO UPDATE]',
		'Updating project constraints:',
		' - org/package2 => ~0.8.0',
		'Apply updates:',
		' - org/package2 => updated to ~0.8.0',
		'',
		'Done.',
	], $outputProvider);

	Assert::same([
		'org/package1' => 'v2.4.3',
		'org/package2' => 'v0.8.0',
	], $memoryBridge->getInstalledVersions());

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - running `composer update` [NOTHING TO UPDATE]',
		'Updating project constraints:',
		' - org/package2 => ~1.0.0',
		'Apply updates:',
		' - org/package2 => updated to ~1.0.0',
		'',
		'Done.',
	], $outputProvider);

	Assert::same([
		'org/package1' => 'v2.4.3',
		'org/package2' => 'v1.0.0',
	], $memoryBridge->getInstalledVersions());

});


test('Project update (only patches)', function () {
	$outputProvider = Tests::createConsoleOutput();
	$memoryBridge = new \JP\ComposerUpdater\MemoryBridge(
		[
			'org/package1' => [
				'v2.4.0' => [],
				'v2.4.1' => [],
				'v2.4.2' => [],
				'v2.4.3' => [],
			],
		],
		'project',
		[
			'org/package1' => '^2.4',
		],
		[
			'org/package1' => 'v2.4.2',
		]
	);
	$updater = new \JP\ComposerUpdater\Updater($memoryBridge, Tests::createConsole($outputProvider));

	$updater->run(FALSE);
	Tests::assertOutput([
		'Stabilization of project constraints:',
		' - org/package1 => ~2.4.2',
		'Done.',
	], $outputProvider);

	Assert::same([
		'org/package1' => 'v2.4.2',
	], $memoryBridge->getInstalledVersions());

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - running `composer update` [UPDATED]',
		'Bump of project constraints:',
		' - org/package1 => ~2.4.3',
		'Done.',
	], $outputProvider);

	Assert::same([
		'org/package1' => 'v2.4.3',
	], $memoryBridge->getInstalledVersions());

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - nothing to update.',
		'Done.',
	], $outputProvider);

	Assert::same([
		'org/package1' => 'v2.4.3',
	], $memoryBridge->getInstalledVersions());

});


test('Tilda update', function () {
	$outputProvider = Tests::createConsoleOutput();
	$memoryBridge = new \JP\ComposerUpdater\MemoryBridge(
		[
			'org/package1' => [
				'v2.4.0' => [],
				'v2.4.1' => [],
				'v2.5.0' => [],
				'v2.5.1' => [],
				'v3.0.0' => [],
			],
		],
		'project',
		[
			'org/package1' => '~2.4.0',
		],
		[
			'org/package1' => 'v2.4.0',
		]
	);
	$updater = new \JP\ComposerUpdater\Updater($memoryBridge, Tests::createConsole($outputProvider));

	Assert::same([
		'org/package1' => 'v2.4.0',
	], $memoryBridge->getInstalledVersions());

	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - running `composer update` [UPDATED]',
		'Bump of project constraints:',
		' - org/package1 => ~2.4.1',
		'Done.',
	], $outputProvider);

	Assert::same([
		'org/package1' => 'v2.4.1',
	], $memoryBridge->getInstalledVersions());

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - running `composer update` [NOTHING TO UPDATE]',
		'Updating project constraints:',
		' - org/package1 => ~2.5.0',
		'Apply updates:',
		' - org/package1 => updated to ~2.5.0',
		'Bump of project constraints:',
		' - org/package1 => ~2.5.1',
		'',
		'Done.',
	], $outputProvider);

	Assert::same([
		'org/package1' => 'v2.5.1',
	], $memoryBridge->getInstalledVersions());

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - running `composer update` [NOTHING TO UPDATE]',
		'Updating project constraints:',
		' - org/package1 => ~3.0.0',
		'Apply updates:',
		' - org/package1 => updated to ~3.0.0',
		'',
		'Done.',
	], $outputProvider);

	Assert::same([
		'org/package1' => 'v3.0.0',
	], $memoryBridge->getInstalledVersions());
});


test('Nothing to update', function () {
	$outputProvider = Tests::createConsoleOutput();
	$updater = Tests::createUpdater(
		[
			'org/package1' => [
				'v2.4.0' => [],
				'v2.4.1' => [],
				'v2.4.2' => [],
				'v2.4.3' => [],
			],
			'org/package2' => [
				'v0.7.0' => [],
				'v0.8.0' => [],
				'v1.0.0' => [],
			],
			'org/package3' => [
				'v0.7.0' => [],
				'v0.8.0' => [],
				'v1.0.0' => [],
			],
		],
		'project',
		[
			'org/package1' => '^2.4',
			'org/package2' => '^0.7 || ^1.0',
			'org/package3' => '~1.0.0',
		],
		[
			'org/package1' => 'v2.4.3',
			'org/package2' => 'v1.0.0',
			'org/package3' => 'v1.0.0',
		],
		$outputProvider
	);

	$updater->run(FALSE);
	Tests::assertOutput([
		'Stabilization of project constraints:',
		' - org/package1 => ~2.4.3',
		' - org/package2 => ~1.0.0',
		'Done.',
	], $outputProvider);

	$outputProvider->resetOutput();
	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - nothing to update.',
		'Done.',
	], $outputProvider);
});

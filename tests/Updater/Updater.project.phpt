<?php

use JP\ComposerUpdater;
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
		'Updating project dependencies:',
		' - running `composer update` [UPDATED]',
		'Done.',
	], $outputProvider);

	Assert::same('v2.4.3', $memoryBridge->getInstalledVersion('org/package1'));

	Assert::same('v0.7.0', $memoryBridge->getInstalledVersion('org/package2'));

	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - running `composer update` [UPDATED]',
		'Done.',
		'Updating project dependencies:',
		' - running `composer update` [NOTHING TO UPDATE]',
		'Updating project constraints:',
		' - org/package2 => ^1.0',
		'Apply updates:',
		' - org/package2 => updated to ^1.0',
		'',
		'Done.',
	], $outputProvider);
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
			'org/package1' => 'v2.4.2',
			'org/package2' => 'v0.7.0',
			'org/package3' => 'v0.7.0',
		],
		$outputProvider
	);

	$updater->run(FALSE);
	Tests::assertOutput([
		'Updating project dependencies:',
		' - nothing to update.',
		'Done.',
	], $outputProvider);
});

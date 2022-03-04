<?php

use JP\ComposerUpdater;

require __DIR__ . '/../bootstrap.php';


test('Library update', function () {
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
		],
		'library',
		[
			'org/package1' => '^2.4',
			'org/package2' => '^0.7',
		],
		NULL,
		$outputProvider
	);

	$updater->run(FALSE);
	Tests::assertOutput([
		'Missing composer.lock, running of `composer install`.',
		'Updating library dependencies:',
		' - org/package2 => ^0.7 || ^1.0',
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
		],
		'library',
		[
			'org/package1' => '^2.4',
			'org/package2' => '^0.7 || ^1.0',
		],
		NULL,
		$outputProvider
	);

	$updater->run(FALSE);
	Tests::assertOutput([
		'Missing composer.lock, running of `composer install`.',
		'Updating library dependencies:',
		' - nothing to update.',
		'Done.',
	], $outputProvider);
});

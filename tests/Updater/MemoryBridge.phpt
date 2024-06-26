<?php

declare(strict_types=1);

use JP\ComposerUpdater;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('No lock file', function () {
	$memoryBridge = new ComposerUpdater\MemoryBridge(
		[
			'org/package' => [
				'v1.0.0' => [],
			],
		],
		'library',
		[
			'org/package' => '^2.4',
		],
		NULL
	);

	Assert::same('library', $memoryBridge->getType());
	Assert::false($memoryBridge->existsLockFile());
	Assert::same([], $memoryBridge->getOutdated());
});


test('No lock file & install', function () {
	$memoryBridge = new ComposerUpdater\MemoryBridge(
		[
			'org/package' => [
				'v1.0.0' => [],
				'v2.5.0' => [],
				'v3.1.0' => [],
			],
		],
		'library',
		[
			'org/package' => '^2.4',
		],
		NULL
	);

	$memoryBridge->runComposerInstall();
	Assert::same([
		'org/package' => 'v2.5.0',
	], $memoryBridge->getInstalledVersions());
	Assert::equal([
		new ComposerUpdater\Package('org/package', '^2.4', 'v2.5.0', 'v3.1.0'),
	], $memoryBridge->getOutdated());
});


test('Require without update', function () {
	$memoryBridge = new ComposerUpdater\MemoryBridge(
		[
			'org/package' => [
				'v1.0.0' => [],
				'v2.5.0' => [],
				'v3.1.0' => [],
			],
		],
		'library',
		[
			'org/package' => '^2.4',
		],
		NULL
	);

	$memoryBridge->requirePackageWithoutUpdate('org/package', '^3.0');
	Assert::equal('^3.0', $memoryBridge->getPackageConstraint('org/package'));
});


test('No lock file & install & update', function () {
	$memoryBridge = new ComposerUpdater\MemoryBridge(
		[
			'org/package' => [
				'v1.0.0' => [],
				'v2.5.0' => [],
				'v3.1.0' => [],
			],
		],
		'library',
		[
			'org/package' => '^2.4',
		],
		NULL
	);

	$memoryBridge->runComposerInstall();
	Assert::same([
		'org/package' => 'v2.5.0',
	], $memoryBridge->getInstalledVersions());
	Assert::false($memoryBridge->runComposerUpdate(TRUE)); // nothing to update

	$memoryBridge->requirePackageWithoutUpdate('org/package', '^3.0');
	Assert::true($memoryBridge->runComposerUpdate(TRUE));
	Assert::same([
		'org/package' => 'v3.1.0',
	], $memoryBridge->getInstalledVersions());
});


test('No lock file & install & require', function () {
	$memoryBridge = new ComposerUpdater\MemoryBridge(
		[
			'org/package' => [
				'v1.0.0' => [],
				'v2.5.0' => [],
				'v3.1.0' => [],
			],
		],
		'library',
		[
			'org/package' => '^2.4',
		],
		NULL
	);

	$memoryBridge->runComposerInstall();
	Assert::same([
		'org/package' => 'v2.5.0',
	], $memoryBridge->getInstalledVersions());

	Assert::true($memoryBridge->tryRequirePackage('org/package', '^3.0', FALSE));
	Assert::same([
		'org/package' => 'v3.1.0',
	], $memoryBridge->getInstalledVersions());
});


test('No lock file & install & require + dry run', function () {
	$memoryBridge = new ComposerUpdater\MemoryBridge(
		[
			'org/package' => [
				'v1.0.0' => [],
				'v2.5.0' => [],
				'v3.1.0' => [],
			],
		],
		'library',
		[
			'org/package' => '^2.4',
		],
		NULL
	);

	$memoryBridge->runComposerInstall();
	Assert::same([
		'org/package' => 'v2.5.0',
	], $memoryBridge->getInstalledVersions());

	Assert::true($memoryBridge->tryRequirePackage('org/package', '^3.0', TRUE));
	Assert::same([
		'org/package' => 'v2.5.0',
	], $memoryBridge->getInstalledVersions());
});


test('No lock file & install & require - invalid constraint', function () {
	$memoryBridge = new ComposerUpdater\MemoryBridge(
		[
			'org/package' => [
				'v1.0.0' => [],
				'v2.5.0' => [],
				'v3.1.0' => [],
			],
		],
		'library',
		[
			'org/package' => '^2.4',
		],
		NULL
	);

	$memoryBridge->runComposerInstall();
	Assert::same([
		'org/package' => 'v2.5.0',
	], $memoryBridge->getInstalledVersions());

	Assert::false($memoryBridge->tryRequirePackage('org/package', '^4.0', TRUE));
});

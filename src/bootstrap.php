<?php

declare(strict_types=1);

namespace JP\ComposerUpdater;

$autoload = is_file(__DIR__ . '/../vendor/autoload.php')
	? __DIR__ . '/../vendor/autoload.php'
	: __DIR__ . '/../../../autoload.php';

if (@!include $autoload) {
	echo 'Install packages using `composer update`';
	exit(1);
}

set_time_limit(0);

$console = \CzProject\PhpCli\ConsoleFactory::createConsole();
$composerFile = $console->getOption('composer-file')
	->setNullable()
	->getValue();


$console->output('JP\\Composer Updater')
	->nl()
	->output('-------------------')
	->nl();

if (!$console->hasParameters()) {
	$console->output('Usage:')
		->nl()
		->output('    php composer-updater <command> [options]')
		->nl()
		->nl()
		->output('Commands:')
		->nl()
		->output('    update       Updates composer.json & composer.lock if needed.')
		->nl()
		->nl()
		->output('Options:')
		->nl()
		->output('    --composer-file=<path>       Path to composer.json file (optional)')
		->nl()
		->output('    --composer-bin=<executable>  Composer executable (optional, default: `composer`)')
		->nl()
		->output('    --dry-run                    Enable dry-run mode')
		->nl()
		->nl();

} else {
	$command = $console->getArgument(0)
		->setDefaultValue('update')
		->getValue();

	if ($command !== 'update') {
		throw new \RuntimeException("Unknow command '$command'.");
	}

	if ($composerFile === NULL) {
		$filesToSearch = [
			'composer.json',
			'.data/composer.json',
		];

		$currentDirectory = $console->getCurrentDirectory();

		foreach ($filesToSearch as $fileToSearch) {
			if (is_file($currentDirectory . '/' . $fileToSearch)) {
				$composerFile = $currentDirectory . '/' . $fileToSearch;
				break;
			}
		}

	} elseif (!\Nette\Utils\FileSystem::isAbsolute($composerFile)) {
		$composerFile = $console->getCurrentDirectory() . '/' . $composerFile;
	}

	if ($composerFile === NULL) {
		throw new \RuntimeException('Missing composer.json file, use --composer-file parameter.');
	}

	if (!is_file($composerFile)) {
		throw new \RuntimeException('Composer file ' . $composerFile . ' not found.');
	}

	$bridge = new CliBridge(
		$composerFile,
		$console->getOption('composer-bin')
			->setDefaultValue('composer')
			->getValue()
	);
	$updater = new Updater(
		$bridge,
		$console
	);
	$ok = $updater->run(
		$console->getOption('dry-run', 'bool')
			->setDefaultValue(FALSE)
			->getValue()
	);

	exit($ok ? 0 : 1);
}

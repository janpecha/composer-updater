<?php

	declare(strict_types=1);

	namespace JP\ComposerUpdater;


	class Exception extends \Exception
	{
	}


	class CliRunnerException extends Exception
	{
		/** @var \CzProject\Runner\RunnerResult */
		private $runnerResult;


		/**
		 * @param  string $message
		 */
		public function __construct($message, \CzProject\Runner\RunnerResult $runnerResult)
		{
			parent::__construct($message, $runnerResult->getCode());
			$this->runnerResult = $runnerResult;
		}


		/**
		 * @return \CzProject\Runner\RunnerResult
		 */
		public function getRunnerResult()
		{
			return $this->runnerResult;
		}
	}

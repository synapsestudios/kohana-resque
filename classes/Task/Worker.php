<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Simple Reskue worker
 *
 */
class Task_Worker extends Minion_Task
{
	/**
	 * A set of config options that this task accepts
	 * @var array
	 */
	protected $_options = array(
		'verbose'   => FALSE,
		'interval'  => 5,
		'resque'    => '',
		'count'     => 1,
		'shutdown'  => '',
	);

	/**
	 * Execute the task
	 *
	 * @param array Configuration
	 */
	protected function _execute(array $options)
	{
		require Kohana::find_file('vendor', 'php-resque/lib/Resque');
		require Kohana::find_file('vendor', 'php-resque/lib/Resque/Worker');
		require Kohana::find_file('vendor', 'php-resque/lib/Resque/Scheduled');

		// Listen for failures and log them
		Resque_Event::listen('onFailure', function ($exception, $job) {
			Kohana::$log->add(LOG::ERROR, 'Error processing queued job, '.$job.', failed with "'.$exception->getMessage().'"');
			Kohana::$log->write();
		});

		$loglevel = 0;

		if ($options['verbose'] !== FALSE)
		{
			$loglevel = Resque_Worker::LOG_NORMAL;
		}

		if ($options['shutdown'] === NULL)
		{
			$this->_shutdown_workers();
			return;
		}

		Resque::setBackend(Kohana::$config->load('reskue')->host);

		$queues = explode(',', $options['resque']);

		$this->_start_workers($queues, $options['count'], $loglevel, $options['interval']);
	}

	protected function _shutdown_workers()
	{
		$workers = Resque_Worker::all();

		foreach ($workers as $worker)
		{
			list($name, $pid, $queues) = explode(':', (string) $worker);
			posix_kill( (int) $pid, SIGQUIT);
		}

		Minion_CLI::write('SIGQUIT sent to '.count($workers).' workers.');
	}

	protected function _start_workers($queues, $count = 1, $loglevel = 0, $interval = 5)
	{
		for($i = 0; $i < $count; ++$i)
		{
			$pid = pcntl_fork();

			if ($pid == -1)
			{
				Minion_CLI::write('Could not fork worker '.$i);
				return;
			}
			elseif ( ! $pid)
			{
				// Child, start the worker
				$worker = new Resque_Worker($queues);
				$worker->logLevel = $loglevel;

				Minion_CLI::write('*** Starting worker '.$worker);
				$worker->work($interval);
				break;
			}
		}
	}
}
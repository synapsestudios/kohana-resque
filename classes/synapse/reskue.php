<?php defined('SYSPATH') OR die('No direct script access.');

class Kohana_Reskue {

	public static function factory($config = array())
	{
		return new Reskue($config);
	}

	protected function __construct($config = array())
	{
		if ( ! $config)
		{
			$config = Kohana::config('reskue');
		}

		// Include the php-resque library
		require_once Kohana::find_file('vendor', 'php-resque/lib/Resque');

		Resque::setBackend(Arr::get($config, 'host'));
	}

	public function enqueue($queue, $class, $args = NULL, $track_status = FALSE)
	{
		Resque::enqueue($queue, $class, $args, $track_status);
	}

	public function enque_delayed(DateTime $delay, $queue, $class, $args = NULL, $track_status = FALSE)
	{
		Resque::enqueDelayed($delay, $queue, $class, $args, $track_status);
	}

	public function process_delayed_jobs()
	{
		Resque::processDelayedJobs();
	}
}
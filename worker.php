<?php

/**
 * The directory in which your application specific resources are located.
 * The application directory must contain the bootstrap.php file.
 *
 * @see  http://kohanaframework.org/guide/about.install#application
 */
$application = 'application';

/**
 * The directory in which your modules are located.
 *
 * @see  http://kohanaframework.org/guide/about.install#modules
 */
$modules = 'modules';

/**
 * The directory in which the Kohana resources are located. The system
 * directory must contain the classes/kohana.php file.
 *
 * @see  http://kohanaframework.org/guide/about.install#system
 */
$system = 'system';

/**
 * The default extension of resource files. If you change this, all resources
 * must be renamed to use the new extension.
 *
 * @see  http://kohanaframework.org/guide/about.install#ext
 */
define('EXT', '.php');

/**
 * Set the PHP error reporting level. If you set this in php.ini, you remove this.
 * @see  http://php.net/error_reporting
 *
 * When developing your application, it is highly recommended to enable notices
 * and strict warnings. Enable them by using: E_ALL | E_STRICT
 *
 * In a production environment, it is safe to ignore notices and strict warnings.
 * Disable them by using: E_ALL ^ E_NOTICE
 *
 * When using a legacy application with PHP >= 5.3, it is recommended to disable
 * deprecated notices. Disable with: E_ALL & ~E_DEPRECATED
 */
error_reporting(E_ALL | E_STRICT);

/**
 * End of standard configuration! Changing any of the code below should only be
 * attempted by those with a working knowledge of Kohana internals.
 *
 * @see  http://kohanaframework.org/guide/using.configuration
 */

// Set the full path to the docroot
define('DOCROOT', realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR);

// Make the application relative to the docroot
if ( ! is_dir($application) AND is_dir(DOCROOT.$application))
	$application = DOCROOT.$application;

// Make the modules relative to the docroot
if ( ! is_dir($modules) AND is_dir(DOCROOT.$modules))
	$modules = DOCROOT.$modules;

// Make the system relative to the docroot
if ( ! is_dir($system) AND is_dir(DOCROOT.$system))
	$system = DOCROOT.$system;

// Define the absolute paths for configured directories
define('APPPATH', realpath($application).DIRECTORY_SEPARATOR);
define('MODPATH', realpath($modules).DIRECTORY_SEPARATOR);
define('SYSPATH', realpath($system).DIRECTORY_SEPARATOR);

// Clean up the configuration vars
unset($application, $modules, $system);

/**
 * Define the start time of the application, used for profiling.
 */
if ( ! defined('KOHANA_START_TIME'))
{
	define('KOHANA_START_TIME', microtime(TRUE));
}

/**
 * Define the memory usage at the start of the application, used for profiling.
 */
if ( ! defined('KOHANA_START_MEMORY'))
{
	define('KOHANA_START_MEMORY', memory_get_usage());
}

// Bootstrap the application
require APPPATH.'bootstrap'.EXT;

require Kohana::find_file('vendor', 'php-resque/lib/Resque');
require Kohana::find_file('vendor', 'php-resque/lib/Resque/Worker');
require Kohana::find_file('vendor', 'php-resque/lib/Resque/Scheduled');

$QUEUE = getenv('QUEUE');

if (empty($QUEUE))
{
	die("Set QUEUE env var containing the list of queues to work.\n");
}

$REDIS_BACKEND = getenv('REDIS_BACKEND');

if ( ! empty($REDIS_BACKEND))
{
	Resque::setBackend($REDIS_BACKEND);
}

$logLevel = 0;
$LOGGING = getenv('LOGGING');
$VERBOSE = getenv('VERBOSE');
$VVERBOSE = getenv('VVERBOSE');

if ( ! empty($LOGGING) || !empty($VERBOSE))
{
	$logLevel = Resque_Worker::LOG_NORMAL;
}
elseif ( ! empty($VVERBOSE))
{
	$logLevel = Resque_Worker::LOG_VERBOSE;
}

$interval = 5;
$INTERVAL = getenv('INTERVAL');

if ( ! empty($INTERVAL))
{
	$interval = $INTERVAL;
}

// Are we running a worker or the scheduled task runner?
if ($QUEUE == 'scheduled_tasks')
{
	fwrite(STDOUT, '*** Starting Schedule Task Runner '."\n");
	$scheduled = new Resque_Scheduled();

	$scheduled->logLevel = $logLevel;
	$scheduled->work($interval);
}
else
{
	$count = 1;
	$COUNT = getenv('COUNT');

	if ( ! empty($COUNT) && $COUNT > 1)
	{
		$count = $COUNT;
	}

	if ($count > 1)
	{
		for($i = 0; $i < $count; ++$i)
		{
			$pid = pcntl_fork();

			if ($pid == -1)
			{
				die("Could not fork worker ".$i."\n");
			}
			// Child, start the worker
			elseif ( ! $pid)
			{
				$queues = explode(',', $QUEUE);
				$worker = new Resque_Worker($queues);
				$worker->logLevel = $logLevel;
				fwrite(STDOUT, '*** Starting worker '.$worker."\n");
				$worker->work($interval);
				break;
			}
		}
	}
	else
	{
		// Start a single worker
		$queues = explode(',', $QUEUE);
		$worker = new Resque_Worker($queues);
		$worker->logLevel = $logLevel;
		
		$PIDFILE = getenv('PIDFILE');

		if ($PIDFILE)
		{
			file_put_contents($PIDFILE, getmypid()) or
				die('Could not write PID information to ' . $PIDFILE);
		}

		fwrite(STDOUT, '*** Starting worker '.$worker."\n");
		$worker->work($interval);
	}
}
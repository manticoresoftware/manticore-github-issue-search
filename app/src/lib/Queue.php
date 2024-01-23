<?php declare(strict_types=1);

namespace App\Lib;

use Beanstalk\Client;

class Queue {
	const RELEASE_DELAY = 5;

	/**
	 * Get current instance of th eclient
	 * @return Client
	 */
	protected static function client(): Client {
		static $client;

		if (!$client) {
			$client = new Client(config('queue'));
			$client->connect();
		}

		return $client;
	}

	/**
	 * Add new job to the queue
	 * @param string      $ns
	 * @param mixed       $job
	 * @param int $delay
	 * @param int $ttr
	 * @return bool
	 */
	public static function add(string $ns, mixed $job, int $delay = 0, int $ttr = 300): bool {
		$func = function () use ($ns, $job, $delay, $ttr) {
			$client = static::client();
			if (!$client->connected) {
				return false;
			}

			$client->useTube($ns);
			$client->put(0, $delay, $ttr, json_encode($job));
			return true;
		};

		if (function_exists('fastcgi_finish_request')) {
			register_shutdown_function(
				function () use ($func) {
					$func();
					fastcgi_finish_request();
				}
			);
			return true;
		}

		return $func();
	}

	/**
	 * Process
	 * @param  string   $ns
	 * @param  callable $func
	 * @return
	 */
	public static function process(string $ns, callable $func): bool {
		if (!static::client()->connected) {
			return false;
		}
		static::client()->watch($ns);

		while (true) {
			if (false === static::fetch($func)) {
				return false;
			}
			usleep(200000);
		}
	}

	/**
	 * Fetch the queue with callable processor
	 * @param  callable $func
	 * @return bool
	 */
	public static function fetch(callable $func): bool {
		$client = static::client();
		$job = $client->reserve();
		if ($job === false) {
			return false;
		}
		$payload = json_decode($job['body'], true);
		$result = $func($payload);

		if (false === $result) {
			$client->release($job['id'], 0, static::RELEASE_DELAY);
		} else {
			$client->delete($job['id']);
		}

		return true;
	}

	/**
	 * Descturcotr
	 */
	public function __destruct() {
		static::client()->disconnect();
	}
}

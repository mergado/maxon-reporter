<?php

namespace Mergado\Maxon\Reporter;

class Signals {

	const HANDLED_SIGNALS = [

		// Common "quit" signals.
		SIGTERM,
		SIGQUIT,
		SIGINT,

		// Handle more signals.
		SIGHUP,
		SIGABRT,
		SIGPIPE,
		SIGXCPU,
		SIGVTALRM,
		SIGXFSZ,
		SIGUSR1,
		SIGUSR2,
		SIGSEGV,

	];

	protected static $latest = false;

	public static function getLatest() {
		return self::$latest;
	}

	/**
	 * This function registers signal handling and is to be called
	 * only inside the final daemonized process.
	 */
	public static function register() {

		pcntl_async_signals(true);
		foreach (self::HANDLED_SIGNALS as $sig) {
			pcntl_signal($sig, [self::class, 'handler']);
		}

	}

	public static function handler(int $signal) {

		// Define last signal so that shutdown_handler(), which will be invoked
		// upon exiting, knows what signal did cause the exit.
		self::$latest = $signal;

		$msg = "Received signal $signal.";
		logger($msg);
		info($msg);

		switch ($signal) {
			case SIGTERM:
			case SIGQUIT:
			case SIGINT:
				die;
		}

	}

}

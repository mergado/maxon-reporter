<?php

require_once __DIR__ . '/loader.php';

function info(string $text) {
	echo "> $text\n";
}

function error(string $text) {
	die("! Error: $text\n");
}

function json_decode_safe(...$args) {

	static $errors = [];

	if (!$errors) {
		$constants = get_defined_constants(true);
		foreach ($constants["json"] as $name => $value) {
			if (!strncmp($name, "JSON_ERROR_", 11)) {
				$errors[$value] = $name;
			}
		}
	}

	$result = json_decode(...$args);
	$err = json_last_error();
	if ($err !== JSON_ERROR_NONE) {
		error("Could not decode JSON ($errors[$err])");
	}

	return $result;

}

function init() {

	error_reporting(E_ALL);
	ini_set("display_errors", 1);

	set_exception_handler(function($ex) {

		$date = date("r");

		$msg = <<<ERR
█ {$date}
Error: {$ex->getMessage()} ({$ex->getFile()} at line {$ex->getLine()})
Stack:
{$ex->getTraceAsString()}\n
ERR;

		logger($msg);
		echo $msg; // This will not be visible if daemonized.

	});

	set_error_handler(function($severity, $message, $file, $line) {
		throw new ErrorException($message, 0, $severity, $file, $line);
	}, E_ALL);

	register_shutdown_function('shutdown_handler');

}

/**
 * This function registers signal handling and is to be called
 * only inside the final daemonized process.
 */
function init_daemon() {

	pcntl_signal(SIGTERM, "sig_handler");
	pcntl_signal(SIGQUIT, "sig_handler");
	pcntl_signal(SIGINT, "sig_handler");

}

function sig_handler($signal) {

	info("Received signal $signal.");
	switch ($signal) {
		case SIGTERM:
		case SIGQUIT:
		case SIGINT:
			// Define last signal so that shutdown_handler(), which will be invoked
			// upon exiting, knows what signal did cause the exit.
			define('LAST_SIGNAL', $signal);
			exit;
	}

}

function shutdown_handler() {

	if (!defined('LAST_SIGNAL')) {

		// Do not log exiting when reporter was not daemonized.
		// If it were daemonized, this LAST_SIGNAL constant would be defined.
		die;

	}

	$signal = sprintf("(received signal %d)", LAST_SIGNAL);
	$date = date("r");

	$msg = <<<MSG
█ {$date} Shutdown. $signal\n
MSG;

	logger($msg);

}

function logger($msg) {
	file_put_contents('./err.log', $msg, FILE_APPEND); // Even if daemonized.
}

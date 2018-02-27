<?php

namespace Mergado\Maxon\Reporter;

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
	ini_set("display_errors", 0);
	Signals::register();

	set_exception_handler(function($ex) {

		$date = date("r");
		$formattedStack = preg_replace('#(^|\n)#', "$1\t", $ex->getTraceAsString());

		$msg = <<<ERR
Error {$ex->getMessage()} in file ({$ex->getFile()} at line {$ex->getLine()})
Stack:
{$formattedStack}
ERR;

		logger($msg);
		echo $msg; // This will not be visible if daemonized.

	});

	set_error_handler(function($severity, $message, $file, $line) {
		throw new \ErrorException($message, 0, $severity, $file, $line);
	}, E_ALL);

	register_shutdown_function(__NAMESPACE__ . '\\shutdown_handler');


}

function shutdown_handler() {

	// Used e.g. when exitting during launching daemonization...
	if (defined('NO_SHUTDOWN_HANDLER')) {
		die;
	}

	if ($signal = Signals::getLatest()) {
		$reason = sprintf("(received signal %d)", $signal);
	} else {
		$reason = "(no signal received)";
	}

	logger("Shutdown. $reason");

}

function logger($msg) {

	$date = date("r");
	$msg = <<<MSG
[$date] $msg
MSG;

	file_put_contents('./info.log', $msg . "\n", FILE_APPEND); // Even if daemonized.

}

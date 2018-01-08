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

		echo $msg; // This will not be visible if daemonized.
		file_put_contents('./error.log', $msg, FILE_APPEND); // Even if daemonized.

	});

	set_error_handler(function($severity, $message, $file, $line) {

		// This error code is not included in error_reporting.
		if (!(error_reporting() & $severity)) {
			return;
		}

		throw new ErrorException($message, 0, $severity, $file, $line);

	}, E_ALL);

}

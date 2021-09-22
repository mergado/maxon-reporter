<?php

declare(strict_types=1);

namespace Mergado\Maxon\Reporter;

require_once __DIR__ . '/loader.php';

function info(string $text) {
	echo "> $text\n";
}

function error(string $text, $die = true) {

	$msg = "! Error: $text";

	logger($msg);
	echo $msg;
	echo "\n";

	if ($die) {
		die(1);
	}

}

function iter_all(iterable $input, ?callable $callback = null): bool {

	foreach ($input as $v) {
        if (!($callback !== null ? $callback($v) : $v)) {
            return false;
        }
    }

    return true;

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
	ini_set("display_errors", '1');

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

}

function init_daemon() {

	ini_set("display_errors", '0');
	Signals::register();

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

/**
 * This will allow running multiple reporters on a single machine by assigning
 * each reporter's instance (running from different path) a unique PID file.
 */
function determine_pid_file_path() {

	if (defined('BINARY_PATH')) {
		$hash = substr(md5(BINARY_PATH), 0, 6);
		$filename = sprintf('.maxon_reporter_%s.pid', $hash);
	} else {
		$filename = '.maxon_reporter.pid';
	}

	$pidDir = getenv('HOME') ?: "/tmp";
	return $pidDir . "/$filename";

}

function determine_config_file(string $overridePath) {

	static $predefinedPaths = [
		'./config/config.json',
		'./config.json',
	];

	if ($overridePath) {
		if (is_readable($overridePath)) {
			return $overridePath;
		} else {
			error("Specified config file '$overridePath' not found!");
		}
	}

	foreach ($predefinedPaths as $path) {
		if (is_readable($path)) {
			return $path;
		}
	}

	error(sprintf("No config file found! (tried: '%s')", implode(", '", $predefinedPaths)));

}

function expand_variables(string $string, array $variables) {

	// Evaluate expressions that may be present.
	return preg_replace_callback('#\${(.*?)}#', function($m) use ($variables) {
		return eval_expression($m[1], $variables);
	}, $string);

}


/**
 * Rewrite marked fields in template array (recursively) and replace variable
 * placeholders with variable values..
 */
function prepare_payload(array $template, array $variables): array {

	foreach ($template as &$value) {
		if (is_array($value)) {
			$value = prepare_payload($value, $variables);
		} elseif (is_string($value)) {
			$value = expand_variables($value, $variables);
		}
	}

	return $template;

}

/**
 * Validate and return a list of strings (valid target URLs) or null,
 * if the argument does not constitute a valid value for "target" field.
 *
 * Valid values for the "target" field are:
 * 1. A simple string (single target URL).
 * 2. A non-empty list of strings (multiple target URLs).
 */
function validate_config_target($target): ?array {

	if (is_string($target)) {
		// Always return list of strings.
		return [$target];
	}

	if (is_array($target)) {

		// List must not be empty.
		if (!$target) {
			return null;
		}

		// All items in the list must be strings.
		$areAllStrings = iter_all($target, function($x) {
			return is_string($x);
		});

		if (!$areAllStrings) {
			return null;
		}

		return $target;

	}

	return null;

}

function validate_config(array $config): array {

	$template = $config['payload'] ?? false;
	if (!$template) {
		error("Config file doesn't contain valid 'payload' template!");
	}

	$gatherers = $config['gatherers'] ?? false;
	if (!$gatherers) {
		error("Config file doesn't contain valid array of 'gatherers'!");
	}

	$targetUrls = validate_config_target($config['target'] ?? false);
	if (!$targetUrls) {
		error("Config file 'target' URL must be string or a list of strings!");
	}

	return [$template, $gatherers, $targetUrls];

}

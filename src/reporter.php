<?php

namespace Mergado\Maxon\Reporter;

require_once __DIR__ . '/loader.php';

// Initialize Reporter app.
init();

$pidDir = getenv('HOME') ?: "/tmp";
define('DAEMON_PID_FILE', $pidDir . '/.maxon_reporter.pid');

info("Maxon Reporter");

info("Premysl Karbula, Mergado, 2017");
if (defined('COMPILED_AT')) {
	info(sprintf("Compiled at: %s", COMPILED_AT));
}
info("Machine: " . gethostname());

$command = array_shift($argv);

// If there are any arguments, parse them.
// If there are none, display help and quit.
if ($argv) {
	$config = parse_arguments($argv);
} else {
	die(get_help());
}

if ($config['daemonize']) {
	info("Daemonizing ...");
	daemonize();
	init_daemon();
}

run($config);

function run(array $config) {

	$configFile = $config['config_path'];

	if ($configFile) {

		if (is_readable($configFile)) {
			$json = file_get_contents($configFile);
			$userConfig = json_decode_safe($json, true);
		} else {
			error("Config file '$configFile' not found!");
		}

	} else {
		error("No config file specified!");
		die;
	}

	$template = $userConfig['payload'] ?? [];
	if (!$template) {
		error("Config file doesn't contain valid 'payload' template!");
	}

	$gatherers = $userConfig['gatherers'] ?? [];
	if (!$gatherers) {
		error("Config file doesn't contain valid array of 'gatherers'!");
	}

	$targetUrl = $userConfig['target'] ?? false;
	if (!$targetUrl) {
		error("Config file doesn't contain valid 'target' URL!");
	}

	// Pass environment variables defined in config file to the current process
	// environment.
	$envVars = $userConfig['env'] ?? [];
	foreach ($envVars as $name => $value) {
		putenv("$name=$value");
	}

	while (true) {

		$report = report($gatherers);

		if (!$config['daemonize']) {
			echo(json_encode($report, JSON_PRETTY_PRINT) . "\n");
		}

		if ($targetUrl) {
			$final = prepare($template, $report);
			send($targetUrl, $final);
		}

		if ($config['interval'] === false) {
			break;
		}

		sleep($config['interval']);

		// We need to "repeat" the signal after sleep()
		// so that our signal handler is invoked properly.
		pcntl_signal_dispatch();

	}

}

function report(array $gatherers) {

	$report = [];

	foreach ($gatherers as $path) {

		$name = basename($path);
		if (!is_readable($path)) {
			error(sprintf("Gatherer '%s' not found.", $path));
		}

		info("Gathering from '$name' ...");
		exec("chmod +x $path");
		exec($path, $resultLines, $retval);

		// We need to "repeat" the signal after exec()
		// so that our signal handler is invoked properly.
		pcntl_signal_dispatch();

		if ($retval !== 0) {
			error(sprintf("Gatherer '%s' returned non-zero value %d. Skipping.", $name, $retval));
			continue;
		}

		$data = parse_ini_string(implode("\n", $resultLines), false, INI_SCANNER_TYPED);
		if ($data === false) {
			info(sprintf("Gatherer '%s' returned invalid data. Skipping.", $name));
			continue;
		}

		$report += $data;

	}

	return $report;

}

/**
 * Rewrite marked fields in template array (recursively).
 */
function prepare(array $template, array $variables): array {

	foreach ($template as $key => &$value) {
		if (is_array($value)) {
			$value = prepare($value, $variables);
		} else {

			// Evaluate expressions that may be present.
			$value = preg_replace_callback('#\${(.*)}#', function($m) use ($variables) {
				return eval_expression($m[1], $variables);
			}, $value);

		}
	}

	return $template;

}

function send(string $url, array $payload) {

	// Open connection
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

	curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	info(sprintf("(%s) Payload sent to %s (received %s).", date('r'), $url, $code));

}

function parse_arguments(array $args): array {

	$config = [
		'config_path' => false,
		'daemonize' => false,
		'send' => false,
		'interval' => false,
		'env' => [],
	];

	while ($a = array_shift($args)) {
		switch ($a) {
			case "-c":
			case "--config":
				$config['config_path'] = array_shift($args);;
				break;
			case "-s":
			case "--send":
				$config['send'] = true;
				break;
			case "-d":
				case "--daemonize":
				$config['daemonize'] = true;
				// If interval is already set, do not overwrite it.
				$config['interval'] = $config['interval'] ?: 5;
				break;
			case "-i":
			case "--interval":
				$config['interval'] = max(0, (int) array_shift($args));
				break;
			case "-p":
			case "--pid":
				if ($pid = get_daemon_pid()) {
					info("Daemon PID: " . $pid);
				} else {
					error("No existing daemon!");
				}
				die;
			case "--self-update":
				self_update();
				die();
			case "-h":
			case "--help":
				die(get_help());
			default:
				error("Unknown option '$a'");
				die;
		}
	}

	return $config;

}

function get_help() {

	echo <<<HELP

Usage:
reporter <options>

Options:
--help, -h
	Display this help.
--config <path>, -c <path>
	Path to the config file.
--send, -s
	POST the gathered report to target URL specified in the config file.
--daemonize, -d
	Daemonize the reporter after initializing (send it to background).
--interval <seconds>, -i <seconds>
	Delay in seconds between gatherings (do it once by default).
--pid, -p
	Report an existing daemon's PID.
--self-update
	Perform a self-update from online repository.


HELP;

}

// Daemonization.

function daemonize() {

	if ($daemonPid = get_daemon_pid()) {
		exec("kill -9 $daemonPid 2>&1 > /dev/null");
		@unlink(DAEMON_PID_FILE);
		info("Killed previous daemon running with PID $daemonPid.\n");
	}

	$newpid = pcntl_fork();
	if ($newpid === -1) {
		error("Couldn't fork!");
	} elseif ($newpid) {
		// I'm the parent that started the fork. Let's self-destruct.
		exit(0);
	}

	// Become the session leader
	posix_setsid();
	usleep(100000);

	// Fork again, but now as session leader.
	$newpid = pcntl_fork();
	if ($newpid === -1) {
		error("Couldn't fork!");
	} elseif ($newpid) {
		// I'm the parent that started the second fork. Let's self-destruct.
		exit(0);
	}

	$pid = posix_getpid();
	file_put_contents(DAEMON_PID_FILE, $pid);

	detach_terminal();

}

function detach_terminal() {

	fclose(STDIN);
	fclose(STDOUT);
	fclose(STDERR);

	$void = fopen('/dev/null', 'w');

	// Catch all input and send it to /dev/null.
	// If don't do this, any output would (I figure) result in silent error and then exit.
	// (Silent because there's no way to output any error - STDERR is closed.)
	ob_start(function($buffer) use($void) {
		fwrite($void, $buffer);
	}, 100);

	ob_clean();

}

function get_daemon_pid() {

	// Does PID file exist?
	if (!is_readable(DAEMON_PID_FILE)) {
		return false;
	}

	$pid = file_get_contents(DAEMON_PID_FILE);

	// Does the PID (process) really exist?
	if (!file_exists( "/proc/$pid")) {
		return false;
	}

	return $pid;

}

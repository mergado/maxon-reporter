<?php

declare(strict_types=1);

namespace Mergado\Maxon\Reporter;

require_once __DIR__ . '/loader.php';

// Initialize Reporter app.
init();

define('DAEMON_PID_FILE', determine_pid_file_path());
const REQUIRED_EXTENSIONS = [
	'pcntl',
	'json',
	'posix',
	'curl',
];

info("Maxon Reporter");

info("Premysl Karbula, Mergado, 2021");
if (defined('COMPILED_AT')) {
	info(sprintf("Compiled at: %s", COMPILED_AT));
}
info("Machine: " . gethostname());

bootcheck();

$command = array_shift($argv);
$config = parse_arguments($argv);

run($config);

function run(array $config) {

	$configFile = determine_config_file($config['config_path']);
	$json = file_get_contents($configFile);
	$userConfig = json_decode_safe($json, true);

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

	if (!$config['single']) {
		daemonize();
	}

	// Pass environment variables defined in config file to the current process
	// environment.
	$envVars = $userConfig['env'] ?? [];
	foreach ($envVars as $name => $value) {
		putenv("$name=$value");
	}

	while (true) {

		$report = report($gatherers);

		if ($config['single']) {
			echo(json_encode($report, JSON_PRETTY_PRINT) . "\n");
			die;
		}

		if ($targetUrl) {
			$final = prepare($template, $report);
			send($targetUrl, $final);
		}

		if ($config['interval'] === false) {
			break;
		}

		sleep($config['interval']);

	}

}

function report(array $gatherers) {

	$report = [];

	foreach ($gatherers as $path) {

		$name = basename($path);
		if (!is_readable($path)) {
			info(sprintf("Gatherer '%s' not found. Skipping.", $path));
			continue;
		}

		info("Gathering from '$name' ...");
		exec("chmod +x $path");
		exec($path, $resultLines, $retval);

		if ($retval !== 0) {
			info(sprintf("Gatherer '%s' returned non-zero value %d. Skipping.", $name, $retval));
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
		'config_path' => '',
		'single' => false,
		'interval' => 5,
	];

	while ($a = array_shift($args)) {
		switch ($a) {
			case "-c":
			case "--config":
				$config['config_path'] = array_shift($args);;
				break;
			case "-s":
				case "--single":
				$config['single'] = true;
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
	Specify path to the config file.
	"./config/config.json" or "./config.json" are tried by default.
--single, -s
	Run the reporter once and print out gathered results.
	By default the reporter is automatically daemonized.
--interval <seconds>, -i <seconds>
	Delay in seconds between gatherings (default 5).
--pid, -p
	Report an existing daemon's PID.
--self-update
	Perform a self-update from online repository.


HELP;

}

function bootcheck() {

	$missing = [];
	foreach (REQUIRED_EXTENSIONS as $ext) {
		if (!extension_loaded($ext)) {
			$missing[] = $ext;
		}
	}

	if ($missing) {
		error("Missing required PHP extensions: " . implode(', ', $missing));
	}

}

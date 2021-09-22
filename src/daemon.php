<?php

declare(strict_types=1);

namespace Mergado\Maxon\Reporter;

require_once __DIR__ . '/loader.php';

function daemonize() {

	info("Daemonizing ...");

	// Kill previous daemon, if it exists.
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
		define('NO_SHUTDOWN_HANDLER', true);
		die;
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
		define('NO_SHUTDOWN_HANDLER', true);
		die;
	}

	init_daemon();

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
	// If we didn't do this, any output would (I figure) result in silent error
	// and then exit. (Silent because there's no way to output any
	// error - because the STDERR is closed.)
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

<?php

declare(strict_types=1);

namespace Mergado\Maxon\Reporter;

require_once __DIR__ . '/loader.php';

const USER_AGENT = "mergado-maxon-reporter-app";
const REPO = "mergado/maxon-reporter";
const BINARY_FILENAME = "reporter";
const GITHUB_API_URL = "https://api.github.com/";
const GITHUB_BINARY_URL_FORMAT = "repos/%s/contents/build/%s";

function self_update() {

	if (!defined('BINARY_PATH')) {
		error("Self update can be performed only from within binary!");
	}

	info("Retrieving newest binary URL ...");
	$url = get_latest_binary_url();
	if (!$url) {
		error("Could not retrieve latest binary URL!");
	}

	info("Performing self-update ...");
	copy($url, BINARY_PATH);
	exec(sprintf("chmod +x %s", BINARY_PATH));
	info("Done.");

}

function api_fetch(string $url) {
	return json_decode_safe(url_fetch(GITHUB_API_URL . $url), true);
}

function url_fetch(string $url) {

	$context = get_stream_context();

	if (PHP_MAJOR_VERSION >= 8) {
		$headers = get_headers($url, false, $context);
	} else {
		// PHP 7 compatibility.
		$headers = get_headers($url, 0, $context);
	}

	// Check if the URL doesn't return an error.
	if ($headers) {
		[$http, $code, $reason] = explode(' ', reset($headers), 3);
		if ($code > 400) {
			error("Cannot fetch '$url': [$code] $reason.");
		}
	}

	return file_get_contents($url, false, $context);

}

function get_latest_binary_url() {

	$contentUrl = sprintf(GITHUB_BINARY_URL_FORMAT, REPO, BINARY_FILENAME);
	return api_fetch($contentUrl)['download_url'] ?? false;

}

function get_stream_context() {

	static $context;

	// Github v3 API needs an user-agent to be sent, otherwise it returns 403.
	if (!$context) {

		$headers = [];
		$headers[] = 'User-Agent: ' . USER_AGENT;

		// This ENV may or may not be set.
		// (And it should be set in Travis CI testing environment.)
		if ($token = getenv('GITHUB_TOKEN')) {
			$headers[] = "Authorization: token $token";
		}

		$options = ['http' => ['header' => implode("\n", $headers)]];
		$context = stream_context_create($options);

	}

	return $context;

}

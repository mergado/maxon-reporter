<?php

namespace Mergado\Maxon\Reporter;

require_once __DIR__ . '/loader.php';

const REPO = "mergado/maxon-reporter";
const GITHUB_BINARY_URL_FORMAT = "https://api.github.com/repos/%s/contents/build/%s";

function self_update() {

	if (!defined('BINARY_PATH')) {
		error("Self update can be performed only from within binary!");
	}

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
	return json_decode_safe(url_fetch($url), true);
}

function url_fetch(string $url) {

	$context = get_stream_context();

	// Check if the URL doesn't return an error.
	if ($headers = get_headers($url, null, $context)) {
		list($http, $code, $reason) = explode(' ', reset($headers), 3);
		if ($code > 400) {
			error("Cannot fetch '$url': [$code] $reason.");
		}
	}

	return file_get_contents($url, false, $context);

}

function get_latest_binary_url() {

	$contentUrl = sprintf(GITHUB_BINARY_URL_FORMAT, REPO, basename(BINARY_PATH));
	return api_fetch($contentUrl)['download_url'] ?? false;

}

function get_stream_context() {

	static $context;

	// Github v3 API needs an user-agent to be sent, otherwise it returns 403.
	if (!$context) {
		$options = ['http' => ['header' => ['User-Agent: PHP']]];
		$context = stream_context_create($options);
	}

	return $context;

}

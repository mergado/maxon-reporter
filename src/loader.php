<?php

const AUTOLOAD_FILES = [
	__DIR__ . '/helpers.php',
	__DIR__ . '/signals.php',
	__DIR__ . '/update.php',
	__DIR__ . '/expr.php',
];

foreach (AUTOLOAD_FILES as $f) {
	require_once $f;
}

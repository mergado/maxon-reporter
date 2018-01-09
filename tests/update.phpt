<?php

use \Tester\Assert;
use \Mergado\Maxon\Reporter;

require __DIR__ . '/bootstrap.php';

// Test fetching GitHub API URL.
$result = Reporter\api_fetch('meta');
Assert::type('array', $result);
Assert::truthy($result);

// Test getting URL of the (latest) binary.
$result = Reporter\get_latest_binary_url();
Assert::contains('reporter', $result);
Assert::contains('github', $result);

// Test fetching some URL.
$result = Reporter\url_fetch('https://httpbin.org/anything');
Assert::truthy($result);
Assert::true(json_decode($result) !== null);

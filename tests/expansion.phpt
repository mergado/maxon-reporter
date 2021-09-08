<?php

use \Tester\Assert;
use \Mergado\Maxon\Reporter;

require __DIR__ . '/bootstrap.php';

$vars = [
	"abc" => 123,
	"def" => "lols",
	"ghi" => "6xyz"
];

// Test fetching GitHub API URL.
$result = Reporter\expand_variables('A${abc * 2}B C${def}D/${ghi}E', $vars);
Assert::equal("A246B ClolsD/6xyzE", $result);

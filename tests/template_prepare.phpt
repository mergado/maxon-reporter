<?php

use \Tester\Assert;
use \Mergado\Maxon\Reporter;

require __DIR__ . '/bootstrap.php';

$vars = [
	"abc" => 123,
	"def" => "lols",
	"ghi" => "6xyz",
	"be" => "OXO",
];

$template = [
	'whatever' => [
		'structure' => [
			'should' => 'X${be}X',
			'supported' => 'but',
		],
		'variables' => [
			'should' => 'NOT ${be}${be}${be}',
			123 => 'expanded',
		],
	],
	0 => '',
	1 => 'non-scalar ${abc}',
	'values, such as' => true,
	'or' => false,
	'or even' => 123456,
	'numbers!' => [
		'not even in' => [
			'nested' => 999,
			'arrays' => true,
			'yay' => '_${ghi}_',
		],
	],
];

$expected = [
	'whatever' => [
		'structure' => [
			'should' => 'XOXOX',
			'supported' => 'but',
		],
		'variables' => [
			'should' => 'NOT OXOOXOOXO',
			123 => 'expanded',
		],
	],
	0 => '',
	1 => 'non-scalar 123',
	'values, such as' => true,
	'or' => false,
	'or even' => 123456,
	'numbers!' => [
		'not even in' => [
			'nested' => 999,
			'arrays' => true,
			'yay' => '_6xyz_',
		],
	],
];

Assert::same($expected, Reporter\prepare($template, $vars));

<?php

use \Tester\Assert;
use function \Mergado\Maxon\Reporter\eval_expression;

require __DIR__ . '/bootstrap.php';

$result = eval_expression("28+782-287*27+87-287*827*78+7-72");
Assert::same('-18520139', $result);

$parenths = eval_expression("28 + 782 - (287 * 27) + 87 - (287 * 827 * 78) + 7 - 72");
Assert::same($result, $parenths);

// Test crazy spaces.
$result = eval_expression("1+2 * 3 / 4*-3 +1*-12/-4");
Assert::same('-0.5', $result);

// Test variables.
$result = eval_expression("a +b*c /d *-3+ 1*-12/ -4", [
	'a' => 1,
	'b' => 2,
	'c' => 3,
	'd' => 4,
]);
Assert::same('-0.5', $result);

// Test various parentheses.
Assert::same('18', eval_expression("(1 + 2) * 6"));
Assert::same('13', eval_expression("1 + (2 * 6)"));
Assert::same('13', eval_expression("(1 + (2) * 6)"));

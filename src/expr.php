<?php

namespace Mergado\Maxon\Reporter;

require_once __DIR__ . '/loader.php';

define('REPORTER_EXPR_GRAMMAR', build_expression_grammar());

function eval_expression($expr, array $varPool = [], $state = null) {

	$grammar = REPORTER_EXPR_GRAMMAR;

	// Detect sub-expressions (parentheses) and expand (evaluate) them first.
	// Start with the inner ones and evaluate them until there are none left.
	while (preg_match("#{$grammar['parenthsRegex']}#", $expr)) {
		$expr = preg_replace_callback("#{$grammar['parenthsRegex']}#", function($m) use ($varPool) {
			return eval_expression($m[1], $varPool, 'parenthseses');
		}, $expr);
	}

	// At this point we know there are no more parentheses in the current
	// expression. We're gonna evaluate from left to right from now on.

	// Evaluate multiplication/division pairs first.
	if ($state !== 'multiply') {
		// Don't stop until there are no pairs left.
		while (preg_match("#{$grammar['multiplyRegex']}#", $expr)) {
			$expr = preg_replace_callback("#{$grammar['multiplyRegex']}#", function($m) use ($varPool) {

				// Pass the state so we're not going to be stuck in a loop
				// matching the same multiplication/division pair again and
				// again.
				return eval_expression($m[0], $varPool, 'multiply');

			}, $expr, 1); // Do only one operation at a time, so we start always from the left-most operand.
		}
	}

	// Evaluate addition/subtraction pairs last.
	if ($state !== 'add') {
		// Don't stop until there are no pairs left.
		while (preg_match("#{$grammar['addRegex']}#", $expr)) {
			$expr = preg_replace_callback("#{$grammar['addRegex']}#", function($m) use ($varPool) {

				// Pass the state so we're not going to be stuck in a loop
				// matching the same addition/subtraction pair again and
				// again.
				return eval_expression($m[0], $varPool, 'add');

			}, $expr, 1); // Do only one operation at a time, so we start always from the left-most operand.
		}
	}

	// At this point we know that we have either an expression with two operands
	// or a single value or variable.

	// Find + - * / symbol, if it's present.
	preg_match("#
		(?<l>{$grammar['operandRegex']})
		\s*(?<op>[+*/\-])\s*
		(?<r>{$grammar['operandRegex']})
	#x", $expr, $m);
	$operator = trim($m['op'] ?? null);

	if ($operator) {

		$l = trim($m['l']);
		$r = trim($m['r']);
		$l = try_expanding_variable($l, $varPool);
		$r = try_expanding_variable($r, $varPool);

		// Calculate the result.
		switch ($operator) {
			case "*":
				$result = $l * $r;
			break;
			case "/":
				$result = $l / $r;
			break;
			case "-":
				$result = $l - $r;
			break;
			case "+":
				$result = $l + $r;
			break;
		}

	} else {

		// Expression is now only a single variable or a value.
		$result = try_expanding_variable($expr, $varPool);

	}

	return $result;

}

function try_expanding_variable($expr, array $varPool) {

	$grammar = REPORTER_EXPR_GRAMMAR;

	// If expression matches a variable name - expand it.
	if (preg_match("#{$grammar['variableRegex']}#", $expr)) {
		if (isset($varPool[$expr])) {
			return $varPool[$expr];
		} else {
			error("Undefined variable '$expr'");
		}
	}

	// Was not a variable, just return the value.
	return $expr;

}

function build_expression_grammar() {

	$g = [];
	$g['numberRegex'] = '[+-]?\d+(\.\d+)?';
	$g['variableRegex'] = '[a-zA-Z][a-zA-Z0-9_.]*';
	$g['operandRegex'] = "(({$g['variableRegex']})|({$g['numberRegex']}))";
	$g['multiplyRegex'] = "{$g['operandRegex']}\s*[*\/]\s*{$g['operandRegex']}";
	$g['addRegex'] = "{$g['operandRegex']}\s*[+-]\s*{$g['operandRegex']}";
	$g['parenthsRegex'] = '\(([^\(]*?)\)';

	return $g;

}

<?php

use \Tester\Assert;
use function \Mergado\Maxon\Reporter\validate_config_target;

require __DIR__ . '/bootstrap.php';

Assert::null(validate_config_target(1));
Assert::null(validate_config_target(false));
Assert::null(validate_config_target([]));
Assert::null(validate_config_target(['this is ok, but the second item is not', []]));
Assert::null(validate_config_target(['this is ok, but the second item is not', false]));
Assert::null(validate_config_target(['this is ok, but the second item is not', 0.1]));

Assert::type('array', validate_config_target('yay'));
Assert::type('array', validate_config_target(['yay', 'two yays']));

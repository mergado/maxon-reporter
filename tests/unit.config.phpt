<?php

use \Tester\Assert;
use function \Mergado\Maxon\Reporter\validate_config_target;

require __DIR__ . '/bootstrap.php';

Assert::false(validate_config_target(1));
Assert::false(validate_config_target(false));
Assert::false(validate_config_target([]));
Assert::false(validate_config_target(['this is ok, but the second item is not', []]));
Assert::false(validate_config_target(['this is ok, but the second item is not', false]));
Assert::false(validate_config_target(['this is ok, but the second item is not', 0.1]));

Assert::true(validate_config_target('yay'));
Assert::true(validate_config_target(['yay', 'two yays']));

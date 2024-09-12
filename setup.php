<?php

use Wwwision\DamExample\Factory;

require __DIR__ . '/vendor/autoload.php';

$dam = Factory::build();
$dam->setup();

echo "Done\n";
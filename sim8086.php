<?php

require_once 'vendor/autoload.php';

use Opmvpc\Sim8086\Sim;

// get args from cli [php sim8086.php filename]
$filename = $argv[1];

Sim::run($filename);

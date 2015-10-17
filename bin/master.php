<?php

require "../vendor/autoload.php";
require "../db/bootstrap.php";

$loop = \React\EventLoop\Factory::create();

$master = new \BitWasp\Payments\Worker\Master($loop);

$loop->run();
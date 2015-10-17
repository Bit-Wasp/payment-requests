<?php

require "../vendor/autoload.php";

$loop = React\EventLoop\Factory::create();
$cWorker = new \BitWasp\Payments\Worker\CoinWorker($loop);
$loop->run();
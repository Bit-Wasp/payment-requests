<?php

require "../vendor/autoload.php";

$loop = React\EventLoop\Factory::create();
$worker = new \BitWasp\Payments\Worker($loop);
$loop->run();

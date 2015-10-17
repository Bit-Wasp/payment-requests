<?php

require_once "../vendor/autoload.php";

$loop = \React\EventLoop\Factory::create();
$context = new \React\ZMQ\Context($loop);

$listener = $context->getSocket(ZMQ::SOCKET_PULL);
$listener->bind('tcp://127.0.0.1:5559');
$listener->on('message', function ($msg) {
    echo " + - - - - - - - - - + \n";
    echo "       RESULTS         \n";
    print_r($msg);
    echo "\n + - - - - - - - - - + \n\n";
});

$loop->run();
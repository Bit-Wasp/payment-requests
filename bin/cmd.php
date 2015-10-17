<?php

require "../vendor/autoload.php";

$loop = React\EventLoop\Factory::create();

$context = new React\ZMQ\Context($loop);

$push = $context->getSocket(ZMQ::SOCKET_PUSH);
$push->connect('tcp://127.0.0.1:5555');

$push->send(json_encode(["command"=>"new"]));

$loop->run();

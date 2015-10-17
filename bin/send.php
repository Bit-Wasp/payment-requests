<?php

require "../vendor/autoload.php";

$loop = React\EventLoop\Factory::create();

$context = new React\ZMQ\Context($loop);

$push = $context->getSocket(ZMQ::SOCKET_PUSH);
$push->connect('tcp://127.0.0.1:5555');

$push->send(json_encode(['slug' => mt_rand(0, 99999999), 'command'=> 'new', 'outputs' => [
    ['value' => 50, 'script' => '76a91462e907b15cbf27d5425399ebf6f0fb50ebb88f1888ac']
]]));


$loop->run();

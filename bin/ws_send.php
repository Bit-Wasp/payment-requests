<?php

require "../vendor/autoload.php";

$loop = React\EventLoop\Factory::create();

$context = new React\ZMQ\Context($loop);

$push = $context->getSocket(ZMQ::SOCKET_PUSH);
$push->connect('tcp://127.0.0.1:5510');
$push->send(json_encode(['slug' => '2ab2320fe62fcafeb6933c48585ceae37df548df6dc11a16aaa091ea4778496b', 'req'=> [
    'title' => 'test.tx',
    'tx' => [
        'txid' => 'aeaeaeae',
        'totalValue' => 10000000,
        'totalValueBtc' => '0.001',
        'outputs' => [
            [   'script'    => 'aeae',
                'valueBtc'  => '0.001',
                'value'     => 1000000  ],
        ]
    ]
]]));


$loop->run();

<?php

require "../vendor/autoload.php";

$loop = React\EventLoop\Factory::create();

$netFactory = new \BitWasp\Bitcoin\Networking\Factory($loop);
$dns = $netFactory->getDns();
$peerFactory = $netFactory->getPeerFactory($dns);
$locator = $peerFactory->getLocator();
$manager = $peerFactory->getManager(true);

$locator->queryDnsSeeds(1)->then(function ($locator) use ($manager, $loop) {
    $manager
        ->connectToPeers($locator, 2)
        ->then(function (array $peers) use ($loop) {

            $contracts = [];
            $inventory = new SplObjectStorage();
            $context = new React\ZMQ\Context($loop);

            // subscribes to work.new
            // publishes work done.

            $listener = $context->getSocket(ZMQ::SOCKET_PUSH);
            $listener->connect('tcp://127.0.0.1:5559');

            $receiver = $context->getSocket(ZMQ::SOCKET_PULL);
            $receiver->connect('tcp://127.0.0.1:5557');
            $receiver->on('error', function ($e) {
                var_dump($e->getMessage());
            });

            $receiver->on('message', function ($msg) use (&$contracts) {
                echo "Received: $msg\n";
                $message = json_decode($msg, true);
                if (is_array($message)) {
                    if (isset($message['command'])) {
                        echo "Add request to memory\n";
                        if ($message['command'] == 'new') {
                            $contract = [
                                'id' => $message['id'],
                                'command' => $message['command']
                            ];

                            $cumulative = array();
                            foreach ($message['outputs'] as $output) {
                                $script = pack("H*", $output['script']);
                                if (!isset($cumulative[$script])) {
                                    $cumulative[$script] = $output['value'];
                                } else {
                                    $cumulative[$script] += $output['value'];
                                }
                            }

                            $contract['requirements'] = $cumulative;
                            $contracts[$message['id']] = $contract;
                        }
                    }
                }
            });

            foreach ($peers as $peer) {
                $peer->on('inv', function (
                    \BitWasp\Bitcoin\Networking\Peer\Peer $peer,
                    \BitWasp\Bitcoin\Networking\Messages\Inv $inv
                ) use ($inventory) {

                    $count = count($inv->getItems());
                    $request = [];
                    for ($i = 0; $i < $count; $i++) {
                        $item = $inv->getItem($i);
                        if (!$inventory->contains($item)) {
                            if ($item->isTx()) {
                                $inventory->attach($item);
                                $request[] = $item;
                            }
                        } else {
                            echo "dup";
                        }
                    }
                    echo "\n";

                    if (count($request)) {
                        echo "GET "  . count($request) . " txs \n";
                        $peer->getdata($request);
                    }
                });

                $peer->on('tx', function (
                    \BitWasp\Bitcoin\Networking\Peer\Peer $peer,
                    \BitWasp\Bitcoin\Networking\Messages\Tx $tx
                ) use (&$contracts, $listener) {

                    echo "GOT TX\n";
                    $transaction = $tx->getTransaction();
                    $results = [];
                    $nOut = count($transaction->getOutputs());
                    for ($i = 0; $i < $nOut; $i++) {
                        $output = $transaction->getOutput($i);
                        $script = $output->getScript()->getBinary();
                        if (!isset($results[$script])) {
                            $results[$script] = $output->getValue();
                        } else {
                            $results[$script] += $output->getValue();
                        }
                    }

                    foreach ($contracts as $contract) {
                        $requirements = $contract['requirements'];

                        $rCount = count($requirements);
                        $have = 0;
                        foreach ($requirements as $script => $value) {
                            if (isset($results[$script])) {
                                echo 'pmt';
                                if ($results[$script] >= $value) {
                                    $have++;
                                }
                            }
                        }

                        if ($have > 0) {
                            if ($have < $rCount) {
                                $command = 'tx.partial';
                            } else {
                                $command = 'tx.complete';
                            }

                            $listener->send(json_encode([
                                'id' => $contract['id'],
                                'command' => $command,
                                'tx' => $tx->getHex()
                            ]));
                        }
                    }
                });
            }

        });
});


$loop->run();


<?php


namespace BitWasp\Payments\Worker;


use BitWasp\Bitcoin\Networking\Messages\Block;
use BitWasp\Bitcoin\Networking\Messages\Inv;
use BitWasp\Bitcoin\Networking\Messages\Tx;
use BitWasp\Bitcoin\Networking\Peer\Peer;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

class CoinWorker extends EventEmitter
{
    /**
     * @var
     */
    private $inventory;

    /**
     * @var \ZMQSocket
     */
    private $listener;

    /**
     * @var \ZMQSocket
     */
    private $receiver;

    /**
     * @var array
     */
    private $contracts = [];

    /**
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop)
    {
        $this->inventory = [];
        $netFactory = new \BitWasp\Bitcoin\Networking\Factory($loop);
        $dns = $netFactory->getDns();
        $peerFactory = $netFactory->getPeerFactory($dns);
        $locator = $peerFactory->getLocator();
        $manager = $peerFactory->getManager(true);

        $locator
            ->queryDnsSeeds(1)
            ->then(function ($locator) use ($manager, $loop) {
            $manager
                ->connectToPeers($locator, 1)
                ->then(function (array $peers) use ($loop) {
                    $this->initZmq($loop);
                    foreach ($peers as $peer) {
                        $this->initPeer($peer);
                    }
                });
        });
    }

    /**
     * @param TransactionInterface $transaction
     */
    private function processTransaction(TransactionInterface $transaction)
    {
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

        // Compare results to known contracts
        foreach ($this->contracts as $contract) {
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

                $this->listener->send(json_encode([
                    'slug' => $contract['slug'],
                    'command' => $command,
                    'tx' => $transaction->getHex()
                ]));
            }
        }
    }

    /**
     * @param Peer $peer
     * @param Inv $inv
     */
    public function onInv(Peer $peer, Inv $inv)
    {
        $count = count($inv->getItems());
        $request = [];
        for ($i = 0; $i < $count; $i++) {
            $item = $inv->getItem($i);
            $hash = $item->getHash()->getBinary();
            array_push($this->inventory, $hash);
            $request[] = $item;
        }

        if (count($request)) {
            echo "GET "  . count($request) . " txs \n";
            $peer->getdata($request);
        }
    }

    /**
     * @param Peer $peer
     * @param Tx $tx
     */
    public function onTx(Peer $peer, Tx $tx)
    {
        $this->emit('tx', [$tx->getTransaction()]);
    }

    /**
     * @param Peer $peer
     * @param Block $block
     */
    public function onBlock(Peer $peer, Block $block)
    {
        $start = microtime(true);
        $block = $block->getBlock();
        $txs = $block->getTransactions();
        $count = count($txs);
        for ($i = 0; $i < $count; $i++) {
            $this->emit('tx', [$txs->getTransaction($i)]);
        }
        echo "Block took " . (microtime(true) - $start) . "\n";
    }

    /**
     * @param Peer $peer
     */
    public function initPeer(Peer $peer)
    {
        $self = $this;
        $peer->on('inv', array($self, 'onInv'));
        $peer->on('tx', array($self, 'onTx'));
        $peer->on('block', array($self, 'onBlock'));
        $this->on('tx', function (TransactionInterface $tx) {
            $this->processTransaction($tx);
        });
    }

    /**
     * @param LoopInterface $loop
     */
    public function initZmq(LoopInterface $loop)
    {
        /** @var \ZMQContext $context */
        $context = new \React\ZMQ\Context($loop);
        $this->listener = $context->getSocket(\ZMQ::SOCKET_PUSH);
        $this->listener->connect('tcp://127.0.0.1:5559');

        $this->receiver = $context->getSocket(\ZMQ::SOCKET_PULL);
        $this->receiver->connect('tcp://127.0.0.1:5557');

        $this->receiver->on('message', function ($msg) {
            $message = json_decode($msg, true);

            if (is_array($message) && isset($message['outputs']) && is_array($message['outputs'])) {
                $cumulative = array();
                foreach ($message['outputs'] as $output) {
                    $script = pack("H*", $output['script']);
                    if (!isset($cumulative[$script])) {
                        $cumulative[$script] = $output['value'];
                    } else {
                        $cumulative[$script] += $output['value'];
                    }
                }

                $message['requirements'] = $cumulative;
                $this->contracts[$message['slug']] = $message;
                echo "New contract: " . $msg . "\n";
            }
        });
    }

}
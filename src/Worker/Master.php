<?php


namespace BitWasp\Payments\Worker;


use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Payments\Db\Request;
use BitWasp\Payments\Db\Transaction;
use BitWasp\Payments\WebSocket\Pusher;
use React\EventLoop\LoopInterface;

class Master
{

    // save request (obtain id)
    // issue to worker
    // upon candidate
    //  save tx
    // upon solution
    //  save + commit

    public function __construct(LoopInterface $loop)
    {
        $context = new \React\ZMQ\Context($loop);

        $pusher = new Pusher();

        $workers = $context->getSocket(\ZMQ::SOCKET_PUSH);
        $workers->bind("tcp://127.0.0.1:5557");

        // Listen for work from the web
        $receiver = $context->getSocket(\ZMQ::SOCKET_PULL);
        $receiver->bind("tcp://127.0.0.1:5555");
        $receiver->on('message', function ($msg) use ($workers) {
            // Create
            echo "Received: " . $msg . "\n";
            $workers->send($msg);
        });

        $control = $context->getSocket(\ZMQ::SOCKET_PULL);
        $control->bind('tcp://127.0.0.1:5510');
        $control->on('message', function ($msg) use ($pusher) {
            echo "CONTROL MESSAGE\n";
            $arr = json_decode($msg, true);
            $slug = $arr['slug'];
            $req = $arr['req'];
            $pusher->send($slug, $arr['req']);
        });

        $listener = $context->getSocket(\ZMQ::SOCKET_PULL);
        $listener->bind('tcp://127.0.0.1:5559');
        $listener->on('message', function ($msg) use ($pusher) {
            echo " + - - - - - - - - - + \n";
            echo "       RESULTS         \n";
            print_r($msg);
            $message = json_decode($msg, true);
            if ($message['command'] == 'client.gotRequest') {
                $pusher->onClientGotRequest($message['slug']);
            } else if ($message['command'] == 'client.gotPayment') {

                $pusher->onClientGotPayment($message['slug']);
            } else if ($message['command'] == 'tx.complete') {
                try {
                    $request = Request::find(['slug' => $message['slug']]);
                    $transaction = TransactionFactory::fromHex($message['tx']);
                    Transaction::create([
                        'transaction' => $message['tx'],
                        'request_id' => $request->id,
                        'txid' => $transaction->getTransactionId()
                    ]);
                    $pusher->onCompleteTx($message['slug'], $message['tx']);
                } catch (\Exception $e) {
                    return;
                }
            } else if ($message['command'] == 'tx.partial') {
                $pusher->onCompleteTx($message['slug'], $message['tx']);
            }
            echo "\n + - - - - - - - - - + \n\n";
        });

        // Set up our WebSocket server for clients wanting real-time updates
        $webSock = new \React\Socket\Server($loop);
        $webSock->listen(8080, '0.0.0.0'); // Binding to 0.0.0.0 means remotes can connect
        $webServer = new \Ratchet\Server\IoServer(
            new \Ratchet\Http\HttpServer(
                new \Ratchet\WebSocket\WsServer(
                    new \Ratchet\Wamp\WampServer(
                        $pusher
                    )
                )
            ),
            $webSock
        );
    }
}
<?php

namespace BitWasp\Payments\WebSocket;


use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;

class Pusher implements WampServerInterface
{
    protected $subscribedTopics = [];

    public function onPartialTx($topic, $tx)
    {
        // If the lookup topic object isn't set there is no one to publish to
        if (!array_key_exists($topic, $this->subscribedTopics)) {
            return;
        }

        $topic = $this->subscribedTopics[$topic];

        // re-send the data to all the clients subscribed to that category
        $topic->broadcast(json_encode([
            'title' => 'tx.partial',
            'tx' => $tx
        ], true));
    }

    public function onCompleteTx($topic, $tx)
    {
        // If the lookup topic object isn't set there is no one to publish to
        if (!array_key_exists($topic, $this->subscribedTopics)) {
            return;
        }

        $topic = $this->subscribedTopics[$topic];

        // re-send the data to all the clients subscribed to that category
        $topic->broadcast(json_encode([
            'title' => 'tx.complete',
            'tx' => $tx
        ], true));
    }

    public function onClientGotPayment($topic)
    {
        // If the lookup topic object isn't set there is no one to publish to
        if (!array_key_exists($topic, $this->subscribedTopics)) {
            return;
        }

        $topic = $this->subscribedTopics[$topic];

        // re-send the data to all the clients subscribed to that category
        $topic->broadcast(json_encode([
            'title' => 'client.gotPayment'
        ], true));
    }

    public function send($topic, $message)
    {
        // If the lookup topic object isn't set there is no one to publish to
        if (!array_key_exists($topic, $this->subscribedTopics)) {
            return;
        }

        $topic = $this->subscribedTopics[$topic];

        // re-send the data to all the clients subscribed to that category
        $topic->broadcast(json_encode($message));
    }

    public function onClientGotRequest($topic)
    {
        // If the lookup topic object isn't set there is no one to publish to
        if (!array_key_exists($topic, $this->subscribedTopics)) {
            return;
        }

        $topic = $this->subscribedTopics[$topic];

        // re-send the data to all the clients subscribed to that category
        $topic->broadcast(json_encode([
            'title' => 'client.gotRequest'
        ], true));
    }

    public function onRequestComplete($topic)
    {

    }

    public function onSubscribe(ConnectionInterface $conn, $topic)
    {
        echo 'client subscribed'. PHP_EOL;
        $this->subscribedTopics[$topic->getId()] = $topic;
    }

    public function onUnSubscribe(ConnectionInterface $conn, $topic)
    {

    }

    public function onOpen(ConnectionInterface $conn)
    {
        echo 'client opened' . PHP_EOL;
    }

    public function onClose(ConnectionInterface $conn)
    {
        echo 'client dropped' . PHP_EOL;
    }

    public function onCall(ConnectionInterface $conn, $id, $topic, array $params)
    {
        $conn->callError($id, $topic, 'You are not allowed to make calls')->close();
    }

    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible)
    {
        $conn->close();
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {

    }
}
<?php
/**
 * Created by PhpStorm.
 * User: aeonium
 * Date: 01/10/15
 * Time: 19:47
 */

namespace BitWasp\Payments\Api;


use BitWasp\Bitcoin\Amount;
use BitWasp\Bitcoin\PaymentProtocol\Protobufs\PaymentRequest;
use BitWasp\Payments\Db\OutputRequirement;
use BitWasp\Payments\Db\Request;

class RequestApi
{
    /**
     * @var \ZMQContext
     */
    private $context;

    /**
     * @var \ZMQSocket
     */
    private $pushSocket;

    /**
     * @var \ZMQSocket
     */
    private $announceSocket;

    /**
     * @param \ZMQContext $context
     */
    public function __construct(\ZMQContext $context)
    {
        $this->context = $context;
        $this->amount = new Amount();
    }

    /**
     * @param array $on
     * @return Request
     * @throws \ActiveRecord\RecordNotFound
     */
    private function getRequest(array $on)
    {
        return Request::find($on);
    }

    /**
     * @param int $id
     * @return Request
     */
    public function getRequestById($id)
    {
        return $this->getRequest(['id' => $id]);
    }

    /**
     * @param string $slug
     * @return Request
     */
    public function getRequestBySlug($slug)
    {
        return $this->getRequest(['slug' => $slug]);
    }

    /**
     * @return \ZMQSocket
     */
    private function getAnnounceSocket()
    {
        if (null == $this->announceSocket) {
            $this->announceSocket = $this->context->getSocket(\ZMQ::SOCKET_PUSH);
            $this->announceSocket->connect('tcp://127.0.0.1:5559');
        }

        return $this->announceSocket;
    }

    /**
     * @return \ZMQSocket
     */
    private function getSocket()
    {
        if (null == $this->pushSocket) {
            $this->pushSocket = $this->context->getSocket(\ZMQ::SOCKET_PUSH, 'my pusher');
            $this->pushSocket->connect("tcp://localhost:5555");
        }

        return $this->pushSocket;
    }

    /**
     * @param $id
     * @param $slug
     */
    public function pushGotRequest($slug)
    {
        $this->getAnnounceSocket()
            ->send(json_encode([
                'command' => 'client.gotRequest',
                'slug' => $slug,
            ]));
        error_log('survived');
    }

    /**
     * @param $id
     * @param $slug
     */
    public function pushGotPayment($slug)
    {
        $this->getAnnounceSocket()
            ->send(json_encode([
                'command' => 'client.gotPayment',
                'slug' => $slug,
            ]));
        error_log('survived');
    }

    /**
     * @param string $id
     * @param OutputRequirement[] $outputs
     */
    public function pushRequest($id, array $outputs)
    {
        $outs = [];
        foreach ($outputs as $output) {
            $outs[] = [
                'value' => $output->value,
                'script' => bin2hex($output->script)
            ];
        }

        $this
            ->getSocket()
            ->send(json_encode([
            'slug' => $id,
            'outputs' => $outs
        ]));
    }
}
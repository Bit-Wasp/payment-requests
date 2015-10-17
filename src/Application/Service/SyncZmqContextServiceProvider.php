<?php

namespace BitWasp\Payments\Application\Service;


use Silex\Application;
use Silex\ServiceProviderInterface;

class SyncZmqContextServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['sync_zmq'] = $app->share(function (Application $app) {
            $app->flush();

            return new \ZMQContext();
        });
    }

    public function boot(Application $app)
    {
    }
}
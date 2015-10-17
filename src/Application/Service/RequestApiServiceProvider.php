<?php

namespace BitWasp\Payments\Application\Service;


use BitWasp\Payments\Api\RequestApi;
use Silex\Application;
use Silex\ServiceProviderInterface;

class RequestApiServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['request_api'] = $app->share(function (Application $app) {
            $app->flush();

            return new RequestApi($app['sync_zmq']);
        });
    }

    public function boot(Application $app)
    {
    }
}
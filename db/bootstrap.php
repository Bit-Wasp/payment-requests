<?php

ActiveRecord\Config::initialize(function($cfg)
{
    $cfg->set_model_directory(__DIR__ . '/../src/Db/');
    $cfg->set_connections(
        array(
            'development' => 'mysql://root:sugarpop101@localhost/payments',
            'test' => 'mysql://root:sugarpop101@localhost/payments',
            'production' => 'mysql://root:sugarpop101@localhost/payments'
        )
    );
});
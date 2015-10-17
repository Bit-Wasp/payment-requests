<?php

require "../vendor/autoload.php";
require "../db/bootstrap.php";

$list = \BitWasp\Payments\Db\Request::find('all');
print_r($list);
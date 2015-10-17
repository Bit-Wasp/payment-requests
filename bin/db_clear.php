<?php

require "../vendor/autoload.php";
require "../db/bootstrap.php";

use BitWasp\Payments\Db\Request;
use BitWasp\Payments\Db\OutputRequirement;

Request::delete_all(array('conditions' => []));
OutputRequirement::delete_all(array('conditions' => []));
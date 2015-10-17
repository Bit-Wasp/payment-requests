<?php

namespace BitWasp\Payments\Application\Validator;


use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class BitcoinAddress extends Constraint
{
    public $message = 'The provided value was not a valid bitcoin address';
}
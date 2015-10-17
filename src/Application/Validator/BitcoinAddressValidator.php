<?php

namespace BitWasp\Payments\Application\Validator;


use BitWasp\Bitcoin\Address\AddressFactory;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class BitcoinAddressValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        try {
            AddressFactory::fromString($value);

        } catch (\Exception $e) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
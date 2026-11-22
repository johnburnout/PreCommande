<?php

declare(strict_types=1);

namespace PreCommande\Validation;

use PreCommande\Vendor\Symfony\Component\Validator\Validation;
use PreCommande\Vendor\Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidatorFactory
{
    public static function create(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }
}

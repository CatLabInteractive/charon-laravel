<?php

namespace CatLab\Charon\Laravel\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use CatLab\Requirements\Exceptions\PropertyValidationException;

/**
 * Class InputValidatorException
 * @package CatLab\Charon\Laravel
 */
class InputValidatorException extends HttpException
{
    /**
     * @param InputValidatorException $e
     * @param int $statusCode
     * @return InputValidatorException
     */
    public static function make(PropertyValidationException $e, $statusCode = 400)
    {
        return new self($statusCode, $e->getMessages()->__toString(), $e);
    }
}
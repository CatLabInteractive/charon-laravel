<?php

namespace CatLab\Charon\Laravel\Exceptions;

use CatLab\Charon\Exceptions\CharonException;
use CatLab\Requirements\Collections\MessageCollection;
use CatLab\Requirements\Exceptions\PropertyValidationException;
use CatLab\Requirements\Models\TranslatableMessage;
use Illuminate\Support\Arr;

/**
 * Class InputValidatorException
 * @package CatLab\Charon\Laravel
 */
class InputValidatorException extends CharonException
{
    /**
     * @var MessageCollection
     */
    protected $messages;

    /**
     * @var string
     */
    protected $container;

    /**
     * @param InputValidatorException $e
     * @param int $statusCode
     * @return InputValidatorException
     */
    public static function make(string $container, PropertyValidationException $e, $statusCode = 400)
    {
        // Use the first message as 'regular' error message, but also store all messages for the error handler
        // to be able to display all messages.

        /** @var TranslatableMessage $message */
        $message = Arr::first($e->getMessages());

        /** @var InputValidatorException $error */
        $error = self::makeTranslatable($container . ': ' . $message->getTemplate(), $message->getValues(), $statusCode, $e);
        $error->messages = $e->getMessages();
        $error->container = $container;

        return $error;
    }

    /**
     * @return MessageCollection
     */
    public function getMessages(): MessageCollection
    {
        return $this->messages;
    }

    /**
     * @return string
     */
    public function getContainer(): string
    {
        return $this->container;
    }
}

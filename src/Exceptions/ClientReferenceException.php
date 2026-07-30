<?php

namespace CatLab\Charon\Laravel\Exceptions;

use CatLab\Requirements\Collections\MessageCollection;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use CatLab\Requirements\Models\Message;

/**
 * Class ClientReferenceException
 *
 * Thrown when a write payload's client reference ('$ref', see
 * CatLab\Charon\Models\ClientReferenceMap) could not be resolved once the
 * whole payload has been processed: some resource linked to a '$ref' that no
 * sibling/nested resource in the same request ever registered.
 *
 * Extends ResourceValidationException so it is caught by the exact same
 * `catch (ResourceValidationException $e)` blocks CrudController already
 * uses for write validation failures, and renders through the same
 * getValidationErrorResponse() body shape -- callers only need to special
 * case the status code (422, since the request was well-formed but
 * unprocessable, unlike a plain 400 validation failure).
 *
 * Note: ResourceValidationException::make() cannot be reused here to
 * construct an instance of this class -- `new self()` inside that inherited
 * static method resolves (at compile time) to ResourceValidationException,
 * not to this subclass. This class therefore keeps its own message
 * collection and overrides getMessages() to expose it.
 *
 * @package CatLab\Charon\Laravel\Exceptions
 */
class ClientReferenceException extends ResourceValidationException
{
    /**
     * @var MessageCollection
     */
    private $refMessages;

    /**
     * @param string $ref
     * @return static
     */
    public static function unresolvedRef(string $ref): self
    {
        $text = sprintf("Unresolved client reference '%s'.", $ref);

        $messages = new MessageCollection();
        $messages->add(new Message($text, null, '$ref'));

        $e = new self($text);
        $e->refMessages = $messages;

        return $e;
    }

    /**
     * @return MessageCollection
     */
    public function getMessages()
    {
        return $this->refMessages;
    }
}

<?php

namespace CatLab\Charon\Laravel\Models;

use CatLab\Charon\Interfaces\SerializableResource;
use CatLab\Charon\Interfaces\Context;
use Illuminate\Http\ResponseTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ResourceResponse
 *
 * Helper method to allow passing resource responses through the laravel / symfony stack.
 *
 * @package CatLab\Charon\Laravel\Models
 */
class ResourceResponse extends Response implements \CatLab\Charon\Laravel\Contracts\Response
{
    use ResponseTrait;

    /**
     * @var SerializableResource
     */
    private $resource;

    /**
     * @var string
     */
    private $output;

    /**
     * @var \CatLab\Charon\Interfaces\Context
     */
    private $context;

    /**
     * Extra top-level meta entries (e.g. "$refs") to merge into the encoded
     * body on top of whatever the wrapped resource/collection serializes.
     * @var array
     */
    private array $meta = [];

    /**
     * ResourceResponse constructor.
     * @param SerializableResource $resource
     * @param Context $context
     * @param int $status
     * @param array $headers
     */
    public function __construct(
        SerializableResource $resource,
        ?Context $context = null,
        $status = 200,
        $headers = []
    ) {
        parent::__construct('', $status, $headers);
        $this->resource = $resource;

        if (isset($context)) {
            $this->setContext($context);
        }
    }

    /**
     * @return SerializableResource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Sends content for the current web response.
     *
     * @return Response
     */
    public function sendContent(): static
    {
        echo $this->getContent();

        return $this;
    }

    /**
     * @return false|string
     */
    public function getContent(): false|string
    {
        if (!isset($this->output)) {
            $this->output = $this->encode();
        }
        return $this->output;
    }

    /**
     * @return \CatLab\Charon\Interfaces\Context
     */
    public function getContext(): \CatLab\Charon\Interfaces\Context
    {
        return $this->context;
    }

    /**
     * @param \CatLab\Charon\Interfaces\Context $context
     * @return ResourceResponse
     */
    public function setContext(\CatLab\Charon\Interfaces\Context $context): ResourceResponse
    {
        $this->context = $context;
        return $this;
    }

    /**
     * @return mixed
     */
    public function toArray()
    {
        $data = $this->resource->toArray();

        if ($this->meta !== [] && is_array($data)) {
            $data['meta'] = array_merge($data['meta'] ?? [], $this->meta);
        }

        return $data;
    }

    /**
     * @param array $meta
     * @return static
     */
    public function setMeta(array $meta): static
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * The extra top-level meta entries set via setMeta(), if any. Exposed
     * (not just merged in toArray()) so subclasses that build their own
     * output shape instead of calling toArray()/encode() -- e.g.
     * JsonApiResponse::toJsonApi() -- can merge it into their own output too.
     * @return array
     */
    protected function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @return string
     */
    protected function encode()
    {
        return json_encode($this->toArray());
    }
}

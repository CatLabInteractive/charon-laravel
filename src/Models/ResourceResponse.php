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
     * ResourceResponse constructor.
     * @param SerializableResource $resource
     * @param Context $context
     * @param int $status
     * @param array $headers
     */
    public function __construct(
        SerializableResource $resource,
        Context $context = null,
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
        return $this->resource->toArray();
    }

    /**
     * @return string
     */
    protected function encode()
    {
        return json_encode($this->toArray());
    }
}

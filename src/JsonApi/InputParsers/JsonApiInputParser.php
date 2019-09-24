<?php

namespace CatLab\Charon\Laravel\JsonApi\InputParsers;

use CatLab\Base\Helpers\ArrayHelper;
use CatLab\Charon\Collections\IdentifierCollection;
use CatLab\Charon\Collections\ParameterCollection;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\DescriptionBuilder;
use CatLab\Charon\Interfaces\InputParser;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Interfaces\ResourceTransformer;

use CatLab\Charon\Laravel\InputParsers\LaravelInputParser;
use CatLab\Charon\Models\Routing\Parameters\ResourceParameter;
use CatLab\Charon\Models\Routing\Route;
use Request;

/**
 * Class JsonBodyInputParser
 * @package CatLab\Charon\InputParsers
 */
class JsonApiInputParser extends \CatLab\Charon\InputParsers\JsonBodyInputParser
{
    protected $contentType = 'application/vnd.api+json';

    use LaravelInputParser;

    /**
     * @return bool
     */
    protected function hasApplicableContentType()
    {
        switch ($this->getContentType()) {
            case $this->contentType:
                return true;
        }

        return false;
    }

    /**
     * Look for identifier input
     * @param ResourceTransformer $resourceTransformer
     * @param ResourceDefinition $resourceDefinition
     * @param Context $context
     * @param null $resource
     * @return IdentifierCollection|null
     */
    public function getIdentifiers(
        ResourceTransformer $resourceTransformer,
        ResourceDefinition $resourceDefinition,
        Context $context,
        $resource = null
    ) {
        if (!$this->hasApplicableContentType()) {
            return null;
        }

        $content = $this->getRawContent();
        $content = json_decode($content, true);

        if (!$content) {
            throw new \InvalidArgumentException("Could not decode body.");
        }

        $identifierCollection = new IdentifierCollection();

        if (!isset($content['data'])) {
            return null;
        }

        if (!ArrayHelper::isAssociative($content['data'])) {
            // This is a list of items
            foreach ($content['data'] as $item) {
                $identifier = $this->arrayToIdentifier($resourceDefinition, $item);
                if ($identifier) {
                    $identifierCollection->add($identifier);
                }
            }
        } else {
            $identifier = $this->arrayToIdentifier($resourceDefinition, $content);
            if ($identifier) {
                $identifierCollection->add($identifier);
            }
        }

        return $identifierCollection;
    }

    /**
     * Look for
     * @param ResourceTransformer $resourceTransformer
     * @param ResourceDefinition $resourceDefinition
     * @param Context $context
     * @param null $request
     * @return ResourceCollection|null
     */
    public function getResources(
        ResourceTransformer $resourceTransformer,
        ResourceDefinition $resourceDefinition,
        Context $context,
        $request = null
    ) {
        if (!$this->hasApplicableContentType()) {
            return null;
        }

        $rawContent = $this->getRawContent();
        $content = json_decode($rawContent, true);

        if (!$content) {
            throw new \InvalidArgumentException("Could not decode body: " . $rawContent);
        }

        if (!isset($content['data']) || !is_array($content['data'])) {
            return null;
        }

        $resourceCollection = $resourceTransformer->getResourceFactory()->createResourceCollection();

        $modelInput = $content['data']['attributes'];

        // our system handles relationships in the same way as attributes, so...
        if (isset($content['data']['relationships'])) {
            foreach ($content['data']['relationships'] as $relationshipName => $relationshipContent) {
                if (!isset($relationshipContent['data'])) {
                    continue;
                }
                $modelInput[$relationshipName] = [
                    'id' => $relationshipContent['data']['id']
                ];
            }
        }

        $resource = $resourceTransformer->fromArray(
            $resourceDefinition,
            $modelInput,
            $context
        );
        $resourceCollection->add($resource);

        return $resourceCollection;
    }
}

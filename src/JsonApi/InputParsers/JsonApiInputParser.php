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
use CatLab\Charon\Interfaces\ResourceDefinitionFactory;
use CatLab\Charon\Interfaces\ResourceTransformer;

use CatLab\Charon\Laravel\InputParsers\LaravelInputParser;
use CatLab\Charon\Models\Routing\Parameters\ResourceParameter;
use CatLab\Charon\Models\Routing\Route;
use Illuminate\Support\Str;
use Request;

/**
 * Class JsonBodyInputParser
 * @package CatLab\Charon\InputParsers
 */
class JsonApiInputParser extends \CatLab\Charon\InputParsers\JsonBodyInputParser
{
    use LaravelInputParser;

    /**
     * @var string
     */
    protected $contentType = 'application/vnd.api+json';

    /**
     * @var array
     */
    private $contentTypeParameters;

    /**
     * @return bool
     */
    protected function hasApplicableContentType()
    {
        if (Str::startsWith($this->getContentType(), $this->contentType)) {
            return true;
        }

        return false;
    }

    /**
     * Look for identifier input
     * @param ResourceTransformer $resourceTransformer
     * @param ResourceDefinitionFactory $resourceDefinition
     * @param Context $context
     * @param null $resource
     * @return IdentifierCollection|null
     */
    public function getIdentifiers(
        ResourceTransformer $resourceTransformer,
        ResourceDefinitionFactory $resourceDefinition,
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
     * @param ResourceTransformer $resourceTransformer
     * @param ResourceDefinitionFactory $resourceDefinition
     * @param Context $context
     * @param null $request
     * @return ResourceCollection|null
     */
    public function getResources(
        ResourceTransformer $resourceTransformer,
        ResourceDefinitionFactory $resourceDefinition,
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

        // do we have a bulk method?
        if (
            $this->hasContentTypeExtension('bulk') &&
            !ArrayHelper::isAssociative($content['data'])
        ) {
            $resources = $content['data'];
            $resourceCollection->addMeta('bulk', true);
        } else {
            $resources = [ $content['data'] ];
            $resourceCollection->addMeta('bulk', false);
        }

        foreach ($resources as $resourceData) {

            $modelInput = [];
            if (isset($resourceData['attributes'])) {
                $modelInput = $resourceData['attributes'];
            }

            if (isset($resourceData['id'])) {
                $modelInput['id'] = $resourceData['id'];
            }

            if (isset($resourceData['type'])) {
                $modelInput['type'] = $resourceData['type'];
            }

            // our system handles relationships in the same way as attributes, so...
            if (isset($resourceData['relationships'])) {
                foreach ($resourceData['relationships'] as $relationshipName => $relationshipContent) {
                    if (
                        !is_array($relationshipContent) ||
                        !array_key_exists('data', $relationshipContent)
                    ) {
                        continue;
                    }

                    // is null?
                    if (!isset($relationshipContent['data'])) {
                        $modelInput[$relationshipName] = null;
                    } elseif ($related = $this->getRelationshipContent($relationshipContent)) { // variable assignment in if stagement
                        $modelInput[$relationshipName] = $related;
                    }
                }
            }

            $resource = $resourceTransformer->fromArray(
                $resourceDefinition,
                $modelInput,
                $context
            );
            $resourceCollection->add($resource);
        }

        return $resourceCollection;
    }

    /**
     * @param $relationshipContent
     * @return array|null
     */
    protected function getRelationshipContent($relationshipContent)
    {
        // is one related resource?
        if (ArrayHelper::isAssociative($relationshipContent['data'])) {
            return $this->getRelatedObject($relationshipContent['data']);
        } else {
            // we have multiple related entities
            $out = [];
            foreach ($relationshipContent['data'] as $relatedResource) {
                if ($related = $this->getRelatedObject($relatedResource)) { // variable assignment in if stagement
                    $out[] = $related;
                }
            }
            return [ ResourceTransformer::RELATIONSHIP_ITEMS => $out ];
        }
    }

    /**
     * @param $relatedResource
     * @return array|null
     */
    protected function getRelatedObject($relatedResource)
    {
        if (isset($relatedResource['attributes'])) {
            if (isset($relatedResource['id'])) {
                $relatedResource['attributes']['id'] = $relatedResource['id'];
            }
            return $relatedResource['attributes'];
        } elseif (isset($relatedResource['id'])) {
            return [ 'id' => $relatedResource['id']];
        }

        return null;
    }

    /**
     * @param string $string
     * @return bool
     */
    private function hasContentTypeExtension($extension)
    {
        // get the last part
        $contentTypeParameters = $this->getContentTypeParameters();
        if (!isset($contentTypeParameters['ext'])) {
            return false;
        }

        if (in_array($extension, explode(',', $contentTypeParameters['ext']))) {
            return true;
        }

        return false;
    }

    /**
     * Get all parameters that were set in the 'content type' header.
     * http://springbot.github.io/json-api/extensions/
     * @return array
     */
    private function getContentTypeParameters()
    {
        if (!isset($this->contentTypeParameters)) {

            $parameters = [];
            $contentType = Str::substr($this->getContentType(), Str::length($this->contentType) + 1);

            // Replace ; with newline (in order to be able to use ini parsing)
            $contentType = str_replace(';', "\n", $contentType);

            if (empty($contentType)) {
                return $parameters;
            }

            $parameters = parse_ini_string($contentType);
            if ($parameters) {
                $this->contentTypeParameters = $parameters;
            } else {
                $this->contentTypeParameters = [];
            }
        }

        return $this->contentTypeParameters;
    }

    /**
     * @param DescriptionBuilder $builder
     * @param Route $route
     * @param ResourceParameter $parameter
     * @param ResourceDefinition $resourceDefinition
     * @param null $resource
     * @return ParameterCollection
     */
    public function getResourceRouteParameters(
        DescriptionBuilder $builder,
        Route $route,
        ResourceParameter $parameter,
        ResourceDefinition $resourceDefinition,
        $action,
        $resource = null
    ): ParameterCollection
    {
        $out = parent::getResourceRouteParameters($builder, $route, $parameter, $resourceDefinition, $action, $resource);

        $route->consumes($this->contentType . ' ext=bulk');

        return $out;
    }
}

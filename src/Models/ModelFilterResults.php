<?php


namespace CatLab\Charon\Laravel\Models;

use CatLab\Charon\Models\FilterResults;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ModelFilterResults
 * @package CatLab\Charon\Laravel\Models
 */
class ModelFilterResults
{
    /**
     * @var Model[]
     */
    private $models;

    /**
     * @var FilterResults
     */
    private $filterResults;

    public function __construct($models, $filterResults = null)
    {
        $this->setModels($models);
        $this->setFilterResults($filterResults);
    }

    /**
     * @return Model[]
     */
    public function getModels()
    {
        return $this->models;
    }

    /**
     * @param Model[] $models
     * @return ModelFilterResults
     */
    public function setModels($models): ModelFilterResults
    {
        $this->models = $models;
        return $this;
    }

    /**
     * @return FilterResults
     */
    public function getFilterResults()
    {
        return $this->filterResults;
    }

    /**
     * @param FilterResults $filterResults
     * @return ModelFilterResults
     */
    public function setFilterResults($filterResults): ModelFilterResults
    {
        $this->filterResults = $filterResults;
        return $this;
    }
}

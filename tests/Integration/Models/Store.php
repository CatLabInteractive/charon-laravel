<?php

namespace Tests\Integration\Models;

use CatLab\Base\Enum\Operator;
use CatLab\Charon\Enums\FilterOperator;
use CatLab\Charon\Laravel\Database\Model;
use CatLab\Charon\Models\ConditionFilter;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $table = 'stores';
    protected $guarded = [];

    /**
     * An entity filter method that ignores the operator (only meant for "=").
     */
    public static function filterAddress($query, $value, $operator)
    {
        $query->where('address', 'LIKE', $value . '%');
    }

    /**
     * @var ConditionFilter[] filters filterName() received, for the tests
     */
    public static $receivedNameFilters = [];

    /**
     * An entity filter method that handles every operator: it asks for a
     * ConditionFilter by type.
     */
    public static function filterName($query, ConditionFilter $filter, $context, string $entityClass)
    {
        self::$receivedNameFilters[] = $filter;

        match ($filter->getOperator()) {
            Operator::NEQ => $query->where('name', '!=', $filter->getValue()),
            FilterOperator::EMPTY => $query->whereNull('name'),
            FilterOperator::NOT_EMPTY => $query->whereNotNull('name'),
            default => $query->where('name', $filter->getOperator(), $filter->getValue()),
        };
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class, 'store_id');
    }
}

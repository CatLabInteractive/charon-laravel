<?php

namespace Tests\Integration\Models;

use CatLab\Charon\Laravel\Database\Model;
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

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class, 'store_id');
    }
}

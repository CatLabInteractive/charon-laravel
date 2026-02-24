<?php

namespace Tests\Integration\Models;

use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $table = 'stores';
    protected $guarded = [];

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class, 'store_id');
    }
}

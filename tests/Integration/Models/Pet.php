<?php

namespace Tests\Integration\Models;

use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pet extends Model
{
    protected $table = 'pets';
    protected $guarded = [];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'pet_id');
    }
}

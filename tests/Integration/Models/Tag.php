<?php

namespace Tests\Integration\Models;

use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends Model
{
    protected $table = 'tags';
    protected $guarded = [];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'pet_id');
    }

    public function metadata(): HasMany
    {
        return $this->hasMany(TagMetadata::class, 'tag_id');
    }
}

<?php

namespace Tests\Integration\Models;

use CatLab\Charon\Laravel\Database\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagMetadata extends Model
{
    protected $table = 'tag_metadata';
    protected $guarded = [];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }
}

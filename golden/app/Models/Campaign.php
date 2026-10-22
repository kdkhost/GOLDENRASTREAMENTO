<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = ['name', 'source', 'medium', 'content', 'channel', 'ativa'];

    protected $casts = ['ativa' => 'boolean'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function getLabelAttribute(): string
    {
        $parts = array_filter([$this->name, $this->source, $this->medium]);
        return implode(' · ', $parts);
    }
}

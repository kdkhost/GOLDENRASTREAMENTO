<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = ['nome', 'cidade', 'papel', 'texto', 'nota', 'aprovado', 'ordem'];

    protected $casts = ['aprovado' => 'boolean', 'nota' => 'integer', 'ordem' => 'integer'];

    public function scopeAprovados($q)
    {
        return $q->where('aprovado', true)->orderBy('ordem');
    }
}

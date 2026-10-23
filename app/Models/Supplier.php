<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['nome', 'cnpj', 'contato', 'telefone', 'email', 'observacoes', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public function scopeAtivos($q)
    {
        return $q->where('ativo', true)->orderBy('nome');
    }
}

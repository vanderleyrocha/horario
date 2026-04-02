<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExecucaoAlgoritmo extends Model
{
    protected $casts = [
        'parametros_utilizados' => 'array',
    ];

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function alocacoes()
    {
        return $this->hasMany(Alocacao::class);
    }
}

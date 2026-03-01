<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExecucaoAlgoritmo extends Model {

    protected $fillable = [
        'horario_id',
        'fitness_score',
        'geracoes_executadas',
        'tempo_execucao_ms',
        'parametros_utilizados',
        'status',
        'ativa',
    ];

    protected $casts = [
        'parametros_utilizados' => 'array',
    ];

    public function horario() {
        return $this->belongsTo(Horario::class);
    }

    public function alocacoes() {
        return $this->hasMany(Alocacao::class);
    }
}

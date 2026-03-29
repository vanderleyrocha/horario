<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Disciplina extends Model
{
    use HasFactory;

    protected $table = 'disciplinas';

    protected $fillable = [
        'nome',
        'codigo',
        'carga_horaria_semanal',
        'descricao',
        'cor',
        'ativa',
    ];

    protected $casts = [
        'carga_horaria_semanal' => 'integer',
        'ativa' => 'boolean',
    ];

    public function alocacoes(): HasMany
    {
        return $this->hasMany(Alocacao::class);
    }

    public function restricoesTempo(): MorphMany
    {
        return $this->morphMany(RestricaoTempo::class, 'entidade');
    }

    public function aulas(): HasMany
    {
        return $this->hasMany(Aula::class);
    }

    public function scopeAtiva($query)
    {
        return $query->where('ativa', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Turma extends Model
{
    use HasFactory;

    protected $table = 'turmas';

    protected $fillable = [
        'nome',
        'codigo',
        'serie',
        'turno',
        'numero_alunos',
        'ano',
        'ativa',
    ];

    protected $casts = [
        'serie' => 'integer',
        'numero_alunos' => 'integer',
        'ano' => 'integer',
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
        return $this->hasMany(Aula::class)->orderBy('disciplina_id');
    }

    public function scopeAtiva($query)
    {
        return $query->where('ativa', true);
    }

    public function getTurnoLabelAttribute(): string
    {
        return match ($this->turno) {
            'matutino' => 'Matutino (07:00 - 12:00)',
            'vespertino' => 'Vespertino (13:00 - 18:00)',
            'noturno' => 'Noturno (19:00 - 23:00)',
            'integral' => 'Integral (07:00 - 18:00)',
            default => ucfirst((string) $this->turno),
        };
    }

    public function getAulasCountAttribute(): int
    {
        $aulas = $this->aulas()->get();

        return $aulas->sum(function (Aula $aula): int {
            return $aula->aulas_semana * match ($aula->tipo) {
                'simples' => 1,
                'dupla' => 2,
                'tripla' => 3,
                default => 1,
            };
        });
    }
}

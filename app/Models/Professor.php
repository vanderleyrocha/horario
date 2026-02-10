<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Professor extends Model {

    use HasFactory;
    
    protected $table = 'professores';

    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'dias_disponiveis',
        'carga_horaria_maxima',
        'ativo',
    ];

    protected $casts = [
        'dias_disponiveis' => 'array',
        'ativo' => 'boolean',
    ];

    public function alocacoes(): HasMany {
        return $this->hasMany(Alocacao::class);
    }

    public function scopeAtivo($query) {
        return $query->where('ativo', true);
    }

    public function restricoesTempo(): MorphMany {
        return $this->morphMany(RestricaoTempo::class, 'entidade');
    }

    public function aulas(): HasMany {
        return $this->hasMany(Aula::class);
    }
}

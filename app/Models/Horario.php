<?php
// app/Models/Horario.php - Adicionar este método para evitar conflito

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Horario extends Model {

    use HasFactory;

    protected $table = 'horarios';

    protected $fillable = [
        'nome',
        'ano',
        'semestre',
        'status',
        'fitness_score',
        'configuracao', // JSON para parâmetros do algoritmo
        'gerado_em',
        'criado_por',
        'ativado_por',
        'ativado_em',
        'geracoes_executadas',
        'geracoes_sem_melhoria',
        'melhor_fitness',
        'fitness_medio',
        'historico_fitness',
        'conflitos_hard',
        'conflitos_soft',
        'detalhes_conflitos',
        'tempo_processamento_segundos',
    ];

    protected $casts = [
        'gerado_em' => 'datetime',
        'ativado_em' => 'datetime',
        'configuracao' => 'array', // Converte JSON do BD para Array PHP automaticamente
        'historico_fitness' => 'array',
        'detalhes_conflitos' => 'array',
        'fitness_score' => 'float',
        'melhor_fitness' => 'float',
        'fitness_medio' => 'float',
        'geracoes_executadas' => 'integer',
        'geracoes_sem_melhoria' => 'integer',
        'conflitos_hard' => 'integer',
        'conflitos_soft' => 'integer',
        'tempo_processamento_segundos' => 'integer',
    ];


    protected static function booted() {
        static::creating(function ($horario) {
            if (empty($horario->configuracao)) {
                $horario->configuracao = [
                    'geracoes' => 500,
                    'populacao' => 100,
                    'taxa_mutacao' => 0.3,
                    'taxa_crossover' => 0.7
                ];
            }
        });
    }

    // Relacionamentos
    public function alocacoes(): HasMany {
        return $this->hasMany(Alocacao::class);
    }

    // Usar um nome diferente para o relacionamento para evitar conflito
    public function configuracaoHorario(): HasOne {
        return $this->hasOne(ConfiguracaoHorario::class);
    }

    public function aulas(): HasMany {
        return $this->hasMany(Aula::class);
    }

    public function restricoes(): HasMany {
        return $this->hasMany(RestricaoTempo::class);
    }

    public function criadoPor(): BelongsTo {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function ativadoPor(): BelongsTo {
        return $this->belongsTo(User::class, 'ativado_por');
    }

    // Scopes
    public function scopeAtivo($query) {
        return $query->where('status', 'ativo');
    }

    public function scopePorAno($query, $ano) {
        return $query->where('ano', $ano);
    }

    public function scopePorSemestre($query, $semestre) {
        return $query->where('semestre', $semestre);
    }
}

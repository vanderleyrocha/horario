<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleConstraint extends Model
{
    use HasFactory;

    protected $table = 'schedule_constraints';

    protected $fillable = [
        'horario_id',
        'name',
        'description',
        'type',
        'level',
        'weight',
        'is_active',
        'payload_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'horario_id' => 'integer',
        'weight' => 'integer',
        'is_active' => 'boolean',
        'payload_json' => 'array',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }
}

<?php

namespace App\Models\Ai;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auditoría de gobernanza de IA: qué propuso una Skill, con qué modelo/score,
 * y cómo lo resolvió el humano. Fuente de KPIs (tasa de aceptación, correcciones).
 */
class AiDecision extends Model
{
    public const ACCION_SUGERIDA = 'sugerida';
    public const ACCION_AUTO_APLICADA = 'auto_aplicada';
    public const ACCION_CONFIRMADA = 'confirmada';
    public const ACCION_EDITADA = 'editada';
    public const ACCION_DESCARTADA = 'descartada';
    public const ACCION_ERROR = 'error';

    protected $table = 'ai_decision';

    protected $fillable = [
        'skill',
        'accion',
        'driver',
        'model',
        'empresa_id',
        'usuario_id',
        'entidad_tipo',
        'entidad_id',
        'score',
        'latencia_ms',
        'input_hash',
        'payload',
        'resuelto_por',
        'resuelto_at',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'usuario_id' => 'integer',
        'entidad_id' => 'integer',
        'score' => 'float',
        'latencia_ms' => 'integer',
        'payload' => 'array',
        'resuelto_por' => 'integer',
        'resuelto_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function resolutor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'resuelto_por');
    }
}

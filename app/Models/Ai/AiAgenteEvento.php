<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento operativo emitido por un auditor/proceso; lleva plan HITL opcional.
 */
class AiAgenteEvento extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_VISTO = 'visto';

    public const ESTADO_DESCARTADO = 'descartado';

    public const ESTADO_RESUELTO = 'resuelto';

    protected $table = 'ai_agente_evento';

    protected $fillable = [
        'evento',
        'origen',
        'severidad',
        'estado',
        'entidad_tipo',
        'entidad_id',
        'empresa_id',
        'resumen',
        'payload_json',
        'plan_json',
        'ai_decision_id',
        'visto_at',
        'resuelto_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'plan_json' => 'array',
        'visto_at' => 'datetime',
        'resuelto_at' => 'datetime',
    ];

    public function decision(): BelongsTo
    {
        return $this->belongsTo(AiDecision::class, 'ai_decision_id');
    }
}

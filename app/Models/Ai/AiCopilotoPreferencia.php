<?php

namespace App\Models\Ai;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preferencias persistidas del copiloto (FAB / digest).
 */
class AiCopilotoPreferencia extends Model
{
    protected $table = 'ai_copiloto_preferencia';

    protected $fillable = [
        'usuario_id',
        'empresa_id',
        'centrocosto_codigo',
        'deposito_codigo',
        'asistente_rol',
        'auto_brief',
        'notify_brief_campanita',
        'notify_brief_mail',
        'notify_alerta_umbral',
        'umbral_cheques_vencidos',
        'umbral_deuda_ap',
        'umbral_tickets_abiertos',
        'umbral_stock_critico',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'usuario_id' => 'integer',
        'auto_brief' => 'boolean',
        'notify_brief_campanita' => 'boolean',
        'notify_brief_mail' => 'boolean',
        'notify_alerta_umbral' => 'boolean',
        'umbral_cheques_vencidos' => 'float',
        'umbral_deuda_ap' => 'float',
        'umbral_tickets_abiertos' => 'integer',
        'umbral_stock_critico' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

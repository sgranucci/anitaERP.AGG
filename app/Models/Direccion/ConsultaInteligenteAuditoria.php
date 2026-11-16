<?php

namespace App\Models\Direccion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log de consultas CI (sin SoftDeletes; append-only).
 * No usa OwenIt: es la auditoría en sí.
 */
class ConsultaInteligenteAuditoria extends Model
{
    public $timestamps = false;

    protected $table = 'consulta_inteligente_auditoria';

    protected $fillable = [
        'usuario_id',
        'accion',
        'entidad',
        'detalle',
        'meta_json',
        'created_at',
    ];

    protected $casts = [
        'meta_json' => 'array',
        'usuario_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

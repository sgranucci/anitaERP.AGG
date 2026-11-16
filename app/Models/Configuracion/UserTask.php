<?php

namespace App\Models\Configuracion;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTask extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CERRADA = 'cerrada';

    protected $table = 'user_task';

    protected $fillable = [
        'fuente', 'entity_type', 'entity_id', 'usuario_id', 'empresa_id',
        'titulo', 'subtitulo', 'url', 'estado', 'prioridad', 'sla_horas', 'meta',
        'abierta_en', 'vence_en', 'escalado_en', 'escalado_a_usuario_id',
        'reasignado_de_usuario_id', 'cerrada_en',
    ];

    protected $casts = [
        'meta' => 'array',
        'abierta_en' => 'datetime',
        'vence_en' => 'datetime',
        'escalado_en' => 'datetime',
        'cerrada_en' => 'datetime',
        'entity_id' => 'integer',
        'usuario_id' => 'integer',
        'empresa_id' => 'integer',
        'prioridad' => 'integer',
        'sla_horas' => 'integer',
        'escalado_a_usuario_id' => 'integer',
        'reasignado_de_usuario_id' => 'integer',
    ];

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }
}

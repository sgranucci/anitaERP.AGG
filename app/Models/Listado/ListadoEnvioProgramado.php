<?php

namespace App\Models\Listado;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListadoEnvioProgramado extends Model
{
    protected $table = 'listado_envio_programado';

    protected $fillable = [
        'recurso',
        'usuario_id',
        'email',
        'frecuencia',
        'filtros_json',
        'activo',
        'ultimo_envio_at',
    ];

    protected $casts = [
        'filtros_json' => 'array',
        'activo' => 'boolean',
        'ultimo_envio_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

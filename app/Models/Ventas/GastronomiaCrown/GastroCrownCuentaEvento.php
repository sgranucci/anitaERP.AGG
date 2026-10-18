<?php

namespace App\Models\Ventas\GastronomiaCrown;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eventos de negocio de la cuenta. Sin Auditable: es el propio log.
 */
class GastroCrownCuentaEvento extends Model
{
    public $timestamps = false;

    protected $table = 'gastro_crown_cuenta_evento';

    protected $fillable = [
        'gastro_crown_cuenta_id',
        'evento',
        'usuario_id',
        'detalle',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(GastroCrownCuenta::class, 'gastro_crown_cuenta_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

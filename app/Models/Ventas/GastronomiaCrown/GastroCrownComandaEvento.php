<?php

namespace App\Models\Ventas\GastronomiaCrown;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Timeline KDS / impresión. Sin Auditable: es el propio log de eventos.
 */
class GastroCrownComandaEvento extends Model
{
    public $timestamps = false;

    protected $table = 'gastro_crown_comanda_evento';

    protected $fillable = [
        'gastro_crown_comanda_id',
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

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(GastroCrownComanda::class, 'gastro_crown_comanda_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

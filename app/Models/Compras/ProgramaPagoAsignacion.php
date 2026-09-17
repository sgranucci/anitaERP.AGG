<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramaPagoAsignacion extends Model
{
    public const CLAVE_TRANSF = 'transf';

    protected $table = 'programa_pago_asignacion';

    protected $fillable = [
        'programa_pago_linea_id',
        'clave',
        'monto',
    ];

    protected $casts = [
        'monto' => 'float',
    ];

    public function lineas(): BelongsTo
    {
        return $this->belongsTo(ProgramaPagoLinea::class, 'programa_pago_linea_id');
    }
}

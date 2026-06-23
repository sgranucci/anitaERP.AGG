<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UifConciliacionWigosTito extends Model
{
    protected $table = 'uif_conciliacion_wigos_tito';

    protected $fillable = [
        'periodo_id',
        'numero',
        'secuencia',
        'tipo',
        'promocion',
        'monto',
        'estado',
        'terminal',
        'cuenta',
        'fecha_emision',
        'terminal_caja',
        'fecha_pago',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'float',
        'fecha_emision' => 'datetime',
        'fecha_pago' => 'datetime',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(UifConciliacionWigosPeriodo::class, 'periodo_id');
    }
}

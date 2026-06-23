<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UifConciliacionWigosPm extends Model
{
    protected $table = 'uif_conciliacion_wigos_pm';

    protected $fillable = [
        'periodo_id',
        'fecha',
        'proveedor',
        'nombre',
        'id_planta',
        'monto_original',
        'monto_pagado',
        'tipo',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'monto_original' => 'float',
        'monto_pagado' => 'float',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(UifConciliacionWigosPeriodo::class, 'periodo_id');
    }
}

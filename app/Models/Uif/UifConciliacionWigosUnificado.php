<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UifConciliacionWigosUnificado extends Model
{
    protected $table = 'uif_conciliacion_wigos_unificado';

    protected $fillable = [
        'periodo_id',
        'fecha_pago',
        'fecha_emision',
        'monto',
        'terminal',
        'numero',
        'origen',
        'estado_conciliacion',
        'observaciones',
        'tito_id',
        'pm_id',
        'orden',
    ];

    protected $casts = [
        'fecha_pago' => 'datetime',
        'fecha_emision' => 'datetime',
        'monto' => 'float',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(UifConciliacionWigosPeriodo::class, 'periodo_id');
    }

    public function tito(): BelongsTo
    {
        return $this->belongsTo(UifConciliacionWigosTito::class, 'tito_id');
    }

    public function premioMaquina(): BelongsTo
    {
        return $this->belongsTo(UifConciliacionWigosPm::class, 'pm_id');
    }
}

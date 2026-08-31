<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Contrato_Venta_Periodo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'contrato_venta_periodo';

    protected $fillable = [
        'contrato_venta_id',
        'periodo_desde',
        'periodo_hasta',
        'estado',
        'venta_id',
        'venta_emision_id',
    ];

    protected $casts = [
        'periodo_desde' => 'date',
        'periodo_hasta' => 'date',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato_Venta::class, 'contrato_venta_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function ventaEmision(): BelongsTo
    {
        return $this->belongsTo(Venta_Emision::class, 'venta_emision_id');
    }
}

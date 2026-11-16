<?php

declare(strict_types=1);

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Importacion_Gasto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'importacion_gasto';

    protected $fillable = [
        'importacion_id',
        'importacion_gasto_tipo_id',
        'naturaleza',
        'proveedor_id',
        'comprobante_proveedor_id',
        'moneda_id',
        'cotizacion',
        'importe',
        'importe_moneda_local',
        'base_prorrateo',
        'fecha',
        'referencia',
        'observacion',
    ];

    protected $casts = [
        'cotizacion' => 'float',
        'importe' => 'float',
        'importe_moneda_local' => 'float',
        'fecha' => 'date',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(Importacion_Gasto_Tipo::class, 'importacion_gasto_tipo_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function comprobanteProveedor(): BelongsTo
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }
}

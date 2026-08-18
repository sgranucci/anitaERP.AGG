<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaGastronomiaNcOrigen extends Model
{
    protected $table = 'venta_gastronomia_nc_origen';

    protected $fillable = [
        'venta_nc_id',
        'venta_factura_id',
    ];

    public function ventaNc(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_nc_id');
    }

    public function ventaFactura(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_factura_id');
    }
}

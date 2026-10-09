<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Nota de crédito emitida contra una factura de Facturación Local.
 * Una factura puede tener varias. La suma no puede superar el total de la factura.
 */
class FacturacionLocalNotaCredito extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'facturacion_local_nota_credito';

    protected $fillable = [
        'venta_factura_id',
        'venta_nc_id',
    ];

    protected $casts = [
        'venta_factura_id' => 'integer',
        'venta_nc_id' => 'integer',
    ];

    public function factura()
    {
        return $this->belongsTo(Venta::class, 'venta_factura_id');
    }

    public function notaCredito()
    {
        return $this->belongsTo(Venta::class, 'venta_nc_id');
    }
}

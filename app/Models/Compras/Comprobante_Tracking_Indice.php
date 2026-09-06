<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

/**
 * Índice materializado del tracking: PDF, fecha de carga real y estado de pago.
 *
 * Estos tres datos no se pueden calcular con columnas propias del ERP —viven en
 * el puente Anita o no los trajo la importación—, y consultarlos por fila haría
 * inusable la grilla. Se resuelven en el backfill y la grilla sólo lee acá.
 */
class Comprobante_Tracking_Indice extends Model
{
    protected $table = 'comprobante_tracking_indice';

    protected $fillable = [
        'comprobante_proveedor_id',
        'pdf_origen', 'pdf_documento_id', 'pdf_archivo_id', 'pdf_ruta', 'pdf_disponible',
        'fechacarga_efectiva', 'fechacarga_origen',
        'pago_estado', 'pago_origen', 'pago_monto', 'pago_pagado', 'pago_saldo', 'pago_fecha',
        'sincronizado_at',
    ];

    protected $casts = [
        'pdf_disponible' => 'boolean',
        'pdf_documento_id' => 'integer',
        'pdf_archivo_id' => 'integer',
        'fechacarga_efectiva' => 'date',
        'pago_monto' => 'float',
        'pago_pagado' => 'float',
        'pago_saldo' => 'float',
        'pago_fecha' => 'date',
        'sincronizado_at' => 'datetime',
    ];

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }
}

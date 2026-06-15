<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use App\Models\Ventas\Formapago;
use Illuminate\Database\Eloquent\Model;

class Comprobante_Proveedor_Cuota extends Model
{
    protected $table = 'comprobante_proveedor_cuota';

    protected $fillable = [
        'comprobante_proveedor_id', 'numero_cuota', 'fechavencimiento', 'monto',
        'moneda_id', 'cotizacion', 'formapago_id', 'detalle',
        'ordencompra_comprobante_cuota_id', 'total_pagado',
    ];

    protected $casts = [
        'fechavencimiento' => 'date',
    ];

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function formapagos()
    {
        return $this->belongsTo(Formapago::class, 'formapago_id');
    }

    public function ordencompra_comprobante_cuotas()
    {
        return $this->belongsTo(Ordencompra_Comprobante_Cuota::class, 'ordencompra_comprobante_cuota_id');
    }
}

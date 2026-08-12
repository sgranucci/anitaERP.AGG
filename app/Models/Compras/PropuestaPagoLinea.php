<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use Illuminate\Database\Eloquent\Model;

class PropuestaPagoLinea extends Model
{
    protected $table = 'propuesta_pago_linea';

    protected $fillable = [
        'propuesta_pago_id',
        'proveedor_id',
        'proveedor_cuentacorriente_id',
        'comprobante_proveedor_id',
        'ordencompra_id',
        'fechavencimiento',
        'moneda_id',
        'formapago_id',
        'cuentacaja_id',
        'detalle_pago',
        'saldo_deuda',
        'monto_propuesto',
        'incluido',
        'pagoproveedor_id',
        'estado_linea',
    ];

    protected $casts = [
        'fechavencimiento' => 'date',
        'saldo_deuda' => 'float',
        'monto_propuesto' => 'float',
        'incluido' => 'boolean',
    ];

    public function propuesta_pagos()
    {
        return $this->belongsTo(PropuestaPago::class, 'propuesta_pago_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function formapagos()
    {
        return $this->belongsTo(\App\Models\Ventas\Formapago::class, 'formapago_id');
    }

    public function cuentacajas()
    {
        return $this->belongsTo(\App\Models\Caja\Cuentacaja::class, 'cuentacaja_id');
    }

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function pagoproveedores()
    {
        return $this->belongsTo(Pagoproveedor::class, 'pagoproveedor_id');
    }

    public function proveedor_cuentacorrientes()
    {
        return $this->belongsTo(Proveedor_Cuentacorriente::class, 'proveedor_cuentacorriente_id');
    }

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }
}

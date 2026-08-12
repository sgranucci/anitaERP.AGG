<?php

namespace App\Models\Compras;

use App\Models\Caja\Caja;
use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cheque;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Asiento;
use App\Models\Seguridad\Usuario;
use App\Traits\Compras\PagoproveedorEstadoTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pagoproveedor extends Model
{
    use SoftDeletes;
    use PagoproveedorEstadoTrait;

    protected $table = 'pagoproveedor';

    protected $fillable = [
        'empresa_id', 'tipotransaccion_caja_id', 'tipocomprobante', 'letra', 'sucursal',
        'numerotransaccion', 'fecha', 'caja_id', 'proveedor_id', 'detalle', 'estado',
        'monto', 'cotizacion', 'moneda_id', 'modo_cotizacion', 'usuario_id',
        'asiento_id', 'caja_movimiento_id',
        'propuesta_pago_id', 'pagoproveedor_origen_id', 'pagoproveedor_revertido_por_id',
        'interbanking_transferencia_id',
        'interbanking_movimiento_id',
        'bloqueado_banco',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'float',
        'cotizacion' => 'float',
        'bloqueado_banco' => 'boolean',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function cajas()
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function tipotransaccion_cajas()
    {
        return $this->belongsTo(Tipotransaccion_Caja::class, 'tipotransaccion_caja_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function pagoproveedor_comprobantes()
    {
        return $this->hasMany(Pagoproveedor_Comprobante::class, 'pagoproveedor_id');
    }

    public function pagoproveedor_retenciones()
    {
        return $this->hasMany(Pagoproveedor_Retencion::class, 'pagoproveedor_id');
    }

    public function pagoproveedor_estados()
    {
        return $this->hasMany(Pagoproveedor_Estado::class, 'pagoproveedor_id');
    }

    public function pagoproveedor_archivos()
    {
        return $this->hasMany(Pagoproveedor_Archivo::class, 'pagoproveedor_id');
    }

    public function cheques()
    {
        return $this->hasMany(Cheque::class, 'pagoproveedor_id')
            ->with(['monedas', 'bancos', 'cuentacajas']);
    }

    public function caja_movimientos()
    {
        return $this->hasMany(Caja_Movimiento::class, 'pagoproveedor_id')
            ->with('caja_movimiento_cuentacajas.cuentacajas');
    }

    /**
     * Colección 0..n para formasientoexterno (`->first()`).
     */
    public function asientos()
    {
        return $this->hasMany(Asiento::class, 'pagoproveedor_id')
            ->with('asiento_movimientos');
    }

    public function etiquetaComprobante(): string
    {
        return sprintf(
            '%s %s%04d-%s',
            $this->tipocomprobante,
            $this->letra,
            (int) $this->sucursal,
            $this->numerotransaccion
        );
    }

    public function propuesta_pagos()
    {
        return $this->belongsTo(PropuestaPago::class, 'propuesta_pago_id');
    }

    public function interbanking_transferencias()
    {
        return $this->belongsTo(\App\Models\Caja\InterbankingTransferencia::class, 'interbanking_transferencia_id');
    }

    public function interbanking_movimientos()
    {
        return $this->belongsTo(\App\Models\Caja\InterbankingMovimiento::class, 'interbanking_movimiento_id');
    }

    public function pagoproveedor_origen()
    {
        return $this->belongsTo(self::class, 'pagoproveedor_origen_id');
    }

    public function pagoproveedor_revertido_por()
    {
        return $this->belongsTo(self::class, 'pagoproveedor_revertido_por_id');
    }
}

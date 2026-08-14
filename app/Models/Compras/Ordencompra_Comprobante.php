<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\OrdencompraComprobanteEstados;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Ordencompra_Comprobante extends Model
{
    protected $table = 'ordencompra_comprobante';

    protected $fillable = [
        'ordencompra_id', 'tipocomprobante', 'fechavencimiento', 'monto', 'moneda_id', 'cotizacion', 'detalle',
        'cantidadcuota', 'condicionpago_id', 'estado', 'creousuario_id',
    ];

    protected $attributes = [
        'estado' => OrdencompraComprobanteEstados::PENDIENTE,
    ];

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function condicionpagos()
    {
        return $this->belongsTo(Condicionpago::class, 'condicionpago_id');
    }

    public function ordencompra_comprobante_cuotas()
    {
        return $this->hasMany(Ordencompra_Comprobante_Cuota::class, 'ordencompra_comprobante_id')
            ->orderBy('fechavencimiento')
            ->orderBy('id');
    }

    public function comprobante_proveedores()
    {
        return $this->hasMany(Comprobante_Proveedor::class, 'ordencompra_comprobante_id');
    }

    /**
     * Comprobantes a venir aún disponibles para cargar factura.
     */
    public function scopePendientesDeFacturar(Builder $query): Builder
    {
        return $query
            ->where('estado', OrdencompraComprobanteEstados::PENDIENTE)
            ->whereDoesntHave('comprobante_proveedores', function (Builder $q) {
                $q->where('estado', '!=', ComprobanteProveedorEstados::ANULADO);
            });
    }
}

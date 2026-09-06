<?php

namespace App\Models\Configuracion;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Requisicion;
use App\Models\Ordenventa\Ordenventa;
use App\Models\Sala\RequisicionSala;
use App\Models\Seguridad\Usuario;
use App\Traits\Configuracion\Arbolaprobacion_MovimientoTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Arbolaprobacion_Movimiento extends Model implements Auditable
{
    use Arbolaprobacion_MovimientoTrait;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'arbolaprobacion_id', 'fechaenvio', 'enviousuario_id', 'requisicion_id', 'requisicion_sala_id', 'ordencompra_id',
        'solicitudpago_id', 'ordenventa_id', 'pedido_id', 'propuesta_pago_id', 'articulo_id', 'hashaprobacion', 'hashrechazo', 'hashvisualizar', 'nivel',
        'destinatariousuario_id', 'fechaproceso', 'estado', 'observacion', 'circuito_oc',
        'arbolaprobacion_oc_trigger_id', 'circuito_re', 'arbolaprobacion_re_trigger_id',
    ];

    protected $table = 'arbolaprobacion_movimiento';

    public function arbolaprobaciones()
    {
        return $this->belongsTo(Arbolaprobacion::class, 'arbolaprobacion_id');
    }

    public function ordenventas()
    {
        return $this->belongsTo(Ordenventa::class, 'ordenventa_id');
    }

    public function pedidos()
    {
        return $this->belongsTo(\App\Models\Ventas\Pedido::class, 'pedido_id');
    }

    public function requisiciones()
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function requisicion_salas()
    {
        return $this->belongsTo(RequisicionSala::class, 'requisicion_sala_id');
    }

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function propuesta_pagos()
    {
        return $this->belongsTo(\App\Models\Compras\PropuestaPago::class, 'propuesta_pago_id');
    }

    public function articulos()
    {
        return $this->belongsTo(\App\Models\Stock\Articulo::class, 'articulo_id');
    }

    public function enviousuarios()
    {
        return $this->belongsTo(Usuario::class, 'enviousuario_id');
    }

    public function destinatariousuarios()
    {
        return $this->belongsTo(Usuario::class, 'destinatariousuario_id');
    }
}

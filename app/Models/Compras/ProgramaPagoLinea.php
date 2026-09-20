<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramaPagoLinea extends Model
{
    protected $table = 'programa_pago_linea';

    protected $fillable = [
        'programa_pago_id',
        'proveedor_id',
        'saldo_adeudado',
        'observacion',
        'orden',
    ];

    protected $casts = [
        'saldo_adeudado' => 'float',
        'orden' => 'integer',
    ];

    public function programa_pagos(): BelongsTo
    {
        return $this->belongsTo(ProgramaPago::class, 'programa_pago_id');
    }

    public function proveedores(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(ProgramaPagoAsignacion::class, 'programa_pago_linea_id');
    }

    public function chequesAsignados(): HasMany
    {
        return $this->hasMany(ProgramaPagoCheque::class, 'programa_pago_linea_id');
    }
}

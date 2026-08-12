<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class PropuestaPagoEstado extends Model
{
    protected $table = 'propuesta_pago_estado';

    protected $fillable = [
        'propuesta_pago_id',
        'fecha',
        'estado',
        'usuario_id',
        'observacion',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function propuesta_pagos()
    {
        return $this->belongsTo(PropuestaPago::class, 'propuesta_pago_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

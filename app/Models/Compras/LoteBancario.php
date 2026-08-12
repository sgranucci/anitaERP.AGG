<?php

namespace App\Models\Compras;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class LoteBancario extends Model
{
    protected $table = 'lote_bancario';

    protected $fillable = [
        'propuesta_pago_id',
        'empresa_id',
        'cuentacaja_id',
        'estado',
        'cantidad_lineas',
        'monto_total',
        'archivo_nombre',
        'usuario_id',
        'exportado_at',
        'enviado_banco_at',
        'convenio_driver',
    ];

    protected $casts = [
        'monto_total' => 'float',
        'exportado_at' => 'datetime',
        'enviado_banco_at' => 'datetime',
    ];

    public function propuesta_pagos()
    {
        return $this->belongsTo(PropuestaPago::class, 'propuesta_pago_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacajas()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function lineas()
    {
        return $this->hasMany(LoteBancarioLinea::class, 'lote_bancario_id');
    }
}

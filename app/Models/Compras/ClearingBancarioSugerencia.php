<?php

namespace App\Models\Compras;

use App\Models\Caja\InterbankingMovimiento;
use App\Models\Caja\InterbankingTransferencia;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class ClearingBancarioSugerencia extends Model
{
    protected $table = 'clearing_bancario_sugerencia';

    protected $fillable = [
        'empresa_id',
        'propuesta_pago_id',
        'pagoproveedor_id',
        'interbanking_transferencia_id',
        'interbanking_movimiento_id',
        'lado_banco',
        'score',
        'regla',
        'estado',
        'motivo',
        'monto_erp',
        'monto_banco',
        'cbu_erp',
        'cbu_banco',
        'cuit_erp',
        'cuit_banco',
        'fecha_erp',
        'fecha_banco',
        'detalle_json',
        'usuario_id',
        'confirmado_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'monto_erp' => 'float',
        'monto_banco' => 'float',
        'fecha_erp' => 'date',
        'fecha_banco' => 'date',
        'detalle_json' => 'array',
        'confirmado_at' => 'datetime',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function propuesta_pagos()
    {
        return $this->belongsTo(PropuestaPago::class, 'propuesta_pago_id');
    }

    public function pagoproveedores()
    {
        return $this->belongsTo(Pagoproveedor::class, 'pagoproveedor_id');
    }

    public function interbanking_transferencias()
    {
        return $this->belongsTo(InterbankingTransferencia::class, 'interbanking_transferencia_id');
    }

    public function interbanking_movimientos()
    {
        return $this->belongsTo(InterbankingMovimiento::class, 'interbanking_movimiento_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

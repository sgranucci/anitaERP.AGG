<?php

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use Illuminate\Database\Eloquent\Model;

/**
 * Agregado mensual de movimientos contables por cuenta (moneda origen + moneda local).
 * Mantenido por Asiento_MovimientoObserver cuando config contable.saldos_cuenta_mes está activa.
 */
class Cuentacontable_Saldo_Mes extends Model
{
    protected $table = 'cuentacontable_saldo_mes';

    protected $fillable = [
        'empresa_id',
        'cuentacontable_id',
        'centrocosto_id',
        'anio_mes',
        'moneda_id',
        'debe',
        'haber',
        'debe_local',
        'haber_local',
        'monto',
        'monto_local',
    ];

    protected $casts = [
        'anio_mes' => 'integer',
        'debe' => 'decimal:4',
        'haber' => 'decimal:4',
        'debe_local' => 'decimal:4',
        'haber_local' => 'decimal:4',
        'monto' => 'decimal:4',
        'monto_local' => 'decimal:4',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }
}

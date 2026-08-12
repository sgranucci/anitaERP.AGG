<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Seguridad\Usuario;
use App\Traits\Compras\PropuestaPagoEstadoTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropuestaPago extends Model
{
    use SoftDeletes;
    use PropuestaPagoEstadoTrait;

    protected $table = 'propuesta_pago';

    protected $fillable = [
        'empresa_id',
        'fecha',
        'fecha_vencimiento_desde',
        'fecha_vencimiento_hasta',
        'moneda_id',
        'estado',
        'monto_total',
        'detalle',
        'usuario_id',
        'caja_id',
        'cuentacaja_id',
        'chequera_id',
        'monto_autorizado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_vencimiento_desde' => 'date',
        'fecha_vencimiento_hasta' => 'date',
        'monto_total' => 'float',
        'monto_autorizado' => 'float',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cajas()
    {
        return $this->belongsTo(\App\Models\Caja\Caja::class, 'caja_id');
    }

    public function cuentacajas()
    {
        return $this->belongsTo(\App\Models\Caja\Cuentacaja::class, 'cuentacaja_id');
    }

    public function chequeras()
    {
        return $this->belongsTo(\App\Models\Caja\Chequera::class, 'chequera_id');
    }

    public function lineas()
    {
        return $this->hasMany(PropuestaPagoLinea::class, 'propuesta_pago_id');
    }

    public function estados()
    {
        return $this->hasMany(PropuestaPagoEstado::class, 'propuesta_pago_id');
    }

    public function pagoproveedores()
    {
        return $this->hasMany(Pagoproveedor::class, 'propuesta_pago_id');
    }
}

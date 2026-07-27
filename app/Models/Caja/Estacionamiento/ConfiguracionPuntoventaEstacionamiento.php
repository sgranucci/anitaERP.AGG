<?php

namespace App\Models\Caja\Estacionamiento;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Salida;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConfiguracionPuntoventaEstacionamiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'configuracion_puntoventa_estacionamiento';

    protected $fillable = [
        'identificador_pc',
        'descripcion',
        'empresa_id',
        'caja_id',
        'puntoventa_cae_id',
        'puntoventa_caea_id',
        'salida_factura_id',
        'tipotransaccion_id',
        'tipotransaccion_nota_credito_id',
        'tipotransaccion_caja_id',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function caja()
    {
        return $this->belongsTo(\App\Models\Caja\Caja::class, 'caja_id');
    }

    public function puntoventaCae()
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_cae_id');
    }

    public function puntoventaCaea()
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_caea_id');
    }

    public function salidaFactura()
    {
        return $this->belongsTo(Salida::class, 'salida_factura_id');
    }

    public function tipotransaccion()
    {
        return $this->belongsTo(Tipotransaccion::class, 'tipotransaccion_id');
    }

    public function tipotransaccionNotaCredito()
    {
        return $this->belongsTo(Tipotransaccion::class, 'tipotransaccion_nota_credito_id');
    }

    public function tipotransaccionCaja()
    {
        return $this->belongsTo(Tipotransaccion_Caja::class, 'tipotransaccion_caja_id');
    }
}

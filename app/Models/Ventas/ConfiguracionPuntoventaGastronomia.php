<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Salida;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Ventas\UbicacionGastronomia;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConfiguracionPuntoventaGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'configuracion_puntoventa_gastronomia';

    protected $fillable = [
        'identificador_pc',
        'descripcion',
        'empresa_id',
        'puntoventa_cae_id',
        'puntoventa_caea_id',
        'ubicacion_id',
        'salida_comanda_id',
        'salida_factura_id',
        'listaprecio_id',
        'deposito_venta_id',
        'deposito_insumos_id',
        'tipotransaccion_id',
        'tipotransaccion_caja_id',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function puntoventaCae()
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_cae_id');
    }

    public function puntoventaCaea()
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_caea_id');
    }

    public function ubicacion()
    {
        return $this->belongsTo(UbicacionGastronomia::class, 'ubicacion_id');
    }

    public function salidaComanda()
    {
        return $this->belongsTo(Salida::class, 'salida_comanda_id');
    }

    public function salidaFactura()
    {
        return $this->belongsTo(Salida::class, 'salida_factura_id');
    }

    public function listaprecio()
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_id');
    }

    public function depositoVenta()
    {
        return $this->belongsTo(Depmae::class, 'deposito_venta_id');
    }

    public function depositoInsumos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_insumos_id');
    }

    public function tipotransaccion()
    {
        return $this->belongsTo(Tipotransaccion::class, 'tipotransaccion_id');
    }

    public function tipotransaccionCaja()
    {
        return $this->belongsTo(Tipotransaccion_Caja::class, 'tipotransaccion_caja_id');
    }
}

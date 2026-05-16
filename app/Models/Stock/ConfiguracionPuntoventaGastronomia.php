<?php

namespace App\Models\Stock;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Salida;
use App\Models\Ventas\Puntoventa;
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
}

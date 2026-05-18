<?php

namespace App\Models\Ventas;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;

class VentaGastronomiaEmision extends Model
{
    protected $table = 'venta_gastronomia_emision';

    protected $primaryKey = 'venta_id';

    public $incrementing = false;

    protected $fillable = [
        'venta_id', 'cuenta_gastronomia_id', 'identificador_pc', 'configuracion_puntoventa_gastronomia_id',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cuenta()
    {
        return $this->belongsTo(CuentaGastronomia::class, 'cuenta_gastronomia_id');
    }

    public function configuracionPuntoventa()
    {
        return $this->belongsTo(ConfiguracionPuntoventaGastronomia::class, 'configuracion_puntoventa_gastronomia_id');
    }
}

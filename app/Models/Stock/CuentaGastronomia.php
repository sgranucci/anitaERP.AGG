<?php

namespace App\Models\Stock;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CuentaGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPO_MESA = 'mesa';

    public const TIPO_CUENTA = 'cuenta';

    public const ESTADO_ABIERTA = 'abierta';

    public const ESTADO_CERRADA = 'cerrada';

    public const ESTADO_FACTURADA = 'facturada';

    protected $table = 'cuenta_gastronomia';

    protected $fillable = [
        'tipo', 'empresa_id', 'mesa_gastronomia_id', 'mozo_gastronomia_id', 'cubiertos',
        'estado', 'identificador_pc', 'cliente_id', 'descuento_gastronomia_id',
        'configuracion_puntoventa_gastronomia_id', 'venta_id',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function mesa()
    {
        return $this->belongsTo(MesaGastronomia::class, 'mesa_gastronomia_id');
    }

    public function mozo()
    {
        return $this->belongsTo(MozoGastronomia::class, 'mozo_gastronomia_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function descuentoGastronomia()
    {
        return $this->belongsTo(DescuentoGastronomia::class, 'descuento_gastronomia_id');
    }

    public function configuracionPuntoventa()
    {
        return $this->belongsTo(ConfiguracionPuntoventaGastronomia::class, 'configuracion_puntoventa_gastronomia_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function lineas()
    {
        return $this->hasMany(CuentaGastronomiaLinea::class, 'cuenta_gastronomia_id')->orderBy('numero_linea');
    }
}

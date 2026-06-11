<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;

class CanjeMarketingEntregaGastronomia extends Model
{
    protected $table = 'canje_marketing_entrega_gastronomia';

    protected $fillable = [
        'venta_id',
        'cuenta_gastronomia_id',
        'cliente_vip_gastronomia_id',
        'mozo_gastronomia_id',
        'empresa_id',
        'descuento_gastronomia_id',
        'identificador_pc',
        'nrodocumento_vip',
        'apellido_vip',
        'nombre_vip',
        'fechacanje',
    ];

    protected $casts = [
        'fechacanje' => 'date',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cuenta()
    {
        return $this->belongsTo(CuentaGastronomia::class, 'cuenta_gastronomia_id');
    }

    public function clienteVip()
    {
        return $this->belongsTo(ClienteVipGastronomia::class, 'cliente_vip_gastronomia_id');
    }

    public function mozo()
    {
        return $this->belongsTo(MozoGastronomia::class, 'mozo_gastronomia_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function descuentoGastronomia()
    {
        return $this->belongsTo(DescuentoGastronomia::class, 'descuento_gastronomia_id');
    }
}

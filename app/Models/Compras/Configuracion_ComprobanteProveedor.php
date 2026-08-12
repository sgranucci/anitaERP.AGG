<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;

class Configuracion_ComprobanteProveedor extends Model
{
    protected $table = 'configuracion_comprobante_proveedor';

    protected $fillable = [
        'empresa_id',
        'activo',
        'exige_flujo_oc_com_fac',
        'controla_sku_vs_com',
        'controla_precio_unitario',
        'tolerancia_precio_pct',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'exige_flujo_oc_com_fac' => 'boolean',
        'controla_sku_vs_com' => 'boolean',
        'controla_precio_unitario' => 'boolean',
        'tolerancia_precio_pct' => 'float',
    ];

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function tolerancias()
    {
        return $this->hasMany(Configuracion_ComprobanteProveedorTolerancia::class, 'empresa_id', 'empresa_id');
    }
}

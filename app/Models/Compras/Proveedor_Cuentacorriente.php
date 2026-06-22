<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor_Cuentacorriente extends Model
{
    use SoftDeletes;

    protected $table = 'proveedor_cuentacorriente';

    protected $fillable = [
        'fecha', 'fechavencimiento', 'proveedor_id', 'total', 'moneda_id', 'cotizacion',
        'empresa_id', 'comprobante_proveedor_id', 'comprobante_proveedor_cuota_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fechavencimiento' => 'date',
        'deleted_at' => 'datetime',
    ];

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function comprobante_proveedor_cuotas()
    {
        return $this->belongsTo(Comprobante_Proveedor_Cuota::class, 'comprobante_proveedor_cuota_id');
    }

    public function proveedor_cuentacorriente_aplicaciones()
    {
        return $this->hasMany(Proveedor_Cuentacorriente_Aplicacion::class, 'proveedor_cuentacorriente_id');
    }
}

<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use Illuminate\Database\Eloquent\Model;

class Proveedor_Cuentacorriente_Aplicacion extends Model
{
    protected $table = 'proveedor_cuentacorriente_aplicacion';

    protected $fillable = [
        'fecha', 'proveedor_cuentacorriente_id', 'total', 'moneda_id', 'cotizacion',
        'comprobante_proveedor_aplicado_id', 'comprobanteaplicado', 'empresa_id',
        'proveedor_cuentacorriente_aplicado_id', 'pagoproveedor_id',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function proveedor_cuentacorrientes()
    {
        return $this->belongsTo(Proveedor_Cuentacorriente::class, 'proveedor_cuentacorriente_id');
    }

    public function proveedor_cuentacorriente_aplicados()
    {
        return $this->belongsTo(Proveedor_Cuentacorriente::class, 'proveedor_cuentacorriente_aplicado_id');
    }

    public function comprobante_proveedor_aplicados()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_aplicado_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}

<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use Illuminate\Database\Eloquent\Model;

class Pagoproveedor_Comprobante extends Model
{

    protected $table = 'pagoproveedor_comprobante';

    protected $fillable = [
        'pagoproveedor_id', 'proveedor_cuentacorriente_id', 'montoaplicado',
        'cotizacion', 'moneda_id', 'cotizacion_aplicada', 'diferencia_cambio',
        'proveedor_cuentacorriente_dc_id',
    ];

    public function pagoproveedores()
    {
        return $this->belongsTo(Pagoproveedor::class, 'pagoproveedor_id');
    }

    public function proveedor_cuentacorrientes()
    {
        return $this->belongsTo(Proveedor_Cuentacorriente::class, 'proveedor_cuentacorriente_id');
    }

    public function proveedor_cuentacorriente_dc()
    {
        return $this->belongsTo(Proveedor_Cuentacorriente::class, 'proveedor_cuentacorriente_dc_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }
}

<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use App\Models\Ventas\Formapago;
use Illuminate\Database\Eloquent\Model;

class Ordencompra_Comprobante_Cuota extends Model
{
    protected $table = 'ordencompra_comprobante_cuota';

    protected $fillable = [
        'ordencompra_comprobante_id', 'fechavencimiento', 'monto', 'moneda_id', 'cotizacion', 'formapago_id', 'detalle', 'creousuario_id',
    ];

    protected $casts = [
        'fechavencimiento' => 'date',
    ];

    public function ordencompra_comprobantes()
    {
        return $this->belongsTo(Ordencompra_Comprobante::class, 'ordencompra_comprobante_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function formapagos()
    {
        return $this->belongsTo(Formapago::class, 'formapago_id');
    }
}

<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use Illuminate\Database\Eloquent\Model;

class Ordencompra_Comprobante extends Model
{
    protected $table = 'ordencompra_comprobante';

    protected $fillable = [
        'ordencompra_id', 'tipocomprobante', 'fechavencimiento', 'monto', 'moneda_id', 'cotizacion', 'detalle',
        'cantidadcuota', 'condicionpago_id', 'creousuario_id',
    ];

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function condicionpagos()
    {
        return $this->belongsTo(Condicionpago::class, 'condicionpago_id');
    }

    public function ordencompra_comprobante_cuotas()
    {
        return $this->hasMany(Ordencompra_Comprobante_Cuota::class, 'ordencompra_comprobante_id');
    }
}

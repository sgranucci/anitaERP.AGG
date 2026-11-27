<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Moneda;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class AplicacionCcClienteComprobanteLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'aplicacion_cc_cliente_comprobante_linea';

    protected $fillable = [
        'comprobante_id', 'aplicacion_deuda_id', 'aplicacion_credito_id', 'deuda_cc_id', 'credito_cc_id',
        'deuda_etiqueta', 'credito_etiqueta', 'monto_deuda', 'moneda_deuda_id', 'cotizacion_deuda',
        'monto_credito', 'moneda_credito_id', 'cotizacion_credito', 'cotizacion_liquidacion',
        'valor_local_deuda', 'diferencia_cambio', 'asiento_id',
    ];

    public function comprobantes()
    {
        return $this->belongsTo(AplicacionCcClienteComprobante::class, 'comprobante_id');
    }

    public function moneda_deudas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_deuda_id');
    }

    public function moneda_creditos()
    {
        return $this->belongsTo(Moneda::class, 'moneda_credito_id');
    }
}

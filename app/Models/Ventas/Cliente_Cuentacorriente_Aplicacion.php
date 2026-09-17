<?php

namespace App\Models\Ventas;

use App\Models\Caja\Cobranza;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cliente_Cuentacorriente_Aplicacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cliente_cuentacorriente_aplicacion';

    protected $fillable = [
        'fecha',
        'cliente_cuentacorriente_id',
        'total',
        'moneda_id',
        'cotizacion',
        'cotizacion_liquidacion',
        'diferencia_cambio',
        'asiento_id',
        'ventaaplicado_id',
        'cobranza_id',
        'comprobanteaplicado',
        'empresa_id',
        'cliente_cuentacorriente_aplicado_id',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function cliente_cuentacorrientes()
    {
        return $this->belongsTo(Cliente_Cuentacorriente::class, 'cliente_cuentacorriente_id', 'id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cobranzas()
    {
        return $this->belongsTo(Cobranza::class, 'cobranza_id');
    }

    /**
     * Comprobante de venta aplicado (ventaaplicado_id).
     * Alias histórico usado por el repository (`with('ventas')`).
     */
    public function ventas()
    {
        return $this->belongsTo(Venta::class, 'ventaaplicado_id');
    }

    public function ventasaplicados()
    {
        return $this->belongsTo(Venta::class, 'ventaaplicado_id');
    }

    public function cliente_cuentacorriente_aplicados()
    {
        return $this->belongsTo(Cliente_Cuentacorriente::class, 'cliente_cuentacorriente_aplicado_id', 'id');
    }
}

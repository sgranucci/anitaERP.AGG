<?php

namespace App\Models\Caja;

use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cobranza_Descuento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPO_PORCENTAJE = 'porcentaje';

    public const TIPO_IMPORTE = 'importe';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_EMITIDA = 'emitida';

    public const ESTADO_ERROR = 'error';

    protected $table = 'cobranza_descuento';

    protected $fillable = [
        'cobranza_id',
        'venta_origen_id',
        'cliente_cuentacorriente_origen_id',
        'venta_nc_id',
        'cliente_cuentacorriente_nc_id',
        'tipo',
        'valor',
        'importe_calculado',
        'leyenda',
        'estado',
    ];

    protected $casts = [
        'valor' => 'float',
        'importe_calculado' => 'float',
    ];

    public function cobranzas()
    {
        return $this->belongsTo(Cobranza::class, 'cobranza_id');
    }

    public function ventaOrigen()
    {
        return $this->belongsTo(Venta::class, 'venta_origen_id');
    }

    public function ventaNc()
    {
        return $this->belongsTo(Venta::class, 'venta_nc_id');
    }

    public function clienteCuentacorrienteOrigen()
    {
        return $this->belongsTo(Cliente_Cuentacorriente::class, 'cliente_cuentacorriente_origen_id');
    }

    public function clienteCuentacorrienteNc()
    {
        return $this->belongsTo(Cliente_Cuentacorriente::class, 'cliente_cuentacorriente_nc_id');
    }
}

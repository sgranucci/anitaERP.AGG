<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class FacturacionLocalEmision extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'facturacion_local_emision';

    protected $fillable = [
        'local_venta_id',
        'turno_operativo_local_id',
        'venta_id',
        'venta_nc_id',
        'vale_cliente_local_id',
        'es_ticket_regalo',
        'payload_resumen_json',
    ];

    protected $casts = [
        'es_ticket_regalo' => 'boolean',
        'payload_resumen_json' => 'array',
    ];

    public function localVenta()
    {
        return $this->belongsTo(LocalVenta::class, 'local_venta_id');
    }

    public function turno()
    {
        return $this->belongsTo(TurnoOperativoLocal::class, 'turno_operativo_local_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function ventaNc()
    {
        return $this->belongsTo(Venta::class, 'venta_nc_id');
    }

    public function vale()
    {
        return $this->belongsTo(ValeClienteLocal::class, 'vale_cliente_local_id');
    }
}

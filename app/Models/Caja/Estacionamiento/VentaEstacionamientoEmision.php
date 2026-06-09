<?php

namespace App\Models\Caja\Estacionamiento;

use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;

class VentaEstacionamientoEmision extends Model
{
    protected $table = 'venta_estacionamiento_emision';

    protected $primaryKey = 'venta_id';

    public $incrementing = false;

    protected $fillable = [
        'venta_id',
        'ticket_estacionamiento_id',
        'identificador_pc',
        'configuracion_puntoventa_estacionamiento_id',
        'jornada_estacionamiento_id',
        'turno_operativo_estacionamiento_id',
        'venta_factura_origen_id',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function ticket()
    {
        return $this->belongsTo(TicketEstacionamiento::class, 'ticket_estacionamiento_id');
    }

    public function configuracionPuntoventa()
    {
        return $this->belongsTo(ConfiguracionPuntoventaEstacionamiento::class, 'configuracion_puntoventa_estacionamiento_id');
    }

    public function jornada()
    {
        return $this->belongsTo(JornadaEstacionamiento::class, 'jornada_estacionamiento_id');
    }

    public function turnoOperativo()
    {
        return $this->belongsTo(TurnoOperativoEstacionamiento::class, 'turno_operativo_estacionamiento_id');
    }

    public function ventaFacturaOrigen()
    {
        return $this->belongsTo(Venta::class, 'venta_factura_origen_id');
    }

    public function notaCreditoEmision()
    {
        return $this->hasOne(self::class, 'venta_factura_origen_id', 'venta_id');
    }
}

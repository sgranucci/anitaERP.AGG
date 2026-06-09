<?php

namespace App\Models\Caja\Estacionamiento;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TicketEstacionamiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_INGRESO = 'ingreso';

    public const ESTADO_FACTURADO = 'facturado';

    public const ESTADO_ANULADO = 'anulado';

    protected $table = 'ticket_estacionamiento';

    protected $fillable = [
        'empresa_id',
        'jornada_estacionamiento_id',
        'turno_operativo_estacionamiento_id',
        'configuracion_puntoventa_estacionamiento_id',
        'identificador_pc',
        'numero_ticket',
        'patente',
        'categoria_automovil_estacionamiento_id',
        'item_estacionamiento_id',
        'estado',
        'ingreso_en',
        'salida_en',
        'facturado_en',
        'venta_id',
        'monto_estimado',
        'observacion',
    ];

    protected $casts = [
        'ingreso_en' => 'datetime',
        'salida_en' => 'datetime',
        'facturado_en' => 'datetime',
        'monto_estimado' => 'float',
        'numero_ticket' => 'integer',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function jornada()
    {
        return $this->belongsTo(JornadaEstacionamiento::class, 'jornada_estacionamiento_id');
    }

    public function turnoOperativo()
    {
        return $this->belongsTo(TurnoOperativoEstacionamiento::class, 'turno_operativo_estacionamiento_id');
    }

    public function configuracionPuntoventa()
    {
        return $this->belongsTo(ConfiguracionPuntoventaEstacionamiento::class, 'configuracion_puntoventa_estacionamiento_id');
    }

    public function categoriaAutomovil()
    {
        return $this->belongsTo(CategoriaAutomovil::class, 'categoria_automovil_estacionamiento_id');
    }

    public function item()
    {
        return $this->belongsTo(ItemEstacionamiento::class, 'item_estacionamiento_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }
}

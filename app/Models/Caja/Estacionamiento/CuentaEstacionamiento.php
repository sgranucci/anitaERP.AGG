<?php

namespace App\Models\Caja\Estacionamiento;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CuentaEstacionamiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_ABIERTA = 'abierta';

    public const ESTADO_CERRADA = 'cerrada';

    public const ESTADO_FACTURADA = 'facturada';

    protected $table = 'cuenta_estacionamiento';

    protected $fillable = [
        'empresa_id',
        'identificador_pc',
        'estado',
        'categoria_automovil_estacionamiento_id',
        'patente',
        'cliente_id',
        'descuento_estacionamiento_id',
        'cliente_interno_descuento_id',
        'factura_receptor_nombre',
        'factura_receptor_documento',
        'factura_receptor_domicilio',
        'factura_receptor_tipodocumento_id',
        'configuracion_puntoventa_estacionamiento_id',
        'jornada_estacionamiento_id',
        'turno_operativo_estacionamiento_id',
        'venta_id',
        'ticket_estacionamiento_id',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function categoriaAutomovil()
    {
        return $this->belongsTo(CategoriaAutomovil::class, 'categoria_automovil_estacionamiento_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function descuentoEstacionamiento()
    {
        return $this->belongsTo(DescuentoEstacionamiento::class, 'descuento_estacionamiento_id');
    }

    public function clienteInternoDescuento()
    {
        return $this->belongsTo(Cliente::class, 'cliente_interno_descuento_id');
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

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function ticket()
    {
        return $this->belongsTo(TicketEstacionamiento::class, 'ticket_estacionamiento_id');
    }

    public function lineas()
    {
        return $this->hasMany(CuentaEstacionamientoLinea::class, 'cuenta_estacionamiento_id')->orderBy('numero_linea');
    }
}

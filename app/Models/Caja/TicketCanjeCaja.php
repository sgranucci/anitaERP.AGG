<?php

namespace App\Models\Caja;

use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TicketCanjeCaja extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_PENDIENTE = 'P';

    public const ESTADO_CANJEADO = 'C';

    /** Cliente VIP: monto ticket 0, no canjeable en POS. */
    public const ESTADO_VIP = 'V';

    /**
     * @return array<string, string>
     */
    public static function etiquetasEstado(): array
    {
        return [
            self::ESTADO_PENDIENTE => 'Pendiente',
            self::ESTADO_CANJEADO => 'Canjeado',
            self::ESTADO_VIP => 'VIP',
        ];
    }

    public function etiquetaEstado(): string
    {
        return self::etiquetasEstado()[$this->estado] ?? (string) $this->estado;
    }

    protected $table = 'ticket_canje_caja';

    protected $fillable = [
        'empresa_id',
        'movimiento_id',
        'numero_ticket',
        'fecha',
        'nro_documento',
        'nombre_cliente',
        'es_vip',
        'cliente_vip_caja_id',
        'monto_venta',
        'monto_ticket',
        'estado',
        'venta_id',
        'fecha_canje',
        'usuario_id',
        'cajero_id',
        'turno_operativo_estacionamiento_id',
        'identificador_pc',
        'numerocupon',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_canje' => 'date',
        'es_vip' => 'boolean',
        'monto_venta' => 'float',
        'monto_ticket' => 'float',
        'movimiento_id' => 'integer',
        'numero_ticket' => 'integer',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function clienteVip()
    {
        return $this->belongsTo(ClienteVipCaja::class, 'cliente_vip_caja_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cajero()
    {
        return $this->belongsTo(Usuario::class, 'cajero_id');
    }

    public function turnoOperativo()
    {
        return $this->belongsTo(TurnoOperativoEstacionamiento::class, 'turno_operativo_estacionamiento_id');
    }

    public function etiquetaVale(): string
    {
        return sprintf('%d-%d', (int) $this->movimiento_id, (int) $this->numero_ticket);
    }

    public function codigoBarras(): string
    {
        return sprintf('%06d%06d', (int) $this->movimiento_id, (int) $this->numero_ticket);
    }
}

<?php

namespace App\Models\Stock;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

/**
 * Cabecera de un préstamo de materiales entre depósitos.
 *
 * Estados (constantes ESTADO_*) describen el ciclo de vida; los cambios
 * pasan siempre por PrestamoService para que se ejecuten los
 * movimientos de stock y notificaciones por correo asociados.
 */
class Prestamo extends Model
{

    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_PENDIENTE_APROBACION = 'PENDIENTE_APROBACION';

    public const ESTADO_APROBADO = 'APROBADO';

    public const ESTADO_RECHAZADO = 'RECHAZADO';

    public const ESTADO_DEVUELTO = 'DEVUELTO';

    public const ESTADO_DEVUELTO_PARCIAL = 'DEVUELTO_PARCIAL';

    public const ESTADO_CANCELADO = 'CANCELADO';

    protected $table = 'prestamo';

    protected $fillable = [
        'codigo',
        'fecha_prestamo',
        'fecha_devolucion_prometida',
        'fecha_aprobacion',
        'fecha_devolucion_real',
        'deposito_origen_id',
        'deposito_destino_id',
        'solicitante_id',
        'aprobador_id',
        'estado',
        'observaciones',
        'motivo_rechazo',
        'movimientostock_salida_id',
        'movimientostock_ingreso_id',
        'ultimo_recordatorio_enviado_el',
    ];

    protected $casts = [
        'fecha_prestamo' => 'date',
        'fecha_devolucion_prometida' => 'date',
        'fecha_aprobacion' => 'date',
        'fecha_devolucion_real' => 'date',
        'ultimo_recordatorio_enviado_el' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(Prestamo_Item::class, 'prestamo_id');
    }

    public function estados()
    {
        return $this->hasMany(Prestamo_Estado::class, 'prestamo_id');
    }

    public function tokens()
    {
        return $this->hasMany(Prestamo_Token::class, 'prestamo_id');
    }

    public function depositoOrigen()
    {
        return $this->belongsTo(Depmae::class, 'deposito_origen_id');
    }

    public function depositoDestino()
    {
        return $this->belongsTo(Depmae::class, 'deposito_destino_id');
    }

    public function solicitante()
    {
        return $this->belongsTo(Usuario::class, 'solicitante_id');
    }

    public function aprobador()
    {
        return $this->belongsTo(Usuario::class, 'aprobador_id');
    }

    public function movimientoSalida()
    {
        return $this->belongsTo(MovimientoStock::class, 'movimientostock_salida_id');
    }

    public function movimientoIngreso()
    {
        return $this->belongsTo(MovimientoStock::class, 'movimientostock_ingreso_id');
    }

    public function estaAbierto(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_BORRADOR,
            self::ESTADO_PENDIENTE_APROBACION,
            self::ESTADO_APROBADO,
            self::ESTADO_DEVUELTO_PARCIAL,
        ], true);
    }

    public function estaPendienteDevolucion(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_APROBADO,
            self::ESTADO_DEVUELTO_PARCIAL,
        ], true);
    }

    public function estaVencido(): bool
    {
        return $this->estaPendienteDevolucion()
            && $this->fecha_devolucion_prometida
            && $this->fecha_devolucion_prometida->lt(now()->startOfDay());
    }
}

<?php

namespace App\Models\Stock;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

/**
 * Cabecera de salida de bienes (evolución de préstamos).
 * Tabla física: prestamo.
 */
class Prestamo extends Model
{
    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_PENDIENTE_APROBACION = 'PENDIENTE_APROBACION';

    public const ESTADO_ENVIADO = 'ENVIADO';

    public const ESTADO_APROBADO = 'APROBADO';

    public const ESTADO_RECHAZADO = 'RECHAZADO';

    public const ESTADO_DEVUELTO = 'DEVUELTO';

    public const ESTADO_DEVUELTO_PARCIAL = 'DEVUELTO_PARCIAL';

    public const ESTADO_CERRADO = 'CERRADO';

    public const ESTADO_CANCELADO = 'CANCELADO';

    public const TIPO_PRESTAMO = 'PRESTAMO';

    public const TIPO_REPARACION = 'REPARACION';

    public const TIPO_ENTREGA = 'ENTREGA';

    public const DEST_DEPOSITO = 'DEPOSITO';

    public const DEST_USUARIO = 'USUARIO';

    public const DEST_EXTERNO = 'EXTERNO';

    public const PRIORIDAD_BAJA = 'BAJA';

    public const PRIORIDAD_NORMAL = 'NORMAL';

    public const PRIORIDAD_ALTA = 'ALTA';

    public const CONDICION_BUENO = 'BUENO';

    public const CONDICION_REGULAR = 'REGULAR';

    public const CONDICION_DANADO = 'DANADO';

    protected $table = 'prestamo';

    protected $fillable = [
        'codigo',
        'tipo',
        'destinatario_tipo',
        'fecha_prestamo',
        'fecha_devolucion_prometida',
        'fecha_aprobacion',
        'fecha_devolucion_real',
        'deposito_origen_id',
        'deposito_destino_id',
        'destinatario_usuario_id',
        'externo_nombre',
        'externo_documento',
        'externo_telefono',
        'externo_email',
        'externo_empresa',
        'espera_devolucion',
        'prioridad',
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
        'espera_devolucion' => 'boolean',
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

    public function destinatarioUsuario()
    {
        return $this->belongsTo(Usuario::class, 'destinatario_usuario_id');
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

    public function esDestinoDeposito(): bool
    {
        return ($this->destinatario_tipo ?? self::DEST_DEPOSITO) === self::DEST_DEPOSITO;
    }

    public function esDestinoUsuario(): bool
    {
        return ($this->destinatario_tipo ?? '') === self::DEST_USUARIO;
    }

    public function esDestinoExterno(): bool
    {
        return ($this->destinatario_tipo ?? '') === self::DEST_EXTERNO;
    }

    public function etiquetaDestinatario(): string
    {
        return match ($this->destinatario_tipo ?? self::DEST_DEPOSITO) {
            self::DEST_USUARIO => optional($this->destinatarioUsuario)->nombre ?? 'Usuario',
            self::DEST_EXTERNO => trim(($this->externo_nombre ?? '').($this->externo_empresa ? ' ('.$this->externo_empresa.')' : '')) ?: 'Externo',
            default => optional($this->depositoDestino)->nombre ?? '—',
        };
    }

    public function etiquetaTipo(): string
    {
        return match ($this->tipo ?? self::TIPO_PRESTAMO) {
            self::TIPO_REPARACION => 'Reparación',
            self::TIPO_ENTREGA => 'Entrega',
            default => 'Préstamo',
        };
    }

    public function estaAbierto(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_BORRADOR,
            self::ESTADO_PENDIENTE_APROBACION,
            self::ESTADO_ENVIADO,
            self::ESTADO_APROBADO,
            self::ESTADO_DEVUELTO_PARCIAL,
        ], true);
    }

    public function estaPendienteDevolucion(): bool
    {
        if (! ($this->espera_devolucion ?? true)) {
            return false;
        }

        return in_array($this->estado, [
            self::ESTADO_APROBADO,
            self::ESTADO_ENVIADO,
            self::ESTADO_DEVUELTO_PARCIAL,
        ], true);
    }

    public function puedeCerrarSinDevolucion(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_APROBADO,
            self::ESTADO_ENVIADO,
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

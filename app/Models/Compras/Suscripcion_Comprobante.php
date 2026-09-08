<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Factura del portal pedida al dueño cuando un cargo de tarjeta se asocia a la suscripción.
 */
class Suscripcion_Comprobante extends Model
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_CARGADO = 'CARGADO';

    public const ESTADO_NO_APLICA = 'NO_APLICA';

    protected $table = 'suscripcion_comprobante';

    protected $fillable = [
        'ordencompra_id',
        'suscripcion_cargo_id',
        'periodo',
        'estado',
        'archivo_nombre',
        'subido_usuario_id',
        'subido_at',
        'observacion',
        'aviso_dueno_at',
        'aviso_escala_at',
    ];

    protected $casts = [
        'subido_at' => 'datetime',
        'aviso_dueno_at' => 'datetime',
        'aviso_escala_at' => 'datetime',
    ];

    public function ordencompras(): BelongsTo
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function suscripcion_cargos(): BelongsTo
    {
        return $this->belongsTo(Suscripcion_Cargo::class, 'suscripcion_cargo_id');
    }

    public function subido_usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'subido_usuario_id');
    }

    public function pendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function tieneArchivo(): bool
    {
        return trim((string) ($this->archivo_nombre ?? '')) !== '';
    }

    public function rutaArchivo(): ?string
    {
        if (! $this->tieneArchivo()) {
            return null;
        }

        return public_path(
            'storage/archivos/ordencompras/'.$this->ordencompra_id
            .'/periodos/'.$this->periodo.'/'
            .basename((string) $this->archivo_nombre)
        );
    }

    public static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_CARGADO => 'Cargado',
            self::ESTADO_NO_APLICA => 'No aplica',
            default => 'Pendiente',
        };
    }

    public static function clasePillEstado(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_CARGADO => 'badge badge-success',
            self::ESTADO_NO_APLICA => 'badge badge-secondary',
            default => 'badge badge-warning',
        };
    }
}

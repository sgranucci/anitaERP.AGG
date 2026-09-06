<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Período importado del resumen de tarjeta corporativa (una cabecera por empresa y mes).
 */
class Suscripcion_Conciliacion extends Model
{
    public const ESTADO_ABIERTA = 'ABIERTA';

    public const ESTADO_CERRADA = 'CERRADA';

    protected $table = 'suscripcion_conciliacion';

    protected $fillable = [
        'empresa_id',
        'periodo',
        'fecha_desde',
        'fecha_hasta',
        'estado',
        'archivo_nombre',
        'filas_importadas',
        'importo_usuario_id',
        'importado_at',
        'cerro_usuario_id',
        'cerrado_at',
        'observacion',
    ];

    protected $casts = [
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'importado_at' => 'datetime',
        'cerrado_at' => 'datetime',
        'filas_importadas' => 'integer',
    ];

    public function empresas(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function importadores(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'importo_usuario_id');
    }

    public function suscripcion_cargos(): HasMany
    {
        return $this->hasMany(Suscripcion_Cargo::class, 'suscripcion_conciliacion_id');
    }

    public function abierta(): bool
    {
        return $this->estado !== self::ESTADO_CERRADA;
    }

    public function etiquetaPeriodo(): string
    {
        try {
            return Carbon::createFromFormat('Y-m', (string) $this->periodo)
                ->locale('es')
                ->isoFormat('MMMM YYYY');
        } catch (\Throwable) {
            return (string) $this->periodo;
        }
    }
}

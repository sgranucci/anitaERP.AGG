<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReporteSueldosDefinibleDataset extends Model
{
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_PUBLICADO = 'publicado';

    public const ESTADO_ARCHIVADO = 'archivado';

    protected $table = 'reporte_sueldos_definible_dataset';

    protected $fillable = [
        'uuid',
        'reporte_sueldos_definible_id',
        'ejecucion_id',
        'version_id',
        'estado',
        'cantidad_filas',
        'columnas',
        'totales',
        'meta',
        'publicado_por',
        'publicado_at',
    ];

    protected $casts = [
        'reporte_sueldos_definible_id' => 'integer',
        'ejecucion_id' => 'integer',
        'version_id' => 'integer',
        'cantidad_filas' => 'integer',
        'columnas' => 'array',
        'totales' => 'array',
        'meta' => 'array',
        'publicado_por' => 'integer',
        'publicado_at' => 'datetime',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinible::class, 'reporte_sueldos_definible_id');
    }

    public function ejecucion(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleEjecucion::class, 'ejecucion_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleVersion::class, 'version_id');
    }

    public function filas(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleDatasetFila::class, 'dataset_id');
    }

    public function publicaciones(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleDatasetPublicacion::class, 'dataset_id');
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'publicado_por');
    }
}

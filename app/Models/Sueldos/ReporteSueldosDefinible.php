<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ReporteSueldosDefinible extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'reporte_sueldos_definible';

    protected $fillable = [
        'codigo',
        'titulo',
        'tipo',
        'asociado_codigo',
        'empresa_id',
        'owner_id',
        'origen',
        'anita_listado',
        'activo',
        'version_actual',
        'estado_publicacion',
        'publicado_ejecucion_id',
        'publicado_dataset_id',
        'incluye_confidencial',
        'observaciones',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'asociado_codigo' => 'integer',
        'empresa_id' => 'integer',
        'owner_id' => 'integer',
        'anita_listado' => 'integer',
        'activo' => 'boolean',
        'incluye_confidencial' => 'boolean',
        'version_actual' => 'integer',
        'publicado_ejecucion_id' => 'integer',
        'publicado_dataset_id' => 'integer',
    ];

    public function columnas(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleColumna::class, 'reporte_sueldos_definible_id')
            ->orderBy('orden')
            ->orderBy('nro_columna');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleVersion::class, 'reporte_sueldos_definible_id')
            ->orderByDesc('version');
    }

    public function suscripciones(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleSuscripcion::class, 'reporte_sueldos_definible_id');
    }

    public function ejecuciones(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleEjecucion::class, 'reporte_sueldos_definible_id')
            ->orderByDesc('id');
    }

    public function variantes(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleVariante::class, 'reporte_sueldos_definible_id');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleAlerta::class, 'reporte_sueldos_definible_id')
            ->orderBy('orden');
    }

    public function certificaciones(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleCertificacion::class, 'reporte_sueldos_definible_id')
            ->orderByDesc('id');
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleWebhook::class, 'reporte_sueldos_definible_id')
            ->orderByDesc('id');
    }

    public function ejecucionPublicada(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleEjecucion::class, 'publicado_ejecucion_id');
    }

    public function datasetPublicado(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleDataset::class, 'publicado_dataset_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'owner_id');
    }
}

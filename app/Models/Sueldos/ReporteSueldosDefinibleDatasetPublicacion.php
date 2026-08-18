<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteSueldosDefinibleDatasetPublicacion extends Model
{
    protected $table = 'reporte_sueldos_definible_dataset_publicacion';

    protected $fillable = [
        'reporte_sueldos_definible_id',
        'dataset_id',
        'usuario_id',
        'accion',
        'comentario',
    ];

    protected $casts = [
        'reporte_sueldos_definible_id' => 'integer',
        'dataset_id' => 'integer',
        'usuario_id' => 'integer',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinible::class, 'reporte_sueldos_definible_id');
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleDataset::class, 'dataset_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

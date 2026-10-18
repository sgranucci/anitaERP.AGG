<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ReporteSueldosDefinibleDatasetFila extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'reporte_sueldos_definible_dataset_fila';

    protected $fillable = [
        'dataset_id',
        'orden',
        'legajo',
        'empleado_id',
        'datos',
    ];

    protected $casts = [
        'dataset_id' => 'integer',
        'orden' => 'integer',
        'legajo' => 'integer',
        'empleado_id' => 'integer',
        'datos' => 'array',
    ];

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleDataset::class, 'dataset_id');
    }
}

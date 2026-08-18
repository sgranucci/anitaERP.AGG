<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteSueldosDefinibleDashboardWidget extends Model
{
    protected $table = 'reporte_sueldos_definible_dashboard_widget';

    protected $fillable = [
        'dashboard_id',
        'titulo',
        'tipo',
        'pivot_spec',
        'orden',
        'ancho',
    ];

    protected $casts = [
        'dashboard_id' => 'integer',
        'pivot_spec' => 'array',
        'orden' => 'integer',
        'ancho' => 'integer',
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleDashboard::class, 'dashboard_id');
    }
}

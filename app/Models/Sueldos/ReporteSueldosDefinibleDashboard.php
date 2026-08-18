<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReporteSueldosDefinibleDashboard extends Model
{
    protected $table = 'reporte_sueldos_definible_dashboard';

    protected $fillable = [
        'reporte_sueldos_definible_id',
        'usuario_id',
        'variante_id',
        'nombre',
        'compartida',
    ];

    protected $casts = [
        'reporte_sueldos_definible_id' => 'integer',
        'usuario_id' => 'integer',
        'variante_id' => 'integer',
        'compartida' => 'boolean',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinible::class, 'reporte_sueldos_definible_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleVariante::class, 'variante_id');
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleDashboardWidget::class, 'dashboard_id')
            ->orderBy('orden');
    }
}

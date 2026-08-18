<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ReporteSueldosDefinibleColumna extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'reporte_sueldos_definible_columna';

    protected $fillable = [
        'reporte_sueldos_definible_id',
        'nro_columna',
        'descripcion',
        'contenido',
        'campo_empleado',
        'largo',
        'formula',
        'orden',
    ];

    protected $casts = [
        'reporte_sueldos_definible_id' => 'integer',
        'nro_columna' => 'integer',
        'campo_empleado' => 'integer',
        'largo' => 'integer',
        'orden' => 'integer',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinible::class, 'reporte_sueldos_definible_id');
    }

    public function conceptos(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleConcepto::class, 'columna_id')
            ->orderBy('orden');
    }
}

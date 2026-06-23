<?php

namespace App\Models\Uif;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UifConciliacionWigosPeriodo extends Model
{
    protected $table = 'uif_conciliacion_wigos_periodo';

    protected $fillable = [
        'empresa_id',
        'anio',
        'mes',
        'titos_archivo',
        'pm_archivo',
        'usuario_id',
        'conciliado_at',
    ];

    protected $casts = [
        'conciliado_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function titos(): HasMany
    {
        return $this->hasMany(UifConciliacionWigosTito::class, 'periodo_id');
    }

    public function premiosMaquina(): HasMany
    {
        return $this->hasMany(UifConciliacionWigosPm::class, 'periodo_id');
    }

    public function unificado(): HasMany
    {
        return $this->hasMany(UifConciliacionWigosUnificado::class, 'periodo_id')->orderBy('orden');
    }
}

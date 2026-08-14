<?php

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AjusteInflacionCorrida extends Model
{
    protected $table = 'ajuste_inflacion_corrida';

    protected $fillable = [
        'empresa_id',
        'periodo_desde',
        'fecha_cierre',
        'indice_cierre_id',
        'estado',
        'asiento_id',
        'confirmada_clave',
        'usuario_id',
        'confirmado_por_id',
        'confirmado_at',
        'observacion',
        'total_ajuste',
        'firma',
    ];

    protected function casts(): array
    {
        return [
            'periodo_desde' => 'date',
            'fecha_cierre' => 'date',
            'confirmado_at' => 'datetime',
            'total_ajuste' => 'decimal:4',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function indiceCierre(): BelongsTo
    {
        return $this->belongsTo(AjusteInflacionIndice::class, 'indice_cierre_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'confirmado_por_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(AjusteInflacionCorridaDetalle::class, 'corrida_id');
    }
}

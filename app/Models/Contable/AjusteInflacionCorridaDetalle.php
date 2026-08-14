<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjusteInflacionCorridaDetalle extends Model
{
    protected $table = 'ajuste_inflacion_corrida_detalle';

    protected $fillable = [
        'corrida_id',
        'cuentacontable_id',
        'centrocosto_id',
        'periodo_origen',
        'indice_origen_id',
        'saldo_origen',
        'coeficiente',
        'importe_reexpresado',
        'ajuste',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'periodo_origen' => 'date',
            'saldo_origen' => 'decimal:4',
            'coeficiente' => 'decimal:10',
            'importe_reexpresado' => 'decimal:4',
            'ajuste' => 'decimal:4',
        ];
    }

    public function corrida(): BelongsTo
    {
        return $this->belongsTo(AjusteInflacionCorrida::class, 'corrida_id');
    }

    public function cuentacontable(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }

    public function centrocosto(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function indiceOrigen(): BelongsTo
    {
        return $this->belongsTo(AjusteInflacionIndice::class, 'indice_origen_id');
    }
}

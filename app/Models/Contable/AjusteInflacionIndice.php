<?php

namespace App\Models\Contable;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AjusteInflacionIndice extends Model
{
    protected $table = 'ajuste_inflacion_indice';

    protected $fillable = [
        'periodo',
        'valor',
        'fuente',
        'provisorio',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'periodo' => 'date',
            'valor' => 'decimal:8',
            'provisorio' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function corridasComoCierre(): HasMany
    {
        return $this->hasMany(AjusteInflacionCorrida::class, 'indice_cierre_id');
    }

    public function detallesComoOrigen(): HasMany
    {
        return $this->hasMany(AjusteInflacionCorridaDetalle::class, 'indice_origen_id');
    }
}

<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grupo_Concepto_Item_Sueldos extends Model
{
    protected $table = 'grupo_concepto_item_sueldos';

    protected $fillable = [
        'grupo_concepto_id', 'concepto_id', 'orden', 'activo',
    ];

    protected $casts = [
        'grupo_concepto_id' => 'integer',
        'concepto_id' => 'integer',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo_Concepto_Sueldos::class, 'grupo_concepto_id');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_id');
    }
}

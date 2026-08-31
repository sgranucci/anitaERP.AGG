<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Venta_Tag extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_venta_tag';

    protected $fillable = [
        'concepto_venta_id',
        'clave',
        'etiqueta',
        'tipo',
        'origen',
        'obligatorio',
        'orden',
        'largo_max',
        'opciones',
    ];

    protected $casts = [
        'obligatorio' => 'boolean',
        'orden' => 'integer',
        'largo_max' => 'integer',
    ];

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto_Venta::class, 'concepto_venta_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Importacion_Prorrateo_Linea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'importacion_prorrateo_linea';

    protected $fillable = [
        'importacion_prorrateo_id',
        'importacion_gasto_id',
        'articulo_id',
        'ordencompra_articulo_id',
        'sku',
        'base',
        'importe_asignado',
        'inventariable',
    ];

    protected $casts = [
        'base' => 'float',
        'importe_asignado' => 'float',
        'inventariable' => 'boolean',
    ];

    public function prorrateo(): BelongsTo
    {
        return $this->belongsTo(Importacion_Prorrateo::class, 'importacion_prorrateo_id');
    }

    public function gasto(): BelongsTo
    {
        return $this->belongsTo(Importacion_Gasto::class, 'importacion_gasto_id');
    }
}

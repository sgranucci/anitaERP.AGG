<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Venta_Emision_Tag_Valor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'venta_emision_tag_valor';

    protected $fillable = [
        'venta_emision_id',
        'clave',
        'valor',
    ];

    public function ventaEmision(): BelongsTo
    {
        return $this->belongsTo(Venta_Emision::class, 'venta_emision_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Contrato_Venta_Dato extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'contrato_venta_dato';

    protected $fillable = [
        'contrato_venta_id',
        'clave',
        'valor',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato_Venta::class, 'contrato_venta_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Venta_Precio extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_venta_precio';

    protected $fillable = [
        'concepto_venta_id',
        'precio',
        'vigencia_desde',
        'vigencia_hasta',
        'creousuario_id',
    ];

    protected $casts = [
        'precio' => 'float',
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
    ];

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto_Venta::class, 'concepto_venta_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }
}

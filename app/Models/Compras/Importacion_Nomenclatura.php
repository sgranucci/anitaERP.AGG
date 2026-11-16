<?php

declare(strict_types=1);

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Importacion_Nomenclatura extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'importacion_nomenclatura';

    protected $fillable = [
        'codigo',
        'descripcion',
        'alicuota_derecho',
        'activo',
    ];

    protected $casts = [
        'alicuota_derecho' => 'float',
        'activo' => 'boolean',
    ];
}

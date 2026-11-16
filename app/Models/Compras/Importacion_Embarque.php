<?php

declare(strict_types=1);

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Importacion_Embarque extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'importacion_embarque';

    protected $fillable = [
        'importacion_id',
        'tipo_documento',
        'numero_documento',
        'contenedor',
        'buque_vuelo',
        'puerto_origen',
        'puerto_destino',
        'fecha_embarque',
        'fecha_arribo',
        'observacion',
    ];

    protected $casts = [
        'fecha_embarque' => 'date',
        'fecha_arribo' => 'date',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }
}

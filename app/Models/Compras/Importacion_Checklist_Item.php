<?php

declare(strict_types=1);

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Importacion_Checklist_Item extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'importacion_checklist_item';

    protected $fillable = [
        'importacion_id',
        'codigo',
        'nombre',
        'obligatorio',
        'cumplido',
        'fecha_vencimiento',
        'observacion',
    ];

    protected $casts = [
        'obligatorio' => 'boolean',
        'cumplido' => 'boolean',
        'fecha_vencimiento' => 'date',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }
}

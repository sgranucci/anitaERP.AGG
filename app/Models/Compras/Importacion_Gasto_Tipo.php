<?php

declare(strict_types=1);

namespace App\Models\Compras;

use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Importacion_Gasto_Tipo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'importacion_gasto_tipo';

    protected $fillable = [
        'codigo',
        'nombre',
        'tratamiento',
        'base_prorrateo',
        'cuentacontable_id',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function cuentacontable(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }
}

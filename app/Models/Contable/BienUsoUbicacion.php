<?php

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class BienUsoUbicacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'bien_uso_ubicacion';

    protected $fillable = [
        'bien_uso_id',
        'empresa_id',
        'fecha',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function bienUso(): BelongsTo
    {
        return $this->belongsTo(BienUso::class, 'bien_uso_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}

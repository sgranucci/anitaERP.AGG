<?php

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjusteInflacionCuenta extends Model
{
    protected $table = 'ajuste_inflacion_cuenta';

    protected $fillable = [
        'empresa_id',
        'cuentacontable_id',
        'activo',
        'metodo_anticuacion',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontable(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }
}

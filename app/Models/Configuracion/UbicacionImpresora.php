<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class UbicacionImpresora extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'ubicacion_impresora';

    protected $fillable = ['nombre', 'descripcion'];

    public function salidas(): HasMany
    {
        return $this->hasMany(Salida::class, 'ubicacion_impresora_id');
    }
}

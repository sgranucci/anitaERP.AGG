<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CategoriafidelidadGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'categoriafidelidad_gastronomia';

    protected $fillable = ['nombre', 'codigo'];

    public function articulos()
    {
        return $this->hasMany(CategoriafidelidadArticuloGastronomia::class, 'categoriafidelidad_id');
    }
}

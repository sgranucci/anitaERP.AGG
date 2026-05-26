<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CategoriafidelidadArticuloGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'categoriafidelidad_articulo_gastronomia';

    protected $fillable = ['categoriafidelidad_id', 'articulo_id'];

    public function categoriafidelidad()
    {
        return $this->belongsTo(CategoriafidelidadGastronomia::class, 'categoriafidelidad_id');
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}

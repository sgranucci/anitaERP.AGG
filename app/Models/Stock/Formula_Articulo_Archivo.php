<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Formula_Articulo_Archivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'formula_articulo_archivo';

    protected $fillable = ['formula_articulo_id', 'nombrearchivo'];

    public function formula_articulos()
    {
        return $this->belongsTo(Formula_Articulo::class, 'formula_articulo_id');
    }
}

<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Formula_Articulo_Hijo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'formula_articulo_hijo';

    protected $fillable = [
        'formula_articulo_id', 'articulo_id', 'cantidad', 'factorcosto', 'formula_hija_id',
        'esopcional', 'ordenopcional', 'deposito_id', 'ranura',
    ];

    protected $casts = [
        'esopcional' => 'boolean',
    ];

    public function formula_articulos()
    {
        return $this->belongsTo(Formula_Articulo::class, 'formula_articulo_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function formula_hija()
    {
        return $this->belongsTo(Formula_Articulo::class, 'formula_hija_id');
    }

    public function depositos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }
}

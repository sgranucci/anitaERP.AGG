<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class MaquinavendingArticulo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'maquinavending_id',
        'numero_rulo',
        'articulo_id',
        'precio_lista',
    ];

    protected $casts = [
        'numero_rulo' => 'integer',
        'precio_lista' => 'float',
    ];

    protected $table = 'maquinavending_articulo';

    public function maquinavending()
    {
        return $this->belongsTo(Maquinavending::class, 'maquinavending_id');
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}

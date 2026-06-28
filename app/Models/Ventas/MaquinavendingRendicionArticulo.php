<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class MaquinavendingRendicionArticulo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'maquinavending_rendicion_id',
        'numero_rulo',
        'articulo_id',
        'cantidad',
        'precio_lista',
        'importe_total',
    ];

    protected $casts = [
        'numero_rulo' => 'integer',
        'cantidad' => 'float',
        'precio_lista' => 'float',
        'importe_total' => 'float',
    ];

    protected $table = 'maquinavending_rendicion_articulo';

    public function rendicion()
    {
        return $this->belongsTo(MaquinavendingRendicion::class, 'maquinavending_rendicion_id');
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}

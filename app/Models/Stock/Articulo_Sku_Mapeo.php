<?php

namespace App\Models\Stock;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Articulo_Sku_Mapeo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'articulo_sku_mapeo';

    protected $fillable = [
        'articulo_id',
        'sku_anterior',
        'sku_nuevo',
        'motivo',
        'usuario_id',
    ];

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

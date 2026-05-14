<?php

namespace App\Models\Stock;

use App\Models\Seguridad\Usuario;
use App\Traits\Stock\Formula_Articulo_EstadoTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Formula_Articulo_Estado extends Model implements Auditable
{
    use Formula_Articulo_EstadoTrait;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'formula_articulo_estado';

    protected $fillable = [
        'formula_articulo_id', 'fecha', 'estado', 'usuario_id', 'observacion',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function formula_articulos()
    {
        return $this->belongsTo(Formula_Articulo::class, 'formula_articulo_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

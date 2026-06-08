<?php

namespace App\Models\Stock;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Recuento_Estado extends Model
{
    public $timestamps = false;

    protected $table = 'recuento_estado';

    protected $fillable = [
        'recuento_id',
        'estado_anterior',
        'estado_nuevo',
        'usuario_id',
        'observaciones',
        'ocurrio_el',
    ];

    protected $casts = [
        'ocurrio_el' => 'datetime',
    ];

    public function recuento()
    {
        return $this->belongsTo(Recuento::class, 'recuento_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

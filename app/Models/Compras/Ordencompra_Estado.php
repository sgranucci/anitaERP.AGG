<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Ordencompra_Estado extends Model
{
    protected $table = 'ordencompra_estado';

    protected $fillable = ['ordencompra_id', 'fecha', 'estado', 'usuario_id', 'observacion'];

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

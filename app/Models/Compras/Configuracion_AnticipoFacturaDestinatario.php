<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Configuracion_AnticipoFacturaDestinatario extends Model
{
    protected $table = 'configuracion_anticipo_factura_destinatario';

    protected $fillable = [
        'usuario_id',
    ];

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

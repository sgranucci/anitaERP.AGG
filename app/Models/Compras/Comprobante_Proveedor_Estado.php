<?php

namespace App\Models\Compras;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class Comprobante_Proveedor_Estado extends Model
{
    protected $table = 'comprobante_proveedor_estado';

    protected $fillable = [
        'comprobante_proveedor_id', 'fecha', 'estado', 'usuario_id', 'observacion',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function comprobante_proveedores()
    {
        return $this->belongsTo(Comprobante_Proveedor::class, 'comprobante_proveedor_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

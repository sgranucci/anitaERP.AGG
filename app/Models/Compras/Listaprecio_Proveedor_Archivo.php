<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Listaprecio_Proveedor_Archivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'listaprecio_proveedor_archivo';

    protected $fillable = ['listaprecio_proveedor_id', 'nombrearchivo'];

    public function listaprecio_proveedores()
    {
        return $this->belongsTo(Listaprecio_Proveedor::class, 'listaprecio_proveedor_id');
    }
}

<?php

namespace App\Models\Stock;

use App\Models\Compras\Proveedor;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Articulo_Proveedor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'articulo_proveedor';

    protected $fillable = [
        'articulo_id',
        'proveedor_id',
        'nombre_articulo_proveedor',
        'codigobarra',
        'codigo_articulo_proveedor',
        'unidadmedida_compra_id',
        'coeficiente_conversion',
        'activo',
        'preferido',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'preferido' => 'boolean',
    ];

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function unidadesmedidacompra()
    {
        return $this->belongsTo(Unidadmedida::class, 'unidadmedida_compra_id');
    }
}

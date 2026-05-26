<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CategoriafidelidadEntregaGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'categoriafidelidad_entrega_gastronomia';

    protected $fillable = [
        'categoriafidelidad_id',
        'documento',
        'tarjeta',
        'trackdata',
        'fechacanje',
        'articulo_id',
        'venta_id',
        'apellido',
        'nombre',
    ];

    protected $casts = [
        'fechacanje' => 'datetime',
    ];

    public function categoriafidelidad()
    {
        return $this->belongsTo(CategoriafidelidadGastronomia::class, 'categoriafidelidad_id');
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }
}

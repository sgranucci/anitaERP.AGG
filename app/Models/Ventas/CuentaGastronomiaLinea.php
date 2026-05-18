<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CuentaGastronomiaLinea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cuenta_gastronomia_linea';

    protected $fillable = [
        'cuenta_gastronomia_id', 'articulo_id', 'cantidad', 'precio_unitario',
        'descuento_linea_pct', 'opcionales_json', 'numero_linea',
    ];

    protected $casts = [
        'opcionales_json' => 'array',
        'cantidad' => 'float',
        'precio_unitario' => 'float',
        'descuento_linea_pct' => 'float',
    ];

    public function cuenta()
    {
        return $this->belongsTo(CuentaGastronomia::class, 'cuenta_gastronomia_id');
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}

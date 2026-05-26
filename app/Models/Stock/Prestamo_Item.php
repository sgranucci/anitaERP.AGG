<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

class Prestamo_Item extends Model
{
    protected $table = 'prestamo_item';

    protected $fillable = [
        'prestamo_id',
        'articulo_id',
        'cantidad',
        'cantidad_devuelta',
        'observaciones',
    ];

    protected $casts = [
        'cantidad' => 'decimal:6',
        'cantidad_devuelta' => 'decimal:6',
    ];

    public function prestamos()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function getCantidadPendienteAttribute(): float
    {
        return max(0, (float) $this->cantidad - (float) $this->cantidad_devuelta);
    }
}

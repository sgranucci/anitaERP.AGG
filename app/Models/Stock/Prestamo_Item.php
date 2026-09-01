<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

class Prestamo_Item extends Model
{
    protected $table = 'prestamo_item';

    protected $fillable = [
        'prestamo_id',
        'articulo_id',
        'descripcion',
        'nro_serie',
        'condicion_salida',
        'condicion_devolucion',
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

    public function tieneArticulo(): bool
    {
        return (int) ($this->articulo_id ?? 0) > 0;
    }

    public function descripcionMostrada(): string
    {
        if ($this->descripcion) {
            return (string) $this->descripcion;
        }

        return (string) (optional($this->articulos)->descripcion ?? '');
    }

    public function getCantidadPendienteAttribute(): float
    {
        return max(0, (float) $this->cantidad - (float) $this->cantidad_devuelta);
    }
}

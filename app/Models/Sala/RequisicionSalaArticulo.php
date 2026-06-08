<?php

namespace App\Models\Sala;

use App\Models\Stock\Articulo;
use App\Traits\Sala\RequisicionSalaArticuloDestinoTrait;
use App\Traits\Sala\RequisicionSalaArticuloEstadoTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RequisicionSalaArticulo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use RequisicionSalaArticuloDestinoTrait;
    use RequisicionSalaArticuloEstadoTrait;

    protected $table = 'requisicion_sala_articulo';

    protected $fillable = [
        'requisicion_sala_id', 'articulo_id', 'cantidad', 'precio', 'detalle',
        'fueradeservicio', 'uid', 'destino', 'estado', 'estadoparcial',
        'numeroremito', 'nombreresponsable', 'numeroparte',
        'cantidadjuego', 'descripcionjuego', 'cantidadso', 'descripcionso',
        'cantidadmemoria', 'descripcionmemoria', 'cantidaddongle', 'descripciondongle',
        'cantidadsim', 'descripcionsim',
    ];

    public function requisicion_salas()
    {
        return $this->belongsTo(RequisicionSala::class, 'requisicion_sala_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}

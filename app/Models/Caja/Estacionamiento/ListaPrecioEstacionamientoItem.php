<?php

namespace App\Models\Caja\Estacionamiento;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ListaPrecioEstacionamientoItem extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'lista_precio_estacionamiento_item';

    protected $fillable = [
        'lista_precio_estacionamiento_id',
        'item_estacionamiento_id',
        'precio',
        'fecha_vigencia',
        'usuarioultcambio_id',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'fecha_vigencia' => 'date',
    ];

    public function listaPrecio()
    {
        return $this->belongsTo(ListaPrecioEstacionamiento::class, 'lista_precio_estacionamiento_id');
    }

    public function itemEstacionamiento()
    {
        return $this->belongsTo(ItemEstacionamiento::class, 'item_estacionamiento_id');
    }

    public function usuarioUltCambio()
    {
        return $this->belongsTo(Usuario::class, 'usuarioultcambio_id');
    }
}

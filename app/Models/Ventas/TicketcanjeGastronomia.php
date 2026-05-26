<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;

class TicketcanjeGastronomia extends Model
{
    protected $table = 'ticketcanje_gastronomia';

    protected $fillable = [
        'numerocupon',
        'ticket_id',
        'articulo_id',
        'puntos',
        'cantidad',
        'fecha',
        'cliente_id',
        'apellido',
        'nombre',
        'numerodocumento',
        'mozo_id',
        'fechacanje',
        'usuariocanje_id',
        'renglon',
        'venta_id',
        'costo',
        'precioventa',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'fechacanje' => 'datetime',
        'puntos' => 'integer',
        'cantidad' => 'float',
        'costo' => 'float',
        'precioventa' => 'float',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function mozo()
    {
        return $this->belongsTo(MozoGastronomia::class, 'mozo_id');
    }

    public function usuarioCanje()
    {
        return $this->belongsTo(Usuario::class, 'usuariocanje_id');
    }
}

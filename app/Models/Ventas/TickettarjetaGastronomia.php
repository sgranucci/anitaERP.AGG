<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class TickettarjetaGastronomia extends Model
{
    public const ESTADO_CANJEADO = 'C';

    public const ESTADO_PENDIENTE = 'P';

    protected $table = 'tickettarjeta_gastronomia';

    protected $fillable = [
        'ticket_id',
        'numerodocumento',
        'fecha',
        'monto',
        'numerocupon',
        'montoticket',
        'numeroticket',
        'estado',
        'venta_id',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'float',
        'montoticket' => 'float',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class CotRemitoEnvio extends Model
{
    protected $table = 'cot_remito_envio';

    protected $fillable = [
        'tipo',
        'letra',
        'sucursal',
        'numero_remito',
        'fecha_remito',
        'venta_id',
        'transporte_id',
        'cliente_id',
        'procesado',
        'nro_unico',
        'cot',
        'numero_comprobante_arba',
        'nombre_archivo',
        'error',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_remito' => 'date',
        'numero_remito' => 'integer',
        'sucursal' => 'integer',
    ];

    public function ventas()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function transportes()
    {
        return $this->belongsTo(Transporte::class, 'transporte_id');
    }

    public function clientes()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

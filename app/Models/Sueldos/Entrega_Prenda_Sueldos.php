<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use Illuminate\Database\Eloquent\Model;

class Entrega_Prenda_Sueldos extends Model
{
    protected $table = 'entrega_prenda_sueldos';

    protected $fillable = [
        'empleado_id',
        'fecha',
        'anio',
        'deposito_id',
        'tipotransaccion_stock_id',
        'movimientostock_id',
        'observacion',
        'usuario_id',
        'origen_anita_id',
        'tulegajo_estado',
        'tulegajo_enviado_at',
        'tulegajo_mensaje',
    ];

    protected $casts = [
        'empleado_id' => 'integer',
        'fecha' => 'date',
        'anio' => 'integer',
        'deposito_id' => 'integer',
        'tipotransaccion_stock_id' => 'integer',
        'movimientostock_id' => 'integer',
        'usuario_id' => 'integer',
        'origen_anita_id' => 'integer',
        'tulegajo_enviado_at' => 'datetime',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function articulos()
    {
        return $this->hasMany(Entrega_Prenda_Articulo_Sueldos::class, 'entrega_id');
    }

    public function deposito()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function movimientostock()
    {
        return $this->belongsTo(MovimientoStock::class, 'movimientostock_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

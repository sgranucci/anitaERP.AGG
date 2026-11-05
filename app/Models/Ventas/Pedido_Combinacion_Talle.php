<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Stock\Articulo_Movimiento_Talle;
use App\Models\Stock\Talle;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Models\Ventas\Ordentrabajo_Combinacion_Talle;
use OwenIt\Auditing\Contracts\Auditable;

class Pedido_Combinacion_Talle extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['pedido_combinacion_id', 'talle_id', 'cantidad', 'precio'];
    protected $table = 'pedido_combinacion_talle';

    protected static function booted(): void
    {
        // La FK borra en cascada los talles de stock que apuntan a este renglón, sin
        // pasar por Eloquent ni dejar audits.
        static::deleting(function (Pedido_Combinacion_Talle $talle): void {
            EloquentAuditDeleteSupport::each(
                Articulo_Movimiento_Talle::query()->where('pedido_combinacion_talle_id', $talle->id)
            );
        });
    }

    public function pedidos_combinacion()
    {
        return $this->belongsTo(Pedido_Combinacion::class, 'pedido_combinacion_id')->with("articulos")->with("pedidos");;
    }

	// Por error en nomenclatura
    public function pedido_combinaciones()
    {
        return $this->belongsTo(Pedido_Combinacion::class, 'pedido_combinacion_id')->with("articulos")->with("pedidos");;
    }

    public function talles()
    {
        return $this->belongsTo(Talle::class, 'talle_id');
    }

    public function pedidos_combinacion_ordenes()
    {
        return $this->hasOne(Ordentrabajo_Combinacion_Talle::class, 'pedido_combinacion_talle_id', 'id');
    }

}


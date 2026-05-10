<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Requisicion_Presupuesto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'requisicion_presupuesto';

    public static $enumEstado = [
        ['valor' => 'A', 'nombre' => 'ACTIVO'],
        ['valor' => 'S', 'nombre' => 'SUSPENDIDO'],
        ['valor' => 'E', 'nombre' => 'ELEGIDO'],
    ];

    protected $fillable = [
        'requisicion_id',
        'fecha',
        'condicionentrega_id',
        'condicioncompra_id',
        'condicionpago_id',
        'proveedor_id',
        'estado',
    ];

    public function requisicion()
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function proveedores()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function condicionentregas()
    {
        return $this->belongsTo(Condicionentrega::class, 'condicionentrega_id');
    }

    public function condicioncompras()
    {
        return $this->belongsTo(Condicioncompra::class, 'condicioncompra_id');
    }

    public function condicionpagos()
    {
        return $this->belongsTo(Condicionpago::class, 'condicionpago_id');
    }

    public function requisicion_presupuesto_articulos()
    {
        return $this->hasMany(Requisicion_Presupuesto_Articulo::class, 'requisicion_presupuesto_id');
    }

    public function requisicion_presupuesto_archivos()
    {
        return $this->hasMany(Requisicion_Presupuesto_Archivo::class, 'requisicion_presupuesto_id');
    }
}

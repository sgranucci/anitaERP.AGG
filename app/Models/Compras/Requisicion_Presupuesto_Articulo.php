<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Requisicion_Presupuesto_Articulo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'requisicion_presupuesto_articulo';

    protected $fillable = [
        'requisicion_presupuesto_id',
        'requisicion_articulo_id',
        'precio_unitario',
        'observacion',
    ];

    public function requisicion_presupuesto()
    {
        return $this->belongsTo(Requisicion_Presupuesto::class, 'requisicion_presupuesto_id');
    }

    public function requisicion_articulo()
    {
        return $this->belongsTo(Requisicion_Articulo::class, 'requisicion_articulo_id');
    }
}

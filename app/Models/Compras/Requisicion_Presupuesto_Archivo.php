<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Requisicion_Presupuesto_Archivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'requisicion_presupuesto_archivo';

    protected $fillable = [
        'requisicion_presupuesto_id',
        'nombrearchivo',
    ];

    public function requisicion_presupuesto()
    {
        return $this->belongsTo(Requisicion_Presupuesto::class, 'requisicion_presupuesto_id');
    }
}

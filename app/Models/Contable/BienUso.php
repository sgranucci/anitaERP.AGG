<?php

namespace App\Models\Contable;

use App\Traits\Contable\BienUsoTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class BienUso extends Model implements Auditable
{
    use BienUsoTrait;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'bien_uso';

    protected $fillable = [
        'codigo_inventario',
        'hostname',
        'ip',
        'modelo',
        'numero_serie',
        'estado',
        'centrocosto_id',
        'tipo_bien',
        'observaciones',
    ];

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}

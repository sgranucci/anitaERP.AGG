<?php

namespace App\Models\Contable;

use App\Models\Configuracion\Empresa;
use App\Traits\Contable\BienUsoTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class BienUso extends Model implements Auditable
{
    use BienUsoTrait;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'bien_uso';

    protected $fillable = [
        'empresa_id',
        'codigo_inventario',
        'uid',
        'hostname',
        'ip',
        'modelo',
        'vendor',
        'tema',
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

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function esMaquinaTragamonedas(): bool
    {
        return $this->tipo_bien === 'M';
    }

    public function esPc(): bool
    {
        return $this->tipo_bien === 'P';
    }
}

<?php

namespace App\Models\Configuracion;

use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Arbolaprobacion_CuentaExcepcion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'arbolaprobacion_cuenta_excepcion';

    protected $fillable = [
        'arbolaprobacion_id',
        'centrocosto_id',
        'empresa_id',
        'cuentacontable_id',
        'activo',
    ];

    public function arbolaprobaciones()
    {
        return $this->belongsTo(Arbolaprobacion::class, 'arbolaprobacion_id');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }
}

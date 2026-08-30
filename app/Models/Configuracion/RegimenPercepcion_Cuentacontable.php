<?php

declare(strict_types=1);

namespace App\Models\Configuracion;

use App\Models\Contable\Cuentacontable;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RegimenPercepcion_Cuentacontable extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'regimen_percepcion_cuentacontable';

    protected $fillable = [
        'regimen_percepcion_id',
        'empresa_id',
        'cuentacontable_id',
        'creousuario_id',
    ];

    public function regimen()
    {
        return $this->belongsTo(RegimenPercepcion::class, 'regimen_percepcion_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }

    public function creousuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }
}

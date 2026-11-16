<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaEmpresaConfig extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_empresa_config';

    protected $fillable = [
        'empresa_id',
        'modo',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
    ];
}

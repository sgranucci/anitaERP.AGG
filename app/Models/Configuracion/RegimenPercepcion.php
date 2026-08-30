<?php

declare(strict_types=1);

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RegimenPercepcion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'regimen_percepcion';

    protected $fillable = [
        'codigo',
        'nombre',
        'habilitado',
        'tasa',
        'minimo_base',
        'minimo_importe',
        'vigencia_desde',
        'vigencia_hasta',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
        'tasa' => 'float',
        'minimo_base' => 'float',
        'minimo_importe' => 'float',
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
    ];

    public function cuentas()
    {
        return $this->hasMany(RegimenPercepcion_Cuentacontable::class, 'regimen_percepcion_id');
    }

    public function esCodigoSistema(): bool
    {
        return in_array(strtoupper(trim((string) $this->codigo)), ['PIVA', 'PNC'], true);
    }
}

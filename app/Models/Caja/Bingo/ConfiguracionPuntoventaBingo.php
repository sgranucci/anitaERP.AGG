<?php

namespace App\Models\Caja\Bingo;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConfiguracionPuntoventaBingo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'configuracion_puntoventa_bingo';

    protected $fillable = [
        'identificador_pc',
        'descripcion',
        'empresa_id',
        'cuentacaja_id',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacaja()
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_id');
    }
}

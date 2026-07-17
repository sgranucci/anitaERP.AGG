<?php

namespace App\Models\Solicitudpago;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Solicitudpago_Cuenta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'solicitudpago_cuenta';

    protected $fillable = [
        'solicitudpago_id',
        'empresa_id',
        'cuentacontable_id',
        'centrocosto_id',
        'debe_haber',
        'monto',
    ];

    protected $casts = [
        'solicitudpago_id' => 'integer',
        'empresa_id' => 'integer',
        'cuentacontable_id' => 'integer',
        'centrocosto_id' => 'integer',
        'monto' => 'decimal:2',
    ];

    public function solicitudpagos()
    {
        return $this->belongsTo(Solicitudpago::class, 'solicitudpago_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}

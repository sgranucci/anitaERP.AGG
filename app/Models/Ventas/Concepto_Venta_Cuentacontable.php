<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Venta_Cuentacontable extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_venta_cuentacontable';

    protected $fillable = [
        'concepto_venta_id',
        'empresa_id',
        'tipotransaccion_id',
        'cuentacontable_id',
        'vigencia_desde',
        'vigencia_hasta',
        'centrocosto_id',
        'creousuario_id',
    ];

    protected $casts = [
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
    ];

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto_Venta::class, 'concepto_venta_id');
    }

    public function empresas(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontables(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function tipotransaccion(): BelongsTo
    {
        return $this->belongsTo(Tipotransaccion::class, 'tipotransaccion_id');
    }

    public function centrocosto(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }
}

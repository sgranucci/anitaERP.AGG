<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Cuentacontable;
use App\Support\Sueldos\SueldosAsientoMapeoSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Imputacion_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_imputacion_sueldos';

    protected $fillable = [
        'empresa_id',
        'alcance',
        'clave',
        'concepto_id',
        'rubro',
        'tipo',
        'cuenta_debe_id',
        'cuenta_haber_id',
        'observacion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'concepto_id' => 'integer',
        'cuenta_debe_id' => 'integer',
        'cuenta_haber_id' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_id');
    }

    public function cuentaDebe(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuenta_debe_id');
    }

    public function cuentaHaber(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuenta_haber_id');
    }

    public function alcanceLabel(): string
    {
        return SueldosAsientoMapeoSupport::etiquetaAlcance((string) $this->alcance);
    }

    public function claveLabel(): string
    {
        return SueldosAsientoMapeoSupport::etiquetaClave($this);
    }
}

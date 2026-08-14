<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use App\Support\Sueldos\DtoFalloTipoOper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Dtofallo_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'dtofallo_sueldos';

    protected $fillable = [
        'empresa_id',
        'empleado_sueldos_id',
        'cierrefallo_id',
        'fecha',
        'periodo',
        'tipo_oper',
        'importe',
        'observacion',
        'novedad_id',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'empleado_sueldos_id' => 'integer',
        'cierrefallo_id' => 'integer',
        'fecha' => 'date',
        'periodo' => 'integer',
        'importe' => 'decimal:2',
        'novedad_id' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_sueldos_id');
    }

    public function cierre(): BelongsTo
    {
        return $this->belongsTo(Cierrefallo_Sueldos::class, 'cierrefallo_id');
    }

    public function novedad(): BelongsTo
    {
        return $this->belongsTo(Novedad_Sueldos::class, 'novedad_id');
    }

    public function tipoLabel(): string
    {
        return DtoFalloTipoOper::etiqueta($this->tipo_oper);
    }

    public function esDebe(): bool
    {
        return DtoFalloTipoOper::esDebe((string) $this->tipo_oper);
    }

    public function esHaber(): bool
    {
        return DtoFalloTipoOper::esHaber((string) $this->tipo_oper);
    }
}

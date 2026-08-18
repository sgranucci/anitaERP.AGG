<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use App\Support\Sueldos\DescuentoFalloTipoOperacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class DescuentoFallo_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'descuento_fallo_sueldos';

    protected $fillable = [
        'empresa_id',
        'empleado_sueldos_id',
        'cierre_descuento_fallo_id',
        'fecha',
        'periodo',
        'tipo_operacion',
        'importe',
        'observacion',
        'novedad_id',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'empleado_sueldos_id' => 'integer',
        'cierre_descuento_fallo_id' => 'integer',
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
        return $this->belongsTo(CierreDescuentoFallo_Sueldos::class, 'cierre_descuento_fallo_id');
    }

    public function novedad(): BelongsTo
    {
        return $this->belongsTo(Novedad_Sueldos::class, 'novedad_id');
    }

    public function tipoLabel(): string
    {
        return DescuentoFalloTipoOperacion::etiqueta($this->tipo_operacion);
    }

    public function esDebe(): bool
    {
        return DescuentoFalloTipoOperacion::esDebe((string) $this->tipo_operacion);
    }

    public function esHaber(): bool
    {
        return DescuentoFalloTipoOperacion::esHaber((string) $this->tipo_operacion);
    }
}

<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Cierrefallo_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_GENERADO = 'generado';

    public const ESTADO_ANULADO = 'anulado';

    protected $table = 'cierrefallo_sueldos';

    protected $fillable = [
        'nro_cierre',
        'empresa_id',
        'periodo_descuento',
        'fecha_fallo_desde',
        'fecha_fallo_hasta',
        'legajo_desde',
        'legajo_hasta',
        'usuario_id',
        'empleados_procesados',
        'movimientos_generados',
        'novedades_generadas',
        'total_perdida',
        'total_descuento',
        'total_sancion',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'nro_cierre' => 'integer',
        'empresa_id' => 'integer',
        'periodo_descuento' => 'integer',
        'fecha_fallo_desde' => 'date',
        'fecha_fallo_hasta' => 'date',
        'legajo_desde' => 'integer',
        'legajo_hasta' => 'integer',
        'usuario_id' => 'integer',
        'empleados_procesados' => 'integer',
        'movimientos_generados' => 'integer',
        'novedades_generadas' => 'integer',
        'total_perdida' => 'decimal:2',
        'total_descuento' => 'decimal:2',
        'total_sancion' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Dtofallo_Sueldos::class, 'cierrefallo_id');
    }

    public function estaAnulado(): bool
    {
        return $this->estado === self::ESTADO_ANULADO;
    }
}

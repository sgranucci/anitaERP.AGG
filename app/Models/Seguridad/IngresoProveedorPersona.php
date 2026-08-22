<?php

namespace App\Models\Seguridad;

use App\Support\Seguridad\IngresoProveedorControlSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class IngresoProveedorPersona extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'ingreso_proveedor_persona';

    protected $fillable = [
        'ingreso_proveedor_id', 'orden', 'nombre', 'documento', 'documento_norm',
        'fecha_ingreso', 'hora_ingreso', 'fecha_egreso', 'hora_egreso', 'minutos_en_planta',
        'usuario_ingreso_id', 'usuario_egreso_id',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_egreso' => 'date',
        'minutos_en_planta' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $persona) {
            $persona->documento_norm = IngresoProveedorControlSupport::normalizarDni($persona->documento);
        });
    }

    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(IngresoProveedor::class, 'ingreso_proveedor_id');
    }

    public function usuarioIngreso(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_ingreso_id');
    }

    public function usuarioEgreso(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_egreso_id');
    }

    public function estaEnPlanta(): bool
    {
        return $this->fecha_ingreso && ! $this->fecha_egreso;
    }
}

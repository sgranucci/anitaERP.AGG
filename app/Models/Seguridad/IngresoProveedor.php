<?php

namespace App\Models\Seguridad;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use App\Support\Seguridad\IngresoProveedorEstados;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class IngresoProveedor extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'ingreso_proveedor';

    protected $fillable = [
        'empresa_id', 'fecha', 'proveedor_id', 'visitante_tipo', 'visitante_nombre', 'ordencompra_id',
        'motivo_id', 'punto_id', 'area_id', 'sector_id',
        'patente', 'estado', 'titulo', 'comentario',
        'fecha_ingreso', 'hora_ingreso', 'fecha_egreso', 'hora_egreso', 'minutos_en_planta',
        'usuario_id', 'usuario_autorizo_id', 'autorizado_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_ingreso' => 'date',
        'fecha_egreso' => 'date',
        'autorizado_at' => 'datetime',
        'minutos_en_planta' => 'integer',
    ];

    public function empresas(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedores(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function ordencompras(): BelongsTo
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function motivos(): BelongsTo
    {
        return $this->belongsTo(IngresoProveedorMotivo::class, 'motivo_id');
    }

    public function puntos(): BelongsTo
    {
        return $this->belongsTo(IngresoProveedorPunto::class, 'punto_id');
    }

    public function areas(): BelongsTo
    {
        return $this->belongsTo(IngresoProveedorArea::class, 'area_id');
    }

    public function sectores(): BelongsTo
    {
        return $this->belongsTo(IngresoProveedorSector::class, 'sector_id');
    }

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function usuarioAutorizo(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_autorizo_id');
    }

    public function personas(): HasMany
    {
        return $this->hasMany(IngresoProveedorPersona::class, 'ingreso_proveedor_id')->orderBy('orden');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(IngresoProveedorArchivo::class, 'ingreso_proveedor_id');
    }

    public function etiquetaEstado(): string
    {
        return IngresoProveedorEstados::etiqueta((string) $this->estado);
    }

    public function badgeEstado(): string
    {
        return IngresoProveedorEstados::badge((string) $this->estado);
    }

    public function recalcularMinutosEnPlanta(): void
    {
        if (! $this->fecha_ingreso || ! $this->hora_ingreso || ! $this->fecha_egreso || ! $this->hora_egreso) {
            $this->minutos_en_planta = null;

            return;
        }

        $desde = Carbon::parse($this->fecha_ingreso->format('Y-m-d').' '.$this->hora_ingreso);
        $hasta = Carbon::parse($this->fecha_egreso->format('Y-m-d').' '.$this->hora_egreso);
        $this->minutos_en_planta = max(0, $desde->diffInMinutes($hasta, false));
    }
}

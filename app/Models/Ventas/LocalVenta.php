<?php

namespace App\Models\Ventas;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Cuentacontable;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class LocalVenta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'local_venta';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
        'empresa_id',
        'puntoventa_id',
        'deposito_id',
        'listaprecio_id',
        'tipotransaccion_fac_id',
        'tipotransaccion_nc_id',
        'tipotransaccion_caja_id',
        'tipotransaccion_caja_devolucion_id',
        'cuentacaja_efectivo_id',
        'cuentacontable_venta_id',
        'anita_servidor',
        'anita_ifx_server',
        'anita_deposito',
        'observacion',
        'pdf_web',
        'pdf_imp_internos',
        'pdf_seguridad_higiene',
        'pdf_habilitacion',
        'pdf_lugar',
        'pdf_inicio_actividad',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'anita_deposito' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Default rápido (columna legacy sincronizada con el pivot es_default).
     */
    public function puntoventa(): BelongsTo
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_id');
    }

    public function puntoventas(): BelongsToMany
    {
        return $this->belongsToMany(Puntoventa::class, 'local_venta_puntoventa', 'local_venta_id', 'puntoventa_id')
            ->withPivot(['es_default', 'orden'])
            ->withTimestamps()
            ->orderByPivot('orden');
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function listaprecio(): BelongsTo
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_id');
    }

    public function tipotransaccionFac(): BelongsTo
    {
        return $this->belongsTo(Tipotransaccion::class, 'tipotransaccion_fac_id');
    }

    public function tipotransaccionNc(): BelongsTo
    {
        return $this->belongsTo(Tipotransaccion::class, 'tipotransaccion_nc_id');
    }

    public function cuentacajaEfectivo(): BelongsTo
    {
        return $this->belongsTo(Cuentacaja::class, 'cuentacaja_efectivo_id');
    }

    public function cuentacontableVenta(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_venta_id');
    }

    public function cuentacajas(): BelongsToMany
    {
        return $this->belongsToMany(Cuentacaja::class, 'local_venta_cuentacaja', 'local_venta_id', 'cuentacaja_id')
            ->withPivot(['medio', 'orden', 'es_default'])
            ->withTimestamps()
            ->orderByPivot('orden');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(TurnoOperativoLocal::class, 'local_venta_id');
    }

    public function puntoventaDefault(): ?Puntoventa
    {
        $desdePivot = $this->relationLoaded('puntoventas')
            ? $this->puntoventas->first(fn (Puntoventa $pv) => (bool) ($pv->pivot->es_default ?? false))
            : $this->puntoventas()->wherePivot('es_default', true)->first();

        if ($desdePivot) {
            return $desdePivot;
        }

        if ($this->relationLoaded('puntoventas') && $this->puntoventas->isNotEmpty()) {
            return $this->puntoventas->first();
        }

        return $this->puntoventa;
    }

    public function puntoventaDefaultId(): ?int
    {
        $pv = $this->puntoventaDefault();
        if ($pv) {
            return (int) $pv->id;
        }
        $id = (int) ($this->puntoventa_id ?: 0);

        return $id > 0 ? $id : null;
    }

    public function tipoFacId(): int
    {
        $id = (int) ($this->tipotransaccion_fac_id ?: 0);

        return $id > 0 ? $id : (int) config('facturacion_local.tipotransaccion_fac_id', 1);
    }

    public function tipoNcId(): int
    {
        $id = (int) ($this->tipotransaccion_nc_id ?: 0);

        return $id > 0 ? $id : (int) config('facturacion_local.tipotransaccion_nc_id', 2);
    }

    public function tipoCajaId(): int
    {
        $id = (int) ($this->tipotransaccion_caja_id ?: 0);

        return $id > 0 ? $id : (int) config('facturacion_local.tipotransaccion_caja_id', 1);
    }

    public function tipoCajaDevolucionId(): int
    {
        $id = (int) ($this->tipotransaccion_caja_devolucion_id ?: 0);

        return $id > 0 ? $id : (int) config('facturacion_local.tipotransaccion_caja_devolucion_id', 3);
    }

    public function anitaServidor(): string
    {
        return trim((string) ($this->anita_servidor ?: config('facturacion_local.anita_servidor_default', 'LOCAL_IP')));
    }

    public function anitaIfxServer(): string
    {
        return trim((string) ($this->anita_ifx_server ?: config('facturacion_local.anita_ifx_server_default', 'IFX_SERVER_LOCAL')));
    }
}

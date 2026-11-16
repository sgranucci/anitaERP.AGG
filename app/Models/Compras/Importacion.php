<?php

declare(strict_types=1);

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Lote;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Ventas\Incoterm;
use App\Support\Compras\ImportacionEstados;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Importacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'importacion';

    protected $fillable = [
        'codigo',
        'empresa_id',
        'proveedor_id',
        'moneda_id',
        'cotizacion',
        'incoterm_id',
        'estado',
        'fecha_apertura',
        'fecha_etd',
        'fecha_eta',
        'fecha_nacionalizacion',
        'fecha_cierre_costos',
        'fob_estimado',
        'gastos_estimados',
        'gastos_reales',
        'landed_cost',
        'coeficiente_embarque',
        'observacion',
        'exige_stock_para_cerrar',
        'usuario_id',
    ];

    protected $casts = [
        'cotizacion' => 'float',
        'fob_estimado' => 'float',
        'gastos_estimados' => 'float',
        'gastos_reales' => 'float',
        'landed_cost' => 'float',
        'coeficiente_embarque' => 'float',
        'exige_stock_para_cerrar' => 'boolean',
        'fecha_apertura' => 'date',
        'fecha_etd' => 'date',
        'fecha_eta' => 'date',
        'fecha_nacionalizacion' => 'date',
        'fecha_cierre_costos' => 'date',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function incoterm(): BelongsTo
    {
        return $this->belongsTo(Incoterm::class, 'incoterm_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ordenescompra(): BelongsToMany
    {
        return $this->belongsToMany(
            Ordencompra::class,
            'importacion_ordencompra',
            'importacion_id',
            'ordencompra_id'
        )->withTimestamps();
    }

    public function embarques(): HasMany
    {
        return $this->hasMany(Importacion_Embarque::class, 'importacion_id');
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(Importacion_Gasto::class, 'importacion_id');
    }

    public function lotes(): BelongsToMany
    {
        return $this->belongsToMany(
            Lote::class,
            'importacion_lote',
            'importacion_id',
            'lote_id'
        )->withTimestamps();
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(Importacion_Archivo::class, 'importacion_id');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(Importacion_Checklist_Item::class, 'importacion_id');
    }

    public function prorrateos(): HasMany
    {
        return $this->hasMany(Importacion_Prorrateo::class, 'importacion_id');
    }

    public function recepciones(): HasMany
    {
        return $this->hasMany(Recepcion_Proveedor::class, 'importacion_id');
    }

    public function getEstadoEtiquetaAttribute(): string
    {
        return ImportacionEstados::etiqueta((string) $this->estado);
    }

    public function getNombreempresaAttribute(): ?string
    {
        return $this->empresa->nombre ?? null;
    }
}

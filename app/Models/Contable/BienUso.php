<?php

namespace App\Models\Contable;

use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Traits\Contable\BienUsoTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class BienUso extends Model implements Auditable
{
    use BienUsoTrait;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'bien_uso';

    protected $fillable = [
        'empresa_id',
        'codigo_bien',
        'descripcion',
        'bien_uso_rubro_id',
        'bien_uso_subrubro_id',
        'fecha_alta',
        'fecha_compra',
        'fecha_baja',
        'fecha_baja_reemplazo',
        'vida_util',
        'valor_origen',
        'valor_baja',
        'cuentacontable_origen_id',
        'cuentacontable_amort_id',
        'cuentacontable_contrap_id',
        'moneda_id',
        'fl_amort_unid',
        'marca',
        'proveedor_id',
        'tipo_factura',
        'letra_factura',
        'sucursal_factura',
        'nro_factura',
        'monto_factura',
        'fecha_factura',
        'bien_reemplazo_id',
        'codigo_inventario',
        'uid',
        'hostname',
        'ip',
        'modelo',
        'vendor',
        'tema',
        'numero_serie',
        'estado',
        'centrocosto_id',
        'tipo_bien',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_alta' => 'date',
            'fecha_compra' => 'date',
            'fecha_baja' => 'date',
            'fecha_baja_reemplazo' => 'date',
            'fecha_factura' => 'date',
            'valor_origen' => 'decimal:2',
            'valor_baja' => 'decimal:2',
            'monto_factura' => 'decimal:2',
            'vida_util' => 'integer',
            'codigo_bien' => 'integer',
            'codigo_inventario' => 'integer',
        ];
    }

    public function centrocostos(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(BienUsoRubro::class, 'bien_uso_rubro_id');
    }

    public function subrubro(): BelongsTo
    {
        return $this->belongsTo(BienUsoSubrubro::class, 'bien_uso_subrubro_id');
    }

    public function cuentaOrigen(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_origen_id');
    }

    public function cuentaAmort(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_amort_id');
    }

    public function cuentaContrap(): BelongsTo
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_contrap_id');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function bienReemplazo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'bien_reemplazo_id');
    }

    public function amortizaciones(): HasMany
    {
        return $this->hasMany(BienUsoAmortizacion::class, 'bien_uso_id');
    }

    public function revaluos(): HasMany
    {
        return $this->hasMany(BienUsoRevaluo::class, 'bien_uso_id');
    }

    public function esMaquinaTragamonedas(): bool
    {
        return $this->tipo_bien === 'M';
    }

    public function esPc(): bool
    {
        return $this->tipo_bien === 'P';
    }
}

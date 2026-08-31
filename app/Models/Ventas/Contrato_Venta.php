<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Contrato_Venta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'contrato_venta';

    protected $fillable = [
        'codigo',
        'empresa_id',
        'cliente_id',
        'concepto_venta_id',
        'estado',
        'vigencia_desde',
        'vigencia_hasta',
        'periodicidad',
        'dia_facturacion',
        'precio',
        'moneda_id',
        'condicionventa_id',
        'observacion',
        'creousuario_id',
    ];

    protected $casts = [
        'vigencia_desde' => 'date',
        'vigencia_hasta' => 'date',
        'dia_facturacion' => 'integer',
        'precio' => 'float',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function conceptoVenta(): BelongsTo
    {
        return $this->belongsTo(Concepto_Venta::class, 'concepto_venta_id');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function condicionventa(): BelongsTo
    {
        return $this->belongsTo(Condicionventa::class, 'condicionventa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public function datos(): HasMany
    {
        return $this->hasMany(Contrato_Venta_Dato::class, 'contrato_venta_id');
    }

    public function periodos(): HasMany
    {
        return $this->hasMany(Contrato_Venta_Periodo::class, 'contrato_venta_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use App\Models\Configuracion\Impuesto;
use App\Models\Stock\Unidadmedida;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Venta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_venta';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'codigo_gtin',
        'unidades_mtx',
        'impuesto_id',
        'unidadmedida_id',
        'activo',
        'codigo_anita',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'unidades_mtx' => 'integer',
        'codigo_anita' => 'integer',
    ];

    public function cuentas(): HasMany
    {
        return $this->hasMany(Concepto_Venta_Cuentacontable::class, 'concepto_venta_id');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(Concepto_Venta_Precio::class, 'concepto_venta_id');
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class, 'impuesto_id');
    }

    public function unidadmedida(): BelongsTo
    {
        return $this->belongsTo(Unidadmedida::class, 'unidadmedida_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function textoCuentasContables(string $separador = "\n"): string
    {
        $partes = [];
        foreach ($this->cuentas as $cuenta) {
            $empresa = trim((string) ($cuenta->empresas->nombre ?? ''));
            $codigo = trim((string) ($cuenta->cuentacontables->codigo ?? ''));
            $nombre = trim((string) ($cuenta->cuentacontables->nombre ?? ''));
            $cuentaTxt = trim($codigo.($nombre !== '' ? '-'.$nombre : ''));
            $tipo = trim((string) ($cuenta->tipotransaccion->abreviatura ?? ''));
            $desde = $cuenta->vigencia_desde?->format('d/m/Y');
            $hasta = $cuenta->vigencia_hasta?->format('d/m/Y');
            $cc = trim((string) ($cuenta->centrocosto->codigo ?? ''));
            $extra = [];
            if ($tipo !== '') {
                $extra[] = $tipo;
            }
            if ($desde || $hasta) {
                $extra[] = trim(($desde ?: '…').'–'.($hasta ?: '…'));
            }
            if ($cc !== '') {
                $extra[] = 'CC '.$cc;
            }
            $linea = trim($empresa.($cuentaTxt !== '' ? ' '.$cuentaTxt : '').($extra !== [] ? ' ('.implode(' ', $extra).')' : ''));
            if ($linea !== '') {
                $partes[] = $linea;
            }
        }

        return implode($separador, $partes);
    }
}

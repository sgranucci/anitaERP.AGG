<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Impuesto;
use App\Models\Configuracion\Provincia;
use App\Models\Contable\Cuentacontable;
use App\Traits\Compras\Concepto_IvacompraTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Concepto_Ivacompra extends Model
{
    use Concepto_IvacompraTrait;

    protected $fillable = [
        'nombre',
        'codigo',
        'formula',
        'columna_ivacompra_id',
        'empresa_id',
        'cuentacontabledebe_id',
        'cuentacontablehaber_id',
        'tipoconcepto',
        'retieneganancia',
        'retieneIIBB',
        'provincia_id',
        'impuesto_id',
        'nombre_ia',
    ];

    protected $table = 'concepto_ivacompra';

    public function concepto_ivacompra_condicionivas()
    {
        return $this->hasMany(Concepto_Ivacompra_Condicioniva::class, 'concepto_ivacompra_id');
    }

    public function concepto_ivacompra_empresas()
    {
        return $this->hasMany(Concepto_Ivacompra_Empresa::class, 'concepto_ivacompra_id');
    }

    public function columna_ivacompras()
    {
        return $this->belongsTo(Columna_Ivacompra::class, 'columna_ivacompra_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontablesdebe()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontabledebe_id');
    }

    public function cuentacontableshaber()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontablehaber_id');
    }

    public function provincias()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function impuestos()
    {
        return $this->belongsTo(Impuesto::class, 'impuesto_id');
    }

    /**
     * Línea de cuentas para una empresa (o null si no hay).
     */
    public function lineaEmpresa(?int $empresaId): ?Concepto_Ivacompra_Empresa
    {
        $empresaId = (int) ($empresaId ?? 0);
        if ($empresaId <= 0) {
            return null;
        }

        /** @var Collection<int, Concepto_Ivacompra_Empresa> $lineas */
        $lineas = $this->relationLoaded('concepto_ivacompra_empresas')
            ? $this->concepto_ivacompra_empresas
            : $this->concepto_ivacompra_empresas()->get();

        return $lineas->firstWhere('empresa_id', $empresaId);
    }

    public function cuentacontableDebeIdParaEmpresa(?int $empresaId): int
    {
        $linea = $this->lineaEmpresa($empresaId);
        if ($linea !== null && (int) ($linea->cuentacontabledebe_id ?? 0) > 0) {
            return (int) $linea->cuentacontabledebe_id;
        }

        return (int) ($this->cuentacontabledebe_id ?? 0);
    }

    public function cuentacontableHaberIdParaEmpresa(?int $empresaId): int
    {
        $linea = $this->lineaEmpresa($empresaId);
        if ($linea !== null && (int) ($linea->cuentacontablehaber_id ?? 0) > 0) {
            return (int) $linea->cuentacontablehaber_id;
        }

        return (int) ($this->cuentacontablehaber_id ?? 0);
    }

    /**
     * Mapa empresa_id => cuenta debe (para meta JS).
     *
     * @return array<int, int>
     */
    public function mapaCuentaDebePorEmpresa(): array
    {
        /** @var Collection<int, Concepto_Ivacompra_Empresa> $lineas */
        $lineas = $this->relationLoaded('concepto_ivacompra_empresas')
            ? $this->concepto_ivacompra_empresas
            : $this->concepto_ivacompra_empresas()->get();

        $mapa = [];
        foreach ($lineas as $linea) {
            $empresaId = (int) ($linea->empresa_id ?? 0);
            $cuentaId = (int) ($linea->cuentacontabledebe_id ?? 0);
            if ($empresaId > 0 && $cuentaId > 0) {
                $mapa[$empresaId] = $cuentaId;
            }
        }

        $legacy = (int) ($this->cuentacontabledebe_id ?? 0);
        if ($legacy > 0 && $mapa === []) {
            $mapa[0] = $legacy;
        }

        return $mapa;
    }

    public function getDescTipoConceptoAttribute()
    {
        $nombreTipoConcepto = '';
        foreach (Concepto_Ivacompra::$enumTipoConcepto as $tipoconcepto) {
            if ($tipoconcepto['valor'] == $this->tipoconcepto) {
                $nombreTipoConcepto = $tipoconcepto['nombre'];
            }
        }

        return $nombreTipoConcepto;
    }

    public function getDescRetieneGananciaAttribute()
    {
        $nombreRetiene = '';
        foreach (Concepto_Ivacompra::$enumRetiene as $retiene) {
            if ($retiene['valor'] == $this->retieneganancia) {
                $nombreRetiene = $retiene['nombre'];
            }
        }

        return $nombreRetiene;
    }

    public function getDescRetieneiibbAttribute()
    {
        $nombreRetiene = '';
        foreach (Concepto_Ivacompra::$enumRetiene as $retiene) {
            if ($retiene['valor'] == $this->retieneIIBB) {
                $nombreRetiene = $retiene['nombre'];
            }
        }

        return $nombreRetiene;
    }
}

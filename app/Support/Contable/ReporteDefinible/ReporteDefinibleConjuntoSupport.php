<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\ReporteContable;
use App\Models\Contable\ReporteContableCcosto;
use App\Models\Contable\ReporteContableConjunto;
use App\Models\Contable\ReporteContableCuenta;
use Illuminate\Support\Collection;

/**
 * Expande sets reutilizables de cuentas sobre los rubros del informe (en memoria).
 */
class ReporteDefinibleConjuntoSupport
{
    public function expandirEnReporte(ReporteContable $reporte): void
    {
        $reporte->loadMissing(['rubros.cuentas.ccostos', 'rubros.cuentas.cuentacontable']);

        $conjuntoIds = $reporte->rubros
            ->pluck('conjunto_id')
            ->filter(fn ($id) => $id !== null && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($conjuntoIds === []) {
            return;
        }

        $conjuntos = ReporteContableConjunto::query()
            ->whereIn('id', $conjuntoIds)
            ->where('activo', true)
            ->with(['cuentas.ccostos', 'cuentas.cuentacontable'])
            ->get()
            ->keyBy('id');

        foreach ($reporte->rubros as $rubro) {
            $cid = (int) ($rubro->conjunto_id ?? 0);
            if ($cid <= 0 || ! $conjuntos->has($cid)) {
                continue;
            }
            $conjunto = $conjuntos->get($cid);
            $existentes = $rubro->cuentas
                ->map(fn ($c) => ((string) $c->origen).':'.((int) $c->codigo_cuenta))
                ->all();
            $existentes = array_flip($existentes);

            foreach ($conjunto->cuentas as $orden => $ctaConj) {
                $clave = ((string) $ctaConj->origen).':'.((int) $ctaConj->codigo_cuenta);
                if (isset($existentes[$clave])) {
                    continue;
                }
                $virtual = new ReporteContableCuenta([
                    'reporte_contable_rubro_id' => (int) $rubro->id,
                    'cuentacontable_id' => $ctaConj->cuentacontable_id,
                    'codigo_cuenta' => (int) $ctaConj->codigo_cuenta,
                    'codigo_hasta' => $ctaConj->codigo_hasta !== null ? (int) $ctaConj->codigo_hasta : null,
                    'origen' => (string) $ctaConj->origen,
                    'signo' => (int) $ctaConj->signo,
                    'carga_ccosto' => (string) $ctaConj->carga_ccosto,
                    'orden' => 10000 + (int) $orden,
                ]);
                $virtual->id = -1 * ((int) $ctaConj->id);
                $virtual->setRelation('cuentacontable', $ctaConj->cuentacontable);
                $ccostos = new Collection();
                foreach ($ctaConj->ccostos as $cc) {
                    $ccostos->push(new ReporteContableCcosto([
                        'reporte_contable_cuenta_id' => $virtual->id,
                        'ccosto_desde' => (int) $cc->ccosto_desde,
                        'ccosto_hasta' => (int) $cc->ccosto_hasta,
                        'centrocosto_id' => $cc->centrocosto_id,
                    ]));
                }
                $virtual->setRelation('ccostos', $ccostos);
                $rubro->cuentas->push($virtual);
            }
        }
    }

    /**
     * @return list<array{id: int, codigo: string, nombre: string, cuentas_count: int}>
     */
    public function listadoParaSelector(bool $soloActivos = true): array
    {
        $q = ReporteContableConjunto::query()->orderBy('codigo');
        if ($soloActivos) {
            $q->where('activo', true);
        }
        $out = [];
        foreach ($q->withCount('cuentas')->get() as $c) {
            $out[] = [
                'id' => (int) $c->id,
                'codigo' => (string) $c->codigo,
                'nombre' => (string) $c->nombre,
                'cuentas_count' => (int) $c->cuentas_count,
            ];
        }

        return $out;
    }
}

<?php

namespace App\Services\Contable;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Contable\ReporteContable;
use App\Models\Contable\ReporteContableCcosto;
use App\Models\Contable\ReporteContableCuenta;
use App\Models\Contable\ReporteContableRubro;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleAnitaBridgeReader;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleJerarquiaSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleSupport;
use Illuminate\Support\Facades\DB;

/**
 * Traduce definiciones Anita (infomae*) al modelo reporte_contable* de anitaERP.
 */
class ReporteDefinibleAnitaTraductorService
{
    public function __construct(
        private readonly ReporteDefinibleAnitaBridgeReader $bridgeReader,
    ) {
    }

    /**
     * @return array{
     *   importados: int,
     *   actualizados: int,
     *   rubros: int,
     *   cuentas: int,
     *   ccostos: int,
     *   advertencias: list<string>,
     *   errores: list<string>
     * }
     */
    public function importar(?int $informeDesde = null, ?int $informeHasta = null, bool $reemplazar = true): array
    {
        $pack = $this->bridgeReader->cargarTodo($informeDesde, $informeHasta);
        $errores = $pack['errores'];
        $advertencias = [];

        if ($pack['cabeceras'] === []) {
            return [
                'importados' => 0,
                'actualizados' => 0,
                'rubros' => 0,
                'cuentas' => 0,
                'ccostos' => 0,
                'advertencias' => $advertencias,
                'errores' => $errores !== [] ? $errores : ['Anita no devolvió cabeceras de informe (infomae).'],
            ];
        }

        $empresaPorCodigo = Empresa::query()
            ->get(['id', 'codigo'])
            ->mapWithKeys(fn ($e) => [(int) $e->codigo => (int) $e->id])
            ->all();

        $centrocostoPorCodigo = Centrocosto::query()
            ->get(['id', 'codigo'])
            ->mapWithKeys(fn ($c) => [(int) $c->codigo => (int) $c->id])
            ->all();

        $rubrosByInf = [];
        foreach ($pack['rubros'] as $row) {
            $inf = (int) ($row->infv_informe ?? 0);
            $rubrosByInf[$inf][] = [
                'rubro' => (int) ($row->infv_rubro ?? 0),
                'desc' => trim((string) ($row->infv_desc ?? '')),
                'nivel' => (int) ($row->infv_nivel ?? 1),
            ];
        }

        $cuentasByInfRubro = [];
        foreach ($pack['cuentas'] as $row) {
            $inf = (int) ($row->infc_informe ?? 0);
            $rub = (int) ($row->infc_rubro ?? 0);
            $cuentasByInfRubro[$inf][$rub][] = $row;
        }

        $ccostosByKey = [];
        foreach ($pack['ccostos'] as $row) {
            $key = sprintf(
                '%d|%d|%d|%s',
                (int) ($row->infcc_informe ?? 0),
                (int) ($row->infcc_rubro ?? 0),
                (int) ($row->infcc_cuenta ?? 0),
                ReporteDefinibleSupport::normalizarOrigen((string) ($row->infcc_real_presup ?? 'R'))
            );
            $ccostosByKey[$key][] = $row;
        }

        $importados = 0;
        $actualizados = 0;
        $totRubros = 0;
        $totCuentas = 0;
        $totCcostos = 0;

        foreach ($pack['cabeceras'] as $cab) {
            $nro = (int) ($cab->infm_informe ?? 0);
            if ($nro <= 0) {
                continue;
            }

            $stats = DB::transaction(function () use (
                $cab,
                $nro,
                $rubrosByInf,
                $cuentasByInfRubro,
                $ccostosByKey,
                $empresaPorCodigo,
                $centrocostoPorCodigo,
                $reemplazar,
                &$advertencias
            ) {
                $existente = ReporteContable::query()->where('codigo', $nro)->first();
                $esNuevo = $existente === null;

                $reporte = $existente ?? new ReporteContable(['codigo' => $nro]);
                $reporte->fill([
                    'nombre' => trim((string) ($cab->infm_desc ?? '')) ?: "Informe {$nro}",
                    'titulo1' => trim((string) ($cab->infm_titulo1 ?? '')) ?: null,
                    'titulo2' => trim((string) ($cab->infm_titulo2 ?? '')) ?: null,
                    'tipo' => ReporteDefinibleSupport::TIPO_REPORTE_OTRO,
                    'origen' => 'anita',
                    'anita_informe' => $nro,
                    'activo' => true,
                ]);
                $reporte->save();

                if ($reemplazar) {
                    // cascade borra rubros/cuentas/ccostos
                    ReporteContableRubro::query()->where('reporte_contable_id', $reporte->id)->delete();
                }

                $rubrosRaw = $rubrosByInf[$nro] ?? [];
                usort($rubrosRaw, fn ($a, $b) => $a['rubro'] <=> $b['rubro']);
                $rubrosJer = ReporteDefinibleJerarquiaSupport::enriquecerConPadre($rubrosRaw);

                /** @var array<int, int> anita_rubro => id ERP */
                $mapRubroId = [];
                $cRubros = 0;
                $cCuentas = 0;
                $cCcostos = 0;

                foreach ($rubrosJer as $rj) {
                    $nivel = (int) $rj['nivel'];
                    $parentId = null;
                    if ($rj['parent_rubro'] !== null && isset($mapRubroId[(int) $rj['parent_rubro']])) {
                        $parentId = $mapRubroId[(int) $rj['parent_rubro']];
                    }

                    $tieneCuentas = ! empty($cuentasByInfRubro[$nro][$rj['rubro']] ?? []);
                    $tipo = $tieneCuentas
                        ? ReporteDefinibleSupport::RUBRO_CUENTAS
                        : ReporteDefinibleSupport::RUBRO_TOTAL;

                    $rubro = ReporteContableRubro::query()->create([
                        'reporte_contable_id' => $reporte->id,
                        'parent_id' => $parentId,
                        'codigo_linea' => 'R'.str_pad((string) $rj['rubro'], 3, '0', STR_PAD_LEFT),
                        'nombre' => $rj['desc'] !== '' ? $rj['desc'] : 'Rubro '.$rj['rubro'],
                        'nivel' => $nivel,
                        'orden' => (int) $rj['orden'],
                        'tipo' => $tipo,
                        'estilo_negrita' => $nivel <= 1,
                        'estilo_subrayado' => false,
                        'mostrar_total' => true,
                        'anita_rubro' => (int) $rj['rubro'],
                    ]);
                    $mapRubroId[(int) $rj['rubro']] = (int) $rubro->id;
                    $cRubros++;

                    $ordenCta = 0;
                    foreach ($cuentasByInfRubro[$nro][$rj['rubro']] ?? [] as $ctaRow) {
                        $codigoCuenta = (int) ($ctaRow->infc_cuenta ?? 0);
                        if ($codigoCuenta <= 0) {
                            continue;
                        }
                        $empresaAnita = (int) ($ctaRow->infc_empresa ?? 0);
                        $empresaId = $empresaPorCodigo[$empresaAnita] ?? null;
                        if ($empresaId === null && $empresaAnita > 0) {
                            $advertencias[] = "Informe {$nro}: empresa Anita {$empresaAnita} sin mapear en ERP (cuenta {$codigoCuenta}).";
                        }

                        $cuentaId = null;
                        if ($empresaId) {
                            $cuentaId = Cuentacontable::query()
                                ->where('empresa_id', $empresaId)
                                ->where('codigo', (string) $codigoCuenta)
                                ->value('id');
                            if (! $cuentaId) {
                                // Plan compartido: buscar cualquier empresa con ese código
                                $cuentaId = Cuentacontable::query()
                                    ->where('codigo', (string) $codigoCuenta)
                                    ->value('id');
                            }
                        } else {
                            $cuentaId = Cuentacontable::query()
                                ->where('codigo', (string) $codigoCuenta)
                                ->value('id');
                        }

                        $origen = ReporteDefinibleSupport::normalizarOrigen((string) ($ctaRow->infc_real_presup ?? 'R'));
                        $carga = ReporteDefinibleSupport::normalizarCargaCcosto($ctaRow->infc_carga_ccosto ?? 'S');

                        $cta = ReporteContableCuenta::query()->create([
                            'reporte_contable_rubro_id' => $rubro->id,
                            'empresa_id' => $empresaId,
                            'cuentacontable_id' => $cuentaId ? (int) $cuentaId : null,
                            'codigo_cuenta' => $codigoCuenta,
                            'origen' => $origen,
                            'signo' => 1,
                            'carga_ccosto' => $carga,
                            'sucursal' => (int) ($ctaRow->infc_sucursal ?? 0) ?: null,
                            'orden' => $ordenCta++,
                        ]);
                        $cCuentas++;

                        $keyCc = sprintf('%d|%d|%d|%s', $nro, (int) $rj['rubro'], $codigoCuenta, $origen);
                        foreach ($ccostosByKey[$keyCc] ?? [] as $ccRow) {
                            $d = (int) ($ccRow->infcc_d_ccosto ?? 0);
                            $h = (int) ($ccRow->infcc_h_ccosto ?? 0);
                            $ccId = null;
                            if ($d > 0 && $d === $h && isset($centrocostoPorCodigo[$d])) {
                                $ccId = $centrocostoPorCodigo[$d];
                            }
                            ReporteContableCcosto::query()->create([
                                'reporte_contable_cuenta_id' => $cta->id,
                                'ccosto_desde' => $d,
                                'ccosto_hasta' => $h > 0 ? $h : $d,
                                'centrocosto_id' => $ccId,
                            ]);
                            $cCcostos++;
                        }
                    }
                }

                return [
                    'nuevo' => $esNuevo,
                    'rubros' => $cRubros,
                    'cuentas' => $cCuentas,
                    'ccostos' => $cCcostos,
                ];
            });

            if ($stats['nuevo']) {
                $importados++;
            } else {
                $actualizados++;
            }
            $totRubros += $stats['rubros'];
            $totCuentas += $stats['cuentas'];
            $totCcostos += $stats['ccostos'];
        }

        return [
            'importados' => $importados,
            'actualizados' => $actualizados,
            'rubros' => $totRubros,
            'cuentas' => $totCuentas,
            'ccostos' => $totCcostos,
            'advertencias' => array_values(array_unique($advertencias)),
            'errores' => $errores,
        ];
    }
}

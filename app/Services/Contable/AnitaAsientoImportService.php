<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Contable\Tipoasiento;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Support\Contable\Anita\AnitaAsientoImportBridgeReader;
use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;
use App\Support\Contable\AsientoAnitaMetadatosSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importa asientos Anita (ctamov + detalle subdiario/subhist) hacia ERP.
 *
 * ctamov resumen de subdiario (sistema V/C/T y ctav_asi_mon_ref ≠ -1) se excluyen;
 * el detalle entra desde subdiario/subhist con numeroasiento = nro_operacion.
 * Los resúmenes sin detalle del sistema en el mes se reportan y solo se importan con
 * $importarResumenSinDetalle: el detalle puede existir en subhist con fecha de otro mes
 * (emp2 jun/2025 se detalla en jul-nov), y ahí importar el resumen duplicaría.
 * Sistema P (PER) se importa desde ctamov: Anita lo graba directo ahí, sin subdiario/subhist.
 * Asientos generados por anitaERP (ctav_asi_mon_ref = -1) se importan desde ctamov
 * aunque tengan sistema V/C/T (no tienen subhist). Equivale a l-mayor es_asiento_resumen()
 * (VTA/COM/TES; no PER).
 *
 * Hasta {@see self::ANITA_FUENTE_VERDAD_HASTA}, Anita manda: ante diferencias se reemplazan
 * cabecera/movimientos ERP (se conservan FKs de proceso: venta_id, etc.).
 */
final class AnitaAsientoImportService
{
    /**
     * Sistemas de resumen de subdiario en ctamov (si asi_mon_ref ≠ -1).
     * No incluye P: personal se asienta directo en ctamov (sin subhist).
     */
    public const SISTEMAS_CIERRE_SUBDIARIO = ['V', 'C', 'T'];

    /** Marca Anita de asientos grabados desde anitaERP (no son resumen de subdiario). */
    public const ASI_MON_REF_ORIGEN_ERP = -1;

    /** Hasta esta fecha inclusive, Anita es fuente de verdad en colisiones. */
    public const ANITA_FUENTE_VERDAD_HASTA = '2026-08-31';

    /** Etiquetas momentáneas en observacion para distinguir origen en el mayor ERP. */
    public const TAG_SUBHIST = '[SUBH]';

    public const TAG_SUBDIARIO = '[SUBD]';

    /** Resumen V/C/T importado por no existir detalle del sistema en el mes. */
    public const TAG_RESUMEN_SIN_DETALLE = '[RESU]';

    /** @var array<string, string> sistema subdiario → abreviatura tipoasiento */
    private const MAPA_SISTEMA_TIPO = [
        'V' => 'VTA',
        'C' => 'COM',
        'T' => 'TES',
        'P' => 'PER',
        'S' => 'STK',
        'B' => 'CONT',
    ];

    public function __construct(
        private readonly AnitaAsientoImportBridgeReader $bridgeReader,
        private readonly CuentacontableRepositoryInterface $cuentacontableRepository,
        private readonly CentrocostoRepositoryInterface $centrocostoRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
    ) {}

    /**
     * @param  list<int>  $empresasAnita
     * @return array<string, mixed>
     */
    public function importarRango(
        string $desdeYmd,
        string $hastaYmd,
        array $empresasAnita = [1, 2, 3],
        int $mesesBloque = 1,
        bool $dryRun = true,
        bool $reemplazarDiferentes = false,
        int $usuarioId = 1,
        ?callable $logger = null,
        bool $importarResumenSinDetalle = false,
    ): array {
        $desde = Carbon::createFromFormat('Y-m-d', $desdeYmd)->startOfDay();
        $hasta = Carbon::createFromFormat('Y-m-d', $hastaYmd)->endOfDay();
        if ($hasta->lt($desde)) {
            throw new \InvalidArgumentException('hasta debe ser >= desde');
        }
        $mesesBloque = max(1, $mesesBloque);
        $empresasAnita = array_values(array_unique(array_filter(
            array_map('intval', $empresasAnita),
            static fn (int $e) => $e > 0,
        )));
        if ($empresasAnita === []) {
            throw new \InvalidArgumentException('Debe indicar al menos una empresa Anita');
        }

        $mapaEmpresa = $this->mapaEmpresaErp($empresasAnita);
        $mapaTipo = $this->mapaTipoasiento();
        $monedaDefaultId = (int) (DB::table('moneda')->where('codigo', '1')->value('id') ?: 1);

        $resumen = $this->resumenVacio();
        $resumen['dry_run'] = $dryRun;
        $resumen['desde'] = $desde->format('Y-m-d');
        $resumen['hasta'] = $hasta->format('Y-m-d');
        $resumen['empresas'] = $empresasAnita;

        $cursor = $desde->copy()->startOfMonth();
        while ($cursor->lte($hasta)) {
            $bloqueDesde = $cursor->copy();
            $bloqueHasta = $cursor->copy()->addMonths($mesesBloque - 1)->endOfMonth();
            if ($bloqueHasta->gt($hasta)) {
                $bloqueHasta = $hasta->copy();
            }
            if ($bloqueDesde->lt($desde)) {
                $bloqueDesde = $desde->copy();
            }

            foreach ($empresasAnita as $empAnita) {
                $empresaErpId = $mapaEmpresa[$empAnita] ?? null;
                if ($empresaErpId === null) {
                    $msg = "Empresa Anita {$empAnita} sin mapeo ERP (codigo)";
                    $resumen['errores'][] = $msg;
                    $this->log($logger, $msg);

                    continue;
                }

                $this->log(
                    $logger,
                    sprintf(
                        'Bloque %s→%s emp Anita %d (ERP %d)%s',
                        $bloqueDesde->format('Y-m-d'),
                        $bloqueHasta->format('Y-m-d'),
                        $empAnita,
                        $empresaErpId,
                        $dryRun ? ' [dry-run]' : '',
                    ),
                );

                $bloque = $this->procesarBloque(
                    $empAnita,
                    $empresaErpId,
                    (int) $bloqueDesde->format('Ymd'),
                    (int) $bloqueHasta->format('Ymd'),
                    $mapaTipo,
                    $monedaDefaultId,
                    $dryRun,
                    $reemplazarDiferentes,
                    $usuarioId,
                    $logger,
                    $importarResumenSinDetalle,
                );

                $this->mergeResumen($resumen, $bloque);
            }

            $cursor->addMonths($mesesBloque)->startOfMonth();
        }

        return $resumen;
    }

    /**
     * @param  array<string, int>  $mapaTipo
     * @return array<string, mixed>
     */
    private function procesarBloque(
        int $empresaAnita,
        int $empresaErpId,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        array $mapaTipo,
        int $monedaDefaultId,
        bool $dryRun,
        bool $reemplazarDiferentes,
        int $usuarioId,
        ?callable $logger,
        bool $importarResumenSinDetalle = false,
    ): array {
        $out = $this->resumenVacio();
        $data = $this->bridgeReader->cargarBloque($empresaAnita, $fechaDesdeYmd, $fechaHastaYmd);
        $out['errores'] = $data['errores'];
        $out['timings'][] = array_merge(
            ['empresa_anita' => $empresaAnita, 'desde' => $fechaDesdeYmd, 'hasta' => $fechaHastaYmd],
            $data['timings'],
        );
        $out['ctamov_filas_leidas'] = count($data['ctamov']);
        $out['subdiario_filas_leidas'] = count($data['subdiario']);
        $out['subhist_filas_leidas'] = count($data['subhist']);

        $this->log($logger, sprintf(
            '  leído ctamov=%d subdiario=%d subhist=%d (%.0f ms)',
            $out['ctamov_filas_leidas'],
            $out['subdiario_filas_leidas'],
            $out['subhist_filas_leidas'],
            (float) ($data['timings']['total_ms'] ?? 0),
        ));

        $planes = $this->armarPlanesDesdeBridge(
            $data,
            $empresaErpId,
            $mapaTipo,
            $monedaDefaultId,
            $usuarioId,
            $importarResumenSinDetalle,
            $out,
        );

        $numeros = array_values(array_unique(array_map(
            static fn (array $p) => (int) $p['plan']['numeroasiento'],
            $planes,
        )));
        $existentes = $this->cargarAsientosExistentesPorNumeros($empresaErpId, $numeros);

        foreach ($planes as $item) {
            $this->resolverPersistencia(
                $item['plan'],
                $existentes,
                $dryRun,
                $reemplazarDiferentes,
                $out,
                $item['origen'],
            );
        }

        return $out;
    }

    /**
     * Auditoría solo lectura: ERP vs Anita (ctamov + subdiario/subhist) por asiento.
     * Compara fecha, cuentas, montos y centros de costo. Lectura bridge por bloque (no por asiento).
     *
     * @param  list<int>  $empresasAnita
     * @return array<string, mixed>
     */
    public function auditarRango(
        string $desdeYmd,
        string $hastaYmd,
        array $empresasAnita = [1, 2, 3],
        int $mesesBloque = 1,
        bool $incluirResumenSinDetalle = false,
        float $tolerancia = 0.01,
        ?callable $logger = null,
    ): array {
        $desde = Carbon::createFromFormat('Y-m-d', $desdeYmd)->startOfDay();
        $hasta = Carbon::createFromFormat('Y-m-d', $hastaYmd)->endOfDay();
        if ($hasta->lt($desde)) {
            throw new \InvalidArgumentException('hasta debe ser >= desde');
        }
        $mesesBloque = max(1, $mesesBloque);
        $empresasAnita = array_values(array_unique(array_filter(
            array_map('intval', $empresasAnita),
            static fn (int $e) => $e > 0,
        )));
        if ($empresasAnita === []) {
            throw new \InvalidArgumentException('Debe indicar al menos una empresa Anita');
        }

        $mapaEmpresa = $this->mapaEmpresaErp($empresasAnita);
        $mapaTipo = $this->mapaTipoasiento();
        $monedaDefaultId = (int) (DB::table('moneda')->where('codigo', '1')->value('id') ?: 1);

        $resumen = [
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $hasta->format('Y-m-d'),
            'empresas' => $empresasAnita,
            'tolerancia' => $tolerancia,
            'ctamov_filas_leidas' => 0,
            'subdiario_filas_leidas' => 0,
            'subhist_filas_leidas' => 0,
            'anita_asientos' => 0,
            'erp_asientos_rango' => 0,
            'ok' => 0,
            'solo_anita' => 0,
            'solo_erp' => 0,
            'diferencias' => 0,
            'ctamov_excluidos_cierre' => 0,
            'ctamov_resumen_sin_detalle' => 0,
            'cuentas_faltantes' => [],
            'errores' => [],
            'timings' => [],
            'diferencias_detalle' => [],
        ];

        /** @var array<int, array<int, true>> empresa_erp_id => numeroasiento => true */
        $numerosAnitaPorEmpresa = [];
        /** @var array<int, array<int, array<string, mixed>>> */
        $erpPorEmpresa = [];

        $cursor = $desde->copy()->startOfMonth();
        while ($cursor->lte($hasta)) {
            $bloqueDesde = $cursor->copy();
            $bloqueHasta = $cursor->copy()->addMonths($mesesBloque - 1)->endOfMonth();
            if ($bloqueHasta->gt($hasta)) {
                $bloqueHasta = $hasta->copy();
            }
            if ($bloqueDesde->lt($desde)) {
                $bloqueDesde = $desde->copy();
            }

            foreach ($empresasAnita as $empAnita) {
                $empresaErpId = $mapaEmpresa[$empAnita] ?? null;
                if ($empresaErpId === null) {
                    $msg = "Empresa Anita {$empAnita} sin mapeo ERP (codigo)";
                    $resumen['errores'][] = $msg;
                    $this->log($logger, $msg);

                    continue;
                }

                $this->log(
                    $logger,
                    sprintf(
                        'Auditoría bloque %s→%s emp Anita %d (ERP %d)',
                        $bloqueDesde->format('Y-m-d'),
                        $bloqueHasta->format('Y-m-d'),
                        $empAnita,
                        $empresaErpId,
                    ),
                );

                $bloque = $this->procesarBloqueAuditoria(
                    $empAnita,
                    $empresaErpId,
                    (int) $bloqueDesde->format('Ymd'),
                    (int) $bloqueHasta->format('Ymd'),
                    $bloqueDesde->format('Y-m-d'),
                    $bloqueHasta->format('Y-m-d'),
                    $mapaTipo,
                    $monedaDefaultId,
                    $incluirResumenSinDetalle,
                    $tolerancia,
                    $logger,
                );

                $resumen['ctamov_filas_leidas'] += (int) ($bloque['ctamov_filas_leidas'] ?? 0);
                $resumen['subdiario_filas_leidas'] += (int) ($bloque['subdiario_filas_leidas'] ?? 0);
                $resumen['subhist_filas_leidas'] += (int) ($bloque['subhist_filas_leidas'] ?? 0);
                $resumen['anita_asientos'] += (int) ($bloque['anita_asientos'] ?? 0);
                $resumen['ok'] += (int) ($bloque['ok'] ?? 0);
                $resumen['solo_anita'] += (int) ($bloque['solo_anita'] ?? 0);
                $resumen['diferencias'] += (int) ($bloque['diferencias'] ?? 0);
                $resumen['ctamov_excluidos_cierre'] += (int) ($bloque['ctamov_excluidos_cierre'] ?? 0);
                $resumen['ctamov_resumen_sin_detalle'] += (int) ($bloque['ctamov_resumen_sin_detalle'] ?? 0);
                foreach ($bloque['cuentas_faltantes'] ?? [] as $codigo => $cant) {
                    $resumen['cuentas_faltantes'][$codigo] = ($resumen['cuentas_faltantes'][$codigo] ?? 0) + (int) $cant;
                }
                $resumen['errores'] = array_merge($resumen['errores'], $bloque['errores'] ?? []);
                $resumen['timings'] = array_merge($resumen['timings'], $bloque['timings'] ?? []);
                $resumen['diferencias_detalle'] = array_merge(
                    $resumen['diferencias_detalle'],
                    $bloque['diferencias_detalle'] ?? [],
                );

                foreach ($bloque['numeros_anita'] ?? [] as $nro) {
                    $numerosAnitaPorEmpresa[$empresaErpId][(int) $nro] = true;
                }
                foreach ($bloque['erp_en_rango'] ?? [] as $nro => $erp) {
                    $erpPorEmpresa[$empresaErpId][(int) $nro] = $erp;
                }
            }

            $cursor->addMonths($mesesBloque)->startOfMonth();
        }

        // solo_erp al cierre del rango: evita falsos positivos por fecha Anita en otro mes del mismo nro.
        foreach ($erpPorEmpresa as $empresaErpId => $erpMap) {
            $empresaAnita = 0;
            foreach ($mapaEmpresa as $codAnita => $erpId) {
                if ((int) $erpId === (int) $empresaErpId) {
                    $empresaAnita = (int) $codAnita;
                    break;
                }
            }
            $resumen['erp_asientos_rango'] += count($erpMap);
            $anitaNros = $numerosAnitaPorEmpresa[$empresaErpId] ?? [];
            foreach ($erpMap as $nro => $erp) {
                if (isset($anitaNros[$nro])) {
                    continue;
                }
                $resumen['solo_erp']++;
                $resumen['diferencias_detalle'][] = $this->filaDiferenciaAuditoria(
                    'solo_erp',
                    (int) $empresaErpId,
                    $empresaAnita,
                    'erp',
                    null,
                    $erp,
                    ['Falta en Anita (ctamov/subdiario/subhist del rango; excluye resumen V/C/T)'],
                );
            }
        }

        return $resumen;
    }

    /**
     * @param  array<string, int>  $mapaTipo
     * @return array<string, mixed>
     */
    private function procesarBloqueAuditoria(
        int $empresaAnita,
        int $empresaErpId,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        string $desdeIso,
        string $hastaIso,
        array $mapaTipo,
        int $monedaDefaultId,
        bool $incluirResumenSinDetalle,
        float $tolerancia,
        ?callable $logger,
    ): array {
        $out = $this->resumenVacio();
        $data = $this->bridgeReader->cargarBloque($empresaAnita, $fechaDesdeYmd, $fechaHastaYmd);
        $out['errores'] = $data['errores'];
        $out['timings'][] = array_merge(
            ['empresa_anita' => $empresaAnita, 'desde' => $fechaDesdeYmd, 'hasta' => $fechaHastaYmd],
            $data['timings'],
        );
        $out['ctamov_filas_leidas'] = count($data['ctamov']);
        $out['subdiario_filas_leidas'] = count($data['subdiario']);
        $out['subhist_filas_leidas'] = count($data['subhist']);

        $this->log($logger, sprintf(
            '  leído ctamov=%d subdiario=%d subhist=%d (%.0f ms)',
            $out['ctamov_filas_leidas'],
            $out['subdiario_filas_leidas'],
            $out['subhist_filas_leidas'],
            (float) ($data['timings']['total_ms'] ?? 0),
        ));

        $planes = $this->armarPlanesDesdeBridge(
            $data,
            $empresaErpId,
            $mapaTipo,
            $monedaDefaultId,
            1,
            $incluirResumenSinDetalle,
            $out,
        );

        $numerosAnita = [];
        foreach ($planes as $item) {
            $numerosAnita[(int) $item['plan']['numeroasiento']] = true;
        }

        $existentesPorNro = $this->cargarAsientosExistentesParaAuditoria(
            $empresaErpId,
            array_keys($numerosAnita),
            $desdeIso,
            $hastaIso,
        );

        $resultado = [
            'ctamov_filas_leidas' => $out['ctamov_filas_leidas'],
            'subdiario_filas_leidas' => $out['subdiario_filas_leidas'],
            'subhist_filas_leidas' => $out['subhist_filas_leidas'],
            'ctamov_excluidos_cierre' => $out['ctamov_excluidos_cierre'],
            'ctamov_resumen_sin_detalle' => $out['ctamov_resumen_sin_detalle'],
            'cuentas_faltantes' => $out['cuentas_faltantes'],
            'errores' => $out['errores'],
            'timings' => $out['timings'],
            'anita_asientos' => count($planes),
            'ok' => 0,
            'solo_anita' => 0,
            'diferencias' => 0,
            'diferencias_detalle' => [],
            'numeros_anita' => array_keys($numerosAnita),
            'erp_en_rango' => $existentesPorNro['en_rango'],
        ];

        foreach ($planes as $item) {
            $plan = $item['plan'];
            $nro = (int) $plan['numeroasiento'];
            $erp = $existentesPorNro['por_numero'][$nro] ?? null;
            if ($erp === null) {
                $resultado['solo_anita']++;
                $resultado['diferencias_detalle'][] = $this->filaDiferenciaAuditoria(
                    'solo_anita',
                    $empresaErpId,
                    $empresaAnita,
                    $item['origen'],
                    $plan,
                    null,
                    ['Falta en ERP'],
                );

                continue;
            }

            $motivos = $this->motivosDiferenciaAuditoria($erp, $plan, $tolerancia);
            if ($motivos === []) {
                $resultado['ok']++;

                continue;
            }

            $resultado['diferencias']++;
            $resultado['diferencias_detalle'][] = $this->filaDiferenciaAuditoria(
                'diferencia',
                $empresaErpId,
                $empresaAnita,
                $item['origen'],
                $plan,
                $erp,
                $motivos,
            );
        }

        $this->log($logger, sprintf(
            '  anita=%d erp_rango=%d ok=%d solo_anita=%d dif=%d',
            $resultado['anita_asientos'],
            count($existentesPorNro['en_rango']),
            $resultado['ok'],
            $resultado['solo_anita'],
            $resultado['diferencias'],
        ));

        return $resultado;
    }

    /**
     * @param  array{ctamov: list<object>, subdiario: list<object>, subhist: list<object>}  $data
     * @param  array<string, int>  $mapaTipo
     * @param  array<string, mixed>  $out
     * @return list<array{origen: string, plan: array<string, mixed>}>
     */
    private function armarPlanesDesdeBridge(
        array $data,
        int $empresaErpId,
        array $mapaTipo,
        int $monedaDefaultId,
        int $usuarioId,
        bool $importarResumenSinDetalle,
        array &$out,
    ): array {
        $detalle = array_merge($data['subdiario'], $data['subhist']);
        $sistemasConDetalle = $this->sistemasConDetallePorMes($detalle);

        $planes = [];
        $gruposCtamov = $this->agruparCtamov($data['ctamov']);
        foreach ($gruposCtamov as $lineas) {
            $primera = $lineas[0];
            $esResumenSinDetalle = false;
            if (self::esAsientoResumenSubdiario($primera)) {
                $sinDetalle = ! $this->hayDetalleParaResumen($primera, $sistemasConDetalle)
                    && ! $this->esEspejoMonedaSinCotizacion($lineas);

                if ($sinDetalle) {
                    $out['ctamov_resumen_sin_detalle']++;
                    $out['resumen_sin_detalle_detalle'][] = [
                        'numeroasiento' => (int) ($primera->ctav_nro_asiento ?? 0),
                        'empresa_id' => $empresaErpId,
                        'fecha' => $this->ymdAIso((int) ($primera->ctav_fecha ?? 0)),
                        'sistema' => strtoupper(trim((string) ($primera->ctav_sistema ?? ''))),
                        'lineas' => count($lineas),
                        'importado' => $importarResumenSinDetalle,
                    ];
                }

                if (! $sinDetalle || ! $importarResumenSinDetalle) {
                    $out['ctamov_excluidos_cierre']++;
                    $out['ctamov_excluidos_lineas'] += count($lineas);

                    continue;
                }

                $esResumenSinDetalle = true;
            }

            $asientoPlan = $this->planDesdeCtamov(
                $lineas,
                $empresaErpId,
                $mapaTipo,
                $monedaDefaultId,
                $usuarioId,
                $out,
                $esResumenSinDetalle,
            );
            if ($asientoPlan === null) {
                continue;
            }
            $planes[] = ['origen' => 'ctamov', 'plan' => $asientoPlan];
        }

        $gruposDetalle = $this->agruparSubdiario($detalle);
        foreach ($gruposDetalle as $lineas) {
            $asientoPlan = $this->planDesdeSubdiario(
                $lineas,
                $empresaErpId,
                $mapaTipo,
                $monedaDefaultId,
                $usuarioId,
                $out,
            );
            if ($asientoPlan === null) {
                continue;
            }
            $origen = ! empty($lineas[0]->subd_origen_subhist) ? 'subhist' : 'subdiario';
            $planes[] = ['origen' => $origen, 'plan' => $asientoPlan];
        }

        return $planes;
    }

    /**
     * @param  list<int>  $numerosAnita
     * @return array{
     *   por_numero: array<int, array<string, mixed>>,
     *   en_rango: array<int, array<string, mixed>>
     * }
     */
    private function cargarAsientosExistentesParaAuditoria(
        int $empresaErpId,
        array $numerosAnita,
        string $desdeIso,
        string $hastaIso,
    ): array {
        $porNumero = [];
        $enRango = [];

        $hidratar = function ($asiento) use (&$porNumero): array {
            $nro = (int) $asiento->numeroasiento;
            $movs = [];
            $suma = 0.0;
            foreach ($asiento->asiento_movimientos as $mov) {
                $monto = (float) $mov->monto;
                $suma += $monto;
                $movs[] = [
                    'cuentacontable_id' => (int) $mov->cuentacontable_id,
                    'centrocosto_id' => $mov->centrocosto_id !== null ? (int) $mov->centrocosto_id : null,
                    'monto' => $monto,
                    'cuenta_codigo' => (string) ($mov->cuentacontables->codigo ?? ''),
                    'centrocosto_codigo' => (string) ($mov->centrocostos->codigo ?? ''),
                ];
            }

            $fila = [
                'id' => (int) $asiento->id,
                'numeroasiento' => $nro,
                'fecha' => Carbon::parse($asiento->fecha)->format('Y-m-d'),
                'observacion' => (string) ($asiento->observacion ?? ''),
                'lineas' => count($movs),
                'suma_monto' => round($suma, 4),
                'movimientos' => $movs,
                'firma' => $this->firmaMovimientosAuditoria($movs),
            ];
            $porNumero[$nro] = $fila;

            return $fila;
        };

        foreach (array_chunk(array_values(array_unique(array_map('intval', $numerosAnita))), 500) as $chunk) {
            if ($chunk === []) {
                continue;
            }
            Asiento::query()
                ->where('empresa_id', $empresaErpId)
                ->whereIn('numeroasiento', $chunk)
                ->with([
                    'asiento_movimientos:id,asiento_id,cuentacontable_id,centrocosto_id,monto',
                    'asiento_movimientos.cuentacontables:id,codigo',
                    'asiento_movimientos.centrocostos:id,codigo',
                ])
                ->get(['id', 'numeroasiento', 'fecha', 'observacion'])
                ->each($hidratar);
        }

        Asiento::query()
            ->where('empresa_id', $empresaErpId)
            ->whereBetween('fecha', [$desdeIso, $hastaIso])
            ->with([
                'asiento_movimientos:id,asiento_id,cuentacontable_id,centrocosto_id,monto',
                'asiento_movimientos.cuentacontables:id,codigo',
                'asiento_movimientos.centrocostos:id,codigo',
            ])
            ->orderBy('numeroasiento')
            ->get(['id', 'numeroasiento', 'fecha', 'observacion'])
            ->each(function ($asiento) use ($hidratar, &$enRango) {
                $fila = $hidratar($asiento);
                $enRango[(int) $asiento->numeroasiento] = $fila;
            });

        return [
            'por_numero' => $porNumero,
            'en_rango' => $enRango,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     */
    private function firmaMovimientosAuditoria(array $movimientos): string
    {
        $parts = [];
        foreach ($movimientos as $mov) {
            $parts[] = sprintf(
                '%d:%d:%.4f',
                (int) ($mov['cuentacontable_id'] ?? 0),
                (int) ($mov['centrocosto_id'] ?? 0),
                round((float) ($mov['monto'] ?? 0), 4),
            );
        }
        sort($parts);

        return implode('|', $parts);
    }

    /**
     * @param  array<string, mixed>  $erp
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    private function motivosDiferenciaAuditoria(array $erp, array $plan, float $tolerancia): array
    {
        $motivos = [];
        if ((string) $erp['fecha'] !== (string) $plan['fecha']) {
            $motivos[] = sprintf('fecha ERP=%s Anita=%s', $erp['fecha'], $plan['fecha']);
        }

        $firmaErp = (string) ($erp['firma'] ?? '');
        $firmaAnita = $this->firmaMovimientosAuditoria($plan['movimientos'] ?? []);
        if ($firmaErp === $firmaAnita) {
            return $motivos;
        }

        $bagErp = $this->bolsaMovimientosAuditoria($erp['movimientos'] ?? []);
        $bagAnita = $this->bolsaMovimientosAuditoria($plan['movimientos'] ?? []);
        $claves = array_unique(array_merge(array_keys($bagErp), array_keys($bagAnita)));
        sort($claves);

        $soloErp = 0;
        $soloAnita = 0;
        $montoDiff = 0;
        $ccDiff = 0;
        $cuentaDiff = 0;
        foreach ($claves as $clave) {
            $a = $bagErp[$clave] ?? null;
            $b = $bagAnita[$clave] ?? null;
            if ($a === null) {
                $soloAnita += (int) ($b['cant'] ?? 1);

                continue;
            }
            if ($b === null) {
                $soloErp += (int) ($a['cant'] ?? 1);

                continue;
            }
            if ((int) $a['cant'] !== (int) $b['cant']) {
                $delta = abs((int) $a['cant'] - (int) $b['cant']);
                if ((int) $a['cuenta'] !== (int) $b['cuenta']) {
                    $cuentaDiff += $delta;
                } elseif ((int) $a['cc'] !== (int) $b['cc']) {
                    $ccDiff += $delta;
                } else {
                    $montoDiff += $delta;
                }
            }
        }

        // Si las firmas difieren pero las bolsas de claves no capturan bien (montos redondeados),
        // comparar sumas y conteos.
        if (abs((float) $erp['suma_monto'] - $this->sumaMontos($plan['movimientos'] ?? [])) > $tolerancia) {
            $motivos[] = sprintf(
                'suma montos ERP=%.4f Anita=%.4f',
                (float) $erp['suma_monto'],
                $this->sumaMontos($plan['movimientos'] ?? []),
            );
        }
        if ((int) $erp['lineas'] !== count($plan['movimientos'] ?? [])) {
            $motivos[] = sprintf(
                'cant. líneas ERP=%d Anita=%d',
                (int) $erp['lineas'],
                count($plan['movimientos'] ?? []),
            );
        }
        if ($soloErp > 0 || $soloAnita > 0 || $ccDiff > 0 || $cuentaDiff > 0 || $montoDiff > 0) {
            $partes = [];
            if ($cuentaDiff > 0 || $soloErp > 0 || $soloAnita > 0) {
                $partes[] = 'cuentas/líneas distintas';
            }
            if ($ccDiff > 0) {
                $partes[] = 'centro de costo distinto';
            }
            if ($montoDiff > 0) {
                $partes[] = 'montos distintos';
            }
            if ($soloErp > 0) {
                $partes[] = "solo ERP={$soloErp} líneas";
            }
            if ($soloAnita > 0) {
                $partes[] = "solo Anita={$soloAnita} líneas";
            }
            $motivos[] = 'movimientos: '.implode('; ', $partes);
        } elseif ($motivos === []) {
            $motivos[] = 'movimientos distintos (firma cuenta:cc:monto)';
        }

        return $motivos;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array<string, array{cant: int, cuenta: int, cc: int, monto: float}>
     */
    private function bolsaMovimientosAuditoria(array $movimientos): array
    {
        $bag = [];
        foreach ($movimientos as $mov) {
            $clave = sprintf(
                '%d:%d:%.4f',
                (int) ($mov['cuentacontable_id'] ?? 0),
                (int) ($mov['centrocosto_id'] ?? 0),
                round((float) ($mov['monto'] ?? 0), 4),
            );
            if (! isset($bag[$clave])) {
                $bag[$clave] = [
                    'cant' => 0,
                    'cuenta' => (int) ($mov['cuentacontable_id'] ?? 0),
                    'cc' => (int) ($mov['centrocosto_id'] ?? 0),
                    'monto' => round((float) ($mov['monto'] ?? 0), 4),
                ];
            }
            $bag[$clave]['cant']++;
        }

        return $bag;
    }

    /**
     * @param  array<string, mixed>|null  $plan
     * @param  array<string, mixed>|null  $erp
     * @param  list<string>  $motivos
     * @return array<string, mixed>
     */
    private function filaDiferenciaAuditoria(
        string $tipo,
        int $empresaErpId,
        int $empresaAnita,
        string $origen,
        ?array $plan,
        ?array $erp,
        array $motivos,
    ): array {
        $movsPlan = $plan['movimientos'] ?? [];
        $movsErp = $erp['movimientos'] ?? [];

        return [
            'tipo' => $tipo,
            'empresa_id' => $empresaErpId,
            'empresa_anita' => $empresaAnita,
            'origen_anita' => $origen,
            'numeroasiento' => (int) ($plan['numeroasiento'] ?? $erp['numeroasiento'] ?? 0),
            'erp_id' => $erp['id'] ?? null,
            'erp_fecha' => $erp['fecha'] ?? null,
            'anita_fecha' => $plan['fecha'] ?? null,
            'erp_lineas' => $erp['lineas'] ?? 0,
            'anita_lineas' => count($movsPlan),
            'erp_suma' => $erp['suma_monto'] ?? null,
            'anita_suma' => $movsPlan !== [] ? $this->sumaMontos($movsPlan) : null,
            'erp_firma' => $erp['firma'] ?? null,
            'anita_firma' => $movsPlan !== [] ? $this->firmaMovimientosAuditoria($movsPlan) : null,
            'motivos' => $motivos,
            'motivo' => implode(' | ', $motivos),
            'erp_movimientos' => $this->resumenMovimientosAuditoria($movsErp),
            'anita_movimientos' => $this->resumenMovimientosAuditoria($movsPlan),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<string>
     */
    private function resumenMovimientosAuditoria(array $movimientos): array
    {
        static $cacheCuenta = [];
        static $cacheCc = [];

        $out = [];
        foreach ($movimientos as $mov) {
            $cuenta = (string) ($mov['cuenta_codigo'] ?? '');
            if ($cuenta === '') {
                $cuentaId = (int) ($mov['cuentacontable_id'] ?? 0);
                if ($cuentaId > 0 && ! array_key_exists($cuentaId, $cacheCuenta)) {
                    $cacheCuenta[$cuentaId] = (string) (DB::table('cuentacontable')->where('id', $cuentaId)->value('codigo') ?? $cuentaId);
                }
                $cuenta = $cacheCuenta[$cuentaId] ?? (string) $cuentaId;
            }

            $cc = (string) ($mov['centrocosto_codigo'] ?? '');
            if ($cc === '') {
                $ccId = (int) ($mov['centrocosto_id'] ?? 0);
                if ($ccId > 0 && ! array_key_exists($ccId, $cacheCc)) {
                    $cacheCc[$ccId] = (string) (DB::table('centrocosto')->where('id', $ccId)->value('codigo') ?? $ccId);
                }
                $cc = $ccId > 0 ? ($cacheCc[$ccId] ?? (string) $ccId) : '0';
            }

            $out[] = sprintf('%s|cc=%s|%.4f', $cuenta, $cc !== '' ? $cc : '0', (float) ($mov['monto'] ?? 0));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $asientoPlan
     * @param  array<int, array<string, mixed>>  $existentes
     * @param  array<string, mixed>  $out
     */
    private function resolverPersistencia(
        array $asientoPlan,
        array &$existentes,
        bool $dryRun,
        bool $reemplazarDiferentes,
        array &$out,
        string $origen,
    ): void {
        $nro = (int) $asientoPlan['numeroasiento'];
        $existente = $existentes[$nro] ?? null;

        if ($existente === null) {
            $out['a_crear']++;
            $out['a_crear_por_origen'][$origen] = ($out['a_crear_por_origen'][$origen] ?? 0) + 1;
            $out['lineas_a_crear'] += count($asientoPlan['movimientos']);
            if (! $dryRun) {
                $this->persistirNuevo($asientoPlan);
                $out['creados']++;
            }
            // Reservar el número en memoria (dry-run y ejecutar) para no contar dos veces el mismo nro.
            $existentes[$nro] = [
                'id' => 0,
                'numeroasiento' => $nro,
                'fecha' => $asientoPlan['fecha'],
                'con_fk_proceso' => false,
                'lineas' => count($asientoPlan['movimientos']),
                'suma_monto' => $this->sumaMontos($asientoPlan['movimientos']),
                'anita_origen' => $asientoPlan['anita_origen'] ?? null,
                'anita_nro_asiento' => $asientoPlan['anita_nro_asiento'] ?? null,
                'importado_ahora' => true,
            ];

            return;
        }

        $anitaVerdad = $this->anitaEsFuenteVerdad((string) $asientoPlan['fecha']);
        $analisis = $this->analizarDuplicado($existente, $asientoPlan, $anitaVerdad);
        $out['duplicados']++;
        $out['duplicados_detalle'][] = [
            'numeroasiento' => $nro,
            'empresa_id' => $asientoPlan['empresa_id'],
            'origen_anita' => $origen,
            'decision' => $analisis['decision'],
            'motivo' => $analisis['motivo'],
            'anita_fuente_verdad' => $anitaVerdad,
            'erp_id' => $existente['id'],
            'erp_fecha' => $existente['fecha'],
            'anita_fecha' => $asientoPlan['fecha'],
            'erp_lineas' => $existente['lineas'],
            'anita_lineas' => count($asientoPlan['movimientos']),
            'erp_suma' => $existente['suma_monto'],
            'anita_suma' => $analisis['anita_suma'],
        ];

        if ($analisis['decision'] === 'dejar') {
            $out['duplicados_dejar']++;
            $origenExistente = trim((string) ($existente['anita_origen'] ?? ''));
            $completarMetadatos = empty($existente['con_fk_proceso']) && $origenExistente === '';
            $completarNroAsiento = (int) ($asientoPlan['anita_nro_asiento'] ?? 0) > 0
                && (int) ($existente['anita_nro_asiento'] ?? 0) <= 0
                && ($completarMetadatos || AsientoAnitaMetadatosSupport::esDetalle($origenExistente));
            if (! $dryRun && ($completarMetadatos || $completarNroAsiento)) {
                $cambios = $completarMetadatos
                    ? $this->metadatosAnitaDesdePlan($asientoPlan)
                    : ['anita_nro_asiento' => (int) $asientoPlan['anita_nro_asiento']];
                DB::table('asiento')
                    ->where('id', (int) $existente['id'])
                    ->update($cambios);
                $out['metadatos_anita_actualizados']++;
                if ($completarMetadatos) {
                    $existentes[$nro]['anita_origen'] = $asientoPlan['anita_origen'] ?? null;
                }
                if ($completarNroAsiento) {
                    $existentes[$nro]['anita_nro_asiento'] = $asientoPlan['anita_nro_asiento'];
                }
            }

            return;
        }

        // decision = reemplazar
        $out['duplicados_reemplazar']++;
        $aplicar = $anitaVerdad || $reemplazarDiferentes;
        if (! $dryRun && $aplicar) {
            $this->reemplazarExistente(
                (int) $existente['id'],
                $asientoPlan,
                empty($existente['con_fk_proceso']),
            );
            $out['reemplazados']++;
            $existentes[$nro] = [
                'id' => (int) $existente['id'],
                'numeroasiento' => $nro,
                'fecha' => $asientoPlan['fecha'],
                'con_fk_proceso' => (bool) ($existente['con_fk_proceso'] ?? false),
                'lineas' => count($asientoPlan['movimientos']),
                'suma_monto' => $this->sumaMontos($asientoPlan['movimientos']),
                'firma_movimientos' => $this->firmaMovimientos($asientoPlan['movimientos']),
                'anita_origen' => empty($existente['con_fk_proceso'])
                    ? ($asientoPlan['anita_origen'] ?? null)
                    : ($existente['anita_origen'] ?? null),
                'anita_nro_asiento' => empty($existente['con_fk_proceso'])
                    ? ($asientoPlan['anita_nro_asiento'] ?? null)
                    : ($existente['anita_nro_asiento'] ?? null),
                'importado_ahora' => true,
            ];
        }
    }

    public static function anitaEsFuenteVerdad(string $fechaIso): bool
    {
        $fecha = trim($fechaIso);
        if ($fecha === '') {
            return false;
        }

        return $fecha <= self::ANITA_FUENTE_VERDAD_HASTA;
    }

    /**
     * @param  array<string, mixed>  $existente
     * @param  array<string, mixed>  $plan
     * @return array{decision: string, motivo: string, anita_suma: float}
     */
    private function analizarDuplicado(array $existente, array $plan, bool $anitaFuenteVerdad): array
    {
        $anitaSuma = $this->sumaMontos($plan['movimientos']);
        $firmaAnita = $this->firmaMovimientos($plan['movimientos']);
        $firmaErp = (string) ($existente['firma_movimientos'] ?? '');
        $mismaFecha = (string) $existente['fecha'] === (string) $plan['fecha'];
        $mismaFirma = $firmaErp !== '' && $firmaErp === $firmaAnita;

        if ($mismaFecha && $mismaFirma) {
            return [
                'decision' => 'dejar',
                'motivo' => 'Coincide fecha y movimientos (cuentas/montos) con Anita',
                'anita_suma' => $anitaSuma,
            ];
        }

        if (! $anitaFuenteVerdad && ! empty($existente['con_fk_proceso'])) {
            return [
                'decision' => 'dejar',
                'motivo' => 'ERP tiene FK de proceso (venta/OC/recepción/etc.); no pisar',
                'anita_suma' => $anitaSuma,
            ];
        }

        $motivos = [];
        if ($anitaFuenteVerdad) {
            $motivos[] = 'Anita fuente de verdad hasta '.self::ANITA_FUENTE_VERDAD_HASTA;
        }
        if (! $mismaFecha) {
            $motivos[] = 'fecha distinta';
        }
        if (! $mismaFirma) {
            $motivos[] = 'movimientos distintos (cuentas/montos)';
        }
        if (! empty($existente['con_fk_proceso']) && $anitaFuenteVerdad) {
            $motivos[] = 'se conserva FK proceso; se alinean líneas a Anita';
        }

        return [
            'decision' => 'reemplazar',
            'motivo' => implode('; ', $motivos),
            'anita_suma' => $anitaSuma,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     */
    private function firmaMovimientos(array $movimientos): string
    {
        $parts = [];
        foreach ($movimientos as $mov) {
            $parts[] = sprintf(
                '%d:%.4f',
                (int) ($mov['cuentacontable_id'] ?? 0),
                round((float) ($mov['monto'] ?? 0), 4),
            );
        }
        sort($parts);

        return implode('|', $parts);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function persistirNuevo(array $plan): void
    {
        DB::transaction(function () use ($plan) {
            $asiento = Asiento::query()->create(array_merge([
                'empresa_id' => $plan['empresa_id'],
                'tipoasiento_id' => $plan['tipoasiento_id'],
                'numeroasiento' => $plan['numeroasiento'],
                'fecha' => $plan['fecha'],
                'observacion' => $plan['observacion'],
                'usuario_id' => $plan['usuario_id'],
                'estado_aprobacion' => Asiento::ESTADO_APROBACION_CONFIRMADO,
            ], $this->metadatosAnitaDesdePlan($plan)));

            foreach ($plan['movimientos'] as $mov) {
                Asiento_Movimiento::query()->create([
                    'asiento_id' => $asiento->id,
                    'cuentacontable_id' => $mov['cuentacontable_id'],
                    'centrocosto_id' => $mov['centrocosto_id'],
                    'monto' => $mov['monto'],
                    'moneda_id' => $mov['moneda_id'],
                    'cotizacion' => $mov['cotizacion'],
                    'observacion' => $mov['observacion'],
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function reemplazarExistente(int $asientoId, array $plan, bool $persistirMetadatosAnita): void
    {
        DB::transaction(function () use ($asientoId, $plan, $persistirMetadatosAnita) {
            // Borrado y update fila por fila: el delete/update masivo no dispara los observers y
            // dejaba cuentacontable_saldo_mes sumando dos veces el asiento reimportado.
            Asiento_Movimiento::query()
                ->where('asiento_id', $asientoId)
                ->get()
                ->each(fn (Asiento_Movimiento $movimiento) => $movimiento->delete());

            $asiento = Asiento::query()->find($asientoId);
            if ($asiento === null) {
                return;
            }
            $cabecera = [
                'tipoasiento_id' => $plan['tipoasiento_id'],
                'fecha' => $plan['fecha'],
                'observacion' => $plan['observacion'],
                'usuario_id' => $plan['usuario_id'],
                'estado_aprobacion' => Asiento::ESTADO_APROBACION_CONFIRMADO,
            ];
            if ($persistirMetadatosAnita) {
                $cabecera = array_merge($cabecera, $this->metadatosAnitaDesdePlan($plan));
            }
            $asiento->update($cabecera);

            foreach ($plan['movimientos'] as $mov) {
                Asiento_Movimiento::query()->create([
                    'asiento_id' => $asientoId,
                    'cuentacontable_id' => $mov['cuentacontable_id'],
                    'centrocosto_id' => $mov['centrocosto_id'],
                    'monto' => $mov['monto'],
                    'moneda_id' => $mov['moneda_id'],
                    'cotizacion' => $mov['cotizacion'],
                    'observacion' => $mov['observacion'],
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function metadatosAnitaDesdePlan(array $plan): array
    {
        return [
            'anita_origen' => $plan['anita_origen'] ?? null,
            'anita_nro_asiento' => $plan['anita_nro_asiento'] ?? null,
            'anita_sistema' => $plan['anita_sistema'] ?? null,
            'anita_tipo' => $plan['anita_tipo'] ?? null,
            'anita_letra' => $plan['anita_letra'] ?? null,
            'anita_sucursal' => $plan['anita_sucursal'] ?? null,
            'anita_nro' => $plan['anita_nro'] ?? null,
            'anita_emisor' => $plan['anita_emisor'] ?? null,
        ];
    }

    /**
     * @param  list<object>  $lineas
     * @param  array<string, int>  $mapaTipo
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>|null
     */
    private function planDesdeCtamov(
        array $lineas,
        int $empresaErpId,
        array $mapaTipo,
        int $monedaDefaultId,
        int $usuarioId,
        array &$out,
        bool $esResumenSinDetalle = false,
    ): ?array {
        usort($lineas, static fn ($a, $b) => ((int) ($a->ctav_nro_linea ?? 0)) <=> ((int) ($b->ctav_nro_linea ?? 0)));
        $primera = $lineas[0];
        $nro = (int) ($primera->ctav_nro_asiento ?? 0);
        if ($nro <= 0) {
            $out['omitidos_sin_numero']++;

            return null;
        }

        $tipoAbr = strtoupper(trim((string) ($primera->ctav_tipo_asiento ?? '')));
        if ($tipoAbr === '') {
            $tipoAbr = 'CONT';
        }
        $tipoId = $mapaTipo[$tipoAbr] ?? ($mapaTipo['CONT'] ?? null);
        if ($tipoId === null) {
            $out['omitidos_sin_tipo']++;
            $out['tipos_faltantes'][$tipoAbr] = ($out['tipos_faltantes'][$tipoAbr] ?? 0) + 1;

            return null;
        }

        $movimientos = [];
        foreach ($lineas as $linea) {
            $imp = AnitaSubdiarioMayorSupport::imputacionLineaCtamov($linea);
            if ($imp === null) {
                $out['lineas_sin_importe']++;

                continue;
            }

            $cuentaId = $this->resolverCuenta($empresaErpId, (int) $imp['cuenta'], $out);
            if ($cuentaId === null) {
                continue;
            }

            $movimientos[] = [
                'cuentacontable_id' => $cuentaId,
                'centrocosto_id' => $this->resolverCentrocosto((int) ($linea->ctav_ccosto ?? 0)),
                'monto' => $imp['dh'] === 'D' ? (float) $imp['importe'] : -1 * (float) $imp['importe'],
                'moneda_id' => $this->resolverMoneda((string) ($linea->ctav_cod_mon ?? '1'), $monedaDefaultId),
                'cotizacion' => (float) ($linea->ctav_cotizacion ?? 1),
                'observacion' => trim((string) ($linea->ctav_desc_mov ?? '')),
            ];
        }

        if ($movimientos === []) {
            $out['omitidos_sin_movimientos']++;

            return null;
        }

        $obs = trim(implode(' ', array_filter([
            $esResumenSinDetalle ? self::TAG_RESUMEN_SIN_DETALLE : null,
            trim((string) ($primera->ctav_sistema ?? '')),
            $esResumenSinDetalle ? $tipoAbr : null,
            trim((string) ($primera->ctav_tipo ?? '')),
            trim((string) ($primera->ctav_letra ?? '')),
            trim((string) ($primera->ctav_sucursal ?? '')),
            trim((string) ($primera->ctav_nro ?? '')),
        ])));

        return [
            'empresa_id' => $empresaErpId,
            'tipoasiento_id' => $tipoId,
            'numeroasiento' => $nro,
            'fecha' => $this->ymdAIso((int) ($primera->ctav_fecha ?? 0)),
            'observacion' => mb_substr($obs !== '' ? $obs : 'Import Anita ctamov', 0, 255),
            'anita_origen' => $esResumenSinDetalle
                ? AsientoAnitaMetadatosSupport::ORIGEN_CTAMOV_RESUMEN
                : AsientoAnitaMetadatosSupport::ORIGEN_CTAMOV,
            'anita_sistema' => strtoupper(trim((string) ($primera->ctav_sistema ?? ''))),
            'anita_tipo' => strtoupper(trim((string) ($primera->ctav_tipo ?? ''))) ?: null,
            'anita_letra' => trim((string) ($primera->ctav_letra ?? ' ')) ?: ' ',
            'anita_sucursal' => (int) ($primera->ctav_sucursal ?? 0),
            'anita_nro' => (int) ($primera->ctav_nro ?? 0) ?: null,
            'anita_emisor' => null,
            'usuario_id' => $usuarioId,
            'movimientos' => $movimientos,
        ];
    }

    /**
     * @param  list<object>  $lineas
     * @param  array<string, int>  $mapaTipo
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>|null
     */
    private function planDesdeSubdiario(
        array $lineas,
        int $empresaErpId,
        array $mapaTipo,
        int $monedaDefaultId,
        int $usuarioId,
        array &$out,
    ): ?array {
        usort($lineas, static function ($a, $b) {
            return [(int) ($a->subd_fecha ?? 0), (int) ($a->subd_nro_interno ?? 0)]
                <=> [(int) ($b->subd_fecha ?? 0), (int) ($b->subd_nro_interno ?? 0)];
        });
        $primera = $lineas[0];
        $nro = (int) ($primera->subd_nro_operacion ?? 0);
        if ($nro <= 0) {
            $out['omitidos_sin_numero']++;

            return null;
        }

        $sistema = strtoupper(trim((string) ($primera->subd_sistema ?? '')));
        $tipoAbr = self::MAPA_SISTEMA_TIPO[$sistema] ?? 'CONT';
        $tipoId = $mapaTipo[$tipoAbr] ?? ($mapaTipo['CONT'] ?? null);
        if ($tipoId === null) {
            $out['omitidos_sin_tipo']++;
            $out['tipos_faltantes'][$tipoAbr] = ($out['tipos_faltantes'][$tipoAbr] ?? 0) + 1;

            return null;
        }

        $movimientos = [];
        foreach ($lineas as $linea) {
            foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($linea) as $imp) {
                $cuentaId = $this->resolverCuenta($empresaErpId, (int) $imp['cuenta'], $out);
                if ($cuentaId === null) {
                    continue;
                }

                $ccCodigo = $imp['lado'] === 'cuenta'
                    ? (int) ($linea->subd_ccosto_cta ?? 0)
                    : (int) ($linea->subd_ccosto_con ?? 0);

                $movimientos[] = [
                    'cuentacontable_id' => $cuentaId,
                    'centrocosto_id' => $this->resolverCentrocosto($ccCodigo),
                    'monto' => $imp['dh'] === 'D' ? (float) $imp['importe'] : -1 * (float) $imp['importe'],
                    'moneda_id' => $this->resolverMoneda((string) ($linea->subd_cod_mon ?? '1'), $monedaDefaultId),
                    'cotizacion' => (float) ($linea->subd_cotizacion ?? 1),
                    'observacion' => trim((string) ($linea->subd_desc_mov ?? '')),
                ];
            }
        }

        if ($movimientos === []) {
            $out['omitidos_sin_movimientos']++;

            return null;
        }

        $tag = ! empty($primera->subd_origen_subhist) ? self::TAG_SUBHIST : self::TAG_SUBDIARIO;
        $emisor = '';
        foreach ($lineas as $linea) {
            $emisor = trim((string) ($linea->subd_emisor ?? ''));
            if ($emisor !== '') {
                break;
            }
        }
        $obs = trim(implode(' ', array_filter([
            $tag,
            $sistema,
            trim((string) ($primera->subd_tipo ?? '')),
            trim((string) ($primera->subd_letra ?? '')),
            trim((string) ($primera->subd_sucursal ?? '')),
            trim((string) ($primera->subd_nro ?? '')),
        ])));

        return [
            'empresa_id' => $empresaErpId,
            'tipoasiento_id' => $tipoId,
            'numeroasiento' => $nro,
            'fecha' => $this->ymdAIso((int) ($primera->subd_fecha ?? 0)),
            'observacion' => mb_substr($obs !== '' ? $obs : $tag.' Import Anita', 0, 255),
            'anita_origen' => ! empty($primera->subd_origen_subhist)
                ? AsientoAnitaMetadatosSupport::ORIGEN_SUBHIST
                : AsientoAnitaMetadatosSupport::ORIGEN_SUBDIARIO,
            'anita_nro_asiento' => (int) ($primera->subd_nro_asiento ?? 0) ?: null,
            'anita_sistema' => $sistema,
            'anita_tipo' => strtoupper(trim((string) ($primera->subd_tipo ?? ''))) ?: null,
            'anita_letra' => trim((string) ($primera->subd_letra ?? ' ')) ?: ' ',
            'anita_sucursal' => (int) ($primera->subd_sucursal ?? 0),
            'anita_nro' => (int) ($primera->subd_nro ?? 0) ?: null,
            'anita_emisor' => $emisor !== '' ? $emisor : null,
            'usuario_id' => $usuarioId,
            'movimientos' => $movimientos,
        ];
    }

    /**
     * Sistemas con detalle disponible por mes: 'V|202512' => true.
     *
     * @param  list<object>  $detalle  subdiario + subhist del bloque
     * @return array<string, true>
     */
    private function sistemasConDetallePorMes(array $detalle): array
    {
        $claves = [];
        foreach ($detalle as $linea) {
            $sistema = strtoupper(trim((string) ($linea->subd_sistema ?? '')));
            $fecha = (int) ($linea->subd_fecha ?? 0);
            if ($sistema === '' || $fecha <= 0) {
                continue;
            }
            $claves[$sistema.'|'.intdiv($fecha, 100)] = true;
        }

        return $claves;
    }

    /**
     * ¿El resumen tiene detalle en subdiario/subhist para su sistema y mes?
     *
     * Sin detalle el resumen puede ser la única fuente del movimiento (asiento de ventas de
     * dic/2025 de emp1) o el detalle puede estar en subhist con fecha posterior (emp2 jun/2025).
     * Distinguirlo requiere mirar el ejercicio completo, por eso la importación es opcional.
     *
     * @param  array<string, true>  $sistemasConDetalle
     */
    private function hayDetalleParaResumen(object $lineaCtamov, array $sistemasConDetalle): bool
    {
        $sistema = strtoupper(trim((string) ($lineaCtamov->ctav_sistema ?? '')));
        $fecha = (int) ($lineaCtamov->ctav_fecha ?? 0);
        if ($sistema === '' || $fecha <= 0) {
            return true;
        }

        return isset($sistemasConDetalle[$sistema.'|'.intdiv($fecha, 100)]);
    }

    /**
     * Espejo en moneda extranjera del resumen (par ctav_asi_mon_ref): mismas imputaciones en otra
     * moneda y sin cotización, por lo que no aporta al mayor en pesos y duplicaría el asiento.
     *
     * @param  list<object>  $lineas
     */
    private function esEspejoMonedaSinCotizacion(array $lineas): bool
    {
        $tienePar = false;
        foreach ($lineas as $linea) {
            $par = (int) ($linea->ctav_asi_mon_ref ?? 0);
            if ($par > 0) {
                $tienePar = true;
            }
            $moneda = trim((string) ($linea->ctav_cod_mon ?? '1'));
            $cotizacion = (float) ($linea->ctav_cotizacion ?? 0);
            if (($moneda === '' || $moneda === '1') || $cotizacion >= 0.01) {
                return false;
            }
        }

        return $tienePar && $lineas !== [];
    }

    /**
     * Resumen de cierre de subdiario en ctamov (l-mayor es_asiento_resumen):
     * sistema V/C/T y ctav_asi_mon_ref ≠ -1.
     * P/PER no es resumen: Anita lo graba en ctamov sin pasar por subdiario.
     * Si asi_mon_ref = -1 es asiento de anitaERP: no excluir (no hay subhist).
     */
    public static function esAsientoResumenSubdiario(object $lineaCtamov): bool
    {
        $sistema = strtoupper(trim((string) ($lineaCtamov->ctav_sistema ?? '')));
        if (! in_array($sistema, self::SISTEMAS_CIERRE_SUBDIARIO, true)) {
            return false;
        }

        $asiMonRef = (int) ($lineaCtamov->ctav_asi_mon_ref ?? 0);

        return $asiMonRef !== self::ASI_MON_REF_ORIGEN_ERP;
    }

    /**
     * @param  list<object>  $filas
     * @return array<int, list<object>>
     */
    private function agruparCtamov(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $nro = (int) ($fila->ctav_nro_asiento ?? 0);
            if ($nro <= 0) {
                continue;
            }
            $out[$nro][] = $fila;
        }

        return $out;
    }

    /**
     * @param  list<object>  $filas
     * @return array<int, list<object>>
     */
    private function agruparSubdiario(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $nro = (int) ($fila->subd_nro_operacion ?? 0);
            if ($nro <= 0) {
                continue;
            }
            $out[$nro][] = $fila;
        }

        return $out;
    }

    /**
     * @param  list<int>  $numeros
     * @return array<int, array<string, mixed>>
     */
    private function cargarAsientosExistentesPorNumeros(int $empresaErpId, array $numeros): array
    {
        $map = [];
        if ($numeros === []) {
            return $map;
        }

        foreach (array_chunk($numeros, 500) as $chunk) {
            $asientos = Asiento::query()
                ->where('empresa_id', $empresaErpId)
                ->whereIn('numeroasiento', $chunk)
                ->with('asiento_movimientos:id,asiento_id,cuentacontable_id,monto')
                ->get([
                    'id', 'numeroasiento', 'fecha',
                    'venta_id', 'movimientostock_id', 'cobranza_id', 'compra_id',
                    'caja_movimiento_id', 'remesa_id', 'jornada_gastronomia_id',
                    'rendicion_estacionamiento_caja_id', 'transferencia_mercaderia_id',
                    'ordencompra_id', 'recepcionproveedor_id', 'comprobante_proveedor_id',
                    'pagoproveedor_id', 'anita_origen', 'anita_nro_asiento',
                ]);

            foreach ($asientos as $asiento) {
                $nro = (int) $asiento->numeroasiento;
                $fks = [
                    $asiento->venta_id, $asiento->movimientostock_id, $asiento->cobranza_id,
                    $asiento->compra_id, $asiento->caja_movimiento_id, $asiento->remesa_id,
                    $asiento->jornada_gastronomia_id, $asiento->rendicion_estacionamiento_caja_id,
                    $asiento->transferencia_mercaderia_id, $asiento->ordencompra_id,
                    $asiento->recepcionproveedor_id, $asiento->comprobante_proveedor_id,
                    $asiento->pagoproveedor_id,
                ];
                $conFk = false;
                foreach ($fks as $fk) {
                    if ((int) $fk > 0) {
                        $conFk = true;
                        break;
                    }
                }

                $suma = 0.0;
                $movs = [];
                foreach ($asiento->asiento_movimientos as $mov) {
                    $suma += (float) $mov->monto;
                    $movs[] = [
                        'cuentacontable_id' => (int) $mov->cuentacontable_id,
                        'monto' => (float) $mov->monto,
                    ];
                }

                $map[$nro] = [
                    'id' => (int) $asiento->id,
                    'numeroasiento' => $nro,
                    'fecha' => Carbon::parse($asiento->fecha)->format('Y-m-d'),
                    'con_fk_proceso' => $conFk,
                    'anita_origen' => $asiento->anita_origen,
                    'anita_nro_asiento' => $asiento->anita_nro_asiento,
                    'lineas' => count($movs),
                    'suma_monto' => round($suma, 4),
                    'firma_movimientos' => $this->firmaMovimientos($movs),
                ];
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $out
     */
    private function resolverCuenta(int $empresaErpId, int $codigoAnita, array &$out): ?int
    {
        if ($codigoAnita <= 0) {
            return null;
        }

        static $cache = [];
        $key = $empresaErpId.'|'.$codigoAnita;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $cuenta = $this->cuentacontableRepository->findPorCodigo($empresaErpId, (string) $codigoAnita);
        if (! $cuenta) {
            // Algunos maestros guardan el código con ceros a la izquierda.
            $cuenta = $this->cuentacontableRepository->findPorCodigo(
                $empresaErpId,
                str_pad((string) $codigoAnita, 9, '0', STR_PAD_LEFT),
            );
        }

        if (! $cuenta) {
            $out['cuentas_faltantes'][$codigoAnita] = ($out['cuentas_faltantes'][$codigoAnita] ?? 0) + 1;
            $cache[$key] = null;

            return null;
        }

        return $cache[$key] = (int) $cuenta->id;
    }

    private function resolverCentrocosto(int $codigo): ?int
    {
        if ($codigo <= 0) {
            return null;
        }

        static $cache = [];
        if (array_key_exists($codigo, $cache)) {
            return $cache[$codigo];
        }

        $cc = $this->centrocostoRepository->findPorCodigo((string) $codigo);

        return $cache[$codigo] = $cc ? (int) $cc->id : null;
    }

    private function resolverMoneda(string $codigo, int $defaultId): int
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return $defaultId;
        }

        static $cache = [];
        if (array_key_exists($codigo, $cache)) {
            return $cache[$codigo];
        }

        $moneda = $this->monedaRepository->findPorCodigo($codigo);

        return $cache[$codigo] = $moneda ? (int) $moneda->id : $defaultId;
    }

    /**
     * @param  list<int>  $empresasAnita
     * @return array<int, int> anita codigo → erp id
     */
    private function mapaEmpresaErp(array $empresasAnita): array
    {
        $map = [];
        foreach ($empresasAnita as $codigo) {
            $empresa = Empresa::query()->where('codigo', $codigo)->first(['id', 'codigo']);
            if ($empresa) {
                $map[$codigo] = (int) $empresa->id;
            }
        }

        return $map;
    }

    /** @return array<string, int> */
    private function mapaTipoasiento(): array
    {
        $map = [];
        foreach (Tipoasiento::query()->get(['id', 'abreviatura']) as $tipo) {
            $abr = strtoupper(trim((string) $tipo->abreviatura));
            if ($abr !== '') {
                $map[$abr] = (int) $tipo->id;
            }
        }

        return $map;
    }

    /** @param  list<array<string, mixed>>  $movimientos */
    private function sumaMontos(array $movimientos): float
    {
        $suma = 0.0;
        foreach ($movimientos as $mov) {
            $suma += (float) ($mov['monto'] ?? 0);
        }

        return round($suma, 4);
    }

    private function ymdAIso(int $ymd): string
    {
        if ($ymd <= 0) {
            return '1970-01-01';
        }
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }

    /** @return array<string, mixed> */
    private function resumenVacio(): array
    {
        return [
            'dry_run' => true,
            'ctamov_filas_leidas' => 0,
            'subdiario_filas_leidas' => 0,
            'subhist_filas_leidas' => 0,
            'ctamov_excluidos_cierre' => 0,
            'ctamov_excluidos_lineas' => 0,
            'ctamov_resumen_sin_detalle' => 0,
            'resumen_sin_detalle_detalle' => [],
            'a_crear' => 0,
            'a_crear_por_origen' => [],
            'lineas_a_crear' => 0,
            'creados' => 0,
            'reemplazados' => 0,
            'metadatos_anita_actualizados' => 0,
            'duplicados' => 0,
            'duplicados_dejar' => 0,
            'duplicados_reemplazar' => 0,
            'duplicados_detalle' => [],
            'omitidos_sin_numero' => 0,
            'omitidos_sin_tipo' => 0,
            'omitidos_sin_movimientos' => 0,
            'lineas_sin_importe' => 0,
            'cuentas_faltantes' => [],
            'tipos_faltantes' => [],
            'errores' => [],
            'timings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $total
     * @param  array<string, mixed>  $bloque
     */
    private function mergeResumen(array &$total, array $bloque): void
    {
        foreach ([
            'ctamov_filas_leidas', 'subdiario_filas_leidas', 'subhist_filas_leidas',
            'ctamov_excluidos_cierre', 'ctamov_excluidos_lineas', 'ctamov_resumen_sin_detalle',
            'a_crear', 'lineas_a_crear', 'creados', 'reemplazados', 'metadatos_anita_actualizados',
            'duplicados', 'duplicados_dejar', 'duplicados_reemplazar',
            'omitidos_sin_numero', 'omitidos_sin_tipo', 'omitidos_sin_movimientos',
            'lineas_sin_importe',
        ] as $k) {
            $total[$k] = (int) ($total[$k] ?? 0) + (int) ($bloque[$k] ?? 0);
        }

        foreach ($bloque['a_crear_por_origen'] ?? [] as $origen => $cant) {
            $total['a_crear_por_origen'][$origen] = ($total['a_crear_por_origen'][$origen] ?? 0) + (int) $cant;
        }
        foreach ($bloque['cuentas_faltantes'] ?? [] as $codigo => $cant) {
            $total['cuentas_faltantes'][$codigo] = ($total['cuentas_faltantes'][$codigo] ?? 0) + (int) $cant;
        }
        foreach ($bloque['tipos_faltantes'] ?? [] as $abr => $cant) {
            $total['tipos_faltantes'][$abr] = ($total['tipos_faltantes'][$abr] ?? 0) + (int) $cant;
        }

        $total['duplicados_detalle'] = array_merge(
            $total['duplicados_detalle'] ?? [],
            $bloque['duplicados_detalle'] ?? [],
        );
        $total['resumen_sin_detalle_detalle'] = array_merge(
            $total['resumen_sin_detalle_detalle'] ?? [],
            $bloque['resumen_sin_detalle_detalle'] ?? [],
        );
        $total['errores'] = array_merge($total['errores'] ?? [], $bloque['errores'] ?? []);
        $total['timings'] = array_merge($total['timings'] ?? [], $bloque['timings'] ?? []);
    }

    private function log(?callable $logger, string $mensaje): void
    {
        if ($logger) {
            $logger($mensaje);
        }
        Log::info('anita_asiento_import', ['msg' => $mensaje]);
    }
}

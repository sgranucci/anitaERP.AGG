<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaComprobantePkSupport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza venta (cabecera Informix) ↔ ERP gastronomía por jornada:
 * ERP→Anita (replicar faltantes) y Anita→ERP (importar / vincular emisión).
 */
final class GastronomiaSincronizarVentasAnitaErpService
{
    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoService,
        private readonly GastronomiaReplicarVentasAnitaErpService $replicarService,
        private readonly GastronomiaFacturaImportacionAnitaService $importacionService,
    ) {
    }

    /**
     * @return array{
     *   combinaciones:int,
     *   erp_anita: array<string, mixed>,
     *   anita_erp: array<string, mixed>,
     *   errores: list<string>
     * }
     */
    public function sincronizar(
        string $fechaDesde,
        ?string $fechaHasta,
        int $empresaId,
        int $usuarioId,
        ?string $codigoPv = null,
        bool $dryRun = false,
    ): array {
        Config::set(
            'gastronomia.genera_contabilidad_al_cobrar',
            (bool) config('gastronomia_anita_import.genera_contabilidad_cobranza', false),
        );

        $fechaHasta = $fechaHasta !== null && $fechaHasta !== '' ? $fechaHasta : $fechaDesde;

        $erpAnita = $this->replicarService->replicarFaltantes(
            $fechaDesde,
            $fechaHasta,
            $empresaId,
            $codigoPv,
            $dryRun,
            0,
            false,
            null,
        );

        $combinaciones = $this->listarCombinaciones($fechaDesde, $fechaHasta, $empresaId, $codigoPv);

        $anitaErp = [
            'importados' => 0,
            'vinculados' => 0,
            'omitidos' => 0,
            'solo_anita_detectados' => 0,
            'detalle' => [],
        ];
        $errores = $erpAnita['errores'] ?? [];

        foreach ($combinaciones as $combo) {
            $pvId = (int) $combo['puntoventa_id'];
            $fechaJornada = (string) $combo['fecha_jornada'];
            $sucursal = (int) $combo['sucursal'];

            try {
                $chequeo = $this->chequeoService->chequear($pvId, $fechaJornada, 0.02, true, 0);
            } catch (\Throwable $e) {
                $errores[] = 'Chequeo PV '.($combo['codigo_pv'] ?? $pvId).' '.$fechaJornada.': '.$e->getMessage();
                continue;
            }

            foreach ($chequeo['filas'] as $fila) {
                if (($fila['estado'] ?? '') !== 'solo_anita') {
                    continue;
                }

                $this->procesarSoloAnita($fila, $combo, $empresaId, $usuarioId, $dryRun, $anitaErp, $errores);
            }

            $this->procesarCabecerasAnitaSinParErp(
                $pvId,
                $fechaJornada,
                $sucursal,
                $empresaId,
                $usuarioId,
                $dryRun,
                (string) ($combo['codigo_pv'] ?? ''),
                $anitaErp,
                $errores,
            );
        }

        return [
            'combinaciones' => count($combinaciones),
            'erp_anita' => $erpAnita,
            'anita_erp' => $anitaErp,
            'errores' => array_values(array_map(
                static fn ($e) => is_array($e) ? json_encode($e) : (string) $e,
                $errores,
            )),
        ];
    }

    /**
     * @return list<array{puntoventa_id:int,codigo_pv:string,fecha_jornada:string,sucursal:int}>
     */
    private function listarCombinaciones(
        string $fechaDesde,
        string $fechaHasta,
        int $empresaId,
        ?string $codigoPv,
    ): array {
        $map = [];

        foreach ($this->chequeoService->listarCombinacionesPvJornada($fechaDesde, $fechaHasta, $empresaId, $codigoPv) as $combo) {
            $pvId = (int) $combo['puntoventa_id'];
            $fecha = (string) $combo['fecha_jornada'];
            $map[$pvId.'|'.$fecha] = [
                'puntoventa_id' => $pvId,
                'codigo_pv' => (string) $combo['codigo_pv'],
                'fecha_jornada' => $fecha,
                'sucursal' => $this->chequeoService->sucursalDesdeCodigoPuntoventa((string) $combo['codigo_pv']),
            ];
        }

        $periodo = CarbonPeriod::create(Carbon::parse($fechaDesde), Carbon::parse($fechaHasta));
        $pvIds = $this->puntoventasGastronomiaEmpresa($empresaId, $codigoPv);

        foreach ($periodo as $dia) {
            $fecha = $dia->format('Y-m-d');
            foreach ($pvIds as $pv) {
                $pvId = (int) $pv->id;
                $clave = $pvId.'|'.$fecha;
                if (isset($map[$clave])) {
                    continue;
                }

                $cabeceras = $this->chequeoService->cabecerasAnitaMapPorPuntoventa($pvId, $fecha, []);
                if ($cabeceras === []) {
                    continue;
                }

                $map[$clave] = [
                    'puntoventa_id' => $pvId,
                    'codigo_pv' => (string) $pv->codigo,
                    'fecha_jornada' => $fecha,
                    'sucursal' => $this->chequeoService->sucursalDesdeCodigoPuntoventa((string) $pv->codigo),
                ];
            }
        }

        $lista = array_values($map);
        usort($lista, static fn (array $a, array $b): int => [$a['fecha_jornada'], $a['codigo_pv']] <=> [$b['fecha_jornada'], $b['codigo_pv']]);

        return $lista;
    }

    /**
     * @return list<Puntoventa>
     */
    private function puntoventasGastronomiaEmpresa(int $empresaId, ?string $codigoPv): array
    {
        $cfgIds = ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->get(['puntoventa_cae_id', 'puntoventa_caea_id']);

        $ids = [];
        foreach ($cfgIds as $cfg) {
            if ((int) ($cfg->puntoventa_cae_id ?? 0) > 0) {
                $ids[(int) $cfg->puntoventa_cae_id] = true;
            }
            if ((int) ($cfg->puntoventa_caea_id ?? 0) > 0) {
                $ids[(int) $cfg->puntoventa_caea_id] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $query = Puntoventa::query()
            ->whereIn('id', array_keys($ids))
            ->where('empresa_id', $empresaId)
            ->where('modofacturacion', '!=', 'M');

        if ($codigoPv !== null && trim($codigoPv) !== '') {
            $query->where('codigo', trim($codigoPv));
        }

        return $query->get()->all();
    }

    private function resolverIdentificadorPc(int $sucursal, int $empresaId, string $pvCodigo): string
    {
        $map = config('gastronomia_anita_import.identificador_pc_por_sucursal', []);
        $pc = trim((string) ($map[$sucursal] ?? ''));
        if ($pc !== '') {
            return $pc;
        }

        $pv = Puntoventa::query()
            ->where('codigo', $pvCodigo)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($pv === null) {
            throw new \InvalidArgumentException('Sin identificador_pc para sucursal '.$sucursal.'.');
        }

        $pcDb = \App\Models\Ventas\Venta::query()
            ->join('venta_gastronomia_emision', 'venta_gastronomia_emision.venta_id', '=', 'venta.id')
            ->where('venta.puntoventa_id', $pv->id)
            ->whereNotNull('venta_gastronomia_emision.identificador_pc')
            ->selectRaw('venta_gastronomia_emision.identificador_pc as pc, COUNT(*) as n')
            ->groupBy('venta_gastronomia_emision.identificador_pc')
            ->orderByDesc('n')
            ->value('pc');

        $pcDb = is_string($pcDb) ? trim($pcDb) : '';
        if ($pcDb === '') {
            throw new \InvalidArgumentException('Sin identificador_pc para sucursal '.$sucursal.' PV '.$pvCodigo.'.');
        }

        return $pcDb;
    }

    /**
     * @param  array<string, mixed>  $combo
     * @param  array<string, mixed>  $anitaErp
     * @param  list<string>  $errores
     */
    private function procesarSoloAnita(
        array $fila,
        array $combo,
        int $empresaId,
        int $usuarioId,
        bool $dryRun,
        array &$anitaErp,
        array &$errores,
    ): void {
        $anitaErp['solo_anita_detectados']++;
        $partes = GastronomiaAnitaComprobantePkSupport::parseClaveVenta((string) ($fila['clave'] ?? ''));
        if ($partes === null) {
            $errores[] = 'Clave Anita no reconocida: '.($fila['clave'] ?? '');

            return;
        }

        $this->importarNumeroAnita(
            (int) $partes['numero'],
            (int) ($combo['sucursal'] ?? 0),
            $empresaId,
            $usuarioId,
            $dryRun,
            (string) ($combo['codigo_pv'] ?? ''),
            (string) ($combo['fecha_jornada'] ?? ''),
            (string) ($fila['clave'] ?? ''),
            isset($fila['anita']['total']) ? (float) $fila['anita']['total'] : null,
            $anitaErp,
            $errores,
        );
    }

    /**
     * Cabeceras Anita sin venta gastronomía ERP (incluye excluidas por resvta legacy en chequeo).
     *
     * @param  array<string, mixed>  $anitaErp
     * @param  list<string>  $errores
     */
    private function procesarCabecerasAnitaSinParErp(
        int $puntoventaId,
        string $fechaJornada,
        int $sucursal,
        int $empresaId,
        int $usuarioId,
        bool $dryRun,
        string $codigoPv,
        array &$anitaErp,
        array &$errores,
    ): void {
        $mapAnita = $this->chequeoService->cabecerasAnitaMapPorPuntoventa($puntoventaId, $fechaJornada, []);

        $clavesErp = [];
        $ventas = \App\Models\Ventas\Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->whereDate('fechajornada', $fechaJornada)
            ->whereHas('gastronomiaEmision')
            ->get(['codigo', 'numerocomprobante']);

        foreach ($ventas as $venta) {
            $clave = $this->chequeoService->claveComprobanteDesdeVentaErp($venta);
            if ($clave !== null) {
                $clavesErp[$clave] = true;
            }
        }

        $procesados = [];
        foreach ($anitaErp['detalle'] as $d) {
            $procesados[(string) ($d['pv'] ?? '').'|'.(int) ($d['numero'] ?? 0)] = true;
        }

        foreach ($mapAnita as $clave => $cab) {
            if (isset($clavesErp[$clave])) {
                continue;
            }

            $partes = GastronomiaAnitaComprobantePkSupport::parseClaveVenta($clave);
            if ($partes === null) {
                continue;
            }

            $nro = (int) $partes['numero'];
            $marcador = $codigoPv.'|'.$nro;
            if (isset($procesados[$marcador])) {
                continue;
            }

            $anitaErp['solo_anita_detectados']++;
            $this->importarNumeroAnita(
                $nro,
                $sucursal,
                $empresaId,
                $usuarioId,
                $dryRun,
                $codigoPv,
                $fechaJornada,
                $clave,
                round((float) ($cab->ven_monto ?? 0), 2),
                $anitaErp,
                $errores,
            );
            $procesados[$marcador] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $anitaErp
     * @param  list<string>  $errores
     */
    private function importarNumeroAnita(
        int $nro,
        int $sucursal,
        int $empresaId,
        int $usuarioId,
        bool $dryRun,
        string $codigoPv,
        string $fechaJornada,
        string $clave,
        ?float $totalAnita,
        array &$anitaErp,
        array &$errores,
    ): void {
        $tipoAnita = GastronomiaAnitaComprobantePkSupport::parseClaveVenta($clave)['tipo'] ?? 'FAC';

        try {
            $pc = $this->resolverIdentificadorPc($sucursal, $empresaId, $codigoPv);
            $estado = $this->importacionService->importarNumero(
                $sucursal,
                $nro,
                $empresaId,
                $usuarioId,
                $dryRun,
                $pc,
                $tipoAnita,
            );
        } catch (\Throwable $e) {
            $errores[] = $codigoPv.' FAC '.$nro.' '.$fechaJornada.': '.$e->getMessage();
            Log::warning('gastronomia.sincronizar.anita_erp.fallo', [
                'pv' => $codigoPv,
                'nro' => $nro,
                'fecha' => $fechaJornada,
                'msg' => $e->getMessage(),
            ]);

            return;
        }

        if ($estado === 'importado') {
            $anitaErp['importados']++;
        } elseif ($estado === 'vinculado') {
            $anitaErp['vinculados']++;
        } else {
            $anitaErp['omitidos']++;
        }

        $anitaErp['detalle'][] = [
            'estado' => $estado,
            'pv' => $codigoPv,
            'numero' => $nro,
            'fecha_jornada' => $fechaJornada,
            'clave' => $clave,
            'total_anita' => $totalAnita,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\ApiAnita;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Services\Ventas\Gastronomia\GastronomiaChequeoVentasAnitaErpService;
use App\Support\Caja\AnitaSync\RendicionAnitaFechaAlfaSupport;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Caja\AnitaSync\RendicionGastronomiaCabeceraAnitaMapper;
use RuntimeException;

/**
 * Facturas PV CAEA Rebisco migradas de post-cierre 10/11/06 → jornada 23/06/2026.
 * Van en rendgastro aparte (host 00030-AGREGADOS), no en CIERRE-WAITRY del día.
 */
final class GastronomiaConciliacionRebiscoAgregadosCaeaSupport
{
    public const EMPRESA_ID = 3;

    public const FECHA_JORNADA = '2026-06-23';

    public const FECHA_ENTERA = 20260623;

    public const PV_CAEA = '00030';

    public const HOST_RENDG = CierreJornadaProcesoRendicionAnitaSupport::HOST_AGREGADOS_CAEA;

    public const NRO_OPER_RENDG = 765884;

    public const NRO_OPER_POST_CIERRE = 300042;

    /** @var list<int> */
    public const VENTA_IDS = [11728, 11729, 11730, 11748, 11749, 11750, 11751];

    public const IMPORTE_ESPERADO = 484700.0;

    public const IMPORTE_POST_CIERRE_ESPERADO = 289800.0;

    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoVentasService,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
        private readonly GastronomiaConciliacionExclusionEmisionSupport $exclusionEmisionSupport,
    ) {
    }

    public function aplica(int $empresaId, string $fechaJornada): bool
    {
        return $empresaId === self::EMPRESA_ID && $fechaJornada === self::FECHA_JORNADA;
    }

    /**
     * @return array{
     *   ventas_erp: float,
     *   ventas_anita: float,
     *   rendgastro_z: float|null,
     *   rendgastro_nro_oper: int|null,
     *   cantidad_facturas: int,
     *   venta_ids: list<int>
     * }
     */
    public function totalesDia(int $empresaId, string $fechaJornada, ?array $indiceAnitaBulk = null): array
    {
        if (! $this->aplica($empresaId, $fechaJornada)) {
            return $this->vacios();
        }

        $ventaIds = self::VENTA_IDS;
        $erpTotal = round((float) Venta::query()->whereIn('id', $ventaIds)->sum('total'), 2);

        $cacheCabeceras = [];
        $pvCaeaId = (int) (Puntoventa::query()->where('codigo', self::PV_CAEA)->value('id') ?? 0);
        $clavesExcluir = $pvCaeaId > 0
            ? $this->exclusionEmisionSupport->clavesExcluirListaParaPuntoventa(
                $empresaId,
                $fechaJornada,
                $pvCaeaId,
                $indiceAnitaBulk,
            )
            : [];
        $anitaTotal = $this->chequeoVentasService->totalFacturacionBrutaAnitaParaVentasIds(
            $ventaIds,
            $fechaJornada,
            $cacheCabeceras,
            $clavesExcluir,
            $indiceAnitaBulk,
        );

        $rendg = $this->importeRendgAgregados($empresaId);

        return [
            'ventas_erp' => $erpTotal,
            'ventas_anita' => round($anitaTotal, 2),
            'rendgastro_z' => $rendg['total'],
            'rendgastro_nro_oper' => $rendg['nro_oper'],
            'cantidad_facturas' => count($ventaIds),
            'venta_ids' => $ventaIds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filaReporte(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia,
        ?array $indiceAnitaBulk = null,
    ): array {
        $totales = $this->totalesDia($empresaId, $fechaJornada, $indiceAnitaBulk);
        $diffErpAnita = round($totales['ventas_erp'] - $totales['ventas_anita'], 2);
        $rendgZ = $totales['rendgastro_z'];
        $diffErpRendg = $rendgZ !== null
            ? round($totales['ventas_erp'] - $rendgZ, 2)
            : null;

        return GastronomiaConciliacionEstadoSupport::aplicarEstadosEnFila([
            'identificador_pc' => self::HOST_RENDG,
            'tipo_fila' => 'caea_agregados_migrados',
            'tipo_pv' => 'CAEA_AGREG',
            'pv_codigo' => self::PV_CAEA,
            'descripcion_pc' => 'PV '.self::PV_CAEA.' agregados migrados (10–11/06 → 23/06)',
            'pv_cae' => '—',
            'pv_caea' => self::PV_CAEA,
            'ventas_erp_cae' => 0.0,
            'ventas_erp_caea' => $totales['ventas_erp'],
            'ventas_erp' => $totales['ventas_erp'],
            'ventas_anita_cae' => 0.0,
            'ventas_anita_caea' => $totales['ventas_anita'],
            'ventas_anita' => $totales['ventas_anita'],
            'rendgastro_z' => $rendgZ,
            'rendgastro_z_cae' => null,
            'rendgastro_caea' => $rendgZ,
            'rendgastro_nro_oper' => $totales['rendgastro_nro_oper'],
            'diff_erp_anita' => $diffErpAnita,
            'diff_erp_rendg' => $diffErpRendg,
            'cantidad_facturas_erp' => $totales['cantidad_facturas'],
            'es_caea_agregados_migrados' => true,
            'jornada_abierta' => false,
        ], $tolerancia);
    }

    /**
     * Separa rendgastro: CIERRE-WAITRY = post-cierre real; host agregados = fc migradas.
     *
     * @return array<string, mixed>
     */
    public function sincronizarRendgastroAnita(): array
    {
        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', self::EMPRESA_ID)
            ->whereDate('fecha_jornada', self::FECHA_JORNADA)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->actualizarCabecera(
            self::NRO_OPER_POST_CIERRE,
            CierreJornadaProcesoRendicionAnitaSupport::HOST,
            self::IMPORTE_POST_CIERRE_ESPERADO,
            (int) $jornada->id,
        );

        $this->actualizarCabecera(
            self::NRO_OPER_RENDG,
            self::HOST_RENDG,
            self::IMPORTE_ESPERADO,
            (int) $jornada->id,
        );

        return [
            'post_cierre_nro_oper' => self::NRO_OPER_POST_CIERRE,
            'post_cierre_z' => self::IMPORTE_POST_CIERRE_ESPERADO,
            'agregados_nro_oper' => self::NRO_OPER_RENDG,
            'agregados_host' => self::HOST_RENDG,
            'agregados_z' => self::IMPORTE_ESPERADO,
        ];
    }

    private function actualizarCabecera(int $nroOper, string $host, float $importe, int $jornadaId): void
    {
        $tipoOper = (string) config('rendicion_gastronomia_anita.tipo_oper', 'F');
        $alfa = RendicionAnitaFechaAlfaSupport::desdeFechaEntera(self::FECHA_ENTERA);
        $sucCaea = 30;
        $valores = "rendg_host = '".RendicionGastronomiaCabeceraAnitaMapper::texto($host, 15)."'"
            .', rendg_total_z = '.RendicionGastronomiaCabeceraAnitaMapper::decimal($importe)
            .', rendg_total_x = '.RendicionGastronomiaCabeceraAnitaMapper::decimal($importe)
            .', rendg_tot_nc = 0'
            .', rendg_tot_fc_caea = '.RendicionGastronomiaCabeceraAnitaMapper::decimal($host === CierreJornadaProcesoRendicionAnitaSupport::HOST ? $importe : 0)
            .', rendg_tot_nc_caea = 0'
            .', rendg_fecha = '.self::FECHA_ENTERA
            .", rendg_fecha_alfa = '".RendicionGastronomiaCabeceraAnitaMapper::texto($alfa, 10)."'"
            .", rendg_turno = '".CierreJornadaProcesoRendicionAnitaSupport::TURNO_LETRA."'"
            .', rendg_nro_rend_vta = '.$jornadaId
            .', rendg_suc_caea = '.$sucCaea;

        $api = new ApiAnita();
        $resp = $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => (string) config('rendicion_gastronomia_anita.tabla_cabecera', 'rendgastro'),
            'sistema' => (string) config('rendicion_gastronomia_anita.sistema', 'caja'),
            'valores' => $valores,
            'whereArmado' => RendicionGastronomiaCabeceraAnitaMapper::whereClave($nroOper, $tipoOper),
        ], 'rebisco rendg agregados/post-cierre');

        if (! ApiAnita::respuestaBridgeEscrituraExitosa($resp)) {
            throw new RuntimeException(
                'No se pudo actualizar rendgastro #'.$nroOper.': '.(ApiAnita::extraerMensajeError($resp) ?? $resp),
            );
        }
    }

    /**
     * @return array{total: float|null, nro_oper: int|null}
     */
    private function importeRendgAgregados(int $empresaId): array
    {
        $cab = $this->rendgastroSupport->listarCabeceraPorNroOper($empresaId, self::NRO_OPER_RENDG);
        if ($cab === null) {
            return ['total' => null, 'nro_oper' => null];
        }

        $host = trim((string) ($cab->rendg_host ?? ''));
        if ($host !== self::HOST_RENDG) {
            return ['total' => null, 'nro_oper' => self::NRO_OPER_RENDG];
        }

        $z = round((float) ($cab->rendg_total_z ?? 0), 2);

        return [
            'total' => $z > 0.02 ? $z : null,
            'nro_oper' => self::NRO_OPER_RENDG,
        ];
    }

    /**
     * @return array{ventas_erp: float, ventas_anita: float, rendgastro_z: null, rendgastro_nro_oper: null, cantidad_facturas: int, venta_ids: list<int>}
     */
    private function vacios(): array
    {
        return [
            'ventas_erp' => 0.0,
            'ventas_anita' => 0.0,
            'rendgastro_z' => null,
            'rendgastro_nro_oper' => null,
            'cantidad_facturas' => 0,
            'venta_ids' => [],
        ];
    }
}

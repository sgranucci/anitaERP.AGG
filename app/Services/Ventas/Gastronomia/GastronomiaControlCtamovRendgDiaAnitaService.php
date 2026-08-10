<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Support\Database\SqlDialectSupport;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Venta;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaMesCacheSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionVendingRendgSupport;
use App\Support\Ventas\Gastronomia\GastronomiaControlCtamovRendgDiaAnitaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaControlFlashSupport;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Cuadre jornada: contabilidad ctamov vs rendiciones (Z−NC) vs venta Informix vs venta ERP vs flash (caja).
 */
final class GastronomiaControlCtamovRendgDiaAnitaService
{
    public function __construct(
        private readonly GastronomiaAnitaMesCacheSupport $cacheSupport,
        private readonly GastronomiaControlCtamovRendgDiaAnitaSupport $controlSupport,
        private readonly GastronomiaConciliacionVendingRendgSupport $vendingRendgSupport,
        private readonly GastronomiaControlFlashSupport $flashSupport,
    ) {
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    public function generar(
        array $empresaIds,
        string $fechaDesde,
        string $fechaHasta,
        float $tolerancia = 0.02,
        bool $forzarDescarga = true,
        bool $soloReporte = false,
    ): array {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            throw new \InvalidArgumentException('fecha-desde no puede ser posterior a fecha-hasta.');
        }

        $empresas = [];
        $flashCodigos = [];
        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $flashCodigos[] = (int) (Empresa::query()->findOrFail($empresaId)->codigo ?? $empresaId);
        }

        $flashPorEmpresaJornada = $this->flashSupport->totalesPorEmpresaJornada(
            $desde,
            $hasta,
            $flashCodigos,
        );

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $empresa = Empresa::query()->findOrFail($empresaId);
            $empresaCodigo = (int) ($empresa->codigo ?? $empresaId);

            if ($soloReporte) {
                $cache = $this->cacheSupport->cargarCtamovRendg($empresaId, $desde, $hasta);
                $manifest = $cache['manifest'];
            } else {
                $manifest = $this->cacheSupport->descargarCtamovRendg($empresaId, $desde, $hasta, $forzarDescarga);
                $cache = $this->cacheSupport->cargarCtamovRendg($empresaId, $desde, $hasta);
            }

            $empresas[] = $this->armarEmpresa(
                $empresaId,
                $desde,
                $hasta,
                $tolerancia,
                $cache,
                $manifest,
                $flashPorEmpresaJornada[$empresaCodigo] ?? [],
            );
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tolerancia' => $tolerancia,
            'empresas' => $empresas,
            'hay_diferencias' => $this->hayDiferencias($empresas),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $empresas
     */
    public function hayDiferencias(array $empresas): bool
    {
        foreach ($empresas as $empresa) {
            foreach ($empresa['filas'] ?? [] as $fila) {
                if (($fila['estado'] ?? '') === 'DIF') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function guardarCsv(string $ruta, array $informe): void
    {
        if (file_put_contents($ruta, $this->construirContenidoCsv($informe)) === false) {
            throw new \RuntimeException('No se pudo escribir CSV: '.$ruta);
        }
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function construirContenidoCsv(array $informe): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear buffer CSV.');
        }

        fputcsv($handle, [
            'empresa_id', 'empresa_nombre', 'fecha_jornada',
            'total_contabilidad', 'total_rendiciones_neto', 'rendg_z', 'rendg_nc',
            'venta_anita_tabla', 'venta_anita_vending_rmv', 'vending_pv_detalle', 'vending_rendg_informativo',
            'venta_erp_tabla', 'venta_erp_vending',
            'total_venta_anita', 'total_venta_erp', 'total_flash',
            'dif_cont_rend', 'dif_cont_anita', 'dif_cont_erp', 'dif_cont_flash',
            'dif_rend_anita', 'dif_rend_erp', 'dif_rend_flash',
            'dif_anita_flash', 'dif_erp_flash',
            'estado', 'cabeceras_rendg', 'cabeceras_venta_anita', 'cuentas_ctamov',
        ], ';');

        foreach ($informe['empresas'] ?? [] as $empresa) {
            foreach ($empresa['filas'] ?? [] as $fila) {
                fputcsv($handle, [
                    $empresa['empresa_id'],
                    $empresa['empresa_nombre'],
                    $fila['fecha_jornada'],
                    $fila['total_contabilidad'],
                    $fila['total_rendiciones_neto'],
                    $fila['rendg_z'] ?? '',
                    $fila['rendg_nc'] ?? '',
                    $fila['venta_anita_tabla'] ?? '',
                    $fila['venta_anita_vending_rmv'] ?? '',
                    $fila['vending_pv_detalle'] ?? '',
                    $fila['vending_rendg_informativo'] ?? '',
                    $fila['venta_erp_tabla'] ?? '',
                    $fila['venta_erp_vending'] ?? '',
                    $fila['total_venta_anita'],
                    $fila['total_venta_erp'],
                    $fila['total_flash'] ?? '',
                    $fila['dif_cont_rend'] ?? '',
                    $fila['dif_cont_anita'] ?? '',
                    $fila['dif_cont_erp'] ?? '',
                    $fila['dif_cont_flash'] ?? '',
                    $fila['dif_rend_anita'] ?? '',
                    $fila['dif_rend_erp'] ?? '',
                    $fila['dif_rend_flash'] ?? '',
                    $fila['dif_anita_flash'] ?? '',
                    $fila['dif_erp_flash'] ?? '',
                    $fila['estado'],
                    $fila['cabeceras_rendg'] ?? '',
                    $fila['cabeceras_venta_anita'] ?? '',
                    implode(',', $empresa['codigos_ctamov'] ?? []),
                ], ';');
            }
        }

        rewind($handle);
        $contenido = stream_get_contents($handle);
        fclose($handle);

        return $contenido !== false ? $contenido : '';
    }

    public function nombreArchivoCsv(array $informe): string
    {
        $desde = str_replace('-', '', (string) ($informe['fecha_desde'] ?? ''));
        $hasta = str_replace('-', '', (string) ($informe['fecha_hasta'] ?? $desde));

        return 'cuadre_jornada_'.$desde.($hasta !== $desde ? '_'.$hasta : '').'.csv';
    }

    public function rutaCsvDefecto(array $informe): string
    {
        $dir = trim((string) config('gastronomia.control_ctamov_rendg_dia_anita.directorio_reportes', 'reportes/gastronomia/cuadre_jornada'));
        if ($dir === '') {
            $dir = 'reportes/gastronomia/cuadre_jornada';
        }

        $base = str_starts_with($dir, '/') ? $dir : storage_path('app/'.$dir);
        if (! is_dir($base)) {
            mkdir($base, 0755, true);
        }

        return $base.'/'.$this->nombreArchivoCsv($informe);
    }

    /**
     * @param  array<string, mixed>  $informe
     * @return array{enviado: bool, destino?: string, error?: string}
     */
    public function enviarCorreo(array $informe, ?string $destinoOverride = null): array
    {
        $destino = trim((string) ($destinoOverride ?? config('gastronomia.control_ctamov_rendg_dia_anita.email', '')));
        if ($destino === '') {
            $destino = trim((string) config('gastronomia.auditoria_anita_diaria.email', ''));
        }
        if ($destino === '') {
            return ['enviado' => false, 'error' => 'Sin destino de correo configurado'];
        }

        $csv = $this->construirContenidoCsv($informe);
        $nombre = $this->nombreArchivoCsv($informe);

        try {
            \Illuminate\Support\Facades\Mail::to($destino)->send(
                new \App\Mail\Ventas\GastronomiaControlCuadreJornadaAnita($informe, $csv, $nombre),
            );
            \Illuminate\Support\Facades\Log::info('gastronomia.control_cuadre_jornada.mail_ok', [
                'destino' => $destino,
                'fecha_desde' => $informe['fecha_desde'] ?? null,
                'fecha_hasta' => $informe['fecha_hasta'] ?? null,
                'hay_diferencias' => $informe['hay_diferencias'] ?? false,
            ]);

            return ['enviado' => true, 'destino' => $destino];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('gastronomia.control_cuadre_jornada.mail_fallo', [
                'destino' => $destino,
                'msg' => $e->getMessage(),
            ]);

            return ['enviado' => false, 'destino' => $destino, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $cache
     * @param  array<string, mixed>  $manifest
     * @param  array<string, float>  $flashPorJornada
     * @return array<string, mixed>
     */
    private function armarEmpresa(
        int $empresaId,
        string $desde,
        string $hasta,
        float $tolerancia,
        array $cache,
        array $manifest,
        array $flashPorJornada,
    ): array {
        $empresa = Empresa::query()->findOrFail($empresaId);
        $empresaCodigo = trim((string) ($empresa->codigo ?? $empresaId));
        $codigosCtamov = $this->controlSupport->codigosCtamovEmpresa($empresaId);
        $ctamovPorFecha = $this->controlSupport->indexarCtamovPorFecha($cache['ctamov'] ?? []);
        $rendgPorFecha = $cache['rendgastro'] ?? [];
        $ventaPorJornada = $this->controlSupport->indexarVentaAnitaPorJornada($cache['venta'] ?? [], $empresaCodigo);
        $ventaErpPorJornada = $this->totalesVentaErpPorJornada($empresaId, $desde, $hasta);
        $vendingErpPorJornada = $this->vendingRendgSupport->totalesMaquinavendingErpPorJornada($empresaId, $desde, $hasta);

        $filas = [];
        foreach (CarbonPeriod::create($desde, $hasta) as $dia) {
            $fecha = $dia->toDateString();
            if ($this->esJornadaPreMigracion($empresaId, $fecha)) {
                continue;
            }

            $cabecerasRendg = $rendgPorFecha[$fecha] ?? [];
            $filasCtamov = $ctamovPorFecha[$fecha] ?? [];
            $cabecerasVentaAnita = $this->controlSupport->cabecerasVentaAnitaDia($ventaPorJornada[$fecha] ?? []);

            $totalContabilidad = $this->controlSupport->totalCtamovVentasIva($filasCtamov, $codigosCtamov);
            $rendgZ = $this->controlSupport->totalRendgBrutoZ($cabecerasRendg);
            $rendgNc = $this->controlSupport->totalRendgNotasCredito($cabecerasRendg);
            $totalRendiciones = $this->controlSupport->totalRendgNetoDia($cabecerasRendg);
            $ventaAnitaTabla = $this->controlSupport->totalVentaAnitaNeto($cabecerasVentaAnita);
            $ventaErpVending = round((float) ($vendingErpPorJornada[$fecha] ?? 0), 2);
            $vendingAnita = $this->vendingRendgSupport->ventaAnitaVendingDesdeRendg($empresaId, $cabecerasRendg);
            $totalVentaAnitaVending = round((float) ($vendingAnita['total'] ?? 0), 2);
            $totalVentaAnita = round($ventaAnitaTabla + $totalVentaAnitaVending, 2);

            // ERP: tabla venta MySQL + maquinavending_rendicion (análogo a venta Anita + RMV vending).
            $ventaErpTabla = round((float) ($ventaErpPorJornada[$fecha] ?? 0), 2);
            $totalVentaErp = round($ventaErpTabla + $ventaErpVending, 2);
            $totalFlash = round((float) ($flashPorJornada[$fecha] ?? 0), 2);

            $sinActividad = abs($totalContabilidad) <= $tolerancia
                && abs($totalRendiciones) <= $tolerancia
                && abs($totalVentaAnita) <= $tolerancia
                && abs($totalVentaErp) <= $tolerancia
                && abs($totalFlash) <= $tolerancia;

            $totales = [$totalContabilidad, $totalRendiciones, $totalVentaAnita, $totalVentaErp, $totalFlash];
            $estado = $sinActividad
                ? '—'
                : ($this->controlSupport->cuadranTodos($totales, $tolerancia) ? 'OK' : 'DIF');

            $filas[] = [
                'fecha_jornada' => $fecha,
                'total_contabilidad' => $totalContabilidad,
                'total_rendiciones_neto' => $totalRendiciones,
                'rendg_z' => $rendgZ,
                'rendg_nc' => $rendgNc,
                'venta_anita_tabla' => $ventaAnitaTabla,
                'venta_anita_vending_rmv' => $totalVentaAnitaVending,
                'vending_pv_detalle' => $this->formatearVendingPvDetalle($vendingAnita['por_pv'] ?? []),
                'vending_rendg_informativo' => '',
                'venta_erp_tabla' => $ventaErpTabla,
                'venta_erp_vending' => $ventaErpVending,
                'total_venta_anita' => $totalVentaAnita,
                'total_venta_erp' => $totalVentaErp,
                'total_flash' => $totalFlash,
                'dif_cont_rend' => round($totalContabilidad - $totalRendiciones, 2),
                'dif_cont_anita' => round($totalContabilidad - $totalVentaAnita, 2),
                'dif_cont_erp' => round($totalContabilidad - $totalVentaErp, 2),
                'dif_cont_flash' => round($totalContabilidad - $totalFlash, 2),
                'dif_rend_anita' => round($totalRendiciones - $totalVentaAnita, 2),
                'dif_rend_erp' => round($totalRendiciones - $totalVentaErp, 2),
                'dif_rend_flash' => round($totalRendiciones - $totalFlash, 2),
                'dif_anita_flash' => round($totalVentaAnita - $totalFlash, 2),
                'dif_erp_flash' => round($totalVentaErp - $totalFlash, 2),
                'cabeceras_rendg' => count($cabecerasRendg),
                'cabeceras_venta_anita' => count($cabecerasVentaAnita),
                'estado' => $estado,
            ];
        }

        return [
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'codigos_ctamov' => $codigosCtamov,
            'cache' => $manifest,
            'filas' => $filas,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function totalesVentaErpPorJornada(int $empresaId, string $desde, string $hasta): array
    {
        $netoSql = GastronomiaVentaComprobanteSignoSupport::sqlTotalComprobante('venta.total', 'tipotransaccion.signo');

        $rows = Venta::query()
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->where(function ($fecha) use ($desde, $hasta) {
                $fecha->where(function ($conJornada) use ($desde, $hasta) {
                    $conJornada->whereNotNull('venta.fechajornada')
                        ->whereDate('venta.fechajornada', '>=', $desde)
                        ->whereDate('venta.fechajornada', '<=', $hasta);
                })->orWhere(function ($legacy) use ($desde, $hasta) {
                    $legacy->whereNull('venta.fechajornada')
                        ->whereDate('venta.fecha', '>=', $desde)
                        ->whereDate('venta.fecha', '<=', $hasta);
                });
            })
            ->selectRaw(SqlDialectSupport::fecha('COALESCE(venta.fechajornada, venta.fecha)').' as fecha_jornada')
            ->selectRaw('SUM('.$netoSql.') as neto')
            ->groupByRaw(SqlDialectSupport::fecha('COALESCE(venta.fechajornada, venta.fecha)'))
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->fecha_jornada] = round((float) ($row->neto ?? 0), 2);
        }

        return $map;
    }

    /**
     * @param  list<array{pv_sucursal: int, pv_codigo: string, rmv_z: float, rmv_nc: float, neto: float, rendg_nro_oper: int|null}>  $porPv
     */
    private function formatearVendingPvDetalle(array $porPv): string
    {
        if ($porPv === []) {
            return '';
        }

        $partes = [];
        foreach ($porPv as $fila) {
            $partes[] = sprintf(
                'PV%s Z=%s NC=%s neto=%s oper=%s',
                $fila['pv_codigo'] ?? '',
                number_format((float) ($fila['rmv_z'] ?? 0), 2, '.', ''),
                number_format((float) ($fila['rmv_nc'] ?? 0), 2, '.', ''),
                number_format((float) ($fila['neto'] ?? 0), 2, '.', ''),
                (string) ($fila['rendg_nro_oper'] ?? ''),
            );
        }

        return implode(' | ', $partes);
    }

    private function esJornadaPreMigracion(int $empresaId, string $fechaJornada): bool
    {
        $map = config('gastronomia.conciliacion_diaria_reporte.fecha_jornada_desde_por_empresa', []);
        $min = trim((string) ($map[$empresaId] ?? ''));
        if ($min === '') {
            return false;
        }

        return $fechaJornada < Carbon::parse($min)->toDateString();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Exports\Ventas\GastronomiaAuditoriaMesTotalesAnitaExport;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaMesCacheSupport;
use App\Support\Ventas\Gastronomia\GastronomiaAuditoriaMesTotalesAnitaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaFacturacionAuditoriaCtamovSupport;
use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;
use App\Support\Ventas\KandikoAnitaVentaTipoSupport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Reporte mensual día a día: totales Anita (venta, vengrav, ctamov, rendgastro) sin conciliar ERP.
 */
final class GastronomiaAuditoriaMesTotalesAnitaService
{
    public function __construct(
        private readonly GastronomiaAnitaMesCacheSupport $cacheSupport,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
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
        bool $forzarDescarga = false,
        bool $soloReporte = false,
    ): array {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            throw new \InvalidArgumentException('fecha-desde no puede ser posterior a fecha-hasta.');
        }

        $empresas = [];
        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $manifest = $soloReporte
                ? ($this->cacheSupport->cargar($empresaId, $desde, $hasta)['manifest'] ?? [])
                : $this->cacheSupport->descargar($empresaId, $desde, $hasta, $forzarDescarga);
            $cache = $this->cacheSupport->cargar($empresaId, $desde, $hasta);
            $empresas[] = $this->armarEmpresa($empresaId, $desde, $hasta, $tolerancia, $cache, $manifest);
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tolerancia' => $tolerancia,
            'modo' => 'solo_anita',
            'empresas' => $empresas,
            'hay_alertas' => $this->hayAlertas($empresas),
        ];
    }

    /**
     * @param  array<string, mixed>  $empresas
     */
    public function hayAlertas(array $empresas): bool
    {
        foreach ($empresas as $empresa) {
            foreach ($empresa['filas'] ?? [] as $fila) {
                if (($fila['estado'] ?? '') === 'ALERTA') {
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
        $handle = fopen($ruta, 'w');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo escribir CSV: '.$ruta);
        }

        fputcsv($handle, [
            'empresa_id', 'empresa_nombre', 'fecha_jornada',
            'total_venta_anita', 'total_vengrav_anita', 'total_ctamov_anita', 'total_rendg_anita',
            'cant_cabeceras_venta_anita', 'huecos_corr_anita',
            'estado', 'observaciones',
        ], ';');

        foreach ($informe['empresas'] ?? [] as $empresa) {
            foreach ($empresa['filas'] ?? [] as $fila) {
                fputcsv($handle, [
                    $empresa['empresa_id'],
                    $empresa['empresa_nombre'],
                    $fila['fecha_jornada'],
                    $fila['total_venta_anita'],
                    $fila['total_vengrav_anita'],
                    $fila['total_ctamov_anita'],
                    $fila['total_rendg_anita'],
                    $fila['cant_cabeceras_venta_anita'],
                    $fila['huecos_corr_anita'],
                    $fila['estado'],
                    $fila['observaciones'],
                ], ';');
            }
        }

        fclose($handle);
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
            'total_venta_anita', 'total_vengrav_anita', 'total_ctamov_anita', 'total_rendg_anita',
            'cant_cabeceras_venta_anita', 'huecos_corr_anita',
            'estado', 'observaciones',
        ], ';');

        foreach ($informe['empresas'] ?? [] as $empresa) {
            foreach ($empresa['filas'] ?? [] as $fila) {
                fputcsv($handle, [
                    $empresa['empresa_id'],
                    $empresa['empresa_nombre'],
                    $fila['fecha_jornada'],
                    $fila['total_venta_anita'],
                    $fila['total_vengrav_anita'],
                    $fila['total_ctamov_anita'],
                    $fila['total_rendg_anita'],
                    $fila['cant_cabeceras_venta_anita'],
                    $fila['huecos_corr_anita'],
                    $fila['estado'],
                    $fila['observaciones'],
                ], ';');
            }
        }

        rewind($handle);
        $contenido = stream_get_contents($handle);
        fclose($handle);

        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer buffer CSV.');
        }

        return $contenido;
    }

    public function nombreArchivoCsv(array $informe): string
    {
        $desde = str_replace('-', '', (string) ($informe['fecha_desde'] ?? ''));
        $hasta = str_replace('-', '', (string) ($informe['fecha_hasta'] ?? ''));

        return 'auditoria_anita_mes_'.$desde.'_'.$hasta.'.csv';
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function construirContenidoExcel(array $informe): string
    {
        return GastronomiaAuditoriaMesTotalesAnitaExport::contenidoBinario($informe);
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function guardarExcel(string $ruta, array $informe): void
    {
        GastronomiaAuditoriaMesTotalesAnitaExport::guardarEnRuta($ruta, $informe);
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function nombreArchivoExcel(array $informe): string
    {
        return GastronomiaAuditoriaMesTotalesAnitaExport::nombreArchivo($informe);
    }

    /**
     * @param  array<string, mixed>  $informe
     * @return array{enviado: bool, destino?: string, error?: string}
     */
    public function enviarCorreo(array $informe, ?string $destinoOverride = null): array
    {
        $config = config('gastronomia.auditoria_mes_totales_anita', []);
        $destino = trim((string) ($destinoOverride ?? $config['email'] ?? ''));
        if ($destino === '') {
            $destino = trim((string) config('gastronomia.auditoria_anita_diaria.email', ''));
        }
        if ($destino === '') {
            return ['enviado' => false, 'error' => 'Sin destino de correo configurado'];
        }

        $excel = $this->construirContenidoExcel($informe);
        $nombre = $this->nombreArchivoExcel($informe);

        try {
            \Illuminate\Support\Facades\Mail::to($destino)->send(
                new \App\Mail\Ventas\GastronomiaAuditoriaMesTotalesAnita($informe, $excel, $nombre),
            );
            \Illuminate\Support\Facades\Log::info('gastronomia.auditoria_mes_totales_anita.mail_ok', [
                'destino' => $destino,
                'fecha_desde' => $informe['fecha_desde'] ?? null,
                'fecha_hasta' => $informe['fecha_hasta'] ?? null,
                'hay_alertas' => $informe['hay_alertas'] ?? false,
            ]);

            return ['enviado' => true, 'destino' => $destino];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('gastronomia.auditoria_mes_totales_anita.mail_fallo', [
                'destino' => $destino,
                'msg' => $e->getMessage(),
            ]);

            return ['enviado' => false, 'destino' => $destino, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $cache
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function armarEmpresa(
        int $empresaId,
        string $desde,
        string $hasta,
        float $tolerancia,
        array $cache,
        array $manifest,
    ): array {
        $empresa = Empresa::query()->findOrFail($empresaId);
        $empresaCodigo = trim((string) ($empresa->codigo ?? $empresaId));
        $cuentas = GastronomiaFacturacionAuditoriaCtamovSupport::cuentasVentasConciliacion($empresaId);

        $ventaCache = $cache['venta'] ?? [];
        $vengravCache = $cache['vengrav'] ?? [];
        $ctamovCache = $cache['ctamov'] ?? [];
        $rendgCache = $cache['rendgastro'] ?? [];

        $ventaPorJornada = $this->indexarVentaAnitaPorJornada($ventaCache, $empresaCodigo);
        $vengravPorClave = $this->indexarVengrav($vengravCache);
        $ctamovPorFecha = $this->indexarCtamovPorFecha($ctamovCache);

        $filas = [];
        foreach (CarbonPeriod::create($desde, $hasta) as $dia) {
            $fecha = $dia->toDateString();
            $cabecerasDia = GastronomiaAuditoriaMesTotalesAnitaSupport::filtrarCabecerasIncluidas(
                $this->deduplicarCabecerasComprobante($ventaPorJornada[$fecha] ?? []),
            );

            $totalVentaAnita = $this->sumarVenMontoCabeceras($cabecerasDia);
            $totalVengravAnita = $this->sumarVengravDesdeCabeceras($cabecerasDia, $vengravPorClave);
            $totalCtamovAnita = GastronomiaFacturacionAuditoriaCtamovSupport::sumarVentasDesdeCtamov(
                $ctamovPorFecha[$fecha] ?? [],
                $cuentas['codigos_cuenta'],
            );
            $totalRendgAnita = $this->totalRendgAnitaCompleto($empresaId, $rendgCache[$fecha] ?? []);

            $corr = $this->resumenCorrelatividadAnitaDia($cabecerasDia);

            $sinActividad = count($cabecerasDia) === 0
                && abs($totalRendgAnita) <= $tolerancia
                && abs($totalCtamovAnita) <= $tolerancia;

            $diffVentaRendg = round($totalVentaAnita - $totalRendgAnita, 2);

            $obs = [];
            if ($corr['huecos_corr_anita'] > 0) {
                $obs[] = 'Huecos correlativos Anita: '.$corr['huecos_corr_anita'];
            }
            if (! $sinActividad && abs($diffVentaRendg) > $tolerancia) {
                $obs[] = 'Venta Anita vs rendgastro: Δ $ '.number_format($diffVentaRendg, 2, ',', '.');
            }

            $estado = 'OK';
            if ($sinActividad) {
                $estado = '—';
            } elseif ($corr['huecos_corr_anita'] > 0 || abs($diffVentaRendg) > $tolerancia) {
                $estado = 'ALERTA';
            }

            $filas[] = [
                'fecha_jornada' => $fecha,
                'total_venta_anita' => $totalVentaAnita,
                'total_vengrav_anita' => $totalVengravAnita,
                'total_ctamov_anita' => $totalCtamovAnita,
                'total_rendg_anita' => $totalRendgAnita,
                'diff_venta_rendg_anita' => $diffVentaRendg,
                'cant_cabeceras_venta_anita' => count($cabecerasDia),
                'huecos_corr_anita' => $corr['huecos_corr_anita'],
                'estado' => $estado,
                'observaciones' => implode(' | ', $obs),
            ];
        }

        return [
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'empresa_codigo' => $empresaCodigo,
            'modo' => 'solo_anita',
            'cache' => $manifest,
            'filas' => $filas,
        ];
    }

    /**
     * Todas las cabeceras venta Anita de la empresa (gastronomía, estacionamiento, marketing, etc.).
     *
     * @param  list<object>  $ventaCache
     * @return array<string, list<object>>
     */
    private function indexarVentaAnitaPorJornada(array $ventaCache, string $empresaCodigo): array
    {
        $map = [];

        foreach ($ventaCache as $fila) {
            if (GastronomiaAnitaImportEmpresaSupport::usaFiltroEmpresaAnita()) {
                $empCab = trim((string) ($fila->ven_empresa ?? ''));
                if ($empCab !== '' && $empCab !== $empresaCodigo) {
                    continue;
                }
            }

            $fechaJornada = $this->fechaJornadaDesdeAnita((string) ($fila->ven_fecha_vto ?? ''));
            if ($fechaJornada === null) {
                continue;
            }

            $map[$fechaJornada][] = $fila;
        }

        return $map;
    }

    /**
     * Una cabecera por comprobante (letra+sucursal+nro); FAK prevalece sobre FAC duplicado.
     *
     * @param  list<object>  $cabeceras
     * @return list<object>
     */
    private function deduplicarCabecerasComprobante(array $cabeceras): array
    {
        $map = [];

        foreach ($cabeceras as $cab) {
            $sucursal = (int) preg_replace('/\D+/', '', (string) ($cab->ven_sucursal ?? ''));
            $nro = (int) ($cab->ven_nro ?? 0);
            $letra = strtoupper(trim((string) ($cab->ven_letra ?? 'B')));
            if ($sucursal <= 0 || $nro <= 0) {
                continue;
            }

            $key = $letra.'|'.$sucursal.'|'.$nro;
            $existente = $map[$key] ?? null;
            if ($existente === null) {
                $map[$key] = $cab;

                continue;
            }

            $tipoNuevo = strtoupper(trim((string) ($cab->ven_tipo ?? '')));
            $tipoExist = strtoupper(trim((string) ($existente->ven_tipo ?? '')));
            if ($tipoExist !== KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE
                && $tipoNuevo === KandikoAnitaVentaTipoSupport::TIPO_VENTA_BRIDGE) {
                $map[$key] = $cab;
            }
        }

        return array_values($map);
    }

    /**
     * @param  list<object>  $vengravCache
     * @return array<string, list<object>>
     */
    private function indexarVengrav(array $vengravCache): array
    {
        $map = [];
        foreach ($vengravCache as $fila) {
            $clave = $this->claveComprobanteBasica(
                (string) ($fila->veng_letra ?? 'B'),
                (string) ($fila->veng_sucursal ?? ''),
                (int) ($fila->veng_nro ?? 0),
            );
            $map[$clave][] = $fila;
        }

        return $map;
    }

    /**
     * @param  list<object>  $ctamovCache
     * @return array<string, list<array<string, mixed>>>
     */
    private function indexarCtamovPorFecha(array $ctamovCache): array
    {
        $map = [];
        foreach ($ctamovCache as $fila) {
            $fechaEntera = (int) preg_replace('/\D+/', '', (string) ($fila->ctav_fecha ?? ''));
            if ($fechaEntera <= 0) {
                continue;
            }
            $fecha = substr((string) $fechaEntera, 0, 4).'-'.substr((string) $fechaEntera, 4, 2).'-'.substr((string) $fechaEntera, 6, 2);
            $map[$fecha][] = [
                'ctav_cuenta' => $fila->ctav_cuenta ?? 0,
                'ctav_importe' => $fila->ctav_importe ?? 0,
                'ctav_d_h' => $fila->ctav_d_h ?? 'D',
            ];
        }

        return $map;
    }

    /**
     * @param  list<object>  $cabeceras
     */
    private function sumarVenMontoCabeceras(array $cabeceras): float
    {
        $total = 0.0;
        foreach ($cabeceras as $cab) {
            $tipo = strtoupper(trim((string) ($cab->ven_tipo ?? '')));
            $monto = round((float) ($cab->ven_monto ?? 0), 2);
            if (str_starts_with($tipo, 'NC')) {
                $total -= abs($monto);
            } else {
                $total += $monto;
            }
        }

        return round($total, 2);
    }

    /**
     * @param  list<object>  $cabeceras
     * @param  array<string, list<object>>  $vengravPorClave
     */
    private function sumarVengravDesdeCabeceras(array $cabeceras, array $vengravPorClave): float
    {
        $total = 0.0;

        foreach ($cabeceras as $cab) {
            $clave = $this->claveComprobanteBasica(
                (string) ($cab->ven_letra ?? 'B'),
                (string) ($cab->ven_sucursal ?? ''),
                (int) ($cab->ven_nro ?? 0),
            );
            $vistos = [];
            foreach ($vengravPorClave[$clave] ?? [] as $vg) {
                $dedupe = (string) ($vg->veng_codigo_tasa ?? '').'|'
                    .round((float) ($vg->veng_gravado ?? 0), 2).'|'
                    .round((float) ($vg->veng_impuesto ?? 0), 2);
                if (isset($vistos[$dedupe])) {
                    continue;
                }
                $vistos[$dedupe] = true;
                $total += round((float) ($vg->veng_gravado ?? 0), 2);
                $total += round((float) ($vg->veng_impuesto ?? 0), 2);
            }
        }

        return round($total, 2);
    }

    /**
     * TOTAL-DIA rendgastro Anita (neto): Σ netoGrupoHost por host + vending (host vacío por sucursal) + post-cierre Waitry.
     *
     * @param  list<object>  $cabecerasRendg
     */
    private function totalRendgAnitaCompleto(int $empresaId, array $cabecerasRendg): float
    {
        if ($cabecerasRendg === []) {
            return 0.0;
        }

        $vendingSupport = app(GastronomiaConciliacionVendingRendgSupport::class);
        $pvVending = $vendingSupport->puntoventasVendingPorSucursal($empresaId);

        $suma = 0.0;
        /** @var array<string, list<object>> $porHost */
        $porHost = [];
        /** @var array<int, list<object>> $porSucursalHostVacio */
        $porSucursalHostVacio = [];

        foreach ($cabecerasRendg as $fila) {
            if ($this->rendgastroSupport->esCabeceraPostCierreWaitry($fila)) {
                continue;
            }

            $host = trim((string) ($fila->rendg_host ?? ''));
            if ($host === '') {
                $sucursal = (int) ($fila->rendg_sucursal ?? 0);
                if ($sucursal > 0 && isset($pvVending[$sucursal])) {
                    $porSucursalHostVacio[$sucursal][] = $fila;
                }

                continue;
            }

            if ($vendingSupport->esCabeceraVending($fila, $empresaId)) {
                $sucursal = (int) ($fila->rendg_sucursal ?? 0);
                if ($sucursal > 0) {
                    $porHost['VENDING|'.$sucursal][] = $fila;
                }

                continue;
            }

            $porHost[$host][] = $fila;
        }

        foreach ($porHost as $grupo) {
            // Incluye hosts de estacionamiento: restar también sus NC (cada host es homogéneo por PV;
            // no afecta gastro, cuyos grupos no tienen cabeceras de estacionamiento).
            $suma += $this->rendgastroSupport->netoGrupoHost($grupo, true);
        }

        foreach ($porSucursalHostVacio as $grupo) {
            $suma += $this->rendgastroSupport->netoGrupoHost($grupo, true);
        }

        $post = array_values(array_filter(
            $cabecerasRendg,
            fn (object $fila): bool => $this->rendgastroSupport->esCabeceraPostCierreWaitry($fila),
        ));
        foreach ($post as $cab) {
            $x = round((float) ($cab->rendg_total_x ?? 0), 2);
            $z = round((float) ($cab->rendg_total_z ?? 0), 2);
            $fcCaea = round((float) ($cab->rendg_tot_fc_caea ?? 0), 2);
            $bruto = $x > 0 ? $x : ($z > 0 ? $z : ($fcCaea > 0 ? $fcCaea : 0.0));
            $nc = round((float) ($cab->rendg_tot_nc ?? 0), 2);
            $ncCaea = round((float) ($cab->rendg_tot_nc_caea ?? 0), 2);
            $suma += round($bruto - $nc - $ncCaea, 2);
        }

        return round($suma, 2);
    }

    /**
     * Huecos en numeración Anita por sucursal + tipo (FAC/FAK/…), sin NC ni FSL/FBI.
     *
     * @param  list<object>  $cabecerasAnita
     * @return array{huecos_corr_anita: int}
     */
    private function resumenCorrelatividadAnitaDia(array $cabecerasAnita): array
    {
        /** @var array<string, list<object>> $porSucursalTipo */
        $porSucursalTipo = [];

        foreach ($cabecerasAnita as $cab) {
            $tipo = strtoupper(trim((string) ($cab->ven_tipo ?? '')));
            if ($tipo === '' || str_starts_with($tipo, 'NC')) {
                continue;
            }
            if (GastronomiaAuditoriaMesTotalesAnitaSupport::esTipoVentaExcluido($tipo)) {
                continue;
            }

            $sucursal = (int) preg_replace('/\D+/', '', (string) ($cab->ven_sucursal ?? ''));
            if ($sucursal <= 0) {
                continue;
            }

            $porSucursalTipo[$sucursal.'|'.$tipo][] = $cab;
        }

        $huecos = 0;
        foreach ($porSucursalTipo as $grupo) {
            usort($grupo, static fn (object $a, object $b): int => ((int) ($a->ven_nro ?? 0)) <=> ((int) ($b->ven_nro ?? 0)));

            $prev = null;
            foreach ($grupo as $cab) {
                $n = (int) ($cab->ven_nro ?? 0);
                if ($n <= 0) {
                    continue;
                }
                if ($prev !== null && $n > $prev + 1 && ($n - $prev - 1) <= 5000) {
                    $huecos += $n - $prev - 1;
                }
                $prev = $n;
            }
        }

        return ['huecos_corr_anita' => $huecos];
    }

    private function fechaJornadaDesdeAnita(string $fechaEntera): ?string
    {
        $fechaEntera = preg_replace('/\D+/', '', $fechaEntera);
        if ($fechaEntera === null || strlen($fechaEntera) !== 8) {
            return null;
        }

        return substr($fechaEntera, 0, 4).'-'.substr($fechaEntera, 4, 2).'-'.substr($fechaEntera, 6, 2);
    }

    private function claveComprobanteBasica(string $letra, string $sucursal, int $nro): string
    {
        return strtoupper(trim($letra)).'|'
            .(int) preg_replace('/\D+/', '', $sucursal).'|'
            .$nro;
    }
}

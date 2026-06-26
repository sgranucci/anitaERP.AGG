<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaMesCacheSupport;
use App\Support\Ventas\KandikoAnitaVentaTipoSupport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Concilia ERP ↔ Anita usando cache local (venta/rendgastro bulk en pocas consultas bridge)
 * y emparejando en memoria (sin N consultas por comprobante).
 */
final class GastronomiaAuditoriaMesBulkAnitaErpService
{
    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoService,
        private readonly GastronomiaAnitaMesCacheSupport $cacheSupport,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function auditar(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        float $tolerancia = 0.02,
        ?string $codigoPvFiltro = null,
        ?string $fechaJornadaErpDesde = null,
        bool $forzarDescarga = false,
        bool $soloCache = false,
    ): array {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            throw new \InvalidArgumentException('fecha-desde no puede ser posterior a fecha-hasta.');
        }

        $empresa = Empresa::query()->findOrFail($empresaId);
        $empresaCodigo = trim((string) ($empresa->codigo ?? $empresaId));

        $puntoventas = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('modofacturacion', '!=', 'M')
            ->when($codigoPvFiltro !== null && trim($codigoPvFiltro) !== '', function ($q) use ($codigoPvFiltro) {
                $q->where('codigo', trim($codigoPvFiltro));
            })
            ->get()
            ->keyBy('id');

        $pvPorSucursal = [];
        foreach ($puntoventas as $pv) {
            $suc = $this->chequeoService->sucursalDesdeCodigoPuntoventa((string) $pv->codigo);
            if ($suc > 0) {
                $pvPorSucursal[$suc] = $pv;
            }
        }

        $cache = $this->resolverCacheAnita($empresaId, $desde, $hasta, $forzarDescarga, $soloCache);
        $filasAnita = $cache['venta'];
        $anitaPorPvJornada = $this->cacheSupport->indexarCabecerasPorSucursalJornada($filasAnita, $pvPorSucursal);

        $ventasErp = Venta::query()
            ->with(['puntoventas.empresas'])
            ->whereIn('puntoventa_id', $puntoventas->keys()->all())
            ->whereDate('fechajornada', '>=', $desde)
            ->whereDate('fechajornada', '<=', $hasta)
            ->whereHas('gastronomiaEmision')
            ->orderBy('fechajornada')
            ->orderBy('puntoventa_id')
            ->orderBy('numerocomprobante')
            ->get();

        if ($fechaJornadaErpDesde !== null && trim($fechaJornadaErpDesde) !== '') {
            $minErp = Carbon::parse($fechaJornadaErpDesde)->toDateString();
        } else {
            $minErp = $this->inferirPrimeraJornadaErp($ventasErp) ?? $desde;
        }

        $conteoGlobal = $this->conteoVacio();
        $resumenPorJornada = [];
        $detalleProblemas = [];
        $clavesAnitaConsumidas = [];

        foreach ($ventasErp as $venta) {
            $pv = $venta->puntoventas;
            if (! $pv) {
                continue;
            }

            $fechaJornada = Carbon::parse((string) $venta->fechajornada)->toDateString();
            $clave = $this->chequeoService->claveComprobanteDesdeVentaErp($venta);
            if ($clave === null) {
                $conteoGlobal['error']++;
                $detalleProblemas[] = $this->filaDetalle('error', $fechaJornada, (string) $pv->codigo, null, $venta, null, ['erp' => 'Código no reconocido']);

                continue;
            }

            $sucursal = $this->chequeoService->sucursalDesdeCodigoPuntoventa((string) $pv->codigo);
            $cab = $anitaPorPvJornada[$sucursal][$fechaJornada][$clave] ?? null;
            $claveAnita = $sucursal.'|'.$fechaJornada.'|'.$clave;
            if ($cab !== null) {
                $clavesAnitaConsumidas[$claveAnita] = true;
            }

            $conciliacion = $this->chequeoService->conciliarVentaConCabeceraAnita($venta, $cab, $tolerancia);
            $estado = (string) ($conciliacion['estado'] ?? 'error');
            $conteoGlobal[$estado === 'ok' ? 'ok' : ($estado === 'solo_erp' ? 'solo_erp' : 'diferencia')]++;

            if ($estado !== 'ok') {
                $detalleProblemas[] = $this->filaDetalle(
                    $estado,
                    $fechaJornada,
                    (string) $pv->codigo,
                    $clave,
                    $venta,
                    $cab,
                    $conciliacion['diferencias'] ?? [],
                );
            }

            $this->acumularResumenJornada($resumenPorJornada, $fechaJornada, $pv, $estado, $venta, $cab, $tolerancia);
        }

        foreach ($anitaPorPvJornada as $sucursal => $porJornada) {
            $pv = $pvPorSucursal[$sucursal] ?? null;
            if ($pv === null) {
                continue;
            }

            foreach ($porJornada as $fechaJornada => $cabeceras) {
                foreach ($cabeceras as $clave => $cab) {
                    $claveAnita = $sucursal.'|'.$fechaJornada.'|'.$clave;
                    if (isset($clavesAnitaConsumidas[$claveAnita])) {
                        continue;
                    }

                    $tipoAnita = strtoupper(trim((string) ($cab->ven_tipo ?? '')));
                    $categoria = $this->clasificarSoloAnita($tipoAnita, $fechaJornada, $minErp);
                    $conteoGlobal[$categoria]++;

                    if ($categoria === 'solo_anita') {
                        $detalleProblemas[] = [
                            'estado' => 'solo_anita',
                            'fecha_jornada' => $fechaJornada,
                            'puntoventa' => (string) $pv->codigo,
                            'clave' => $clave,
                            'tipo_anita' => $tipoAnita,
                            'numero' => (int) ($cab->ven_nro ?? 0),
                            'total_anita' => round((float) ($cab->ven_monto ?? 0), 2),
                            'motivo' => 'Sin venta gastronomía en ERP',
                        ];
                    }

                    $this->acumularSoloAnitaResumen($resumenPorJornada, $fechaJornada, $pv, $categoria, $cab);
                }
            }
        }

        ksort($resumenPorJornada);

        return [
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'empresa_codigo' => $empresaCodigo,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'fecha_jornada_erp_desde' => $minErp,
            'tolerancia' => $tolerancia,
            'cache' => $cache['manifest'],
            'cabeceras_anita_bulk' => count($filasAnita),
            'ventas_erp_gastronomia' => $ventasErp->count(),
            'conteo' => $conteoGlobal,
            'resumen_por_jornada' => array_values($resumenPorJornada),
            'detalle_problemas' => $detalleProblemas,
            'hay_problemas' => ($conteoGlobal['solo_erp'] + $conteoGlobal['diferencia'] + $conteoGlobal['solo_anita']) > 0,
            'requiere_ven_gravado_cabecera' => true,
        ];
    }

    /**
     * @return array{manifest: array<string, mixed>, venta: list<object>}
     */
    private function resolverCacheAnita(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        bool $forzarDescarga,
        bool $soloCache,
    ): array {
        if ($soloCache) {
            $cargado = $this->cacheSupport->cargar($empresaId, $fechaDesde, $fechaHasta);

            return [
                'manifest' => $cargado['manifest'],
                'venta' => $cargado['venta'],
            ];
        }

        $manifest = $this->cacheSupport->descargar($empresaId, $fechaDesde, $fechaHasta, $forzarDescarga);
        $cargado = $this->cacheSupport->cargar($empresaId, $fechaDesde, $fechaHasta);

        return [
            'manifest' => $manifest ?: $cargado['manifest'],
            'venta' => $cargado['venta'],
        ];
    }

    /**
     * @param  Collection<int, Venta>  $ventasErp
     */
    private function inferirPrimeraJornadaErp(Collection $ventasErp): ?string
    {
        $min = $ventasErp->min('fechajornada');

        return $min !== null ? Carbon::parse((string) $min)->toDateString() : null;
    }

    private function clasificarSoloAnita(string $tipoAnita, string $fechaJornada, string $fechaJornadaErpDesde): string
    {
        if ($tipoAnita === 'FSL') {
            return 'excluido_estacionamiento';
        }

        if ($fechaJornada < $fechaJornadaErpDesde) {
            return 'excluido_pre_erp';
        }

        if (! KandikoAnitaVentaTipoSupport::esTipoGastronomiaAnita($tipoAnita) && $tipoAnita !== 'FBI') {
            return 'excluido_otro_tipo';
        }

        return 'solo_anita';
    }

    /**
     * @return array<string, int>
     */
    private function conteoVacio(): array
    {
        return [
            'ok' => 0,
            'diferencia' => 0,
            'solo_erp' => 0,
            'solo_anita' => 0,
            'excluido_estacionamiento' => 0,
            'excluido_pre_erp' => 0,
            'excluido_otro_tipo' => 0,
            'error' => 0,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $resumenPorJornada
     */
    private function acumularResumenJornada(
        array &$resumenPorJornada,
        string $fechaJornada,
        Puntoventa $pv,
        string $estado,
        Venta $venta,
        ?object $cab,
        float $tolerancia,
    ): void {
        $key = $fechaJornada;
        if (! isset($resumenPorJornada[$key])) {
            $resumenPorJornada[$key] = $this->filaResumenJornadaVacia($fechaJornada);
        }

        $resumenPorJornada[$key]['ventas_erp']++;
        $resumenPorJornada[$key]['total_erp'] += abs((float) $venta->total);

        if ($cab !== null) {
            $resumenPorJornada[$key]['emparejadas']++;
            $resumenPorJornada[$key]['total_anita_emparejado'] += (float) ($cab->ven_monto ?? 0);
        }

        if ($estado === 'ok') {
            $resumenPorJornada[$key]['ok']++;
        } elseif ($estado === 'solo_erp') {
            $resumenPorJornada[$key]['solo_erp']++;
        } elseif ($estado === 'diferencia') {
            $resumenPorJornada[$key]['diferencia']++;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $resumenPorJornada
     */
    private function acumularSoloAnitaResumen(
        array &$resumenPorJornada,
        string $fechaJornada,
        Puntoventa $pv,
        string $categoria,
        object $cab,
    ): void {
        $key = $fechaJornada;
        if (! isset($resumenPorJornada[$key])) {
            $resumenPorJornada[$key] = $this->filaResumenJornadaVacia($fechaJornada);
        }

        $resumenPorJornada[$key][$categoria] = (int) ($resumenPorJornada[$key][$categoria] ?? 0) + 1;
        if ($categoria === 'solo_anita') {
            $resumenPorJornada[$key]['total_solo_anita'] += (float) ($cab->ven_monto ?? 0);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function filaResumenJornadaVacia(string $fechaJornada): array
    {
        return [
            'fecha_jornada' => $fechaJornada,
            'ventas_erp' => 0,
            'emparejadas' => 0,
            'ok' => 0,
            'solo_erp' => 0,
            'diferencia' => 0,
            'solo_anita' => 0,
            'excluido_estacionamiento' => 0,
            'excluido_pre_erp' => 0,
            'excluido_otro_tipo' => 0,
            'total_erp' => 0.0,
            'total_anita_emparejado' => 0.0,
            'total_solo_anita' => 0.0,
            'estado' => 'OK',
        ];
    }

    /**
     * @param  array<string, string>  $diferencias
     * @return array<string, mixed>
     */
    private function filaDetalle(
        string $estado,
        string $fechaJornada,
        string $codigoPv,
        ?string $clave,
        ?Venta $venta,
        ?object $cab,
        array $diferencias,
    ): array {
        return [
            'estado' => $estado,
            'fecha_jornada' => $fechaJornada,
            'puntoventa' => $codigoPv,
            'clave' => $clave,
            'codigo_erp' => $venta !== null ? (string) $venta->codigo : null,
            'venta_id' => $venta !== null ? (int) $venta->id : null,
            'total_erp' => $venta !== null ? round((float) $venta->total, 2) : null,
            'total_anita' => $cab !== null ? round((float) ($cab->ven_monto ?? 0), 2) : null,
            'diferencias' => $diferencias,
        ];
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function finalizarResumenJornadas(array &$informe): void
    {
        foreach ($informe['resumen_por_jornada'] as &$dia) {
            $dia['total_erp'] = round((float) ($dia['total_erp'] ?? 0), 2);
            $dia['total_anita_emparejado'] = round((float) ($dia['total_anita_emparejado'] ?? 0), 2);
            $dia['total_solo_anita'] = round((float) ($dia['total_solo_anita'] ?? 0), 2);
            $dia['estado'] = (($dia['solo_erp'] ?? 0) > 0 || ($dia['diferencia'] ?? 0) > 0) ? 'ALERTA' : 'OK';
        }
        unset($dia);
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function guardarCsv(string $ruta, array $informe): void
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear buffer CSV.');
        }

        fputcsv($handle, [
            'fecha_jornada', 'ventas_erp', 'ok', 'solo_erp', 'diferencia', 'solo_anita',
            'excl_estac', 'excl_pre_erp', 'excl_otro', 'total_erp', 'total_anita_emp', 'total_solo_anita', 'estado',
        ], ';');

        foreach ($informe['resumen_por_jornada'] ?? [] as $dia) {
            fputcsv($handle, [
                $dia['fecha_jornada'],
                $dia['ventas_erp'],
                $dia['ok'],
                $dia['solo_erp'],
                $dia['diferencia'],
                $dia['solo_anita'],
                $dia['excluido_estacionamiento'],
                $dia['excluido_pre_erp'],
                $dia['excluido_otro_tipo'],
                $dia['total_erp'],
                $dia['total_anita_emparejado'],
                $dia['total_solo_anita'],
                $dia['estado'],
            ], ';');
        }

        rewind($handle);
        $contenido = stream_get_contents($handle);
        fclose($handle);

        if ($contenido === false || file_put_contents($ruta, $contenido) === false) {
            throw new \RuntimeException('No se pudo escribir CSV: '.$ruta);
        }
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function guardarDetalleCsv(string $ruta, array $informe): void
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear buffer CSV.');
        }

        fputcsv($handle, [
            'estado', 'fecha_jornada', 'puntoventa', 'clave', 'codigo_erp', 'venta_id',
            'total_erp', 'total_anita', 'tipo_anita', 'numero', 'motivo', 'diferencias',
        ], ';');

        foreach ($informe['detalle_problemas'] ?? [] as $fila) {
            fputcsv($handle, [
                $fila['estado'] ?? '',
                $fila['fecha_jornada'] ?? '',
                $fila['puntoventa'] ?? '',
                $fila['clave'] ?? '',
                $fila['codigo_erp'] ?? '',
                $fila['venta_id'] ?? '',
                $fila['total_erp'] ?? '',
                $fila['total_anita'] ?? '',
                $fila['tipo_anita'] ?? '',
                $fila['numero'] ?? '',
                $fila['motivo'] ?? implode(' | ', array_values($fila['diferencias'] ?? [])),
                json_encode($fila['diferencias'] ?? [], JSON_UNESCAPED_UNICODE),
            ], ';');
        }

        rewind($handle);
        $contenido = stream_get_contents($handle);
        fclose($handle);

        if ($contenido === false || file_put_contents($ruta, $contenido) === false) {
            throw new \RuntimeException('No se pudo escribir CSV detalle: '.$ruta);
        }
    }
}

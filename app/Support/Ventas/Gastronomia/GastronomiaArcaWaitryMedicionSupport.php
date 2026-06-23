<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Services\Arca\ArcaWsfeFacturaElectronicaService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryAuthService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryHttpClient;
use App\Support\Arca\ArcaFailoverStore;
use Carbon\Carbon;
use Throwable;

/**
 * Mediciones de latencia ARCA y Waitry (probe en vivo + resumen desde log de emisión).
 */
final class GastronomiaArcaWaitryMedicionSupport
{
    public function __construct(
        private readonly ArcaWsfeFacturaElectronicaService $wsfeService,
        private readonly WaitryAuthService $authService,
        private readonly WaitryHttpClient $waitryHttp,
    ) {
    }

    /**
     * @return array{
     *     ok:bool,
     *     error?:string,
     *     empresa_id:int,
     *     puntoventa_id:int,
     *     puntoventa_codigo:string,
     *     cbte_tipo:int,
     *     repeticiones:int,
     *     muestras_ms:list<float>,
     *     promedio_ms:float,
     *     min_ms:float,
     *     max_ms:float,
     *     ultimo_nro:?int,
     *     failover_activo:bool
     * }
     */
    public function medirArcaUltimoAutorizado(
        int $empresaId,
        int $puntoventaId,
        int $repeticiones = 3,
    ): array {
        $repeticiones = max(1, min(5, $repeticiones));
        $pv = Puntoventa::query()->find($puntoventaId);
        if ($pv === null) {
            return ['ok' => false, 'error' => 'Punto de venta #'.$puntoventaId.' inexistente.'] + $this->baseArcaVacio($empresaId, $puntoventaId, $repeticiones);
        }

        $ptoVta = (int) $pv->codigo;
        if ($ptoVta <= 0) {
            return ['ok' => false, 'error' => 'PV #'.$puntoventaId.' sin código numérico.'] + $this->baseArcaVacio($empresaId, $puntoventaId, $repeticiones);
        }

        $cbteTipo = $this->resolverCbteTipoDesdePuntoventa($puntoventaId);
        if ($cbteTipo <= 0) {
            return ['ok' => false, 'error' => 'No se pudo resolver cbte_tipo (Factura B).'] + $this->baseArcaVacio($empresaId, $puntoventaId, $repeticiones);
        }

        $muestras = [];
        $ultimoNro = null;
        $ultimoError = null;

        for ($i = 0; $i < $repeticiones; $i++) {
            if ($i > 0) {
                usleep(200_000);
            }

            $t0 = microtime(true);
            try {
                $ultimoNro = $this->wsfeService->feCompUltimoAutorizado($empresaId, $ptoVta, $cbteTipo);
                $muestras[] = round((microtime(true) - $t0) * 1000, 2);
            } catch (Throwable $e) {
                $ultimoError = $e->getMessage();
                $muestras[] = round((microtime(true) - $t0) * 1000, 2);
            }
        }

        $ok = $ultimoError === null;

        return [
            'ok' => $ok,
            'error' => $ultimoError,
            'empresa_id' => $empresaId,
            'puntoventa_id' => $puntoventaId,
            'puntoventa_codigo' => str_pad((string) $ptoVta, 5, '0', STR_PAD_LEFT),
            'cbte_tipo' => $cbteTipo,
            'repeticiones' => $repeticiones,
            'muestras_ms' => $muestras,
            'promedio_ms' => $muestras !== [] ? round(array_sum($muestras) / count($muestras), 2) : 0.0,
            'min_ms' => $muestras !== [] ? min($muestras) : 0.0,
            'max_ms' => $muestras !== [] ? max($muestras) : 0.0,
            'ultimo_nro' => is_int($ultimoNro) ? $ultimoNro : null,
            'failover_activo' => ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE),
        ];
    }

    /**
     * @return array{
     *     ok:bool,
     *     error?:string,
     *     empresa_id:int,
     *     place_id:int,
     *     token_cache_ms:float,
     *     token_fresco_ms:?float,
     *     get_orders_ms:list<float>,
     *     get_orders_promedio_ms:float
     * }
     */
    public function medirWaitry(int $empresaId, int $repeticiones = 2, bool $renovarToken = false): array
    {
        if (! config('waitry.habilitado', false)) {
            return [
                'ok' => false,
                'error' => 'WAITRY_HABILITADO=false',
                'empresa_id' => $empresaId,
                'place_id' => 0,
                'token_cache_ms' => 0.0,
                'token_fresco_ms' => null,
                'get_orders_ms' => [],
                'get_orders_promedio_ms' => 0.0,
            ];
        }

        $placeMap = config('waitry.place_id_por_empresa', []);
        $placeId = is_array($placeMap) ? (int) ($placeMap[$empresaId] ?? 0) : 0;
        if ($placeId <= 0) {
            return [
                'ok' => false,
                'error' => 'Sin placeId para empresa '.$empresaId,
                'empresa_id' => $empresaId,
                'place_id' => 0,
                'token_cache_ms' => 0.0,
                'token_fresco_ms' => null,
                'get_orders_ms' => [],
                'get_orders_promedio_ms' => 0.0,
            ];
        }

        $t0Token = microtime(true);
        $ctx = $this->authService->contextoAutenticado();
        $tokenCacheMs = round((microtime(true) - $t0Token) * 1000, 2);
        if (! ($ctx['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => (string) ($ctx['error'] ?? 'Token Waitry falló'),
                'empresa_id' => $empresaId,
                'place_id' => $placeId,
                'token_cache_ms' => $tokenCacheMs,
                'token_fresco_ms' => null,
                'get_orders_ms' => [],
                'get_orders_promedio_ms' => 0.0,
            ];
        }

        $tokenFrescoMs = null;
        if ($renovarToken) {
            $t0Fresh = microtime(true);
            $this->authService->invalidarToken();
            $this->authService->renovarTokenForzado();
            $ctxFresh = $this->authService->contextoAutenticado();
            $tokenFrescoMs = round((microtime(true) - $t0Fresh) * 1000, 2);
            if (! ($ctxFresh['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'error' => (string) ($ctxFresh['error'] ?? 'Renovación token falló'),
                    'empresa_id' => $empresaId,
                    'place_id' => $placeId,
                    'token_cache_ms' => $tokenCacheMs,
                    'token_fresco_ms' => $tokenFrescoMs,
                    'get_orders_ms' => [],
                    'get_orders_promedio_ms' => 0.0,
                ];
            }
        }

        $url = (string) config('waitry.get_orders_url');
        $minutos = max(5, (int) config('waitry.get_orders_minutos_atras', 20));
        $tz = config('app.timezone', 'America/Argentina/Buenos_Aires');
        $to = Carbon::now($tz);
        $from = $to->copy()->subMinutes($minutos);
        $query = [
            'placeId' => $placeId,
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ];

        $repeticiones = max(1, min(3, $repeticiones));
        $getOrdersMs = [];
        $ultimoError = null;

        for ($i = 0; $i < $repeticiones; $i++) {
            if ($i > 0) {
                usleep(300_000);
            }

            $t0 = microtime(true);
            $resultado = $this->waitryHttp->getJson($url, $query, 'medicion_get_orders_pos');
            $ms = round((microtime(true) - $t0) * 1000, 2);
            $getOrdersMs[] = $ms;

            if (! ($resultado['ok'] ?? false)) {
                $ultimoError = (string) ($resultado['error'] ?? 'getOrdersPOS falló');
            }
        }

        return [
            'ok' => $ultimoError === null,
            'error' => $ultimoError,
            'empresa_id' => $empresaId,
            'place_id' => $placeId,
            'token_cache_ms' => $tokenCacheMs,
            'token_fresco_ms' => $tokenFrescoMs,
            'get_orders_ms' => $getOrdersMs,
            'get_orders_promedio_ms' => $getOrdersMs !== [] ? round(array_sum($getOrdersMs) / count($getOrdersMs), 2) : 0.0,
        ];
    }

    /**
     * @return array{
     *     cfg_id:int,
     *     muestras:int,
     *     arca_ultimo_numero:array{prom:float,min:float,max:float},
     *     arca_solicita_cae:array{prom:float,min:float,max:float},
     *     emision_total:array{prom:float,min:float,max:float},
     *     waitry_post_emision:array{prom:float,min:float,max:float,muestras:int},
     *     ticket_imprimir:array{prom:float,min:float,max:float,muestras:int},
     *     filas:list<array<string, string|float|int|null>>
     * }
     */
    public function resumirDesdeLog(string $logPath, int $cfgId, int $limite = 30): array
    {
        $vacio = [
            'cfg_id' => $cfgId,
            'muestras' => 0,
            'arca_ultimo_numero' => ['prom' => 0.0, 'min' => 0.0, 'max' => 0.0],
            'arca_solicita_cae' => ['prom' => 0.0, 'min' => 0.0, 'max' => 0.0],
            'emision_total' => ['prom' => 0.0, 'min' => 0.0, 'max' => 0.0],
            'waitry_post_emision' => ['prom' => 0.0, 'min' => 0.0, 'max' => 0.0, 'muestras' => 0],
            'ticket_imprimir' => ['prom' => 0.0, 'min' => 0.0, 'max' => 0.0, 'muestras' => 0],
            'filas' => [],
        ];

        if (! is_readable($logPath)) {
            return $vacio;
        }

        $perfiles = $this->leerEntradasProfile($logPath, $limite * 4);
        $waitryPorVenta = $this->leerTimestampsPorVenta($logPath, 'waitry.comanda.ok');
        $syncPorVenta = $this->leerTimestampsPorVenta($logPath, 'waitry.sync_status_pos.ok');
        $ticketPorVenta = $this->leerTicketTiming($logPath);

        $filtrados = [];
        foreach ($perfiles as $p) {
            $cuentaId = (int) ($p['cuenta_id'] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }

            $cuentaCfg = (int) (CuentaGastronomia::query()->whereKey($cuentaId)->value('configuracion_puntoventa_gastronomia_id') ?? 0);
            if ($cuentaCfg !== $cfgId) {
                continue;
            }

            $ventaId = (int) (CuentaGastronomia::query()->whereKey($cuentaId)->value('venta_id') ?? 0);
            $arcaUltimo = $this->msEtapa($p['etapas'], 'arca_ultimo_numero_fin');
            $arcaCae = $this->msEtapa($p['etapas'], 'arca_solicita_cae_fin');
            $emisionTs = $this->parseLogTimestamp($p['fecha']);
            $waitryTs = $ventaId > 0
                ? ($waitryPorVenta[$ventaId] ?? $syncPorVenta[$ventaId] ?? null)
                : null;
            $gapWaitry = ($emisionTs !== null && $waitryTs !== null)
                ? max(0.0, ($waitryTs - $emisionTs) * 1000)
                : null;

            $ticket = $ventaId > 0 ? ($ticketPorVenta[$ventaId] ?? null) : null;

            $filtrados[] = [
                'fecha' => $p['fecha'],
                'cuenta_id' => $cuentaId,
                'venta_id' => $ventaId > 0 ? $ventaId : null,
                'total_ms' => (float) ($p['total_ms'] ?? 0),
                'arca_ultimo_ms' => $arcaUltimo,
                'arca_cae_ms' => $arcaCae,
                'waitry_gap_ms' => $gapWaitry,
                'ticket_imprimir_ms' => $ticket['imprimir_ms'] ?? null,
            ];
        }

        $filtrados = array_slice(array_reverse($filtrados), 0, $limite);

        if ($filtrados === []) {
            return $vacio;
        }

        $arcaUltimos = array_values(array_filter(array_column($filtrados, 'arca_ultimo_ms'), static fn ($v) => $v !== null));
        $arcaCaes = array_values(array_filter(array_column($filtrados, 'arca_cae_ms'), static fn ($v) => $v !== null));
        $totales = array_column($filtrados, 'total_ms');
        $gapsWaitry = array_values(array_filter(array_column($filtrados, 'waitry_gap_ms'), static fn ($v) => $v !== null));
        $impTickets = array_values(array_filter(array_column($filtrados, 'ticket_imprimir_ms'), static fn ($v) => $v !== null));

        $filas = [];
        foreach ($filtrados as $f) {
            $filas[] = [
                'fecha' => $f['fecha'],
                'venta_id' => $f['venta_id'],
                'total_ms' => $f['total_ms'],
                'arca_nro_ms' => $f['arca_ultimo_ms'],
                'arca_cae_ms' => $f['arca_cae_ms'],
                'waitry_gap_ms' => $f['waitry_gap_ms'],
                'imp_ms' => $f['ticket_imprimir_ms'],
            ];
        }

        return [
            'cfg_id' => $cfgId,
            'muestras' => count($filtrados),
            'arca_ultimo_numero' => $this->stats($arcaUltimos),
            'arca_solicita_cae' => $this->stats($arcaCaes),
            'emision_total' => $this->stats($totales),
            'waitry_post_emision' => $this->stats($gapsWaitry) + ['muestras' => count($gapsWaitry)],
            'ticket_imprimir' => $this->stats($impTickets) + ['muestras' => count($impTickets)],
            'filas' => $filas,
        ];
    }

    /**
     * @return array{prom:float,min:float,max:float}
     */
    private function stats(array $valores): array
    {
        if ($valores === []) {
            return ['prom' => 0.0, 'min' => 0.0, 'max' => 0.0];
        }

        return [
            'prom' => round(array_sum($valores) / count($valores), 2),
            'min' => round(min($valores), 2),
            'max' => round(max($valores), 2),
        ];
    }

    /**
     * @param  list<array{etapa:string,ms:float}>  $etapas
     */
    private function msEtapa(array $etapas, string $nombreEtapa): ?float
    {
        foreach ($etapas as $e) {
            if (($e['etapa'] ?? '') === $nombreEtapa) {
                return (float) ($e['ms'] ?? 0);
            }
        }

        return null;
    }

    private function parseLogTimestamp(string $fecha): ?float
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return null;
        }

        try {
            return (float) Carbon::parse($fecha)->getTimestampMs() / 1000;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, float> venta_id => unix timestamp
     */
    private function leerTimestampsPorVenta(string $logPath, string $needle): array
    {
        $map = [];
        $handle = fopen($logPath, 'rb');
        if ($handle === false) {
            return $map;
        }

        while (($linea = fgets($handle)) !== false) {
            if (! str_contains($linea, $needle)) {
                continue;
            }

            preg_match('/^\[([^\]]+)\]/', $linea, $mFecha);
            $jsonStart = strpos($linea, '{');
            if ($jsonStart === false) {
                continue;
            }

            $payload = json_decode(substr($linea, $jsonStart), true);
            if (! is_array($payload)) {
                continue;
            }

            $ventaId = (int) ($payload['venta_id'] ?? 0);
            if ($ventaId <= 0) {
                continue;
            }

            $ts = $this->parseLogTimestamp($mFecha[1] ?? '');
            if ($ts !== null) {
                $map[$ventaId] = $ts;
            }
        }

        fclose($handle);

        return $map;
    }

    /**
     * @return array<int, array{imprimir_ms:float,total_ms:float}>
     */
    private function leerTicketTiming(string $logPath): array
    {
        $map = [];
        $handle = fopen($logPath, 'rb');
        if ($handle === false) {
            return $map;
        }

        while (($linea = fgets($handle)) !== false) {
            if (! str_contains($linea, 'gastronomia.ticket_factura.timing')) {
                continue;
            }

            $jsonStart = strpos($linea, '{');
            if ($jsonStart === false) {
                continue;
            }

            $payload = json_decode(substr($linea, $jsonStart), true);
            if (! is_array($payload)) {
                continue;
            }

            $ventaId = (int) ($payload['venta_id'] ?? 0);
            if ($ventaId <= 0) {
                continue;
            }

            $map[$ventaId] = [
                'imprimir_ms' => (float) ($payload['imprimir_ms'] ?? 0),
                'total_ms' => (float) ($payload['total_ms'] ?? 0),
            ];
        }

        fclose($handle);

        return $map;
    }

    /**
     * @return list<array{fecha:string,cuenta_id:int,total_ms:float,etapas:list<array{etapa:string,ms:float}>}>
     */
    private function leerEntradasProfile(string $logPath, int $limite): array
    {
        $handle = fopen($logPath, 'rb');
        if ($handle === false) {
            return [];
        }

        $buffer = '';
        $entradas = [];

        while (! feof($handle)) {
            $chunk = fread($handle, 65536);
            if ($chunk === false) {
                break;
            }
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $linea = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if (! str_contains($linea, 'gastronomia.emision.profile')) {
                    continue;
                }

                $jsonStart = strpos($linea, '{');
                if ($jsonStart === false) {
                    continue;
                }

                $payload = json_decode(substr($linea, $jsonStart), true);
                if (! is_array($payload)) {
                    continue;
                }

                preg_match('/^\[([^\]]+)\]/', $linea, $m);
                $entradas[] = [
                    'fecha' => $m[1] ?? '',
                    'cuenta_id' => (int) ($payload['cuenta_id'] ?? 0),
                    'total_ms' => (float) ($payload['total_ms'] ?? 0),
                    'etapas' => is_array($payload['etapas'] ?? null) ? $payload['etapas'] : [],
                ];
            }
        }

        fclose($handle);

        return array_slice(array_reverse($entradas), 0, $limite);
    }

    private function resolverCbteTipoDesdePuntoventa(int $puntoventaId): int
    {
        $tipoId = (int) (ConfiguracionPuntoventaGastronomia::query()
            ->where('puntoventa_cae_id', $puntoventaId)
            ->value('tipotransaccion_id') ?? 0);

        if ($tipoId <= 0) {
            $tipoId = (int) config('gastronomia.tipotransaccion_factura_id', 0);
        }

        if ($tipoId <= 0) {
            return 0;
        }

        $codigo = Tipotransaccion::query()->whereKey($tipoId)->value('codigo');

        return (int) ($codigo ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseArcaVacio(int $empresaId, int $puntoventaId, int $repeticiones): array
    {
        return [
            'empresa_id' => $empresaId,
            'puntoventa_id' => $puntoventaId,
            'puntoventa_codigo' => '',
            'cbte_tipo' => 0,
            'repeticiones' => $repeticiones,
            'muestras_ms' => [],
            'promedio_ms' => 0.0,
            'min_ms' => 0.0,
            'max_ms' => 0.0,
            'ultimo_nro' => null,
            'failover_activo' => ArcaFailoverStore::estaActivo(ArcaFailoverStore::WS_WSFE),
        ];
    }
}

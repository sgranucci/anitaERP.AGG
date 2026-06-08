<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use App\Models\Stock\Articulo;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\GastronomiaFormulaOpcionalesService;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use App\Support\Ventas\Waitry\WaitryCierreJornadaVentanaSupport;
use App\Support\Ventas\Waitry\WaitryDisplayIdSupport;
use App\Support\Ventas\Waitry\WaitryFacturacionDuplicadosSupport;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cuentas externas Waitry — getOrdersPOS (doc. pág. 21).
 *
 * Lista órdenes impagas en Waitry e importa ítems al POS gastronomía (sin correlación con facturas Anita).
 *
 * Estado de pago (getOrdersPOS): impaga si `paid` es false/0 o si payment.total_fee.amount ≤ 0
 * (p. ej. type=cash con monto 0). Cobrada en tótem si `paid` true/1 o monto > 0.
 */
final class WaitryOrdenesExternasService
{
    public function __construct(
        private readonly WaitryHttpClient $httpClient,
        private readonly WaitryAuthService $authService,
    ) {
    }

    /** Resolución bajo demanda: evita ciclo CuentaService → Jornada → CierreTotem → Waitry → Cuenta. */
    private function cuentaService(): GastronomiaCuentaService
    {
        return app(GastronomiaCuentaService::class);
    }

    private function opcionalesService(): GastronomiaFormulaOpcionalesService
    {
        return app(GastronomiaFormulaOpcionalesService::class);
    }

    private function analyticsOrdenesService(): WaitryAnalyticsOrdenesService
    {
        return app(WaitryAnalyticsOrdenesService::class);
    }

    /**
     * Código alfanumérico del papelito Waitry para un orderId (getOrdersPOS).
     */
    public function resolverDisplayIdPorOrderId(int $empresaId, int $orderId): string
    {
        if ($orderId <= 0) {
            return '';
        }

        $orden = $this->obtenerOrdenPorId($empresaId, $orderId, false);

        return $orden !== null ? WaitryDisplayIdSupport::extraerDesdeOrden($orden) : '';
    }

    /**
     * Completa waitry_display_id en cuenta si falta (consulta Waitry por orderId).
     */
    public function completarDisplayIdEnCuenta(CuentaGastronomia $cuenta): void
    {
        $actual = trim((string) ($cuenta->waitry_display_id ?? ''));
        if ($actual !== '' && WaitryDisplayIdSupport::esIdentificadorMonitorValido($actual)) {
            return;
        }

        $orderId = (int) ($cuenta->waitry_order_id ?? 0);
        if ($orderId <= 0 || ! config('waitry.habilitado', false)) {
            return;
        }

        $displayId = $this->resolverDisplayIdPorOrderId((int) $cuenta->empresa_id, $orderId);
        if ($displayId === '') {
            return;
        }

        $cuenta->waitry_display_id = mb_substr($displayId, 0, 64);
        $cuenta->save();
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    private function resolverDisplayIdParaImportacion(
        int $empresaId,
        array $orden,
        int $waitryOrderId,
        string $identificadorPapelito,
    ): string {
        $displayId = $this->extraerDisplayIdOrden($orden);
        if ($displayId !== '') {
            return $displayId;
        }

        $identificadorPapelito = trim($identificadorPapelito);
        if ($identificadorPapelito !== '' && WaitryDisplayIdSupport::esIdentificadorMonitorValido($identificadorPapelito)) {
            if (WaitryDisplayIdSupport::esContadorMonitorNumerico($identificadorPapelito)) {
                return WaitryDisplayIdSupport::normalizarContadorMonitor($identificadorPapelito);
            }

            return $identificadorPapelito;
        }

        return $this->resolverDisplayIdPorOrderId($empresaId, $waitryOrderId);
    }

    /**
     * @return array{
     *     ok:bool,
     *     ordenes?:list<array<string,mixed>>,
     *     filtro?:array{minutos_atras:int,from:?string,to:?string},
     *     error?:string
     * }
     */
    public function listarOrdenesPendientes(
        int $empresaId,
        ?string $desde = null,
        ?string $hasta = null,
        bool $omitirCache = false,
    ): array {
        if (! config('waitry.habilitado', false)) {
            return ['ok' => false, 'error' => 'Integración Waitry deshabilitada.'];
        }

        if (! $this->authService->credencialesCompletas()) {
            return ['ok' => false, 'error' => 'Waitry: credenciales incompletas.'];
        }

        $placeId = $this->resolverPlaceId($empresaId);
        if ($placeId === null) {
            return ['ok' => false, 'error' => 'Waitry: no hay placeId para la empresa '.$empresaId.'.'];
        }

        $rango = $this->resolverRangoFechasConsulta($desde, $hasta);
        $desdeLimite = $rango['desde_limite'];

        $consulta = $this->consultarOrdenesRaw((int) $placeId, $rango, $omitirCache);
        if (! ($consulta['ok'] ?? false)) {
            return ['ok' => false, 'error' => $consulta['error'] ?? 'Error al consultar órdenes Waitry.'];
        }

        $ordenesRaw = $consulta['ordenes'] ?? [];

        $importadasAbiertas = CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->whereNotNull('waitry_order_id')
            ->pluck('waitry_order_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $facturadasWaitry = VentaGastronomiaEmision::query()
            ->whereNotNull('waitry_order_id')
            ->pluck('waitry_order_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $lista = [];
        foreach ($ordenesRaw as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            if (! $this->ordenPendienteDePago($orden)) {
                continue;
            }

            if (! $this->ordenDentroDeVentanaHoraria($orden, $desdeLimite)) {
                continue;
            }

            $orderId = (int) ($orden['id'] ?? $orden['orderId'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            if (in_array($orderId, $importadasAbiertas, true)) {
                continue;
            }

            if (in_array($orderId, $facturadasWaitry, true)
                || WaitryFacturacionDuplicadosSupport::waitryOrderIdYaFacturado($orderId)) {
                continue;
            }

            $lineas = $this->extraerLineasDesdeOrden($orden);
            $previewSinSku = $lineas === [] ? $this->extraerLineasPreviewNombreSinSku($orden) : [];
            if ($lineas === [] && $previewSinSku === []) {
                continue;
            }

            $lineasMostrar = $lineas !== [] ? $lineas : $previewSinSku;

            $displayId = $this->extraerDisplayIdOrden($orden);
            $cantidadLineas = count($lineasMostrar);
            $cantidadUnidades = $this->cantidadUnidadesLineas($lineasMostrar);

            $lista[] = [
                'waitry_order_id' => $orderId,
                'display_id' => $displayId,
                'external_reference_id' => trim((string) ($orden['external_reference_id'] ?? $orden['externalId'] ?? '')),
                'current_state' => trim((string) ($orden['current_state'] ?? '')),
                'type' => trim((string) ($orden['type'] ?? '')),
                'placed_at' => $this->fechaColocacionOrden($orden),
                'total_estimado' => round(array_sum(array_map(
                    static fn (array $ln): float => (float) $ln['precio_unitario'] * (float) $ln['cantidad'],
                    $lineasMostrar
                )), 2),
                'cantidad_lineas' => $cantidadLineas,
                'cantidad_unidades' => $cantidadUnidades,
                'cantidad_items' => $cantidadUnidades,
                'lineas_preview' => array_slice($lineasMostrar, 0, 8),
                'requiere_mapeo_sku' => $lineas === [] && $previewSinSku !== [],
            ];
        }

        usort($lista, static function (array $a, array $b): int {
            return ($b['waitry_order_id'] ?? 0) <=> ($a['waitry_order_id'] ?? 0);
        });

        return [
            'ok' => true,
            'ordenes' => $lista,
            'filtro' => [
                'minutos_atras' => $rango['minutos_atras'],
                'from' => $rango['from'],
                'to' => $rango['to'],
                'fuente' => $consulta['fuente'] ?? null,
                'from_cache' => (bool) ($consulta['from_cache'] ?? false),
            ],
        ];
    }

    /**
     * Orden puntual por orderId (getOrdersPOS), sin filtrar impagas — cierre de jornada / huecos de ID.
     *
     * @return array<string, mixed>|null
     */
    public function obtenerOrdenPorIdConciliacion(int $empresaId, int $orderId): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        return $this->obtenerOrdenPorId($empresaId, $orderId, false);
    }

    /**
     * Órdenes Waitry faltantes en getordersdetails — solo pantalla Caja → Cierre jornada Waitry.
     * Una consulta getOrdersPOS acotada a la ventana operativa de jornada (misma lógica que el cierre tótem).
     * No usa el cierre gastronómico ni consultas por orderId.
     *
     * @param  list<int>  $orderIds
     * @return array<int, array<string, mixed>>
     */
    public function mapOrdenesPorIdsConciliacion(
        int $empresaId,
        array $orderIds,
        string $fechaJornada,
        mixed $aperturaEn = null,
        mixed $cierreEn = null,
    ): array {
        $orderIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $orderIds),
            static fn (int $id) => $id > 0,
        )));
        if ($orderIds === []) {
            return [];
        }

        $placeId = $this->resolverPlaceId($empresaId);
        if ($placeId === null) {
            return [];
        }

        $fechaJornada = trim($fechaJornada);
        if ($fechaJornada === '') {
            return [];
        }

        $consulta = $this->consultarOrdenesRaw(
            (int) $placeId,
            $this->rangoJornadaConciliacionGetOrdersPos($fechaJornada, $aperturaEn, $cierreEn),
            false,
        );

        if (! ($consulta['ok'] ?? false)) {
            return [];
        }

        $buscados = array_fill_keys($orderIds, true);
        $map = [];
        foreach ($consulta['ordenes'] ?? [] as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            $id = (int) ($orden['id'] ?? $orden['orderId'] ?? 0);
            if ($id > 0 && isset($buscados[$id])) {
                $map[$id] = $orden;
            }
        }

        return $map;
    }

    /**
     * Índice orderId → orden getOrdersPOS en la ventana operativa de jornada (para enriquecer payment).
     *
     * @return array<int, array<string, mixed>>
     */
    public function mapOrdenesPosEnVentanaJornada(
        int $empresaId,
        string $fechaJornada,
        mixed $aperturaEn = null,
        mixed $cierreEn = null,
    ): array {
        $placeId = $this->resolverPlaceId($empresaId);
        if ($placeId === null) {
            return [];
        }

        $fechaJornada = trim($fechaJornada);
        if ($fechaJornada === '') {
            return [];
        }

        $consulta = $this->consultarOrdenesRaw(
            (int) $placeId,
            $this->rangoJornadaConciliacionGetOrdersPos($fechaJornada, $aperturaEn, $cierreEn),
            false,
        );

        if (! ($consulta['ok'] ?? false)) {
            return [];
        }

        $map = [];
        foreach ($consulta['ordenes'] ?? [] as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            $id = (int) ($orden['id'] ?? $orden['orderId'] ?? 0);
            if ($id > 0) {
                $map[$id] = $orden;
            }
        }

        return $map;
    }

    public function invalidarCacheOrdenesPosEmpresa(int $empresaId): void
    {
        $placeId = $this->resolverPlaceId($empresaId);
        if ($placeId === null) {
            return;
        }

        $versionKey = $this->cacheVersionKeyOrdenesPos((int) $placeId);
        if (Cache::has($versionKey)) {
            Cache::increment($versionKey);
        } else {
            Cache::forever($versionKey, 1);
        }
    }

    /**
     * @param  array{
     *     from:?string,
     *     to:?string,
     *     from_date:?string,
     *     to_date:?string,
     *     minutos_atras:int,
     *     desde_limite:?Carbon
     * }  $rango
     * @return array{ok:bool,ordenes?:list<array<string,mixed>>,fuente?:string,from_cache?:bool,error?:string}
     */
    private function consultarOrdenesRaw(int $placeId, array $rango, bool $omitirCache = false): array
    {
        $query = ['placeId' => (string) $placeId];
        if ($rango['from'] !== null && $rango['from'] !== '') {
            $query['from'] = $rango['from'];
        }
        if ($rango['to'] !== null && $rango['to'] !== '') {
            $query['to'] = $rango['to'];
        }

        $respuestaCache = $this->consultarOrdenesGetOrdersPos($placeId, $query, $omitirCache);
        if ($respuestaCache !== null) {
            return $respuestaCache;
        }

        $urlPos = (string) config('waitry.get_orders_url');
        $resultadoPos = $this->httpClient->getJson($urlPos, $query, 'get_orders_pos');

        if ($resultadoPos['ok'] ?? false) {
            $dataPos = is_array($resultadoPos['data'] ?? null) ? $resultadoPos['data'] : [];
            if ($this->respuestaGetOrdersPosUtil($dataPos)) {
                return $this->guardarYDevolverOrdenesPos(
                    $placeId,
                    $query,
                    $this->extraerOrdenesDesdePayload($dataPos),
                );
            }

            Log::info('waitry.get_orders_pos.sin_datos', [
                'place_id' => $placeId,
                'message' => $dataPos['message'] ?? null,
                'errors' => $dataPos['errors'] ?? null,
            ]);

            // Reintento sin from/to si el filtro horario no devolvió órdenes (p. ej. formato o ventana vacía).
            if (isset($query['from']) || isset($query['to'])) {
                $querySinRango = ['placeId' => (string) $placeId];
                $respuestaCacheSinRango = $this->consultarOrdenesGetOrdersPos($placeId, $querySinRango, $omitirCache);
                if ($respuestaCacheSinRango !== null) {
                    return $respuestaCacheSinRango;
                }

                $resultadoPosSinRango = $this->httpClient->getJson(
                    $urlPos,
                    $querySinRango,
                    'get_orders_pos_sin_rango',
                );
                if ($resultadoPosSinRango['ok'] ?? false) {
                    $dataPosSinRango = is_array($resultadoPosSinRango['data'] ?? null)
                        ? $resultadoPosSinRango['data']
                        : [];
                    if ($this->respuestaGetOrdersPosUtil($dataPosSinRango)) {
                        return $this->guardarYDevolverOrdenesPos(
                            $placeId,
                            $querySinRango,
                            $this->extraerOrdenesDesdePayload($dataPosSinRango),
                        );
                    }
                }
            }
        }

        $errorPos = $resultadoPos['error'] ?? null;
        if (is_string($errorPos) && $errorPos !== '') {
            return ['ok' => false, 'error' => $errorPos];
        }

        $dataPos = is_array($resultadoPos['data'] ?? null) ? $resultadoPos['data'] : [];
        $msg = $this->mensajeErrorPayloadWaitry($dataPos);

        return ['ok' => false, 'error' => $msg !== '' ? $msg : 'Waitry getOrdersPOS no devolvió órdenes.'];
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array{ok:bool,ordenes:list<array<string,mixed>>,fuente:string,from_cache?:bool}|null
     */
    private function consultarOrdenesGetOrdersPos(int $placeId, array $query, bool $omitirCache): ?array
    {
        $cacheSeg = max(0, (int) config('waitry.get_orders_cache_segundos', 15));
        if ($omitirCache || $cacheSeg <= 0) {
            return null;
        }

        $cached = Cache::get($this->cacheKeyOrdenesPos($placeId, $query));
        if (! is_array($cached) || ! ($cached['ok'] ?? false)) {
            return null;
        }

        return array_merge($cached, ['from_cache' => true]);
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @param  list<array<string, mixed>>  $ordenes
     * @return array{ok:bool,ordenes:list<array<string,mixed>>,fuente:string}
     */
    private function guardarYDevolverOrdenesPos(int $placeId, array $query, array $ordenes): array
    {
        $respuesta = [
            'ok' => true,
            'ordenes' => $ordenes,
            'fuente' => 'getOrdersPOS',
        ];

        $cacheSeg = max(0, (int) config('waitry.get_orders_cache_segundos', 15));
        if ($cacheSeg > 0) {
            Cache::put($this->cacheKeyOrdenesPos($placeId, $query), $respuesta, $cacheSeg);
        }

        return $respuesta;
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    private function cacheKeyOrdenesPos(int $placeId, array $query): string
    {
        $normalized = $query;
        foreach (['from', 'to'] as $campo) {
            if (! isset($normalized[$campo]) || ! is_scalar($normalized[$campo])) {
                continue;
            }
            try {
                $normalized[$campo] = Carbon::parse((string) $normalized[$campo])
                    ->format('Y-m-d H:i');
            } catch (\Throwable) {
                // Mantener valor original si no parsea.
            }
        }

        ksort($normalized);
        $version = (int) Cache::get($this->cacheVersionKeyOrdenesPos($placeId), 0);

        return 'waitry:orders_pos:'.$placeId.':v'.$version.':'.md5(json_encode($normalized) ?: '');
    }

    private function cacheVersionKeyOrdenesPos(int $placeId): string
    {
        return 'waitry:orders_pos:version:'.$placeId;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function respuestaGetOrdersPosUtil(array $data): bool
    {
        if (array_key_exists('ok', $data) && $data['ok'] === false) {
            return false;
        }

        $ordenes = $data['orders'] ?? $data['response']['orders'] ?? null;
        if ($ordenes === null || $ordenes === '') {
            return false;
        }

        return is_array($ordenes);
    }

    /**
     * @param  mixed  $data
     * @return list<array<string, mixed>>
     */
    private function extraerOrdenesDesdePayload($data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $ordenes = $data['orders'] ?? $data['response']['orders'] ?? $data['response'] ?? null;
        if (is_array($ordenes) && $this->esOrdenWaitry($ordenes) && ! isset($ordenes[0])) {
            $ordenes = [$ordenes];
        }
        if (! is_array($ordenes)) {
            return [];
        }

        $lista = [];
        foreach ($ordenes as $orden) {
            if (is_array($orden)) {
                $lista[] = $orden;
            }
        }

        return $lista;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mensajeErrorPayloadWaitry(array $data): string
    {
        $partes = [];
        foreach (['message', 'msg', 'error'] as $clave) {
            if (! empty($data[$clave]) && is_string($data[$clave])) {
                $partes[] = trim($data[$clave]);
            }
        }
        if (! empty($data['errors']) && is_array($data['errors'])) {
            foreach ($data['errors'] as $err) {
                if (is_string($err) && trim($err) !== '') {
                    $partes[] = trim($err);
                }
            }
        }

        return implode(' ', array_unique($partes));
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    private function fechaColocacionOrden(array $orden): mixed
    {
        $placed = $orden['placed_at'] ?? $orden['created_at'] ?? null;
        if (($placed === null || $placed === '') && isset($orden['timestamp']['date'])) {
            return $orden['timestamp']['date'];
        }

        return $placed;
    }

    /**
     * Vista previa cuando Waitry no envía externalId/externalCode en el ítem.
     *
     * @param  array<string, mixed>  $orden
     * @return list<array{sku:string,titulo:string,cantidad:float,precio_unitario:float}>
     */
    private function extraerLineasPreviewNombreSinSku(array $orden): array
    {
        $items = $orden['orderItems'] ?? null;
        if (! is_array($items) || $items === []) {
            return [];
        }

        $lineas = [];
        foreach ($items as $oi) {
            if (! is_array($oi)) {
                continue;
            }
            if (array_key_exists('paid', $oi) && ! in_array($oi['paid'], [0, '0', false], true)) {
                continue;
            }

            $item = is_array($oi['item'] ?? null) ? $oi['item'] : [];
            $titulo = trim((string) ($item['name'] ?? $item['namePos'] ?? ''));
            if ($titulo === '') {
                continue;
            }

            $cantidad = (float) ($oi['count'] ?? 1);
            if ($cantidad <= 0.) {
                continue;
            }

            $precio = isset($oi['price']) && is_numeric($oi['price'])
                ? (float) $oi['price']
                : (float) ($item['price'] ?? 0);

            $lineas[] = [
                'sku' => '',
                'titulo' => $titulo,
                'cantidad' => $cantidad,
                'precio_unitario' => round($precio, 4),
            ];
        }

        return $lineas;
    }

    /**
     * Rango from/to para getOrdersPOS; por defecto últimos N minutos (WAITRY_GET_ORDERS_MINUTOS_ATRAS).
     *
     * @return array{
     *     from:?string,
     *     to:?string,
     *     from_date:?string,
     *     to_date:?string,
     *     minutos_atras:int,
     *     desde_limite:?Carbon
     * }
     */
    private function resolverRangoFechasConsulta(?string $desde, ?string $hasta): array
    {
        $desde = $desde !== null ? trim($desde) : '';
        $hasta = $hasta !== null ? trim($hasta) : '';
        $tz = (string) config('app.timezone', 'UTC');

        if ($desde !== '') {
            $desdeDt = null;
            $hastaDt = null;
            try {
                $desdeDt = Carbon::parse($desde, $tz);
                $hastaDt = $hasta !== '' ? Carbon::parse($hasta, $tz) : $desdeDt->copy();
            } catch (\Throwable) {
                $hoy = Carbon::now($tz)->format('Y-m-d');

                return [
                    'from' => $desde,
                    'to' => $hasta !== '' ? $hasta : null,
                    'from_date' => $hoy,
                    'to_date' => $hoy,
                    'minutos_atras' => 0,
                    'desde_limite' => null,
                ];
            }

            return [
                'from' => $this->formatoFromToGetOrdersPos($desdeDt),
                'to' => $hasta !== '' ? $this->formatoFromToGetOrdersPos($hastaDt) : null,
                'from_date' => $desdeDt->format('Y-m-d'),
                'to_date' => $hastaDt->format('Y-m-d'),
                'minutos_atras' => 0,
                'desde_limite' => null,
            ];
        }

        $minutos = max(0, (int) config('waitry.get_orders_minutos_atras', 20));
        $hastaDt = Carbon::now($tz);
        if ($minutos <= 0) {
            $hoy = $hastaDt->format('Y-m-d');

            return [
                'from' => null,
                'to' => null,
                'from_date' => $hoy,
                'to_date' => $hoy,
                'minutos_atras' => 0,
                'desde_limite' => null,
            ];
        }

        $desdeDt = $hastaDt->copy()->subMinutes($minutos);

        return [
            'from' => $this->formatoFromToGetOrdersPos($desdeDt),
            'to' => $this->formatoFromToGetOrdersPos($hastaDt),
            'from_date' => $desdeDt->format('Y-m-d'),
            'to_date' => $hastaDt->format('Y-m-d'),
            'minutos_atras' => $minutos,
            'desde_limite' => $desdeDt,
        ];
    }

    /**
     * Formato Waitry getOrdersPOS para from/to: "YYYY-MM-DD HH:mm:ss" (sin timezone).
     */
    private function formatoFromToGetOrdersPos(Carbon $fecha): string
    {
        return $fecha->format('Y-m-d H:i:s');
    }

    /**
     * Ventana getOrdersPOS para conciliación tesorería: alineada con {@see WaitryCierreJornadaVentanaSupport}.
     *
     * @return array{
     *     from:string,
     *     to:string,
     *     from_date:string,
     *     to_date:string,
     *     minutos_atras:int,
     *     desde_limite:?Carbon
     * }
     */
    private function rangoJornadaConciliacionGetOrdersPos(
        string $fechaJornada,
        mixed $aperturaEn = null,
        mixed $cierreEn = null,
    ): array {
        $ventana = WaitryCierreJornadaVentanaSupport::resolverParaCierreJornada(
            $fechaJornada,
            $aperturaEn,
            $cierreEn,
        )['ventana'];

        return [
            'from' => $this->formatoFromToGetOrdersPos($ventana['desde']),
            'to' => $this->formatoFromToGetOrdersPos($ventana['hasta']),
            'from_date' => $ventana['desde']->format('Y-m-d'),
            'to_date' => $ventana['hasta']->format('Y-m-d'),
            'minutos_atras' => 0,
            'desde_limite' => null,
        ];
    }

    /**
     * @return array{
     *     from:string,
     *     to:string,
     *     from_date:string,
     *     to_date:string,
     *     minutos_atras:int,
     *     desde_limite:?Carbon
     * }
     */
    private function rangoUltimosDiasGetOrdersPos(int $dias): array
    {
        $tz = (string) config('app.timezone', 'UTC');
        $hastaDt = Carbon::now($tz);
        $desdeDt = $hastaDt->copy()->subDays(max(1, $dias));

        return [
            'from' => $this->formatoFromToGetOrdersPos($desdeDt),
            'to' => $this->formatoFromToGetOrdersPos($hastaDt),
            'from_date' => $desdeDt->format('Y-m-d'),
            'to_date' => $hastaDt->format('Y-m-d'),
            'minutos_atras' => 0,
            'desde_limite' => null,
        ];
    }

    /**
     * Filtro defensivo por placed_at si Waitry devuelve órdenes fuera del rango pedido.
     *
     * @param  array<string, mixed>  $orden
     */
    private function ordenDentroDeVentanaHoraria(array $orden, ?Carbon $desdeLimite): bool
    {
        if ($desdeLimite === null) {
            return true;
        }

        $placed = $this->fechaColocacionOrden($orden);
        if ($placed === null || $placed === '') {
            return true;
        }

        try {
            return Carbon::parse((string) $placed)->gte($desdeLimite);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @return array{ok:bool,cuenta?:CuentaGastronomia,errores?:list<string>,error?:string}
     */
    public function importarOrdenEnCuenta(
        ConfiguracionPuntoventaGastronomia $cfg,
        string $identificadorPapelito,
        array $datosApertura = [],
        ?int $cuentaId = null,
        bool $permitirCualquierEstadoPago = false,
    ): array {
        if (! config('waitry.habilitado', false)) {
            return ['ok' => false, 'error' => 'Integración Waitry deshabilitada.'];
        }

        $empresaId = (int) $cfg->empresa_id;
        $identificadorPapelito = trim($identificadorPapelito);
        if ($identificadorPapelito === '') {
            return ['ok' => false, 'error' => 'Debe indicar el número del monitor o código Waitry.'];
        }

        $resolucion = $this->obtenerOrdenPorIdentificadorPapelito(
            $empresaId,
            $identificadorPapelito,
            ! $permitirCualquierEstadoPago,
        );
        if ($resolucion === null) {
            return [
                'ok' => false,
                'error' => 'Orden Waitry «'.$identificadorPapelito.'» no encontrada.',
            ];
        }

        [$orden, $waitryOrderId] = $resolucion;

        if (WaitryFacturacionDuplicadosSupport::waitryOrderIdYaFacturado($waitryOrderId, $cuentaId)) {
            return [
                'ok' => false,
                'error' => WaitryFacturacionDuplicadosSupport::mensajeOrdenYaFacturada($waitryOrderId),
            ];
        }

        $cobroTotem = $this->ordenCobradaEnTotemWaitry($orden);

        $lineasWaitry = $this->extraerLineasDesdeOrden($orden);
        if ($lineasWaitry === []) {
            return ['ok' => false, 'error' => 'La orden Waitry no tiene ítems para importar.'];
        }

        $cuenta = null;
        if ($cuentaId !== null && $cuentaId > 0) {
            $cuenta = CuentaGastronomia::query()
                ->where('id', $cuentaId)
                ->where('empresa_id', $empresaId)
                ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
                ->first();
            if ($cuenta === null) {
                return ['ok' => false, 'error' => 'Cuenta no encontrada o no está abierta.'];
            }
            if ($cuenta->lineas()->exists()) {
                return ['ok' => false, 'error' => 'La cuenta ya tiene consumos; abra una cuenta vacía o cree una nueva.'];
            }
        } else {
            try {
                $cuenta = $this->cuentaService()->abrirCuentaLibre($empresaId, $cfg, $datosApertura);
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        $errores = [];
        foreach ($lineasWaitry as $ln) {
            $sku = (string) $ln['sku'];
            $articulo = $this->resolverArticuloPorCodigoWaitry($cfg, $sku);
            if ($articulo === null) {
                $errores[] = 'SKU «'.$sku.'» ('.($ln['titulo'] ?? '').') no está en catálogo gastronomía.';

                continue;
            }

            if (FormulaArticuloGastronomia::opcionalesHabilitados()) {
                $grupos = $this->opcionalesService()->gruposOpcionalesPorArticulo($articulo);
                if ($grupos !== []) {
                    $msgOpc = 'SKU «'.$sku.'» ('.($ln['titulo'] ?? '').') requiere opcionales de fórmula: '
                        .'no se importa desde Waitry. Carguelo manualmente en el POS (modal de opcionales).';
                    $errores[] = $msgOpc;
                    Log::info('waitry.item_omitido_opcionales', [
                        'waitry_order_id' => $waitryOrderId,
                        'sku' => $sku,
                        'articulo_id' => (int) $articulo->id,
                        'grupos_opcionales' => count($grupos),
                    ]);

                    continue;
                }
            }

            try {
                $this->cuentaService()->agregarLinea(
                    $cuenta,
                    (int) $articulo->id,
                    (float) $ln['cantidad'],
                    (float) $ln['precio_unitario'],
                    [],
                    0.0,
                );
            } catch (\Throwable $e) {
                $errores[] = $sku.': '.$e->getMessage();
            }
        }

        $cuenta->waitry_order_id = $waitryOrderId;
        $displayId = $this->resolverDisplayIdParaImportacion(
            $empresaId,
            $orden,
            $waitryOrderId,
            $identificadorPapelito,
        );
        $cuenta->waitry_display_id = $displayId !== '' ? $displayId : null;
        $cuenta->waitry_cobro_totem = $cobroTotem;
        $waitryTipoPago = $cobroTotem
            ? WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($orden)
            : null;
        if ($cobroTotem && ($waitryTipoPago === null || $waitryTipoPago === '') && $waitryOrderId > 0) {
            $ordenPos = $this->obtenerOrdenPorIdConciliacion($empresaId, $waitryOrderId);
            if ($ordenPos !== null) {
                $waitryTipoPago = WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($ordenPos);
            }
        }
        $cuenta->waitry_tipo_pago = $waitryTipoPago;
        $cuenta->save();

        $cuenta = $this->cuentaService()->cuentaConLineas($cuenta->id);

        if ($cuenta->lineas->isEmpty()) {
            $soloOpcionales = $errores !== []
                && count($errores) === count($lineasWaitry)
                && collect($errores)->every(
                    static fn (string $e): bool => str_contains($e, 'requiere opcionales de fórmula')
                        || str_contains($e, 'Debe seleccionar opcional')
                );

            if ($soloOpcionales) {
                Log::info('waitry.cuenta_sin_lineas_solo_opcionales', [
                    'waitry_order_id' => $waitryOrderId,
                    'cuenta_id' => $cuenta->id,
                    'errores' => $errores,
                ]);

                return [
                    'ok' => true,
                    'cuenta' => $cuenta,
                    'errores' => $errores,
                    'requiere_carga_opcionales_en_pos' => true,
                ];
            }

            $cuenta->delete();

            return [
                'ok' => false,
                'error' => 'No se pudo importar ningún ítem.',
                'errores' => $errores,
            ];
        }

        if ($errores !== []) {
            Log::warning('waitry.importacion_parcial', [
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuenta->id,
                'errores' => $errores,
            ]);
        }

        $this->invalidarCacheOrdenesPosEmpresa($empresaId);

        return [
            'ok' => true,
            'cuenta' => $cuenta,
            'errores' => $errores,
        ];
    }

    /**
     * Resuelve orden por secuencia del monitor (getordersdetails.sequence), orderId global
     * o código alfanumérico legacy (display_id, E-…).
     *
     * @return array{0: array<string, mixed>, 1: int}|null
     */
    private function obtenerOrdenPorIdentificadorPapelito(
        int $empresaId,
        string $identificador,
        bool $soloPendientesDePago = true,
    ): ?array {
        $identificador = trim($identificador);
        if ($identificador === '') {
            return null;
        }

        if (WaitryDisplayIdSupport::esContadorMonitorNumerico($identificador)) {
            $porSecuencia = $this->resolverOrdenPorSecuenciaMonitor(
                $empresaId,
                $identificador,
                $soloPendientesDePago,
            );
            if ($porSecuencia !== null) {
                return $porSecuencia;
            }
        }

        if (ctype_digit($identificador)) {
            $orderId = (int) $identificador;
            if ($orderId > 0) {
                $orden = $this->obtenerOrdenPorId($empresaId, $orderId, $soloPendientesDePago);
                if ($orden !== null) {
                    return [$orden, $orderId];
                }
            }
        }

        $orden = $this->buscarOrdenPorCodigoPapelitoEnListado($empresaId, $identificador, $soloPendientesDePago);
        if ($orden === null) {
            return null;
        }

        $orderId = (int) ($orden['id'] ?? $orden['orderId'] ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        return [$orden, $orderId];
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}|null
     */
    private function resolverOrdenPorSecuenciaMonitor(
        int $empresaId,
        string $secuencia,
        bool $soloPendientesDePago,
    ): ?array {
        $orderId = $this->buscarOrderIdPorSecuenciaMonitor($empresaId, $secuencia);
        if ($orderId === null) {
            return null;
        }

        $orden = $this->obtenerOrdenPorId($empresaId, $orderId, $soloPendientesDePago);
        if ($orden === null) {
            return null;
        }

        return [$orden, $orderId];
    }

    private function buscarOrderIdPorSecuenciaMonitor(int $empresaId, string $secuencia): ?int
    {
        $secuencia = WaitryDisplayIdSupport::normalizarContadorMonitor($secuencia);
        if ($secuencia === '') {
            return null;
        }

        $tz = (string) config('app.timezone', 'UTC');
        $hoy = Carbon::now($tz);
        $fechas = array_values(array_unique([
            $hoy->format('Y-m-d'),
            $hoy->copy()->subDay()->format('Y-m-d'),
        ]));

        foreach ($fechas as $fecha) {
            $consulta = $this->analyticsOrdenesService()->ordenesPorRangoFecha($empresaId, $fecha, $fecha);
            if (! ($consulta['ok'] ?? false)) {
                continue;
            }

            $orderId = WaitryDisplayIdSupport::orderIdDesdeOrdenesPorSecuencia(
                $secuencia,
                $consulta['ordenes'] ?? [],
            );
            if ($orderId !== null) {
                return $orderId;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarOrdenPorCodigoPapelitoEnListado(
        int $empresaId,
        string $identificador,
        bool $soloPendientesDePago,
    ): ?array {
        $placeId = $this->resolverPlaceId($empresaId);
        if ($placeId === null) {
            return null;
        }

        $consulta = $this->consultarOrdenesRaw(
            (int) $placeId,
            $this->rangoUltimosDiasGetOrdersPos(2),
            true,
        );

        if (! ($consulta['ok'] ?? false)) {
            return null;
        }

        $needle = strtoupper($identificador);
        foreach ($consulta['ordenes'] ?? [] as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            if ($soloPendientesDePago && ! $this->ordenPendienteDePago($orden)) {
                continue;
            }
            foreach ($this->codigosPapelitoOrden($orden) as $codigo) {
                if (strtoupper($codigo) === $needle) {
                    return $orden;
                }
            }
        }

        return null;
    }

    /**
     * Código alfanumérico del papelito / tótem (display_id, external_reference, etc.).
     *
     * @param  array<string, mixed>  $orden
     */
    private function extraerDisplayIdOrden(array $orden): string
    {
        return WaitryDisplayIdSupport::extraerDesdeOrden($orden);
    }

    /**
     * @param  list<array{cantidad?:float|int|string}>  $lineas
     */
    private function cantidadUnidadesLineas(array $lineas): float
    {
        $total = 0.;
        foreach ($lineas as $ln) {
            $total += (float) ($ln['cantidad'] ?? 0);
        }

        return round($total, 4);
    }

    /**
     * @param  array<string, mixed>  $orden
     * @return list<string>
     */
    private function codigosPapelitoOrden(array $orden): array
    {
        $codigos = [];
        foreach (['display_id', 'external_reference_id', 'externalDeliveryId', 'externalId'] as $campo) {
            $valor = trim((string) ($orden[$campo] ?? ''));
            if ($valor !== '') {
                $codigos[] = $valor;
            }
        }

        $secuencia = WaitryDisplayIdSupport::normalizarContadorMonitor($orden['sequence'] ?? null);
        if ($secuencia !== '') {
            $codigos[] = $secuencia;
        }

        $orderId = (int) ($orden['id'] ?? $orden['orderId'] ?? 0);
        if ($orderId > 0) {
            $codigos[] = (string) $orderId;
        }

        return array_values(array_unique($codigos));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerOrdenPorId(int $empresaId, int $orderId, bool $soloPendientesDePago = true): ?array
    {
        $placeId = $this->resolverPlaceId($empresaId);
        if ($placeId === null) {
            return null;
        }

        $url = (string) config('waitry.get_orders_url');
        $estrategias = [
            ['placeId' => (string) $placeId, 'orderId' => $orderId],
            ['placeId' => (string) $placeId],
        ];

        foreach ($estrategias as $query) {
            $etiqueta = isset($query['orderId']) ? 'get_orders_pos_detalle' : 'get_orders_pos_detalle_lista';
            $resultado = $this->httpClient->getJson($url, $query, $etiqueta);
            if (! ($resultado['ok'] ?? false)) {
                continue;
            }

            $data = is_array($resultado['data'] ?? null) ? $resultado['data'] : [];
            if (! $this->respuestaGetOrdersPosUtil($data)) {
                continue;
            }

            $orden = $this->extraerOrdenDesdeRespuesta($data, $orderId, $soloPendientesDePago);
            if ($orden !== null) {
                return $orden;
            }
        }

        $consulta = $this->consultarOrdenesRaw(
            (int) $placeId,
            $this->rangoUltimosDiasGetOrdersPos(2),
            true,
        );

        if (! ($consulta['ok'] ?? false)) {
            return null;
        }

        foreach ($consulta['ordenes'] ?? [] as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            $id = (int) ($orden['id'] ?? $orden['orderId'] ?? 0);
            if ($id !== $orderId) {
                continue;
            }
            if ($soloPendientesDePago && ! $this->ordenPendienteDePago($orden)) {
                continue;
            }

            return $orden;
        }

        return null;
    }

    /**
     * @param  mixed  $data
     * @return array<string, mixed>|null
     */
    private function extraerOrdenDesdeRespuesta($data, int $orderId, bool $soloPendientesDePago): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $ordenes = $data['orders'] ?? $data['response']['orders'] ?? $data['order'] ?? null;
        if (is_array($ordenes) && $this->esOrdenWaitry($ordenes) && ! isset($ordenes[0])) {
            $ordenes = [$ordenes];
        }
        if (! is_array($ordenes)) {
            return null;
        }

        foreach ($ordenes as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            $id = (int) ($orden['id'] ?? $orden['orderId'] ?? 0);
            if ($id !== $orderId) {
                continue;
            }
            if ($soloPendientesDePago && ! $this->ordenPendienteDePago($orden)) {
                continue;
            }

            return $orden;
        }

        foreach ($ordenes as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            $id = (int) ($orden['id'] ?? $orden['orderId'] ?? 0);
            if ($id !== $orderId) {
                continue;
            }

            return $orden;
        }

        return null;
    }

    /**
     * @param  array<mixed>  $row
     */
    private function esOrdenWaitry(array $row): bool
    {
        return isset($row['id']) || isset($row['orderId']) || isset($row['cart']) || isset($row['orderItems']);
    }

    /**
     * Impaga en Waitry (getOrdersPOS): `paid` false/0, sin cobro (total_fee.amount ≤ 0),
     * o sin bloque payment. Cobrada si `paid` true/1 o monto > 0.
     */
    private function ordenPendienteDePago(array $orden): bool
    {
        if (array_key_exists('paid', $orden) && $orden['paid'] !== null) {
            if (in_array($orden['paid'], [1, '1', true], true)) {
                return false;
            }
            if (in_array($orden['paid'], [0, '0', false], true)) {
                return true;
            }
        }

        $estado = mb_strtolower(trim((string) ($orden['current_state'] ?? '')));
        if (in_array($estado, ['closed', 'cancelled', 'rejected'], true)) {
            return false;
        }

        return $this->montoPagoWaitry($orden) <= 0.0001;
    }

    /**
     * Cobrada en tótem Waitry: `paid` true/1 o monto de payment > 0.
     */
    private function ordenCobradaEnTotemWaitry(array $orden): bool
    {
        if (array_key_exists('paid', $orden)) {
            return in_array($orden['paid'], [1, '1', true], true);
        }

        return $this->montoPagoWaitry($orden) > 0.0001;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    private function montoPagoWaitry(array $orden): float
    {
        $payment = $orden['payment'] ?? null;
        if (! is_array($payment)) {
            return 0.0;
        }

        $totalFee = $payment['total_fee'] ?? null;
        if (is_array($totalFee) && isset($totalFee['amount'])) {
            return (float) $totalFee['amount'];
        }
        if (is_numeric($totalFee)) {
            return (float) $totalFee;
        }

        return 0.0;
    }

    /**
     * Ítems del carrito getOrdersPOS (cart.items: external_id, quantity, price)
     * o orderItems (getordersdetails / pushExternalOrder).
     *
     * @param  bool  $incluirItemsPagados  true en cierre jornada: las órdenes ya cobradas traen paid=1 en cada ítem.
     * @return list<array{sku:string,titulo:string,cantidad:float,precio_unitario:float}>
     */
    public function extraerLineasDesdeOrden(array $orden, bool $incluirItemsPagados = false): array
    {
        $items = $orden['cart']['items'] ?? $orden['items'] ?? null;
        if (! is_array($items) || $items === []) {
            if (! empty($orden['orderItems']) && is_array($orden['orderItems'])) {
                return $this->extraerLineasFormatoPush($orden['orderItems'], $incluirItemsPagados);
            }

            return [];
        }

        $lineas = [];
        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sku = $this->extraerSkuWaitryDesdeFila($row);
            if ($sku === '') {
                continue;
            }

            $cantidad = (float) ($row['quantity'] ?? $row['count'] ?? 1);
            if ($cantidad <= 0.) {
                continue;
            }

            $precioUnitario = $this->precioUnitarioDesdeItemWaitry($row, $cantidad);
            if ($precioUnitario < 0.) {
                continue;
            }

            $lineas[] = [
                'sku' => $sku,
                'titulo' => $this->extraerTituloWaitryDesdeFila($row, $sku),
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
            ];
        }

        if ($lineas === [] && ! empty($orden['orderItems']) && is_array($orden['orderItems'])) {
            return $this->extraerLineasFormatoPush($orden['orderItems'], $incluirItemsPagados);
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $orderItems
     * @return list<array{sku:string,titulo:string,cantidad:float,precio_unitario:float}>
     */
    private function extraerLineasFormatoPush(array $orderItems, bool $incluirItemsPagados = false): array
    {
        $lineas = [];
        foreach ($orderItems as $oi) {
            if (! is_array($oi)) {
                continue;
            }
            if (! $incluirItemsPagados
                && array_key_exists('paid', $oi)
                && ! in_array($oi['paid'], [0, '0', false], true)) {
                continue;
            }

            $item = is_array($oi['item'] ?? null) ? $oi['item'] : [];
            $sku = $this->extraerSkuWaitryDesdeFila($oi, $item);
            if ($sku === '') {
                continue;
            }

            $cantidad = (float) ($oi['count'] ?? 1);
            if ($cantidad <= 0.) {
                continue;
            }

            $precio = isset($oi['price']) && is_numeric($oi['price'])
                ? (float) $oi['price']
                : (float) ($item['price'] ?? 0);

            $lineas[] = [
                'sku' => $sku,
                'titulo' => $this->extraerTituloWaitryDesdeFila($oi, $sku, $item),
                'cantidad' => $cantidad,
                'precio_unitario' => round($precio, 4),
            ];
        }

        return $lineas;
    }

    /**
     * SKU del ítem o de variaciones/modificadores (ej. «Cerveza Goyeneche» → variación «Goyeneche Blonde» V0942).
     *
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $item
     */
    private function extraerSkuWaitryDesdeFila(array $fila, array $item = []): string
    {
        if ($item === [] && is_array($fila['item'] ?? null)) {
            $item = $fila['item'];
        }

        $sku = trim((string) (
            $fila['external_id']
            ?? $fila['externalId']
            ?? $fila['externalCode']
            ?? $fila['external_code']
            ?? $item['externalId']
            ?? $item['external_id']
            ?? $item['externalCode']
            ?? $item['external_code']
            ?? ''
        ));
        if ($sku !== '') {
            return $sku;
        }

        return $this->extraerSkuDesdeVariacionesWaitry($fila);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function extraerSkuDesdeVariacionesWaitry(array $fila): string
    {
        $colecciones = [
            $fila['orderItemVariations'] ?? null,
            $fila['selected_modifier_groups'] ?? null,
        ];

        foreach ($colecciones as $variaciones) {
            if (! is_array($variaciones) || $variaciones === []) {
                continue;
            }

            foreach ($variaciones as $variacion) {
                if (! is_array($variacion)) {
                    continue;
                }

                $itemVariacion = $variacion['itemVariation']['item']
                    ?? $variacion['item']
                    ?? null;
                if (is_array($itemVariacion)) {
                    $sku = trim((string) (
                        $itemVariacion['externalId']
                        ?? $itemVariacion['external_id']
                        ?? $itemVariacion['externalCode']
                        ?? $itemVariacion['external_code']
                        ?? ''
                    ));
                    if ($sku !== '') {
                        return $sku;
                    }
                }

                $sku = trim((string) (
                    $variacion['external_id']
                    ?? $variacion['externalId']
                    ?? $variacion['externalCode']
                    ?? $variacion['external_code']
                    ?? ''
                ));
                if ($sku !== '') {
                    return $sku;
                }

                $anidados = $variacion['selected_modifier_groups'] ?? null;
                if (is_array($anidados) && $anidados !== []) {
                    $sku = $this->extraerSkuDesdeVariacionesWaitry(['selected_modifier_groups' => $anidados]);
                    if ($sku !== '') {
                        return $sku;
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $item
     */
    private function extraerTituloWaitryDesdeFila(array $fila, string $sku, array $item = []): string
    {
        if ($item === [] && is_array($fila['item'] ?? null)) {
            $item = $fila['item'];
        }

        $titulo = trim((string) (
            $fila['title']
            ?? $item['name']
            ?? $item['namePos']
            ?? ''
        ));

        foreach ([$fila['orderItemVariations'] ?? null, $fila['selected_modifier_groups'] ?? null] as $variaciones) {
            if (! is_array($variaciones)) {
                continue;
            }
            foreach ($variaciones as $variacion) {
                if (! is_array($variacion)) {
                    continue;
                }
                $itemVariacion = $variacion['itemVariation']['item'] ?? $variacion['item'] ?? null;
                if (! is_array($itemVariacion)) {
                    continue;
                }
                $nombreVariacion = trim((string) ($itemVariacion['name'] ?? $itemVariacion['namePos'] ?? ''));
                if ($nombreVariacion === '') {
                    continue;
                }
                $skuVariacion = trim((string) (
                    $itemVariacion['externalId']
                    ?? $itemVariacion['external_id']
                    ?? $itemVariacion['externalCode']
                    ?? $itemVariacion['external_code']
                    ?? ''
                ));
                if ($skuVariacion === $sku) {
                    return $nombreVariacion;
                }
            }
        }

        return $titulo !== '' ? $titulo : $sku;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function precioUnitarioDesdeItemWaitry(array $row, float $cantidad): float
    {
        if (isset($row['price']) && is_numeric($row['price'])) {
            return round((float) $row['price'], 4);
        }

        $price = $row['price'] ?? null;
        if (! is_array($price)) {
            return 0.0;
        }

        $unit = isset($price['unit_price']['amount']) && is_numeric($price['unit_price']['amount'])
            ? (float) $price['unit_price']['amount']
            : null;
        $total = isset($price['total_price']['amount']) && is_numeric($price['total_price']['amount'])
            ? (float) $price['total_price']['amount']
            : null;
        $qty = max(1.0, $cantidad);

        if ($unit !== null && $total !== null) {
            // Waitry a veces repite unit_price en total_price aunque quantity > 1 (ej. 2× Coca).
            if ($qty > 1. && abs($total - $unit) < 0.01) {
                return round($unit, 4);
            }

            if ($total + 0.01 >= $unit * $qty) {
                return round($total / $qty, 4);
            }

            if ($qty <= 1.) {
                return round($total, 4);
            }
        }

        if ($total !== null) {
            return round($total / $qty, 4);
        }

        if ($unit !== null) {
            return round($unit, 4);
        }

        return 0.0;
    }

    private function resolverArticuloPorCodigoWaitry(ConfiguracionPuntoventaGastronomia $cfg, string $codigo): ?Articulo
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $candidatos = array_values(array_unique(array_filter([
            $codigo,
            mb_strtoupper($codigo, 'UTF-8'),
            GastronomiaSkuCatalogoSupport::skuDesdeSufijoDigitos($codigo),
            GastronomiaSkuCatalogoSupport::prefijo().$codigo,
            mb_strtoupper(GastronomiaSkuCatalogoSupport::prefijo().$codigo, 'UTF-8'),
        ])));

        foreach ($candidatos as $sku) {
            $articulo = $this->cuentaService()->buscarArticuloCatalogoPorSku($cfg, $sku);
            if ($articulo !== null) {
                return $articulo;
            }
        }

        return null;
    }

    private function resolverPlaceId(int $empresaId): ?int
    {
        $map = config('waitry.place_id_por_empresa', []);
        if (! is_array($map)) {
            return null;
        }

        $placeId = (int) ($map[$empresaId] ?? 0);

        return $placeId > 0 ? $placeId : null;
    }
}

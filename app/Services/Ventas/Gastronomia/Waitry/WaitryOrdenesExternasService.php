<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use App\Models\Stock\Articulo;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use App\Support\Ventas\Waitry\WaitryFacturacionDuplicadosSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Cuentas externas Waitry — getOrdersPOS (doc. pág. 21) con fallback a getordersdetails.
 *
 * Lista órdenes sin pago en Waitry e importa ítems al POS gastronomía (sin correlación con facturas Anita).
 */
final class WaitryOrdenesExternasService
{
    public function __construct(
        private readonly WaitryHttpClient $httpClient,
        private readonly WaitryAuthService $authService,
        private readonly GastronomiaCuentaService $cuentaService,
    ) {
    }

    /**
     * @return array{
     *     ok:bool,
     *     ordenes?:list<array<string,mixed>>,
     *     filtro?:array{minutos_atras:int,from:?string,to:?string},
     *     error?:string
     * }
     */
    public function listarOrdenesPendientes(int $empresaId, ?string $desde = null, ?string $hasta = null): array
    {
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

        $consulta = $this->consultarOrdenesRaw((int) $placeId, $rango);
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

            $lista[] = [
                'waitry_order_id' => $orderId,
                'display_id' => trim((string) (
                    $orden['display_id']
                    ?? $orden['external_reference_id']
                    ?? $orden['externalDeliveryId']
                    ?? ''
                )),
                'external_reference_id' => trim((string) ($orden['external_reference_id'] ?? $orden['externalId'] ?? '')),
                'current_state' => trim((string) ($orden['current_state'] ?? '')),
                'type' => trim((string) ($orden['type'] ?? '')),
                'placed_at' => $this->fechaColocacionOrden($orden),
                'total_estimado' => round(array_sum(array_map(
                    static fn (array $ln): float => (float) $ln['precio_unitario'] * (float) $ln['cantidad'],
                    $lineasMostrar
                )), 2),
                'cantidad_items' => count($lineasMostrar),
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
            ],
        ];
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
     * @return array{ok:bool,ordenes?:list<array<string,mixed>>,fuente?:string,error?:string}
     */
    private function consultarOrdenesRaw(int $placeId, array $rango): array
    {
        $query = ['placeId' => (string) $placeId];
        if ($rango['from'] !== null && $rango['from'] !== '') {
            $query['from'] = $rango['from'];
        }
        if ($rango['to'] !== null && $rango['to'] !== '') {
            $query['to'] = $rango['to'];
        }

        $urlPos = (string) config('waitry.get_orders_url');
        $resultadoPos = $this->httpClient->getJson($urlPos, $query, 'get_orders_pos');

        if ($resultadoPos['ok'] ?? false) {
            $dataPos = is_array($resultadoPos['data'] ?? null) ? $resultadoPos['data'] : [];
            if ($this->respuestaGetOrdersPosUtil($dataPos)) {
                return [
                    'ok' => true,
                    'ordenes' => $this->extraerOrdenesDesdePayload($dataPos),
                    'fuente' => 'getOrdersPOS',
                ];
            }

            Log::info('waitry.get_orders_pos.sin_datos', [
                'place_id' => $placeId,
                'message' => $dataPos['message'] ?? null,
                'errors' => $dataPos['errors'] ?? null,
            ]);
        }

        if (! config('waitry.get_orders_usar_detalles_fallback', true)) {
            $errorPos = $resultadoPos['error'] ?? null;
            if (is_string($errorPos) && $errorPos !== '') {
                return ['ok' => false, 'error' => $errorPos];
            }

            $dataPos = is_array($resultadoPos['data'] ?? null) ? $resultadoPos['data'] : [];
            $msg = $this->mensajeErrorPayloadWaitry($dataPos);

            return ['ok' => false, 'error' => $msg !== '' ? $msg : 'Waitry getOrdersPOS no devolvió órdenes.'];
        }

        $fromDate = $rango['from_date'] ?? null;
        $toDate = $rango['to_date'] ?? null;
        if ($fromDate === null || $toDate === null) {
            $tz = (string) config('app.timezone', 'UTC');
            $hoy = Carbon::now($tz)->format('Y-m-d');
            $fromDate = $fromDate ?? $hoy;
            $toDate = $toDate ?? $hoy;
        }

        $urlDetalles = (string) config('waitry.get_orders_details_url');
        $resultadoDet = $this->httpClient->postJson($urlDetalles, [
            'placeId' => $placeId,
            'from' => $fromDate,
            'to' => $toDate,
        ], 'get_orders_details');

        if (! ($resultadoDet['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => $this->combinarErroresConsultaOrdenes(
                    $resultadoDet['error'] ?? 'Error al consultar órdenes Waitry (getordersdetails).',
                    $resultadoPos['error'] ?? null,
                ),
            ];
        }

        $dataDet = is_array($resultadoDet['data'] ?? null) ? $resultadoDet['data'] : [];
        if (($dataDet['ok'] ?? true) === false) {
            return [
                'ok' => false,
                'error' => $this->mensajeErrorPayloadWaitry($dataDet) ?: 'Waitry getordersdetails rechazó la consulta.',
            ];
        }

        $ordenes = [];
        foreach ($this->extraerOrdenesDesdePayload($dataDet) as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            $ordenes[] = $this->normalizarOrdenAnalytics($orden);
        }

        return [
            'ok' => true,
            'ordenes' => $ordenes,
            'fuente' => 'getordersdetails',
        ];
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
     * Normaliza orden de analytics/getordersdetails al formato usado por extractores POS.
     *
     * @param  array<string, mixed>  $orden
     * @return array<string, mixed>
     */
    private function normalizarOrdenAnalytics(array $orden): array
    {
        $orderId = (int) ($orden['orderId'] ?? $orden['id'] ?? 0);
        $placedAt = $orden['placed_at'] ?? null;
        if (($placedAt === null || $placedAt === '') && isset($orden['timestamp']['date'])) {
            $placedAt = $orden['timestamp']['date'];
        }

        return array_merge($orden, [
            'id' => $orderId,
            'orderId' => $orderId,
            'placed_at' => $placedAt,
            'display_id' => $orden['display_id'] ?? $orden['externalDeliveryId'] ?? null,
            'external_reference_id' => $orden['external_reference_id'] ?? $orden['externalId'] ?? null,
        ]);
    }

    private function combinarErroresConsultaOrdenes(?string $errorDetalles, ?string $errorPos): string
    {
        $partes = [];
        foreach ([$errorDetalles, $errorPos] as $error) {
            if (! is_string($error)) {
                continue;
            }
            $error = trim($error);
            if ($error === '') {
                continue;
            }
            if (! in_array($error, $partes, true)) {
                $partes[] = $error;
            }
        }

        if ($partes === []) {
            return 'No se pudieron consultar órdenes en Waitry.';
        }

        if (count($partes) === 1) {
            return $partes[0];
        }

        return $partes[0].' (getOrdersPOS: '.$partes[1].')';
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
                'from' => $desde,
                'to' => $hasta !== '' ? $hasta : null,
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
            'from' => $desdeDt->toIso8601String(),
            'to' => $hastaDt->toIso8601String(),
            'from_date' => $desdeDt->format('Y-m-d'),
            'to_date' => $hastaDt->format('Y-m-d'),
            'minutos_atras' => $minutos,
            'desde_limite' => $desdeDt,
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
        bool $incluirOrdenPagada = false,
    ): array {
        if (! config('waitry.habilitado', false)) {
            return ['ok' => false, 'error' => 'Integración Waitry deshabilitada.'];
        }

        $empresaId = (int) $cfg->empresa_id;
        $identificadorPapelito = trim($identificadorPapelito);
        if ($identificadorPapelito === '') {
            return ['ok' => false, 'error' => 'Debe indicar el ID alfanumérico del papelito Waitry.'];
        }

        $resolucion = $this->obtenerOrdenPorIdentificadorPapelito(
            $empresaId,
            $identificadorPapelito,
            ! $incluirOrdenPagada,
        );
        if ($resolucion === null) {
            $msg = 'Orden Waitry «'.$identificadorPapelito.'» no encontrada.';
            if (! $incluirOrdenPagada) {
                $msg .= ' Si ya fue cobrada en el tótem, use «Importar por ID».';
            }

            return ['ok' => false, 'error' => $msg];
        }

        [$orden, $waitryOrderId] = $resolucion;

        if (WaitryFacturacionDuplicadosSupport::waitryOrderIdYaFacturado($waitryOrderId, $cuentaId)) {
            return [
                'ok' => false,
                'error' => WaitryFacturacionDuplicadosSupport::mensajeOrdenYaFacturada($waitryOrderId),
            ];
        }

        $cobroTotem = ! $this->ordenPendienteDePago($orden);

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
                $cuenta = $this->cuentaService->abrirCuentaLibre($empresaId, $cfg, $datosApertura);
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

            try {
                $this->cuentaService->agregarLinea(
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
        $cuenta->waitry_cobro_totem = $cobroTotem;
        $cuenta->save();

        $cuenta = $this->cuentaService->cuentaConLineas($cuenta->id);

        if ($cuenta->lineas->isEmpty()) {
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

        return [
            'ok' => true,
            'cuenta' => $cuenta,
            'errores' => $errores,
        ];
    }

    /**
     * Resuelve orden por ID numérico (orderId) o código alfanumérico del papelito (display_id, etc.).
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

        $tz = (string) config('app.timezone', 'UTC');
        $hastaDt = Carbon::now($tz);
        $desdeDt = $hastaDt->copy()->subDays(2);
        $consulta = $this->consultarOrdenesRaw((int) $placeId, [
            'from' => $desdeDt->toIso8601String(),
            'to' => $hastaDt->toIso8601String(),
            'from_date' => $desdeDt->format('Y-m-d'),
            'to_date' => $hastaDt->format('Y-m-d'),
            'minutos_atras' => 0,
            'desde_limite' => null,
        ]);

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

        if (! config('waitry.get_orders_usar_detalles_fallback', true)) {
            return null;
        }

        $tz = (string) config('app.timezone', 'UTC');
        $hastaDt = Carbon::now($tz);
        $desdeDt = $hastaDt->copy()->subDays(2);
        $consulta = $this->consultarOrdenesRaw((int) $placeId, [
            'from' => $desdeDt->toIso8601String(),
            'to' => $hastaDt->toIso8601String(),
            'from_date' => $desdeDt->format('Y-m-d'),
            'to_date' => $hastaDt->format('Y-m-d'),
            'minutos_atras' => 0,
            'desde_limite' => null,
        ]);

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
     * Sin pago en Waitry: campo paid explícito (push) o sin cobro en payment (getOrders pág. 21).
     */
    private function ordenPendienteDePago(array $orden): bool
    {
        if (array_key_exists('paid', $orden)) {
            $paid = $orden['paid'];

            return in_array($paid, [0, '0', false], true);
        }

        $estado = mb_strtolower(trim((string) ($orden['current_state'] ?? '')));
        if (in_array($estado, ['closed', 'cancelled', 'rejected'], true)) {
            return false;
        }

        $payment = $orden['payment'] ?? null;
        if (! is_array($payment)) {
            return true;
        }

        $totalFee = $payment['total_fee'] ?? null;
        $monto = 0.0;
        if (is_array($totalFee) && isset($totalFee['amount'])) {
            $monto = (float) $totalFee['amount'];
        } elseif (is_numeric($totalFee)) {
            $monto = (float) $totalFee;
        }

        $tipo = mb_strtolower(trim((string) ($payment['type'] ?? '')));

        return $monto <= 0.0001 && $tipo === '';
    }

    /**
     * Ítems del carrito getOrdersPOS (cart.items: external_id, quantity, price).
     *
     * @return list<array{sku:string,titulo:string,cantidad:float,precio_unitario:float}>
     */
    public function extraerLineasDesdeOrden(array $orden): array
    {
        $items = $orden['cart']['items'] ?? $orden['items'] ?? null;
        if (! is_array($items) || $items === []) {
            if (! empty($orden['orderItems']) && is_array($orden['orderItems'])) {
                return $this->extraerLineasFormatoPush($orden['orderItems']);
            }

            return [];
        }

        $lineas = [];
        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sku = trim((string) (
                $row['external_id']
                ?? $row['item']['externalId']
                ?? $row['item']['external_id']
                ?? $row['item']['externalCode']
                ?? $row['item']['external_code']
                ?? ''
            ));
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
                'titulo' => trim((string) ($row['title'] ?? $row['item']['name'] ?? $sku)),
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
            ];
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $orderItems
     * @return list<array{sku:string,titulo:string,cantidad:float,precio_unitario:float}>
     */
    private function extraerLineasFormatoPush(array $orderItems): array
    {
        $lineas = [];
        foreach ($orderItems as $oi) {
            if (! is_array($oi)) {
                continue;
            }
            if (array_key_exists('paid', $oi) && ! in_array($oi['paid'], [0, '0', false], true)) {
                continue;
            }

            $item = is_array($oi['item'] ?? null) ? $oi['item'] : [];
            $sku = trim((string) (
                $item['externalId']
                ?? $item['external_id']
                ?? $item['externalCode']
                ?? $item['external_code']
                ?? ''
            ));
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
                'titulo' => trim((string) ($item['name'] ?? $sku)),
                'cantidad' => $cantidad,
                'precio_unitario' => round($precio, 4),
            ];
        }

        return $lineas;
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

        if (isset($price['total_price']['amount'])) {
            $total = (float) $price['total_price']['amount'];
            $qty = max(1.0, $cantidad);

            return round($total / $qty, 4);
        }

        if (isset($price['unit_price']['amount'])) {
            return round((float) $price['unit_price']['amount'], 4);
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
            $articulo = $this->cuentaService->buscarArticuloCatalogoPorSku($cfg, $sku);
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

<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Configuracion\Condicioniva;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Impuesto;
use App\Models\Configuracion\Moneda;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Configuracion\ImpuestoService;
use App\Services\Ventas\FacturacionService;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaComprobantePkSupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use App\Support\Ventas\InformixUnlSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Backfill ERP → Informix: replica en Anita ventas gastronomía que existen en el ERP pero no en venta (cabecera).
 */
final class GastronomiaReplicarVentasAnitaErpService
{
    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoService,
        private readonly FacturacionService $facturacionService,
        private readonly ImpuestoService $impuestoService,
        private readonly GastronomiaInsumoStkmovAnitaService $insumoStkmovAnitaService,
    ) {
    }

    /**
     * @return array{
     *   combinaciones:int,
     *   faltantes:int,
     *   replicadas:int,
     *   omitidas:int,
     *   errores:list<array<string, mixed>>,
     *   detalle:list<array<string, mixed>>
     * }
     */
    public function replicarFaltantes(
        string $fechaDesde,
        ?string $fechaHasta,
        int $empresaId,
        ?string $codigoPv = null,
        bool $dryRun = false,
        int $limite = 0,
        bool $replicarInsumos = true,
        ?bool $omitirStkmovAnita = null,
    ): array {
        $omitirStkmovAnita = $this->resolverOmitirStkmovAnita($omitirStkmovAnita);

        $combinaciones = $this->chequeoService->listarCombinacionesPvJornada(
            $fechaDesde,
            $fechaHasta,
            $empresaId,
            $codigoPv,
        );

        $resultado = [
            'combinaciones' => count($combinaciones),
            'faltantes' => 0,
            'replicadas' => 0,
            'omitidas' => 0,
            'errores' => [],
            'detalle' => [],
        ];

        $procesadas = 0;

        foreach ($combinaciones as $combo) {
            $faltantes = $this->chequeoService->listarVentasErpSinCabeceraAnita(
                (int) $combo['puntoventa_id'],
                (string) $combo['fecha_jornada'],
            );

            if ($faltantes->isEmpty()) {
                continue;
            }

            $resultado['faltantes'] += $faltantes->count();

            foreach ($faltantes as $ventaResumen) {
                if ($limite > 0 && $procesadas >= $limite) {
                    break 2;
                }

                $venta = $this->cargarVentaCompleta((int) $ventaResumen->id);
                $fila = [
                    'venta_id' => (int) $venta->id,
                    'codigo' => (string) $venta->codigo,
                    'puntoventa' => (string) ($combo['codigo_pv'] ?? ''),
                    'fecha_jornada' => (string) ($combo['fecha_jornada'] ?? ''),
                    'total' => round((float) $venta->total, 2),
                    'estado' => 'pendiente',
                    'mensaje' => '',
                ];

                if ($dryRun) {
                    $fila['estado'] = 'simulado';
                    $resultado['detalle'][] = $fila;
                    $procesadas++;

                    continue;
                }

                try {
                    $this->replicarVenta($venta, $replicarInsumos, $omitirStkmovAnita);
                    $fila['estado'] = 'ok';
                    $resultado['replicadas']++;
                } catch (\Throwable $e) {
                    $fila['estado'] = 'error';
                    $fila['mensaje'] = $e->getMessage();
                    $resultado['errores'][] = $fila;
                    Log::error('gastronomia.replicar_ventas_anita.fallo', [
                        'venta_id' => $venta->id,
                        'codigo' => $venta->codigo,
                        'msg' => $e->getMessage(),
                    ]);
                }

                $resultado['detalle'][] = $fila;
                $procesadas++;
            }
        }

        return $resultado;
    }

    /**
     * Backfill por fecha calendario de emisión (`venta.fecha`), no por jornada.
     *
     * @return array{
     *   combinaciones:int,
     *   faltantes:int,
     *   replicadas:int,
     *   omitidas:int,
     *   errores:list<array<string, mixed>>,
     *   detalle:list<array<string, mixed>>
     * }
     */
    public function replicarFaltantesPorFechaCalendario(
        string $fechaCalendario,
        int $empresaId,
        ?string $codigoPv = null,
        bool $dryRun = false,
        int $limite = 0,
        bool $replicarInsumos = true,
        ?bool $omitirStkmovAnita = null,
    ): array {
        $omitirStkmovAnita = $this->resolverOmitirStkmovAnita($omitirStkmovAnita);

        $combinaciones = $this->chequeoService->listarCombinacionesPvFechaCalendario(
            $fechaCalendario,
            $empresaId,
            $codigoPv,
        );

        $resultado = [
            'combinaciones' => count($combinaciones),
            'faltantes' => 0,
            'replicadas' => 0,
            'omitidas' => 0,
            'errores' => [],
            'detalle' => [],
            'fecha_calendario' => $fechaCalendario,
        ];

        $procesadas = 0;

        foreach ($combinaciones as $combo) {
            $faltantes = $this->chequeoService->listarVentasErpSinCabeceraAnitaPorFechaCalendario(
                (int) $combo['puntoventa_id'],
                $fechaCalendario,
            );

            if ($faltantes->isEmpty()) {
                continue;
            }

            $resultado['faltantes'] += $faltantes->count();

            foreach ($faltantes as $ventaResumen) {
                if ($limite > 0 && $procesadas >= $limite) {
                    break 2;
                }

                $venta = $this->cargarVentaCompleta((int) $ventaResumen->id);
                $fila = [
                    'venta_id' => (int) $venta->id,
                    'codigo' => (string) $venta->codigo,
                    'puntoventa' => (string) ($combo['codigo_pv'] ?? ''),
                    'fecha_calendario' => $fechaCalendario,
                    'fecha_jornada' => (string) ($venta->fechajornada ?? ''),
                    'total' => round((float) $venta->total, 2),
                    'estado' => 'pendiente',
                    'mensaje' => '',
                ];

                if ($dryRun) {
                    $fila['estado'] = 'simulado';
                    $resultado['detalle'][] = $fila;
                    $procesadas++;

                    continue;
                }

                try {
                    $this->replicarVenta($venta, $replicarInsumos, $omitirStkmovAnita);
                    $fila['estado'] = 'ok';
                    $resultado['replicadas']++;
                } catch (\Throwable $e) {
                    $fila['estado'] = 'error';
                    $fila['mensaje'] = $e->getMessage();
                    $resultado['errores'][] = $fila;
                    Log::error('gastronomia.replicar_ventas_anita.fallo', [
                        'venta_id' => $venta->id,
                        'codigo' => $venta->codigo,
                        'fecha_calendario' => $fechaCalendario,
                        'msg' => $e->getMessage(),
                    ]);
                }

                $resultado['detalle'][] = $fila;
                $procesadas++;
            }
        }

        return $resultado;
    }

    public function replicarVenta(Venta $venta, bool $replicarInsumos = true, ?bool $omitirStkmovAnita = null): void
    {
        $omitirStkmovAnita = $this->resolverOmitirStkmovAnita($omitirStkmovAnita);

        $venta = $this->cargarVentaCompleta((int) $venta->id);

        $puntoventa = $venta->puntoventas;
        if (! $puntoventa || ($puntoventa->modofacturacion ?? '') === 'M') {
            throw new \RuntimeException('Punto de venta manual o inexistente; no se replica en Anita.');
        }

        $empresa = Empresa::query()->find($puntoventa->empresa_id);
        if (! $empresa) {
            throw new \RuntimeException('Empresa no encontrada para PV '.$puntoventa->codigo.'.');
        }

        $tipotransaccion = $venta->tipotransacciones;
        if (! $tipotransaccion) {
            throw new \RuntimeException('Tipo de transacción no encontrado para venta #'.$venta->id.'.');
        }

        $letra = $this->resolverLetra($venta);
        $this->liberarCabeceraAnitaSiExiste($venta, $letra);

        $signo = ($tipotransaccion->signo ?? 'S') === 'S' ? 1. : -1.;
        $conceptosTotales = $this->armarConceptosTotales($venta);
        $dataFactura = $this->armarDataFactura($venta, $omitirStkmovAnita);
        $ventaArray = $this->armarVentaArray($venta);
        $dataCAE = $this->armarDataCae($venta, $empresa, $conceptosTotales, $dataFactura);

        $modoMinimo = (bool) config('gastronomia.anita_modo_minimo', true);
        $anita = $this->facturacionService->replicarVentaGastronomiaEnAnita(
            $ventaArray,
            $dataCAE,
            $conceptosTotales,
            $dataFactura,
            $puntoventa,
            $empresa,
            $letra,
            (string) $tipotransaccion->codigo,
            $signo,
            (float) ($venta->descuento ?? 0),
            $modoMinimo,
            true,
            $omitirStkmovAnita,
        );

        if (is_array($anita) && isset($anita['error'])) {
            $detalle = trim((string) ($anita['mensaje'] ?? $anita['error'] ?? 'Error desconocido'));
            if ($anita['error'] === 'Errvend') {
                throw new \RuntimeException('Error Anita: el cliente no tiene vendedor asignado.');
            }

            throw new \RuntimeException('Error Anita: '.$detalle);
        }

        if ($replicarInsumos) {
            $cfg = $this->resolverConfiguracionPv($venta);
            if ($cfg !== null) {
                $this->insumoStkmovAnitaService->replicarMovimientosInsumos(
                    $venta->fresh(),
                    $cfg,
                    (float) ($venta->descuento ?? 0),
                );
            }
        }

        Log::info('gastronomia.replicar_ventas_anita.ok', [
            'venta_id' => $venta->id,
            'codigo' => $venta->codigo,
        ]);
    }

    /**
     * Si la cabecera ya existe en Informix, borra el comprobante completo (venta + stkmov + …)
     * antes de re-grabar desde el ERP. Evita duplicate key en stkmov al backfillear ventas
     * parcialmente sincronizadas o falsamente detectadas como faltantes.
     */
    private function resolverOmitirStkmovAnita(?bool $explicito): bool
    {
        if ($explicito !== null) {
            return $explicito;
        }

        return filter_var(config('gastronomia.anita_omitir_stkmov', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function liberarCabeceraAnitaSiExiste(Venta $venta, string $letra): void
    {
        $consulta = $this->chequeoService->consultarCabeceraAnitaDesdeVenta($venta, $letra);
        if ($consulta['error_lectura'] !== null) {
            throw new \RuntimeException(
                'No se pudo verificar cabecera en Anita antes de replicar: '.$consulta['error_lectura']
            );
        }

        if ($consulta['cabecera'] === null) {
            return;
        }

        Log::info('gastronomia.replicar_ventas_anita.rollback_previo', [
            'venta_id' => $venta->id,
            'codigo' => $venta->codigo,
        ]);

        $this->facturacionService->borraAnitaDesdeVenta($venta);
    }

    private function cargarVentaCompleta(int $ventaId): Venta
    {
        $venta = Venta::query()
            ->with([
                'puntoventas.empresas',
                'clientes.tipodocumentos',
                'clientes.condicionventas',
                'clientes.paises',
                'tipotransacciones',
                'venta_impuestos',
                'venta_emisiones.articulos.categorias',
                'gastronomiaEmision.configuracionPuntoventa',
            ])
            ->findOrFail($ventaId);

        return $venta;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function armarConceptosTotales(Venta $venta): array
    {
        $impuestoIds = $venta->venta_impuestos
            ->pluck('impuesto_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->values()
            ->all();

        $codigosImpuesto = $impuestoIds === []
            ? []
            : Impuesto::query()->whereIn('id', $impuestoIds)->pluck('codigo', 'id')->all();

        $conceptos = [];
        foreach ($venta->venta_impuestos as $row) {
            $item = [
                'concepto' => (string) $row->concepto,
                'baseimponible' => round((float) ($row->baseimponible ?? 0), 2),
                'tasa' => round((float) ($row->tasa ?? 0), 2),
                'importe' => round((float) ($row->importe ?? 0), 2),
                'impuesto_id' => $row->impuesto_id,
                'provincia_id' => $row->provincia_id,
            ];

            if ($row->impuesto_id && preg_match('/^Iva/i', (string) $row->concepto)) {
                $item['codigo'] = (string) ($codigosImpuesto[(int) $row->impuesto_id] ?? '1');
            }

            $conceptos[] = $item;
        }

        return $conceptos;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function armarDataFactura(Venta $venta, bool $omitirStkmovAnita = false): array
    {
        $movItems = GastronomiaVentaDetalleSupport::movimientosItemsFacturados((int) $venta->id)
            ->keyBy('venta_emision_id');

        $items = [];
        foreach ($venta->venta_emisiones->sortBy('numeroitem') as $em) {
            $articulo = $em->articulos;
            $articuloId = (int) ($em->articulo_id ?? 0);
            $precio = (float) $em->precio;
            $omitirStkmov = $omitirStkmovAnita
                || ($articuloId > 0
                    && ! $movItems->has((int) $em->id)
                    && abs($precio) < 0.00001);

            if ($articuloId > 0 && $articulo) {
                $items[] = [
                    'articulo_id' => $articuloId,
                    'sku' => (string) ($articulo->sku ?? ''),
                    'descripcion' => (string) ($articulo->descripcion ?? $em->detalle ?? ''),
                    'detalle' => (string) ($em->detalle ?? $articulo->descripcion ?? ''),
                    'cantidad' => (float) $em->cantidad,
                    'precio' => $precio,
                    'descuento' => (float) ($em->descuento ?? 0),
                    'descuentointegrado' => (string) ($em->descuentointegrado ?? ' '),
                    'impuesto_id' => (int) ($em->impuesto_id ?? 0),
                    'incluyeimpuesto' => (string) ($em->incluyeimpuesto ?? '1'),
                    'moneda_id' => (int) ($em->moneda_id ?? $venta->moneda_id ?? 1),
                    'categoria' => (string) ($articulo->categorias->codigo ?? '0'),
                    'omitir_stkmov_anita' => $omitirStkmov,
                ];

                continue;
            }

            $items[] = [
                'cantidad' => (float) $em->cantidad,
                'precio' => $precio,
                'descuento' => (float) ($em->descuento ?? 0),
                'descuentointegrado' => (string) ($em->descuentointegrado ?? ' '),
                'impuesto_id' => (int) ($em->impuesto_id ?? 0),
                'incluyeimpuesto' => (string) ($em->incluyeimpuesto ?? '1'),
                'moneda_id' => (int) ($em->moneda_id ?? $venta->moneda_id ?? 1),
                'detalle' => (string) ($em->detalle ?? 'Item'),
                'descripcion' => (string) ($em->detalle ?? 'Item'),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function armarVentaArray(Venta $venta): array
    {
        $cliente = $venta->clientes;

        return [
            'fecha' => (string) $venta->fecha,
            'fechajornada' => (string) ($venta->fechajornada ?? $venta->fecha),
            'empresa_id' => (int) ($venta->puntoventas->empresa_id ?? 0),
            'tipotransaccion_id' => (int) $venta->tipotransaccion_id,
            'puntoventa_id' => (int) $venta->puntoventa_id,
            'numerocomprobante' => (int) $venta->numerocomprobante,
            'actividad_arca_id' => (int) ($venta->actividad_arca_id ?? 0),
            'cliente_id' => (int) ($venta->cliente_id ?? 0),
            'condicionventa_id' => (int) ($venta->condicionventa_id ?? ($cliente->condicionventa_id ?? 0)),
            'vendedor_id' => (int) ($venta->vendedor_id ?? ($cliente->vendedor_id ?? 0)),
            'transporte_id' => $venta->transporte_id,
            'total' => round((float) $venta->total, 2),
            'moneda_id' => (int) ($venta->moneda_id ?? 1),
            'cotizacion' => (float) ($venta->cotizacion ?: 1),
            'estado' => (string) ($venta->estado ?? ' '),
            'usuario_id' => (int) ($venta->usuario_id ?? 0),
            'leyenda' => (string) ($venta->leyenda ?? ''),
            'descuento' => (float) ($venta->descuento ?? 0),
            'descuentointegrado' => (string) ($venta->descuentointegrado ?? ' '),
            'lugarentrega' => (string) ($venta->lugarentrega ?? ($cliente->lugarentrega ?? '')),
            'cliente_entrega_id' => $venta->cliente_entrega_id,
            'codigo' => (string) $venta->codigo,
            'nombre' => (string) ($venta->nombre ?? ($cliente->nombre ?? '')),
            'domicilio' => (string) ($venta->domicilio ?? ($cliente->domicilio ?? '')),
            'localidad_id' => $venta->localidad_id ?? ($cliente->localidad_id ?? null),
            'provincia_id' => $venta->provincia_id ?? ($cliente->provincia_id ?? null),
            'pais_id' => $venta->pais_id ?? ($cliente->pais_id ?? null),
            'codigopostal' => (string) ($venta->codigopostal ?? ($cliente->codigopostal ?? '')),
            'email' => (string) ($venta->email ?? ($cliente->email ?? '')),
            'telefono' => (string) ($venta->telefono ?? ($cliente->telefono ?? '')),
            'numerodocumento' => (string) ($venta->numerodocumento ?? ($cliente->numerodocumento ?? '')),
            'condicioniva_id' => (int) ($venta->condicioniva_id ?? ($cliente->condicioniva_id ?? 0)),
            'puntoventaremito_id' => null,
            'numeroremito' => 0,
            'cantidadbulto' => (int) ($venta->cantidadbulto ?? 1),
            'ordenventa_id' => (int) ($venta->ordenventa_id ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $conceptosTotales
     * @param  list<array<string, mixed>>  $dataFactura
     * @return array<string, mixed>
     */
    private function armarDataCae(
        Venta $venta,
        Empresa $empresa,
        array $conceptosTotales,
        array $dataFactura,
    ): array {
        $cliente = $venta->clientes;
        $moneda = Moneda::query()->find((int) ($venta->moneda_id ?? 1));
        $codigoMoneda = $moneda ? (string) $moneda->codigo : 'PES';

        $fechaAsignacion = Carbon::parse((string) $venta->fecha);
        $fechaAsignacion->modify('first day of this month');

        $dataCae = [
            'codigoempresa' => $empresa->codigo,
            'tipodoc' => $cliente?->tipodocumentos?->codigoexterno ?? 80,
            'numerodocumento' => (string) ($cliente->numerodocumento ?? $venta->numerodocumento ?? ''),
            'condicioniva_id' => (int) ($venta->condicioniva_id ?? ($cliente->condicioniva_id ?? 0)),
            'numerocomprobante' => (int) $venta->numerocomprobante,
            'fechacomprobante' => date('Ymd', strtotime((string) $venta->fecha)),
            'total' => abs((float) $venta->total),
            'nogravado' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'No Gravado', 'importe'),
            'gravado' => \App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport::gravadoDesdeConceptosTotales(
                $conceptosTotales,
                abs((float) $venta->total),
            ),
            'exento' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Exento', 'importe'),
            'iva' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Iva ', 'importe'),
            'tributo' => 0,
            'fechavencimiento' => date('Ymd', strtotime((string) $venta->fecha)),
            'moneda' => $codigoMoneda,
            'cotizacion' => 1,
            'tributos' => [],
            'impuestos' => [],
            'comprobantesasociados' => [],
            'fechaasignaciondesde' => date('Ymd', strtotime($fechaAsignacion->format('Y-m-d'))),
            'fechaasignacionhasta' => date('Ymd', strtotime((string) $venta->fecha)),
            'pais' => $cliente?->paises?->codigo ?? '',
            'nombrecliente' => (string) ($cliente->nombre ?? $venta->nombre ?? ''),
            'domicilio' => (string) ($cliente->domicilio ?? $venta->domicilio ?? ''),
            'formapago' => (string) ($cliente->condicionventas->nombre ?? ''),
            'formapagoexportacion' => '',
            'incoterms' => '',
            'items' => $dataFactura,
        ];

        $this->aplicarCortesiaMinimaEnDataCae($venta, $dataCae);

        return $dataCae;
    }

    /**
     * @param  array<string, mixed>  $dataCae
     */
    private function aplicarCortesiaMinimaEnDataCae(Venta $venta, array &$dataCae): void
    {
        $ventaPayload = ['total' => (float) $venta->total];
        \App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport::aplicarCortesiaMinimaEnPayloadAnita(
            $ventaPayload,
            $dataCae,
        );
    }

    private function resolverLetra(Venta $venta): string
    {
        $codigo = trim((string) ($venta->codigo ?? ''));
        if (preg_match('/\s+([A-Z])-/', $codigo, $m)) {
            return $m[1];
        }

        if ((int) ($venta->condicioniva_id ?? 0) > 0) {
            $condicion = Condicioniva::query()->find((int) $venta->condicioniva_id);
            if ($condicion && trim((string) ($condicion->letra ?? '')) !== '') {
                return (string) $condicion->letra;
            }
        }

        return 'B';
    }

    private function resolverConfiguracionPv(Venta $venta): ?ConfiguracionPuntoventaGastronomia
    {
        $emision = $venta->gastronomiaEmision;
        if ($emision instanceof VentaGastronomiaEmision && $emision->configuracionPuntoventa) {
            return $emision->configuracionPuntoventa;
        }

        $cfgId = (int) ($emision->configuracion_puntoventa_gastronomia_id ?? 0);
        if ($cfgId > 0) {
            return ConfiguracionPuntoventaGastronomia::query()->find($cfgId);
        }

        return ConfiguracionPuntoventaGastronomia::query()
            ->where('puntoventa_id', (int) $venta->puntoventa_id)
            ->where('empresa_id', (int) ($venta->puntoventas->empresa_id ?? 0))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Genera venta.unl, vengrav.unl y vencae.unl (pipe) para ventas ERP sin cabecera en Informix.
     *
     * @return array{
     *   faltantes:int,
     *   venta_lineas:int,
     *   vengrav_lineas:int,
     *   vencae_lineas:int,
     *   omitidas_sin_cae:int,
     *   omitidas_venta_ya_existe:int,
     *   omitidas_vengrav_ya_existe:int,
     *   omitidas_vencae_ya_existe:int,
     *   errores:list<array<string, mixed>>,
     *   archivos: array{venta:string, vengrav:string, vencae:string}
     * }
     */
    public function exportarFaltantesUnl(
        string $fechaDesde,
        ?string $fechaHasta,
        int $empresaId,
        string $directorioSalida,
        ?string $codigoPv = null,
        ?array $cacheAnita = null,
    ): array {
        if (! is_dir($directorioSalida) && ! mkdir($directorioSalida, 0775, true) && ! is_dir($directorioSalida)) {
            throw new \RuntimeException('No se pudo crear el directorio: '.$directorioSalida);
        }

        $lineasVenta = [];
        $lineasVengrav = [];
        $lineasVencae = [];
        $resultado = [
            'faltantes' => 0,
            'venta_lineas' => 0,
            'vengrav_lineas' => 0,
            'vencae_lineas' => 0,
            'omitidas_sin_cae' => 0,
            'omitidas_venta_ya_existe' => 0,
            'omitidas_vengrav_ya_existe' => 0,
            'omitidas_vencae_ya_existe' => 0,
            'errores' => [],
            'archivos' => [
                'venta' => rtrim($directorioSalida, '/').'/venta.unl',
                'vengrav' => rtrim($directorioSalida, '/').'/vengrav.unl',
                'vencae' => rtrim($directorioSalida, '/').'/vencae.unl',
            ],
        ];

        $usarCache = $cacheAnita !== null && isset($cacheAnita['venta']) && is_array($cacheAnita['venta']);
        $ventaPkIndice = null;
        $vengravPkIndice = null;
        $vencaePkIndice = null;

        if ($usarCache) {
            $ventaPkIndice = GastronomiaAnitaComprobantePkSupport::indexarVenta($cacheAnita['venta']);
            $vengravPkIndice = GastronomiaAnitaComprobantePkSupport::indexarVengrav($cacheAnita['vengrav'] ?? []);
            $vencaePkIndice = GastronomiaAnitaComprobantePkSupport::indexarVencae($cacheAnita['vencae'] ?? []);

            $faltantes = $this->chequeoService->listarVentasErpSinCabeceraAnitaEnRango(
                $empresaId,
                $fechaDesde,
                $fechaHasta,
                $codigoPv,
                $ventaPkIndice,
            );
            $resultado['faltantes'] = $faltantes->count();
            $this->procesarFaltantesUnl(
                $faltantes,
                $empresaId,
                $resultado,
                $lineasVenta,
                $lineasVengrav,
                $lineasVencae,
                true,
                $ventaPkIndice,
                $vengravPkIndice,
                $vencaePkIndice,
            );
        } else {
            $combinaciones = $this->chequeoService->listarCombinacionesPvJornada(
                $fechaDesde,
                $fechaHasta,
                $empresaId,
                $codigoPv,
            );

            foreach ($combinaciones as $combo) {
                $faltantes = $this->chequeoService->listarVentasErpSinCabeceraAnita(
                    (int) $combo['puntoventa_id'],
                    (string) $combo['fecha_jornada'],
                );

                if ($faltantes->isEmpty()) {
                    continue;
                }

                $resultado['faltantes'] += $faltantes->count();
                $this->procesarFaltantesUnl(
                    $faltantes,
                    $empresaId,
                    $resultado,
                    $lineasVenta,
                    $lineasVengrav,
                    $lineasVencae,
                    false,
                    null,
                    null,
                    null,
                );
            }
        }

        file_put_contents($resultado['archivos']['venta'], implode("\n", $lineasVenta).($lineasVenta !== [] ? "\n" : ''));
        file_put_contents($resultado['archivos']['vengrav'], implode("\n", $lineasVengrav).($lineasVengrav !== [] ? "\n" : ''));
        file_put_contents($resultado['archivos']['vencae'], implode("\n", $lineasVencae).($lineasVencae !== [] ? "\n" : ''));

        $resultado['venta_lineas'] = count($lineasVenta);
        $resultado['vengrav_lineas'] = count($lineasVengrav);
        $resultado['vencae_lineas'] = count($lineasVencae);

        return $resultado;
    }

    /**
     * @param  Collection<int, Venta>  $faltantes
     * @param  array<string, true>|null  $ventaPkIndice
     * @param  array<string, true>|null  $vengravPkIndice
     * @param  array<string, true>|null  $vencaePkIndice
     */
    private function procesarFaltantesUnl(
        $faltantes,
        int $empresaId,
        array &$resultado,
        array &$lineasVenta,
        array &$lineasVengrav,
        array &$lineasVencae,
        bool $usarCache,
        ?array $ventaPkIndice,
        ?array $vengravPkIndice,
        ?array $vencaePkIndice,
    ): void {
        foreach ($faltantes as $ventaResumen) {
            try {
                $venta = $this->cargarVentaCompleta((int) $ventaResumen->id);
                $puntoventa = $venta->puntoventas;
                if (! $puntoventa || ($puntoventa->modofacturacion ?? '') === 'M') {
                    continue;
                }

                if ((int) $puntoventa->empresa_id !== $empresaId) {
                    continue;
                }

                $empresa = Empresa::query()->find($puntoventa->empresa_id);
                if (! $empresa) {
                    throw new \RuntimeException('Empresa no encontrada para PV '.$puntoventa->codigo.'.');
                }

                $tipotransaccion = $venta->tipotransacciones;
                if (! $tipotransaccion) {
                    throw new \RuntimeException('Tipo de transacción no encontrado.');
                }

                $letra = $this->resolverLetra($venta);
                $conceptosTotales = $this->armarConceptosTotales($venta);
                $dataFactura = $this->armarDataFactura($venta, true);
                $ventaArray = $this->armarVentaArray($venta);
                $dataCAE = $this->armarDataCae($venta, $empresa, $conceptosTotales, $dataFactura);

                $filas = $this->facturacionService->construirFilasUnlGastronomiaModoMinimo(
                    $puntoventa->codigo,
                    $letra,
                    $ventaArray,
                    $dataCAE,
                    $conceptosTotales,
                    (string) $tipotransaccion->codigo,
                    (string) $empresa->codigo,
                    $puntoventa->modofacturacion ?? null,
                    true,
                    $venta->cae,
                    $venta->fechavencimientocae,
                );

                $tipoAnita = (string) ($filas['venta'][1] ?? '');
                $sucursalAnita = $this->chequeoService->sucursalDesdeCodigoPuntoventa((string) $puntoventa->codigo);
                $numeroAnita = (int) ($venta->numerocomprobante ?? 0);
                $pkVenta = GastronomiaAnitaComprobantePkSupport::claveVenta($tipoAnita, $letra, $sucursalAnita, $numeroAnita);

                $cabeceraYaExiste = $usarCache && $ventaPkIndice !== null && $pkVenta !== null
                    ? isset($ventaPkIndice[$pkVenta])
                    : $this->chequeoService->existeCabeceraEnAnita($tipoAnita, $letra, $sucursalAnita, $numeroAnita);

                if ($cabeceraYaExiste) {
                    $resultado['omitidas_venta_ya_existe']++;
                } else {
                    $lineasVenta[] = InformixUnlSupport::linea($filas['venta']);
                }

                foreach ($filas['vengrav'] as $filaVengrav) {
                    $tipoVengrav = (string) ($filaVengrav[0] ?? $tipoAnita);
                    $letraVengrav = (string) ($filaVengrav[1] ?? $letra);
                    $sucVengrav = GastronomiaAnitaComprobantePkSupport::sucursalEntera((string) ($filaVengrav[2] ?? $puntoventa->codigo));
                    $nroVengrav = (int) ($filaVengrav[3] ?? $numeroAnita);
                    $codigoTasa = (string) ($filaVengrav[4] ?? '');
                    $pkVengrav = GastronomiaAnitaComprobantePkSupport::claveVengrav(
                        $tipoVengrav,
                        $letraVengrav,
                        $sucVengrav,
                        $nroVengrav,
                        $codigoTasa,
                    );
                    $vengravYaExiste = $usarCache && $vengravPkIndice !== null && $pkVengrav !== null
                        ? isset($vengravPkIndice[$pkVengrav])
                        : $this->chequeoService->existeVengravLineaAnita($tipoAnita, $letra, $sucursalAnita, $numeroAnita, $codigoTasa);

                    if ($vengravYaExiste) {
                        $resultado['omitidas_vengrav_ya_existe']++;

                        continue;
                    }

                    $lineasVengrav[] = InformixUnlSupport::linea($filaVengrav);
                }

                if ($filas['vencae'] !== null) {
                    $tipoVencae = (string) ($filas['vencae'][0] ?? $tipoAnita);
                    $letraVencae = (string) ($filas['vencae'][1] ?? $letra);
                    $sucVencae = GastronomiaAnitaComprobantePkSupport::sucursalEntera((string) ($filas['vencae'][2] ?? $puntoventa->codigo));
                    $nroVencae = (int) ($filas['vencae'][3] ?? $numeroAnita);
                    $pkVencae = GastronomiaAnitaComprobantePkSupport::claveVencae($tipoVencae, $letraVencae, $sucVencae, $nroVencae);
                    $vencaeYaExiste = $usarCache && $vencaePkIndice !== null && $pkVencae !== null
                        ? isset($vencaePkIndice[$pkVencae])
                        : $this->chequeoService->existeVencaeEnAnita($tipoAnita, $letra, $sucursalAnita, $numeroAnita);

                    if ($vencaeYaExiste) {
                        $resultado['omitidas_vencae_ya_existe']++;
                    } else {
                        $lineasVencae[] = InformixUnlSupport::linea($filas['vencae']);
                    }
                } else {
                    $resultado['omitidas_sin_cae']++;
                }
            } catch (\Throwable $e) {
                $resultado['errores'][] = [
                    'venta_id' => (int) $ventaResumen->id,
                    'codigo' => (string) ($ventaResumen->codigo ?? ''),
                    'mensaje' => $e->getMessage(),
                ];
            }
        }
    }
}

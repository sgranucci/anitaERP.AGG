<?php

declare(strict_types=1);

namespace App\Services\Caja\Estacionamiento;

use App\Models\Configuracion\Condicioniva;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Impuesto;
use App\Models\Configuracion\Moneda;
use App\Models\Ventas\Venta;
use App\Services\Configuracion\ImpuestoService;
use App\Services\Ventas\FacturacionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Backfill ERP → Informix: replica ventas estacionamiento sin cabecera en Anita.
 * Estacionamiento no replica insumos ni stkmov de stock.
 */
final class EstacionamientoReplicarVentasAnitaErpService
{
    public function __construct(
        private readonly EstacionamientoChequeoVentasAnitaErpService $chequeoService,
        private readonly FacturacionService $facturacionService,
        private readonly ImpuestoService $impuestoService,
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
    ): array {
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
                    $this->replicarVenta($venta);
                    $fila['estado'] = 'ok';
                    $resultado['replicadas']++;
                } catch (\Throwable $e) {
                    $fila['estado'] = 'error';
                    $fila['mensaje'] = $e->getMessage();
                    $resultado['errores'][] = $fila;
                    Log::error('estacionamiento.replicar_ventas_anita.fallo', [
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

    public function replicarVenta(Venta $venta): void
    {
        $venta = $this->cargarVentaCompleta((int) $venta->id);

        if (! $venta->estacionamientoEmision) {
            throw new \RuntimeException('La venta #'.$venta->id.' no tiene emisión estacionamiento.');
        }

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
        $dataFactura = $this->armarDataFactura($venta);
        $ventaArray = $this->armarVentaArray($venta);
        $dataCAE = $this->armarDataCae($venta, $empresa, $conceptosTotales, $dataFactura);

        $modoMinimo = true;
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
            true,
        );

        if (is_array($anita) && isset($anita['error'])) {
            $detalle = trim((string) ($anita['mensaje'] ?? $anita['error'] ?? 'Error desconocido'));
            if ($anita['error'] === 'Errvend') {
                throw new \RuntimeException('Error Anita: el cliente no tiene vendedor asignado.');
            }

            throw new \RuntimeException('Error Anita: '.$detalle);
        }

        Log::info('estacionamiento.replicar_ventas_anita.ok', [
            'venta_id' => $venta->id,
            'codigo' => $venta->codigo,
        ]);
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

        Log::info('estacionamiento.replicar_ventas_anita.rollback_previo', [
            'venta_id' => $venta->id,
            'codigo' => $venta->codigo,
        ]);

        $this->facturacionService->borraAnitaDesdeVenta($venta);
    }

    private function cargarVentaCompleta(int $ventaId): Venta
    {
        return Venta::query()
            ->with([
                'puntoventas.empresas',
                'clientes.tipodocumentos',
                'clientes.condicionventas',
                'clientes.paises',
                'tipotransacciones',
                'venta_impuestos',
                'venta_emisiones.articulos.categorias',
                'estacionamientoEmision',
            ])
            ->findOrFail($ventaId);
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
     * Estacionamiento: cabecera Anita + renglones; sin stkmov (omitir_stkmov_anita en todas las líneas).
     *
     * @return list<array<string, mixed>>
     */
    private function armarDataFactura(Venta $venta): array
    {
        $items = [];
        foreach ($venta->venta_emisiones->sortBy('numeroitem') as $em) {
            $articulo = $em->articulos;
            $articuloId = (int) ($em->articulo_id ?? 0);
            $precio = (float) $em->precio;

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
                    'omitir_stkmov_anita' => true,
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
                'omitir_stkmov_anita' => true,
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

        return [
            'codigoempresa' => $empresa->codigo,
            'tipodoc' => $cliente?->tipodocumentos?->codigoexterno ?? 80,
            'numerodocumento' => (string) ($cliente->numerodocumento ?? $venta->numerodocumento ?? ''),
            'condicioniva_id' => (int) ($venta->condicioniva_id ?? ($cliente->condicioniva_id ?? 0)),
            'numerocomprobante' => (int) $venta->numerocomprobante,
            'fechacomprobante' => date('Ymd', strtotime((string) $venta->fecha)),
            'total' => abs((float) $venta->total),
            'nogravado' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'No Gravado', 'importe'),
            'gravado' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Gravado al', 'importe'),
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
}

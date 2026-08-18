<?php

declare(strict_types=1);

namespace App\Services\Caja\Estacionamiento;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;
use App\Services\Ventas\FacturacionService;
use App\Support\Caja\Estacionamiento\EstacionamientoFacturaPayloadSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Corrige venta_impuesto / venta_emision de facturas estacionamiento emitidas con IVA exento por error.
 */
final class EstacionamientoCorregirImpuestosFacturasService
{
    private const TOLERANCIA_TOTAL = 0.02;

    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly EstacionamientoReplicarVentasAnitaErpService $replicarAnitaService,
    ) {
    }

    /**
     * @return array{
     *   candidatas:int,
     *   corregidas:int,
     *   omitidas_cortesia:int,
     *   omitidas_ya_ok:int,
     *   errores:list<array<string, mixed>>,
     *   detalle:list<array<string, mixed>>
     * }
     */
    public function corregir(
        string $fechaDesde,
        ?string $fechaHasta = null,
        bool $dryRun = false,
        bool $replicarAnita = true,
        int $limite = 0,
    ): array {
        $query = Venta::query()
            ->with([
                'venta_emisiones',
                'venta_impuestos',
                'puntoventas',
                'estacionamientoEmision',
            ])
            ->whereHas('estacionamientoEmision')
            ->where('fechajornada', '>=', $fechaDesde);

        if ($fechaHasta !== null && $fechaHasta !== '') {
            $query->where('fechajornada', '<=', $fechaHasta);
        }

        $query->orderBy('id');

        $resultado = [
            'candidatas' => 0,
            'corregidas' => 0,
            'omitidas_cortesia' => 0,
            'omitidas_ya_ok' => 0,
            'errores' => [],
            'detalle' => [],
        ];

        $procesadas = 0;

        foreach ($query->cursor() as $venta) {
            if ($limite > 0 && $procesadas >= $limite) {
                break;
            }

            $fila = [
                'venta_id' => (int) $venta->id,
                'codigo' => (string) $venta->codigo,
                'fecha_jornada' => (string) ($venta->fechajornada ?? $venta->fecha),
                'total' => round((float) $venta->total, 2),
                'estado' => 'pendiente',
                'mensaje' => '',
            ];

            if ($this->esCortesia($venta)) {
                $fila['estado'] = 'omitida_cortesia';
                $resultado['omitidas_cortesia']++;
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            if ($this->yaTieneIva($venta)) {
                $fila['estado'] = 'omitida_ya_ok';
                $resultado['omitidas_ya_ok']++;
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            $resultado['candidatas']++;

            try {
                $recalculo = $this->recalcularImpuestos($venta);
            } catch (Throwable $e) {
                $fila['estado'] = 'error';
                $fila['mensaje'] = $e->getMessage();
                $resultado['errores'][] = $fila;
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            $totalRecalculado = round((float) ($recalculo['totalcomprobante'] ?? 0), 2);
            $totalOriginal = round(abs((float) $venta->total), 2);
            if (abs($totalRecalculado - $totalOriginal) > self::TOLERANCIA_TOTAL) {
                $fila['estado'] = 'error';
                $fila['mensaje'] = sprintf(
                    'Total recalculado %.2f difiere del original %.2f',
                    $totalRecalculado,
                    $totalOriginal,
                );
                $resultado['errores'][] = $fila;
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            if ($dryRun) {
                $fila['estado'] = 'simulado';
                $fila['mensaje'] = $this->resumenConceptos($recalculo['conceptostotales'] ?? []);
                $resultado['detalle'][] = $fila;
                $procesadas++;

                continue;
            }

            try {
                DB::transaction(function () use ($venta, $recalculo): void {
                    $this->persistirCorreccion($venta, $recalculo);
                });

                if ($replicarAnita) {
                    $venta->refresh();
                    $venta->load([
                        'puntoventas.empresas',
                        'clientes.tipodocumentos',
                        'clientes.condicionventas',
                        'clientes.paises',
                        'tipotransacciones',
                        'venta_impuestos',
                        'venta_emisiones.articulos.categorias',
                        'estacionamientoEmision',
                    ]);
                    $this->replicarAnitaService->replicarVenta($venta);
                }

                $fila['estado'] = 'ok';
                $fila['mensaje'] = $this->resumenConceptos($recalculo['conceptostotales'] ?? []);
                $resultado['corregidas']++;
                $resultado['detalle'][] = $fila;

                Log::info('estacionamiento.corregir_impuestos.ok', [
                    'venta_id' => $venta->id,
                    'codigo' => $venta->codigo,
                ]);
            } catch (Throwable $e) {
                $fila['estado'] = 'error';
                $fila['mensaje'] = $e->getMessage();
                $resultado['errores'][] = $fila;
                $resultado['detalle'][] = $fila;

                Log::error('estacionamiento.corregir_impuestos.fallo', [
                    'venta_id' => $venta->id,
                    'codigo' => $venta->codigo,
                    'msg' => $e->getMessage(),
                ]);
            }

            $procesadas++;
        }

        return $resultado;
    }

    private function esCortesia(Venta $venta): bool
    {
        return abs(round((float) $venta->total, 2)) <= EstacionamientoFacturacionService::IMPORTE_MINIMO_FACTURA;
    }

    private function yaTieneIva(Venta $venta): bool
    {
        foreach ($venta->venta_impuestos as $imp) {
            if (stripos((string) $imp->concepto, 'Iva') !== false && abs((float) $imp->importe) > 0.0001) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function recalcularImpuestos(Venta $venta): array
    {
        $payload = $this->armarPayloadRecalculo($venta);
        $calculo = $this->facturacionService->calculaFacturaGeneral($payload);

        if (isset($calculo['error'])) {
            throw new \RuntimeException((string) $calculo['error']);
        }

        return $calculo;
    }

    /**
     * @return array<string, mixed>
     */
    private function armarPayloadRecalculo(Venta $venta): array
    {
        $gravadoId = EstacionamientoFacturaPayloadSupport::impuestoGravadoId();
        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];
        $impuestoIds = [];
        $incluyeImpuestos = [];

        foreach ($venta->venta_emisiones->sortBy('numeroitem') as $em) {
            $articuloIds[] = (int) ($em->articulo_id ?? 0);
            $cantidades[] = (float) $em->cantidad;
            $precios[] = (float) $em->precio;
            $descripciones[] = trim((string) ($em->detalle ?? '')) !== ''
                ? (string) $em->detalle
                : 'Ítem estacionamiento';
            $impuestoIds[] = $gravadoId;
            $incluyeImpuestos[] = '1';
        }

        if ($articuloIds === []) {
            throw new \RuntimeException('La venta no tiene renglones en venta_emision.');
        }

        return [
            'tipotransaccion_id' => (int) $venta->tipotransaccion_id,
            'puntoventa_id' => (int) $venta->puntoventa_id,
            'fechafactura' => (string) $venta->fecha,
            'leyendafactura' => (string) ($venta->leyenda ?? ''),
            'actividad_arca_id' => (int) ($venta->actividad_arca_id ?? 1),
            'cliente_id' => (int) $venta->cliente_id,
            'moneda_id' => (int) ($venta->moneda_id ?? 1),
            'listaprecio_id' => (int) config('estacionamiento.listaprecio_id', 1),
            'descuentolinea' => 0.,
            'descuentopie' => (float) ($venta->descuento ?? 0),
            'descuentoimportepie' => 0.,
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descripcionarticulos' => $descripciones,
            'impuesto_ids' => $impuestoIds,
            'incluyeimpuestos' => $incluyeImpuestos,
            'omitir_percepciones' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $recalculo
     */
    private function persistirCorreccion(Venta $venta, array $recalculo): void
    {
        $gravadoId = EstacionamientoFacturaPayloadSupport::impuestoGravadoId();
        $dataFactura = $recalculo['datosfactura'] ?? [];
        $conceptosTotales = $recalculo['conceptostotales'] ?? [];

        foreach ($venta->venta_emisiones as $em) {
            $em->impuesto_id = $gravadoId;
            $em->incluyeimpuesto = '1';
            $em->save();
        }

        Venta_Impuesto::query()
            ->where('venta_id', $venta->id)
            ->delete();

        foreach ($conceptosTotales as $conc) {
            if (! is_array($conc) || (float) ($conc['importe'] ?? 0) == 0.) {
                continue;
            }

            $impuestoId = $conc['impuesto_id'] ?? null;
            if ($impuestoId === 0) {
                $impuestoId = null;
            }

            Venta_Impuesto::query()->create([
                'venta_id' => $venta->id,
                'concepto' => (string) ($conc['concepto'] ?? ''),
                'baseimponible' => (float) ($conc['baseimponible'] ?? 0),
                'tasa' => (float) ($conc['tasa'] ?? 0),
                'importe' => (float) ($conc['importe'] ?? 0),
                'provincia_id' => $conc['provincia_id'] ?? null,
                'impuesto_id' => $impuestoId,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>|mixed  $conceptos
     */
    private function resumenConceptos(mixed $conceptos): string
    {
        if (! is_array($conceptos)) {
            return '';
        }

        $partes = [];
        foreach ($conceptos as $conc) {
            if (! is_array($conc)) {
                continue;
            }
            $concepto = (string) ($conc['concepto'] ?? '');
            $importe = round((float) ($conc['importe'] ?? 0), 2);
            if ($importe == 0.) {
                continue;
            }
            if (
                stripos($concepto, 'Iva') !== false
                || stripos($concepto, 'Gravado') !== false
                || $concepto === 'Exento'
            ) {
                $partes[] = $concepto.': '.$importe;
            }
        }

        return implode('; ', $partes);
    }
}

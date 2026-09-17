<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Backfill de asientos ERP para facturas Ferli emitidas por el ERP (OT/picking)
 * que salieron sin contabilidad.
 */
final class VentaAsientoBackfillFerliSupport
{
    public function __construct(
        private FacturacionService $facturacionService,
    ) {}

    /**
     * @return array{
     *   candidatos: list<array<string, mixed>>,
     *   ok: int,
     *   error: int,
     *   errores: list<string>
     * }
     */
    public function ejecutar(string $desdeYmd, bool $dryRun): array
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new \RuntimeException('Solo aplica a EMPRESA=Calzados Ferli.');
        }

        $candidatos = $this->listarCandidatos($desdeYmd);
        $resultado = [
            'candidatos' => [],
            'ok' => 0,
            'error' => 0,
            'errores' => [],
        ];

        foreach ($candidatos as $venta) {
            $fila = [
                'venta_id' => (int) $venta->id,
                'codigo' => (string) $venta->codigo,
                'fecha' => (string) $venta->fecha,
                'created_at' => (string) $venta->created_at,
                'total' => (float) $venta->total,
                'lineas_asiento' => 0,
            ];

            try {
                $payload = $this->armarPayload($venta);
                $fila['lineas_asiento'] = count($payload['asiento']);
                $fila['detalle'] = $payload['detalle'];
                $resultado['candidatos'][] = $fila;

                if ($dryRun) {
                    continue;
                }

                DB::transaction(function () use ($payload) {
                    $this->facturacionService->grabarAsientoContableDesdePayload(
                        $payload['asiento'],
                        $payload['empresa_id'],
                        $payload['fecha'],
                        $payload['venta_id'],
                        $payload['detalle'],
                        null,
                        $payload['moneda_id'],
                        $payload['cotizacion'],
                        $payload['signo'],
                        $payload['contrapartida_id'],
                        $payload['tipo'],
                        $payload['letra'],
                        $payload['sucursal'],
                        $payload['nro'],
                        $payload['modofacturacion'],
                        $payload['fechajornada'],
                        true,
                    );
                });
                $resultado['ok']++;
            } catch (Throwable $e) {
                $resultado['error']++;
                $resultado['errores'][] = sprintf(
                    'venta %d %s: %s',
                    (int) $venta->id,
                    (string) $venta->codigo,
                    $e->getMessage()
                );
                if ($dryRun) {
                    $resultado['candidatos'][] = $fila + ['error' => $e->getMessage()];
                }
            }
        }

        return $resultado;
    }

    /**
     * FAC/NC emitidas por ERP esta semana: tienen líneas de emisión (OT/picking),
     * impuestos con IVA, sin asiento. Excluye el lote importado Anita (sin emisión).
     *
     * @return Collection<int, Venta>
     */
    public function listarCandidatos(string $desdeYmd): Collection
    {
        return Venta::query()
            ->with([
                'venta_impuestos',
                'venta_emisiones.articulos:id,cuentacontableventa_id',
                'puntoventas:id,empresa_id,codigo,modofacturacion',
                'clientes:id,cuentacontable_id',
                'tipotransacciones:id,abreviatura,signo,operacion',
            ])
            ->where('created_at', '>=', $desdeYmd.' 00:00:00')
            ->whereHas('tipotransacciones', function ($q) {
                $q->whereIn('abreviatura', ['FAC', 'FAE', 'FAR', 'FAF', 'NCD', 'NCE', 'NCA', 'NCB', 'NCP', 'NDB', 'NDA']);
            })
            ->whereDoesntHave('asientos')
            ->whereHas('venta_emisiones', fn ($q) => $q->whereNotNull('articulo_id')->where('articulo_id', '>', 0))
            ->whereHas('venta_impuestos', fn ($q) => $q->where('concepto', 'like', 'Iva%'))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{
     *   asiento: list<array<string, mixed>>,
     *   empresa_id: int,
     *   fecha: string,
     *   venta_id: int,
     *   detalle: string,
     *   moneda_id: int,
     *   cotizacion: float,
     *   signo: float,
     *   contrapartida_id: int|null,
     *   tipo: string,
     *   letra: string,
     *   sucursal: string,
     *   nro: int,
     *   modofacturacion: string|null,
     *   fechajornada: string|null
     * }
     */
    private function armarPayload(Venta $venta): array
    {
        $pv = $venta->puntoventas;
        if (! $pv) {
            throw new \RuntimeException('Sin punto de venta');
        }
        $empresaId = (int) ($pv->empresa_id ?? 0);
        if ($empresaId <= 0) {
            throw new \RuntimeException('Sin empresa en punto de venta');
        }

        $dataFactura = $this->dataFacturaDesdeEmisiones($venta);
        if ($dataFactura === []) {
            throw new \RuntimeException('Sin renglones de emisión con artículo');
        }

        $conceptos = [];
        foreach ($venta->venta_impuestos as $imp) {
            $conceptos[] = [
                'concepto' => (string) $imp->concepto,
                'importe' => abs((float) $imp->importe),
                'impuesto_id' => $imp->impuesto_id,
                'tasa' => (float) ($imp->tasa ?? 0),
                'baseimponible' => abs((float) ($imp->baseimponible ?? 0)),
                'provincia_id' => $imp->provincia_id,
            ];
        }
        if ($conceptos === []) {
            throw new \RuntimeException('Sin impuestos de venta');
        }

        $total = abs((float) $venta->total);
        $asiento = $this->facturacionService->armaContabilidad($dataFactura, $conceptos, $empresaId, $total);
        if ($asiento === []) {
            throw new \RuntimeException('armaContabilidad devolvió vacío');
        }

        [$tipo, $letra, $sucursal, $nro] = $this->parsearCodigo((string) $venta->codigo, $venta);
        $signo = ($venta->tipotransacciones?->signo === 'S') ? 1. : -1.;

        return [
            'asiento' => $asiento,
            'empresa_id' => $empresaId,
            'fecha' => (string) $venta->fecha,
            'venta_id' => (int) $venta->id,
            'detalle' => trim($tipo.' '.$letra.' '.$sucursal.' '.$nro),
            'moneda_id' => (int) ($venta->moneda_id ?: 1),
            'cotizacion' => (float) ($venta->cotizacion ?: 1),
            'signo' => $signo,
            'contrapartida_id' => $venta->clientes?->cuentacontable_id
                ? (int) $venta->clientes->cuentacontable_id
                : null,
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'nro' => $nro,
            'modofacturacion' => $pv->modofacturacion ?? null,
            'fechajornada' => $venta->fechajornada ? (string) $venta->fechajornada : (string) $venta->fecha,
        ];
    }

    /**
     * @return list<array{cantidad:float, precio:float, cuentacontable_id:int, centrocosto_id:null}>
     */
    private function dataFacturaDesdeEmisiones(Venta $venta): array
    {
        $agg = [];
        foreach ($venta->venta_emisiones as $em) {
            $articuloId = (int) ($em->articulo_id ?? 0);
            if ($articuloId <= 0) {
                continue;
            }
            $precio = round((float) $em->precio, 2);
            $key = $articuloId.'|'.$precio;
            if (! isset($agg[$key])) {
                $agg[$key] = [
                    'cantidad' => 0.0,
                    'precio' => $precio,
                    'cuentacontable_id' => (int) ($em->articulos?->cuentacontableventa_id ?? 0),
                    'centrocosto_id' => null,
                ];
            }
            $agg[$key]['cantidad'] += abs((float) $em->cantidad);
        }

        return array_values($agg);
    }

    /**
     * @return array{0:string,1:string,2:string,3:int}
     */
    private function parsearCodigo(string $codigo, Venta $venta): array
    {
        // FAC A-00012-00083052
        if (preg_match('/^([A-Z]{3})\s+([A-Z])-(\d+)-(\d+)$/', trim($codigo), $m)) {
            return [$m[1], $m[2], $m[3], (int) $m[4]];
        }

        $tipo = substr($codigo, 0, 3) ?: ($venta->tipotransacciones?->abreviatura ?? 'FAC');
        $letra = 'A';
        $sucursal = (string) ($venta->puntoventas?->codigo ?? '0');
        $nro = (int) ($venta->numerocomprobante ?? 0);

        return [$tipo, $letra, $sucursal, $nro];
    }
}

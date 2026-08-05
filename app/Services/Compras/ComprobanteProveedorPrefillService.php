<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Support\Compras\ComprobanteProveedorCotizacionSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ComprobanteProveedorPrefillService
{
    public function __construct(
        private ComprobanteProveedorCondicionPagoDesdeOcService $condicionPagoDesdeOc,
        private ComprobanteProveedorComLegajoResolucionService $comLegajoResolucion,
        private CotizacionQueryInterface $cotizacionQuery,
    ) {}

  /**
   * @return array{
   *     data: Comprobante_Proveedor,
   *     origen_entrada: string,
   *     conceptos: Collection,
   *     cuotas: list<array<string, mixed>>,
   *     cuotas_escaladas: bool,
   *     permite_edicion_cuotas: bool,
   *     ruta_factura_pdf: string|null
   * }
   */
    public function construirDesdeRequest(Request $request): array
    {
        $precargaId = (int) $request->input('precarga_id', 0);
        $ordencompraId = (int) $request->input('ordencompra_id', 0);

        if ($precargaId > 0) {
            return $this->desdePrecarga($precargaId);
        }

        if ($ordencompraId > 0) {
            return $this->desdeOrdencompra($ordencompraId);
        }

        return $this->manual();
    }

    /** @return array<string, mixed> */
    public function manual(): array
    {
        $data = new Comprobante_Proveedor([
            'fechacomprobante' => now()->format('Y-m-d'),
            'fechaiva' => now()->format('Y-m-d'),
            'modo_carga' => ComprobanteProveedorModoCarga::SIN_RECEPCION,
            'estado' => ComprobanteProveedorEstados::BORRADOR,
            'subtotal' => 0,
            'total' => 0,
            'cotizacion' => 1,
            'moneda_id' => 1,
            'es_fce' => false,
            'pararevisar' => false,
        ]);

        return [
            'data' => $data,
            'origen_entrada' => ComprobanteProveedorOrigenEntrada::MANUAL,
            'conceptos' => collect(),
            'cuotas' => [],
            'cuotas_escaladas' => false,
            'permite_edicion_cuotas' => true,
            'ruta_factura_pdf' => null,
        ];
    }

    /** @return array<string, mixed> */
    public function desdePrecarga(int $precargaId): array
    {
        $precarga = Precarga_Comprobante_Proveedor::query()
            ->with([
                'empresas',
                'proveedores',
                'tipotransaccion_compras',
                'monedas',
                'precarga_comprobante_proveedor_conceptos',
            ])
            ->findOrFail($precargaId);

        $ordencompra = $this->resolverOrdencompraDesdePrecarga($precarga);

        $fechacomprobante = $precarga->fechafactura
            ? Carbon::parse($precarga->fechafactura)->format('Y-m-d')
            : now()->format('Y-m-d');

        $fecharecepcion = $precarga->fecharecepcionemail
            ? Carbon::parse($precarga->fecharecepcionemail)->startOfDay()
            : null;

        $modoCarga = $ordencompra
            ? ComprobanteProveedorModoCarga::ASIGNA_OC
            : ComprobanteProveedorModoCarga::SIN_RECEPCION;

        $monedaId = $this->resolverMonedaIdDesdePrecarga($precarga, $ordencompra);
        $cotizacion = $this->resolverCotizacionDesdePrecarga(
            $fechacomprobante,
            $monedaId,
            $precarga->cotizacion
        );

        $data = new Comprobante_Proveedor([
            'empresa_id' => $precarga->empresa_id,
            'proveedor_id' => $precarga->proveedor_id,
            'tipotransaccion_compra_id' => $precarga->tipotransaccion_compra_id,
            'ordencompra_id' => $ordencompra?->id,
            'precarga_comprobante_proveedor_id' => $precarga->id,
            'letra' => $precarga->letra,
            'sucursal' => $precarga->sucursal,
            'numerocomprobante' => $precarga->numerocomprobante,
            'fechacomprobante' => $fechacomprobante,
            'fechaiva' => $fechacomprobante,
            'fecharecepcion' => $fecharecepcion,
            'fechavencimientocae' => $precarga->fechavencimientocaicae,
            'numerocae' => $precarga->numerocae,
            'tipo_autorizacion' => $precarga->tipo_autorizacion,
            'subtotal' => $precarga->subtotal,
            'total' => $precarga->total,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'modo_carga' => $modoCarga,
            'estado' => ComprobanteProveedorEstados::BORRADOR,
            'es_fce' => false,
            'pararevisar' => (bool) $precarga->pararevisar,
        ]);

        $data->setRelation('empresas', $precarga->empresas);
        $data->setRelation('proveedores', $precarga->proveedores);
        $data->setRelation('tipotransaccion_compras', $precarga->tipotransaccion_compras);
        $data->setRelation('monedas', $precarga->monedas);
        $data->setRelation('ordencompras', $ordencompra);
        $data->setRelation('precarga_comprobante_proveedores', $precarga);

        $conceptos = $precarga->precarga_comprobante_proveedor_conceptos->map(function ($c) {
            return new Comprobante_Proveedor_Concepto([
                'concepto_ivacompra_id' => $c->concepto_ivacompra_id,
                'monto' => $c->monto,
                'orden' => $c->orden ?? 1,
            ]);
        });

        $cuotasMeta = $ordencompra
            ? $this->condicionPagoDesdeOc->resolverDesdeOrdencompra(
                $ordencompra,
                null,
                (float) $precarga->total,
                $fechacomprobante,
            )
            : [
                'condicionpago_id' => null,
                'ordencompra_comprobante_id' => null,
                'cuotas' => [],
                'cuotas_escaladas' => false,
                'permite_edicion_cuotas' => true,
            ];

        if ($cuotasMeta['condicionpago_id']) {
            $data->condicionpago_id = $cuotasMeta['condicionpago_id'];
        }
        if ($cuotasMeta['ordencompra_comprobante_id']) {
            $data->ordencompra_comprobante_id = $cuotasMeta['ordencompra_comprobante_id'];
        }

        $prefill = [
            'data' => $data,
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::origenComprobanteDesdePrecarga(
                $precarga->origen_entrada
            ),
            'conceptos' => $conceptos,
            'cuotas' => $this->alinearCuotasConCabecera(
                $cuotasMeta['cuotas'] ?? [],
                $monedaId,
                $cotizacion
            ),
            'cuotas_escaladas' => (bool) ($cuotasMeta['cuotas_escaladas'] ?? false),
            'permite_edicion_cuotas' => (bool) ($cuotasMeta['permite_edicion_cuotas'] ?? true),
            'ruta_factura_pdf' => $precarga->rutaalmacenamiento,
        ];

        $prefill = $this->comLegajoResolucion->aplicarAlPrefill($prefill, $precarga, $ordencompra);

        $ordencompraFinal = $prefill['data']->ordencompras ?? $ordencompra;
        if ($ordencompraFinal && (int) ($prefill['data']->ordencompra_id ?? 0) !== (int) ($ordencompra?->id ?? 0)) {
            $cuotasMeta = $this->condicionPagoDesdeOc->resolverDesdeOrdencompra(
                $ordencompraFinal,
                null,
                (float) $precarga->total,
                $fechacomprobante,
            );
            if ($cuotasMeta['condicionpago_id']) {
                $prefill['data']->condicionpago_id = $cuotasMeta['condicionpago_id'];
            }
            if ($cuotasMeta['ordencompra_comprobante_id']) {
                $prefill['data']->ordencompra_comprobante_id = $cuotasMeta['ordencompra_comprobante_id'];
            }
            $prefill['cuotas'] = $this->alinearCuotasConCabecera(
                $cuotasMeta['cuotas'] ?? [],
                (int) ($prefill['data']->moneda_id ?: $monedaId),
                (float) ($prefill['data']->cotizacion ?: $cotizacion)
            );
            $prefill['cuotas_escaladas'] = (bool) ($cuotasMeta['cuotas_escaladas'] ?? false);
        }

        return $prefill;
    }

    /** @return array<string, mixed> */
    public function desdeOrdencompra(int $ordencompraId): array
    {
        $ordencompra = Ordencompra::query()
            ->with(['empresas', 'proveedores', 'ordencompra_articulos'])
            ->findOrFail($ordencompraId);

        // Día de carga de la factura (no la fecha de la OC).
        $fecha = now()->format('Y-m-d');
        $monedaId = $this->resolverMonedaIdDesdeOrdencompra($ordencompra);
        $cotizacion = ComprobanteProveedorCotizacionSupport::resolverParaMonedaYFecha(
            $this->cotizacionQuery,
            $fecha,
            $monedaId
        );

        $data = new Comprobante_Proveedor([
            'empresa_id' => $ordencompra->empresa_id,
            'proveedor_id' => $ordencompra->proveedor_id,
            'ordencompra_id' => $ordencompra->id,
            'fechacomprobante' => $fecha,
            'fechaiva' => $fecha,
            'modo_carga' => ComprobanteProveedorModoCarga::ASIGNA_OC,
            'estado' => ComprobanteProveedorEstados::BORRADOR,
            'subtotal' => 0,
            'total' => 0,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'es_fce' => false,
            'pararevisar' => false,
        ]);

        $data->setRelation('empresas', $ordencompra->empresas);
        $data->setRelation('proveedores', $ordencompra->proveedores);
        $data->setRelation('ordencompras', $ordencompra);

        $cuotasMeta = $this->condicionPagoDesdeOc->resolverDesdeOrdencompra(
            $ordencompra,
            null,
            0.0,
            $fecha,
        );

        if ($cuotasMeta['condicionpago_id']) {
            $data->condicionpago_id = $cuotasMeta['condicionpago_id'];
        }
        if ($cuotasMeta['ordencompra_comprobante_id']) {
            $data->ordencompra_comprobante_id = $cuotasMeta['ordencompra_comprobante_id'];
        }

        $cuotas = $this->alinearCuotasConCabecera(
            $cuotasMeta['cuotas'] ?? [],
            $monedaId,
            $cotizacion
        );

        return [
            'data' => $data,
            'origen_entrada' => ComprobanteProveedorOrigenEntrada::ORDENCOMPRA,
            'conceptos' => collect(),
            'cuotas' => $cuotas,
            'cuotas_escaladas' => (bool) ($cuotasMeta['cuotas_escaladas'] ?? false),
            'permite_edicion_cuotas' => (bool) ($cuotasMeta['permite_edicion_cuotas'] ?? true),
            'ruta_factura_pdf' => null,
        ];
    }

    /** @return array<string, mixed> */
    public function paraEdicion(Comprobante_Proveedor $comprobante): array
    {
        $comprobante->loadMissing([
            'empresas',
            'proveedores',
            'tipotransaccion_compras',
            'monedas',
            'ordencompras',
            'precarga_comprobante_proveedores',
            'comprobante_proveedor_conceptos',
            'comprobante_proveedor_cuotas',
            'comprobante_proveedor_estados.usuarios',
            'comprobante_proveedor_archivos',
            'comprobante_proveedor_recepciones.recepcion_proveedores',
        ]);

        $cuotas = $comprobante->comprobante_proveedor_cuotas->map(fn ($c) => [
            'numero_cuota' => $c->numero_cuota,
            'fechavencimiento' => $c->fechavencimiento?->format('Y-m-d'),
            'monto' => (float) $c->monto,
            'moneda_id' => $c->moneda_id,
            'cotizacion' => $c->cotizacion,
            'formapago_id' => $c->formapago_id,
            'detalle' => $c->detalle,
            'ordencompra_comprobante_cuota_id' => $c->ordencompra_comprobante_cuota_id,
        ])->values()->all();

        $rutaPdf = $comprobante->comprobante_proveedor_archivos
            ->firstWhere('tipo', \App\Support\Compras\ComprobanteProveedorArchivoTipos::ORIGEN_IA)
            ?->ruta_externa;

        if (! $rutaPdf && $comprobante->precarga_comprobante_proveedores) {
            $rutaPdf = $comprobante->precarga_comprobante_proveedores->rutaalmacenamiento;
        }

        return [
            'data' => $comprobante,
            'origen_entrada' => $comprobante->origen_entrada
                ?? ComprobanteProveedorOrigenEntrada::resolver(
                    $comprobante->precarga_comprobante_proveedor_id,
                    $comprobante->ordencompra_id,
                ),
            'conceptos' => $comprobante->comprobante_proveedor_conceptos,
            'cuotas' => $cuotas,
            'cuotas_escaladas' => false,
            'permite_edicion_cuotas' => true,
            'ruta_factura_pdf' => $rutaPdf,
        ];
    }

    private function resolverOrdencompraDesdePrecarga(Precarga_Comprobante_Proveedor $precarga): ?Ordencompra
    {
        $numeroOc = trim((string) ($precarga->numeroordencompra ?? ''));
        if ($numeroOc === '') {
            return null;
        }

        return Ordencompra::query()
            ->with('ordencompra_articulos')
            ->where('empresa_id', $precarga->empresa_id)
            ->where('numeroordencompra', $numeroOc)
            ->first();
    }

    private function resolverMonedaIdDesdeOrdencompra(Ordencompra $ordencompra): int
    {
        $ordencompra->loadMissing('ordencompra_articulos');
        $linea = $ordencompra->ordencompra_articulos->first();

        return max(1, (int) ($linea->moneda_id ?? 1));
    }

    private function resolverMonedaIdDesdePrecarga(
        Precarga_Comprobante_Proveedor $precarga,
        ?Ordencompra $ordencompra,
    ): int {
        if ($ordencompra) {
            return $this->resolverMonedaIdDesdeOrdencompra($ordencompra);
        }

        return max(1, (int) ($precarga->moneda_id ?: 1));
    }

    private function resolverCotizacionDesdePrecarga(
        string $fechaComprobanteYmd,
        int $monedaId,
        mixed $cotizacionPrecarga,
    ): float {
        return ComprobanteProveedorCotizacionSupport::resolverDesdePrecarga(
            $this->cotizacionQuery,
            $fechaComprobanteYmd,
            $monedaId,
            $cotizacionPrecarga
        );
    }

    /**
     * @param  list<array<string, mixed>>  $cuotas
     * @return list<array<string, mixed>>
     */
    private function alinearCuotasConCabecera(array $cuotas, int $monedaId, float $cotizacion): array
    {
        return array_map(static function (array $cuota) use ($monedaId, $cotizacion) {
            $cuota['moneda_id'] = $monedaId;
            $cuota['cotizacion'] = $cotizacion;

            return $cuota;
        }, $cuotas);
    }
}

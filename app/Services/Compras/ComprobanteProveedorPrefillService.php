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
use App\Support\Compras\ConceptoIvacompraConsultaSupport;
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
            'fechaiva' => $this->fechaIvaDefaultAlta(),
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
            'articulos' => collect(),
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
                'precarga_comprobante_proveedor_articulos.articulos',
            ])
            ->findOrFail($precargaId);

        $ordencompra = $this->resolverOrdencompraDesdePrecarga($precarga);
        if ($ordencompra) {
            $ordencompra->loadMissing(['empresas', 'proveedores', 'ordencompra_articulos']);
        }

        $fechacomprobante = $precarga->fechafactura
            ? Carbon::parse($precarga->fechafactura)->format('Y-m-d')
            : now()->format('Y-m-d');

        $fecharecepcion = null;

        $modoCarga = $ordencompra
            ? ComprobanteProveedorModoCarga::ASIGNA_OC
            : ComprobanteProveedorModoCarga::SIN_RECEPCION;

        $monedaId = $this->resolverMonedaIdDesdePrecarga($precarga, $ordencompra);
        $cotizacion = $this->resolverCotizacionDesdePrecarga(
            $fechacomprobante,
            $monedaId,
            $precarga->cotizacion
        );

        $subtotal = (float) $precarga->subtotal;
        $total = (float) $precarga->total;
        $sumaConceptos = round((float) $precarga->precarga_comprobante_proveedor_conceptos->sum('monto'), 2);
        if ($sumaConceptos > 0 && ($total <= 1 || $sumaConceptos > $total * 10 || abs($sumaConceptos - $total) > max(1.0, $total * 0.5))) {
            $total = $sumaConceptos;
            if ($subtotal <= 1 || $subtotal > $total) {
                // Neto ≈ mayor línea de concepto (neto gravado) o total si no hay desglose.
                $subtotal = (float) $precarga->precarga_comprobante_proveedor_conceptos->max('monto');
                if ($subtotal <= 0 || $subtotal > $total) {
                    $subtotal = $total;
                }
            }
            Precarga_Comprobante_Proveedor::query()->whereKey($precarga->id)->update([
                'subtotal' => $subtotal,
                'total' => $total,
            ]);
        }

        $data = new Comprobante_Proveedor([
            'empresa_id' => (int) ($ordencompra?->empresa_id ?: $precarga->empresa_id),
            'proveedor_id' => $precarga->proveedor_id,
            'tipotransaccion_compra_id' => $precarga->tipotransaccion_compra_id,
            'ordencompra_id' => $ordencompra?->id,
            'precarga_comprobante_proveedor_id' => $precarga->id,
            'letra' => $precarga->letra,
            'sucursal' => $precarga->sucursal,
            'numerocomprobante' => $precarga->numerocomprobante,
            'fechacomprobante' => $fechacomprobante,
            'fechaiva' => $this->fechaIvaDefaultAlta(),
            'fecharecepcion' => $fecharecepcion,
            'fechavencimiento' => $this->fechaYmdDesdePrecarga($precarga->fechavencimiento ?? null),
            'fechavencimientocae' => $precarga->fechavencimientocaicae,
            'numerocae' => $precarga->numerocae,
            'tipo_autorizacion' => $precarga->tipo_autorizacion,
            'subtotal' => $subtotal,
            'total' => $total,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'modo_carga' => $modoCarga,
            'estado' => ComprobanteProveedorEstados::BORRADOR,
            'es_fce' => false,
            'pararevisar' => (bool) $precarga->pararevisar,
        ]);

        $data->setRelation('empresas', $ordencompra?->empresas ?? $precarga->empresas);
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

        if ($conceptos->isEmpty() && (int) ($precarga->tipotransaccion_compra_id ?? 0) > 0) {
            $conceptos = ConceptoIvacompraConsultaSupport::renglonesPlantillaParaTipo(
                (int) $precarga->tipotransaccion_compra_id
            );
        }

        $articulos = $precarga->precarga_comprobante_proveedor_articulos->map(function ($a) {
            return new \App\Models\Compras\Comprobante_Proveedor_Articulo([
                'articulo_id' => $a->articulo_id,
                'sku' => $a->sku,
                'codigo_proveedor' => $a->codigo_proveedor,
                'descripcion' => $a->descripcion,
                'cantidad' => $a->cantidad,
                'precio_unitario' => $a->precio_unitario,
                'orden' => $a->orden ?? 1,
            ]);
        });
        $articulos->each(function ($fila, $idx) use ($precarga) {
            $src = $precarga->precarga_comprobante_proveedor_articulos[$idx] ?? null;
            if ($src && $src->relationLoaded('articulos') && $src->articulos) {
                $fila->setRelation('articulos', $src->articulos);
            }
        });

        $cuotasMeta = $ordencompra
            ? $this->condicionPagoDesdeOc->resolverDesdeOrdencompra(
                $ordencompra,
                null,
                (float) $precarga->total,
                $fechacomprobante,
                $monedaId,
                $cotizacion,
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
            'articulos' => $articulos,
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
                (int) ($prefill['data']->moneda_id ?: $monedaId),
                (float) ($prefill['data']->cotizacion ?: $cotizacion),
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

        $prefill['cuotas'] = $this->aplicarVencimientoFacturaACuotas(
            $prefill['cuotas'] ?? [],
            $this->fechaYmdDesdePrecarga($precarga->fechavencimiento ?? null),
            (float) ($prefill['data']->total ?? $precarga->total ?? 0),
            (int) ($prefill['data']->moneda_id ?: $monedaId),
            (float) ($prefill['data']->cotizacion ?: $cotizacion),
        );

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
            'fechaiva' => $this->fechaIvaDefaultAlta(),
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
            $monedaId,
            $cotizacion,
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
            'articulos' => collect(),
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
            'ordencompras.sector_legajocompras',
            'precarga_comprobante_proveedores',
            'comprobante_proveedor_conceptos.concepto_ivacompras',
            'comprobante_proveedor_articulos.articulos',
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
            'articulos' => $comprobante->comprobante_proveedor_articulos,
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

        $nro = (int) preg_replace('/\D/', '', $numeroOc);
        if ($nro <= 0) {
            return null;
        }

        $base = Ordencompra::query()
            ->with('ordencompra_articulos')
            ->where('numeroordencompra', $nro);

        // El número de la precarga manda. Si la OC está en otra empresa (CUIT del PDF
        // vs empresa de la OC), igual se usa esa OC; no otra del mismo proveedor.
        $mismaEmpresa = (clone $base)
            ->where('empresa_id', $precarga->empresa_id)
            ->first();
        if ($mismaEmpresa) {
            return $mismaEmpresa;
        }

        return $base->orderByDesc('id')->first();
    }

    private function resolverMonedaIdDesdeOrdencompra(Ordencompra $ordencompra): int
    {
        $ordencompra->loadMissing('ordencompra_articulos');
        $linea = $ordencompra->ordencompra_articulos->first();

        return max(1, (int) ($linea->moneda_id ?? 1));
    }

    /**
     * Manda la moneda de la factura leída del PDF. La OC solo se usa cuando la precarga no
     * trae moneda: su moneda decide la cuenta de proveedores MN/ME, no los importes de la
     * factura (una OC en dólares se puede facturar en pesos y viceversa).
     */
    private function resolverMonedaIdDesdePrecarga(
        Precarga_Comprobante_Proveedor $precarga,
        ?Ordencompra $ordencompra,
    ): int {
        $desdePrecarga = (int) ($precarga->moneda_id ?: 0);
        if ($desdePrecarga > 0) {
            return $desdePrecarga;
        }

        if ($ordencompra) {
            return $this->resolverMonedaIdDesdeOrdencompra($ordencompra);
        }

        return 1;
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

    /** Fecha IVA en alta: siempre el día de carga; el operador puede cambiarla en el form. */
    private function fechaIvaDefaultAlta(): string
    {
        return now()->format('Y-m-d');
    }

    private function fechaYmdDesdePrecarga(mixed $valor): ?string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        if (! is_string($valor) && ! is_numeric($valor)) {
            return null;
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        try {
            return Carbon::parse($texto)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Si la IA/OCR leyó el vencimiento de pago de la factura, lo aplica a la 1ª cuota
     * (o crea una cuota única si no hay cuotas de la OC).
     *
     * @param  list<array<string, mixed>>  $cuotas
     * @return list<array<string, mixed>>
     */
    private function aplicarVencimientoFacturaACuotas(
        array $cuotas,
        ?string $fechaVencimientoYmd,
        float $total,
        int $monedaId,
        float $cotizacion,
    ): array {
        if ($fechaVencimientoYmd === null || $fechaVencimientoYmd === '') {
            return $cuotas;
        }

        if ($cuotas === []) {
            if ($total <= 0) {
                return $cuotas;
            }

            return [[
                'numero_cuota' => 1,
                'fechavencimiento' => $fechaVencimientoYmd,
                'monto' => round($total, 2),
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'formapago_id' => null,
                'detalle' => 'Vencimiento factura',
                'ordencompra_comprobante_cuota_id' => null,
            ]];
        }

        $cuotas[0]['fechavencimiento'] = $fechaVencimientoYmd;

        return $cuotas;
    }
}

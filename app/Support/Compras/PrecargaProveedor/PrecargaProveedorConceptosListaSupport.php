<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Services\Compras\ComprobanteService;
use App\Services\Compras\OrdencompraService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use RuntimeException;

/**
 * Lista de conceptos IVA compra para precarga (misma lógica que API listaConcepto).
 * Tipo de ítem: si el proveedor tiene medidores en proveedor_servicio → fuerza S (FIS/FNS/…).
 * OC multi-fino → tipo P (FPB/…) con conceptos unidos desde cada CC.
 */
final class PrecargaProveedorConceptosListaSupport
{
    public function __construct(
        private OrdencompraService $ordencompraService,
        private CentrocostoRepositoryInterface $centrocostoRepository,
        private ComprobanteService $comprobanteService,
        private PrecargaProveedorProrrateoMultiCcSupport $prorrateoMultiCcSupport,
    ) {}

    /**
     * @return array{
     *   tipocomprobante: string,
     *   letra: string,
     *   centro_costo_codigo: string,
     *   centros_costo?: list<string>,
     *   tipos_origen?: list<string>,
     *   pesos_por_fino?: array<string, float>,
     *   prorrateo_multi_cc?: bool,
     *   conceptos: list<array{id_concepto: int|string, nombre: string, descripcion_ai: string, concepto_ivacompra_id?: int}>,
     *   es_proveedor_servicios?: bool,
     *   tipo_item?: string
     * }
     */
    public function resolver(string $cuitProveedor, string $numeroOc, string $tipoComprobante = 'FC'): array
    {
        $ordencompra = $this->ordencompraService->leeOrdenCompra($numeroOc);
        if ($ordencompra === 'OC inexistente') {
            throw new RuntimeException('OC inexistente');
        }

        $datosOrdenCompra = $ordencompra['ordencompra'];
        $itemsOrdenCompra = $ordencompra['item'];

        $cuitOrdenCompra = str_replace('-', '', (string) ($datosOrdenCompra->prom_cuit ?? ''));
        $cuitProveedor = str_replace('-', '', $cuitProveedor);

        if (! PrecargaProveedorCuitCoincidenciaSupport::coinciden($cuitOrdenCompra, $cuitProveedor)) {
            throw new RuntimeException('OC no corresponde con el CUIT del proveedor indicado');
        }

        $tipoItem = PrecargaProveedorTipoItemSupport::resolver($itemsOrdenCompra, $cuitProveedor);
        $centrosConPeso = PrecargaProveedorProrrateoMultiCcSupport::centrosConPesoParaOc(
            (string) $numeroOc,
            $itemsOrdenCompra,
        );
        // Si hay OC ERP, el tipo de ítem también puede resolverse desde ella (más fiable).
        try {
            $ocErp = \App\Models\Compras\Ordencompra::query()
                ->where('numeroordencompra', $numeroOc)
                ->orWhere('numeroordencompra', ltrim((string) $numeroOc, '0'))
                ->first();
            if ($ocErp) {
                $tipoItem = PrecargaProveedorAbreviaturaTipoSupport::tipoItemDesdeOrdencompra($ocErp);
            }
        } catch (\Throwable) {
        }
        $prorrateo = $this->prorrateoMultiCcSupport->resolverDesdeCentros(
            $tipoComprobante,
            $tipoItem,
            $centrosConPeso,
        );

        if ($prorrateo['activo']) {
            PrecargaProveedorProrrateoMultiCcSupport::assertTipoProrrateadoExiste($prorrateo['tipocomprobante']);

            return [
                'tipocomprobante' => $prorrateo['tipocomprobante'],
                'letra' => (string) ($datosOrdenCompra->prom_letra ?? 'A'),
                'centro_costo_codigo' => (string) ($prorrateo['centros'][0] ?? ''),
                'centros_costo' => $prorrateo['centros'],
                'tipos_origen' => $prorrateo['tipos_origen'],
                'pesos_por_fino' => $prorrateo['pesos_por_fino'],
                'prorrateo_multi_cc' => true,
                'es_proveedor_servicios' => PrecargaProveedorTipoItemSupport::proveedorTieneServicios($cuitProveedor),
                'tipo_item' => $tipoItem,
                'conceptos' => $prorrateo['conceptos'],
            ];
        }

        $centroCostoDestino = PrecargaProveedorCentrocostoDestinoSupport::codigoDesdeOcAnita(
            $datosOrdenCompra,
            $itemsOrdenCompra
        );
        $centrocosto = $this->centrocostoRepository->findPorCodigo($centroCostoDestino);
        if (! $centrocosto) {
            throw new RuntimeException('No existe centro de costo de la OC');
        }

        $tipoIva = (string) ($centrocosto->tipoiva ?? '');
        if (! in_array(substr($tipoIva, 0, 1), ['I', 'D', 'N'], true)) {
            throw new RuntimeException('Centro de costo de la OC sin tipo IVA válido');
        }

        $abreviatura = PrecargaProveedorAbreviaturaTipoSupport::abreviatura(
            $tipoComprobante,
            $centroCostoDestino,
            $tipoIva,
            $tipoItem,
        );
        if ($abreviatura === '') {
            throw new RuntimeException('Tipo de comprobante genérico inválido para listaConcepto');
        }

        $comprobante = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($abreviatura);
        if (! $comprobante || $comprobante->tipotransaccion_compra_concepto_ivacompras->isEmpty()) {
            if (! EntornoEmpresaSupport::esAgg()) {
                return $this->listaProvisoriaInstalacion(
                    $cuitProveedor,
                    $tipoComprobante,
                    null,
                    (string) ($datosOrdenCompra->prom_letra ?? 'A'),
                    $centroCostoDestino,
                );
            }
            throw new RuntimeException('No hay conceptos IVA configurados para tipo «'.$abreviatura.'»');
        }

        $conceptos = [];
        foreach ($comprobante->tipotransaccion_compra_concepto_ivacompras as $linea) {
            $concepto = $linea->concepto_ivacompras;
            if (! $concepto) {
                continue;
            }
            $concepto->loadMissing('impuestos');
            $conceptos[] = [
                'id_concepto' => (int) $concepto->codigo,
                'concepto_ivacompra_id' => (int) $concepto->id,
                'nombre' => (string) $concepto->nombre,
                'descripcion_ai' => (string) ($concepto->nombre_ia ?: $concepto->nombre),
                'tipoconcepto' => (string) ($concepto->tipoconcepto ?? ''),
                'alicuota_iva' => $this->inferirAlicuotaDesdeConcepto($concepto),
            ];
        }

        return [
            'tipocomprobante' => $abreviatura,
            'letra' => (string) ($datosOrdenCompra->prom_letra ?? 'A'),
            'centro_costo_codigo' => $centroCostoDestino,
            'prorrateo_multi_cc' => false,
            'es_proveedor_servicios' => PrecargaProveedorTipoItemSupport::proveedorTieneServicios($cuitProveedor),
            'tipo_item' => $tipoItem,
            'conceptos' => $conceptos,
        ];
    }

    /**
     * Lista de conceptos sin OC: abreviatura fina según centro de costo de compra del proveedor.
     *
     * @return array{
     *   tipocomprobante: string,
     *   letra: string,
     *   centro_costo_codigo: string,
     *   prorrateo_multi_cc?: bool,
     *   conceptos: list<array{id_concepto: int|string, nombre: string, descripcion_ai: string, concepto_ivacompra_id?: int}>,
     *   es_proveedor_servicios?: bool,
     *   tipo_item?: string
     * }
     */
    public function resolverSinOc(
        string $cuitProveedor,
        string $codigoCentroCosto,
        string $tipoComprobante = 'FC',
        ?int $proveedorId = null,
        string $letra = 'A',
    ): array {
        $cuitProveedor = str_replace('-', '', $cuitProveedor);
        $tipoItem = PrecargaProveedorTipoItemSupport::resolver([], $cuitProveedor, $proveedorId);

        $centrocosto = $this->centrocostoRepository->findPorCodigo($codigoCentroCosto);
        if (! $centrocosto) {
            throw new RuntimeException('No existe el centro de costo de compra del proveedor (código '.$codigoCentroCosto.')');
        }

        $tipoIva = (string) ($centrocosto->tipoiva ?? '');
        if (! in_array(substr($tipoIva, 0, 1), ['I', 'D', 'N'], true)) {
            throw new RuntimeException('Centro de costo del proveedor sin tipo IVA válido');
        }

        $abreviatura = PrecargaProveedorAbreviaturaTipoSupport::abreviatura(
            $tipoComprobante,
            $codigoCentroCosto,
            $tipoIva,
            $tipoItem,
        );
        if ($abreviatura === '') {
            throw new RuntimeException('Tipo de comprobante genérico inválido para listaConcepto sin OC');
        }

        $comprobante = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($abreviatura);
        if (! $comprobante || $comprobante->tipotransaccion_compra_concepto_ivacompras->isEmpty()) {
            if (! EntornoEmpresaSupport::esAgg()) {
                return $this->listaProvisoriaInstalacion(
                    $cuitProveedor,
                    $tipoComprobante,
                    $proveedorId,
                    $letra,
                    (string) ($centrocosto->codigo ?? $codigoCentroCosto),
                );
            }
            throw new RuntimeException('No hay conceptos IVA configurados para tipo «'.$abreviatura.'»');
        }

        $conceptos = [];
        foreach ($comprobante->tipotransaccion_compra_concepto_ivacompras as $linea) {
            $concepto = $linea->concepto_ivacompras;
            if (! $concepto) {
                continue;
            }
            $concepto->loadMissing('impuestos');
            $conceptos[] = [
                'id_concepto' => (int) $concepto->codigo,
                'concepto_ivacompra_id' => (int) $concepto->id,
                'nombre' => (string) $concepto->nombre,
                'descripcion_ai' => (string) ($concepto->nombre_ia ?: $concepto->nombre),
                'tipoconcepto' => (string) ($concepto->tipoconcepto ?? ''),
                'alicuota_iva' => $this->inferirAlicuotaDesdeConcepto($concepto),
            ];
        }

        return [
            'tipocomprobante' => $abreviatura,
            'letra' => $letra !== '' ? $letra : 'A',
            'centro_costo_codigo' => (string) ($centrocosto->codigo ?? $codigoCentroCosto),
            'prorrateo_multi_cc' => false,
            'es_proveedor_servicios' => PrecargaProveedorTipoItemSupport::proveedorTieneServicios(
                $cuitProveedor,
                $proveedorId
            ),
            'tipo_item' => $tipoItem,
            'conceptos' => $conceptos,
        ];
    }

    /**
     * Solo precarga PDF con IA, sin OC y sin centro de costo de compra del proveedor.
     * El tipo es provisorio (el primero del maestro que tenga conceptos). No inventa un CC.
     *
     * @return array{
     *   tipocomprobante: string,
     *   letra: string,
     *   centro_costo_codigo: string,
     *   prorrateo_multi_cc: bool,
     *   conceptos: list<array{id_concepto: int|string, nombre: string, descripcion_ai: string, concepto_ivacompra_id?: int}>,
     *   es_proveedor_servicios: bool,
     *   tipo_item: string,
     *   provisorio_sin_centro_costo: bool
     * }
     */
    public function resolverSinOcSinCentroCosto(
        string $cuitProveedor,
        string $tipoComprobante = 'FC',
        ?int $proveedorId = null,
        string $letra = 'A',
    ): array {
        $cuitProveedor = str_replace('-', '', $cuitProveedor);
        $tipoItem = PrecargaProveedorTipoItemSupport::resolver([], $cuitProveedor, $proveedorId);
        $comprobante = null;
        $abreviatura = '';
        foreach ($this->abreviaturasProvisoriasSinCentroCosto($tipoComprobante, $tipoItem) as $candidata) {
            $encontrado = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($candidata);
            if ($encontrado && $encontrado->tipotransaccion_compra_concepto_ivacompras->isNotEmpty()) {
                $comprobante = $encontrado;
                $abreviatura = $candidata;
                break;
            }
        }
        if (! $comprobante) {
            throw new RuntimeException(
                'No hay un tipo de comprobante con conceptos IVA para armar la precarga sin centro de costo.'
            );
        }

        $conceptos = [];
        foreach ($comprobante->tipotransaccion_compra_concepto_ivacompras as $linea) {
            $concepto = $linea->concepto_ivacompras;
            if (! $concepto) {
                continue;
            }
            $concepto->loadMissing('impuestos');
            $conceptos[] = [
                'id_concepto' => (int) $concepto->codigo,
                'concepto_ivacompra_id' => (int) $concepto->id,
                'nombre' => (string) $concepto->nombre,
                'descripcion_ai' => (string) ($concepto->nombre_ia ?: $concepto->nombre),
                'tipoconcepto' => (string) ($concepto->tipoconcepto ?? ''),
                'alicuota_iva' => $this->inferirAlicuotaDesdeConcepto($concepto),
            ];
        }

        return [
            'tipocomprobante' => $abreviatura,
            'letra' => $letra !== '' ? $letra : 'A',
            'centro_costo_codigo' => '',
            'tipo_provisorio' => true,
            'prorrateo_multi_cc' => false,
            'es_proveedor_servicios' => PrecargaProveedorTipoItemSupport::proveedorTieneServicios(
                $cuitProveedor,
                $proveedorId
            ),
            'tipo_item' => $tipoItem,
            'provisorio_sin_centro_costo' => true,
            'conceptos' => $conceptos,
        ];
    }

    /**
     * Tipo del maestro local (FAB/FAS/…) cuando la abreviatura fina de AGG no existe.
     * Conserva el centro de costo real si ya se conocía.
     *
     * @return array<string, mixed>
     */
    private function listaProvisoriaInstalacion(
        string $cuitProveedor,
        string $tipoComprobante,
        ?int $proveedorId,
        string $letra,
        string $centroCostoCodigo,
    ): array {
        $lista = $this->resolverSinOcSinCentroCosto($cuitProveedor, $tipoComprobante, $proveedorId, $letra);
        $lista['centro_costo_codigo'] = $centroCostoCodigo;
        $lista['tipo_provisorio'] = true;
        $lista['provisorio_sin_centro_costo'] = $centroCostoCodigo === '';

        return $lista;
    }

    /**
     * @return list<string>
     */
    private function abreviaturasProvisoriasSinCentroCosto(string $tipoComprobante, string $tipoItem): array
    {
        $tipo = strtoupper(trim($tipoComprobante));
        $item = strtoupper(trim($tipoItem)) ?: 'B';
        if (in_array($tipo, ['REC', 'REM'], true)) {
            return [$tipo];
        }

        return match ($tipo) {
            'ND' => match ($item) {
                'S' => ['DIS', 'DNS', 'NDS', 'DIB', 'NDB'],
                'L' => ['DIL', 'NDL', 'NDB'],
                'U' => ['DIU', 'NDU', 'NDB'],
                default => ['DIB', 'DNB', 'NDB', 'NDS'],
            },
            'NC' => match ($item) {
                'S' => ['CIS', 'CNS', 'NCS', 'CIB', 'NCB'],
                'L' => ['CIL', 'NCL', 'NCB'],
                'U' => ['CIU', 'NCU', 'NCB'],
                default => ['CIB', 'CNB', 'NCB', 'NCS'],
            },
            default => match ($item) {
                'S' => ['FIS', 'FNS', 'FAS', 'FAB', 'FAC'],
                'L' => ['FIL', 'FNL', 'FAL', 'FAB', 'FAC'],
                'U' => ['FIU', 'FNU', 'FAU', 'FAB', 'FAC'],
                default => ['FIB', 'FNB', 'FAB', 'FAS', 'FAC'],
            },
        };
    }

    private function inferirAlicuotaDesdeConcepto(object $concepto): ?float
    {
        if ($concepto->impuestos && isset($concepto->impuestos->valor)) {
            return (float) $concepto->impuestos->valor;
        }

        $texto = strtolower((string) ($concepto->nombre_ia ?? '').' '.($concepto->nombre ?? ''));
        if (preg_match('/\b(21|10[,.]5|27|5)\s*%/', $texto, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }
}

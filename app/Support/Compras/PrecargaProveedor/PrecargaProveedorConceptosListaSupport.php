<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Services\Compras\ComprobanteService;
use App\Services\Compras\OrdencompraService;
use RuntimeException;

/**
 * Lista de conceptos IVA compra para precarga (misma lógica que API listaConcepto).
 */
final class PrecargaProveedorConceptosListaSupport
{
    public function __construct(
        private OrdencompraService $ordencompraService,
        private CentrocostoRepositoryInterface $centrocostoRepository,
        private ComprobanteService $comprobanteService,
    ) {}

    /**
     * @return array{
     *   tipocomprobante: string,
     *   letra: string,
     *   centro_costo_codigo: string,
     *   conceptos: list<array{id_concepto: int|string, nombre: string, descripcion_ai: string, concepto_ivacompra_id?: int}>
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

        if ($cuitOrdenCompra !== $cuitProveedor) {
            throw new RuntimeException('OC no corresponde con el CUIT del proveedor indicado');
        }

        $centroCostoDestino = (string) ($datosOrdenCompra->penmp_ccosto_dest ?? '');
        $centrocosto = $this->centrocostoRepository->findPorCodigo($centroCostoDestino);
        if (! $centrocosto) {
            throw new RuntimeException('No existe centro de costo de la OC');
        }

        $tipoIva = (string) ($centrocosto->tipoiva ?? '');
        if (! in_array(substr($tipoIva, 0, 1), ['I', 'D', 'N'], true)) {
            throw new RuntimeException('Centro de costo de la OC sin tipo IVA válido');
        }

        $tipoItem = 'B';
        foreach ($itemsOrdenCompra as $item) {
            if ($item->stkm_tipo_articulo == 'S') {
                $tipoItem = 'S';
            }
            if ($item->stkm_agrupacion == '0081') {
                $tipoItem = 'L';
            }
            if ($item->stkm_tipo_articulo == 'U') {
                $tipoItem = 'U';
            }
        }

        $inicial = match ($tipoComprobante) {
            'FC' => 'F',
            'ND' => 'D',
            'NC' => 'C',
            default => '',
        };

        if (! in_array($tipoComprobante, ['REC', 'REM'], true)) {
            $abreviatura = match ((int) $centroCostoDestino) {
                85 => $inicial.'GA',
                104 => $inicial.'EG',
                default => $inicial.substr($tipoIva, 0, 1).$tipoItem,
            };
        } else {
            $abreviatura = $tipoComprobante;
        }

        $comprobante = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($abreviatura);
        if (! $comprobante || $comprobante->tipotransaccion_compra_concepto_ivacompras->isEmpty()) {
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
            'conceptos' => $conceptos,
        ];
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

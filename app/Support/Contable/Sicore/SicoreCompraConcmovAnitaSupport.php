<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

use App\ApiAnita;
use App\Models\Compras\Concepto_Ivacompra;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;

/**
 * Lectura Anita compra + concmov para base e importe de retenciones IVA (retimov).
 * Los conceptos se interpretan con el maestro ERP concepto_ivacompra.
 */
final class SicoreCompraConcmovAnitaSupport
{
    /** @var array<string, ?array<string, mixed>> */
    private array $compraCache = [];

    /** @var array<int, list<array<string, mixed>>> */
    private array $concmovCache = [];

    /** @var array<int, ?Concepto_Ivacompra> */
    private array $conceptoPorCodigo = [];

    public function __construct(
        private readonly Concepto_IvacompraRepositoryInterface $conceptoIvacompraRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>  $retimov
     * @return array{importe_comp: float, base_calculo: float}
     */
    public function importesDesdeCompraConcmov(array $retimov): array
    {
        $compra = $this->leeCompra($retimov);
        $nroInterno = (int) ($retimov['retiv_nro_interno'] ?? 0);
        $lineas = $nroInterno > 0 ? $this->leeConcmov($nroInterno) : [];
        $coef = $this->coefMonedaCompra($compra);

        $montoComprobante = (float) ($compra['com_total'] ?? $compra['com_monto'] ?? 0);
        if ($montoComprobante <= 0) {
            $montoComprobante = (float) ($compra['com_subtotal'] ?? 0);
        }

        $totConcepto = $this->totConceptoNetoGravado($lineas);

        return [
            'importe_comp' => round(abs($montoComprobante) * $coef, 2),
            'base_calculo' => round(abs($totConcepto) * $coef, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $retimov
     * @return array<string, mixed>
     */
    private function leeCompra(array $retimov): array
    {
        $proveedor = str_pad(trim((string) ($retimov['retiv_proveedor'] ?? '')), 6, '0', STR_PAD_LEFT);
        $tipo = substr(trim((string) ($retimov['retiv_tipo_comp'] ?? '')), 0, 3);
        $letra = strtoupper(substr(trim((string) ($retimov['retiv_letra_comp'] ?? 'A')), 0, 1));
        $sucursal = (int) ($retimov['retiv_suc_comp'] ?? 0);
        $nro = (int) ($retimov['retiv_nro_comp'] ?? 0);
        $nroInterno = (int) ($retimov['retiv_nro_interno'] ?? 0);

        $clave = implode('|', [$proveedor, $tipo, $letra, $sucursal, $nro, $nroInterno]);
        if (array_key_exists($clave, $this->compraCache)) {
            return $this->compraCache[$clave] ?? [];
        }

        $where = " WHERE com_proveedor = '".$proveedor."'"
            ." AND com_tipo = '".$tipo."'"
            ." AND com_letra = '".$letra."'"
            ." AND com_sucursal = ".$sucursal
            ." AND com_nro = ".$nro;
        if ($nroInterno > 0) {
            $where .= ' AND com_nro_interno = '.$nroInterno;
        }

        $api = new ApiAnita();
        $payload = [
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'compra',
            'campos' => implode(', ', [
                'com_proveedor', 'com_tipo', 'com_letra', 'com_sucursal', 'com_nro',
                'com_nro_interno', 'com_fecha', 'com_subtotal', 'com_total', 'com_monto',
                'com_cod_mon', 'com_cotizacion',
            ]),
            'whereArmado' => $where,
        ];

        $filas = ApiAnita::decodificarListaFilas($api->apiCall($payload));
        $compra = [];
        if ($filas !== []) {
            $compra = (array) $filas[0];
        }

        $this->compraCache[$clave] = $compra;

        return $compra;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leeConcmov(int $nroInterno): array
    {
        if ($nroInterno <= 0) {
            return [];
        }

        if (array_key_exists($nroInterno, $this->concmovCache)) {
            return $this->concmovCache[$nroInterno];
        }

        $api = new ApiAnita();
        $payload = [
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'concmov',
            'campos' => 'concv_nro_interno, concv_concepto, concv_importe, concv_monto',
            'whereArmado' => ' WHERE concv_nro_interno = '.$nroInterno,
            'orderBy' => 'concv_concepto',
        ];

        $filas = [];
        foreach (ApiAnita::decodificarListaFilas($api->apiCall($payload)) as $row) {
            $row = (array) $row;
            $importe = (float) ($row['concv_monto'] ?? $row['concv_importe'] ?? 0);
            if (abs($importe) < 0.0001) {
                continue;
            }
            $filas[] = [
                'concepto' => (int) ($row['concv_concepto'] ?? 0),
                'importe' => $importe,
            ];
        }

        $this->concmovCache[$nroInterno] = $filas;

        return $filas;
    }

    /**
     * Equivalente a CONCV_tot_concepto: neto gravado (conceptos tipo G en ERP).
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    private function totConceptoNetoGravado(array $lineas): float
    {
        $total = 0.0;
        foreach ($lineas as $linea) {
            $codigo = (int) ($linea['concepto'] ?? 0);
            if ($codigo <= 0) {
                continue;
            }

            $concepto = $this->conceptoPorCodigo($codigo);
            if ($concepto === null) {
                continue;
            }

            $tipo = strtoupper(trim((string) ($concepto->tipoconcepto ?? '')));
            if ($tipo !== 'G') {
                continue;
            }

            $total += abs((float) ($linea['importe'] ?? 0));
        }

        return round($total, 2);
    }

    private function conceptoPorCodigo(int $codigo): ?Concepto_Ivacompra
    {
        if (array_key_exists($codigo, $this->conceptoPorCodigo)) {
            return $this->conceptoPorCodigo[$codigo];
        }

        $variants = array_values(array_unique(array_filter([
            (string) $codigo,
            str_pad((string) $codigo, 3, '0', STR_PAD_LEFT),
            str_pad((string) $codigo, 4, '0', STR_PAD_LEFT),
        ])));

        $concepto = null;
        foreach ($variants as $variant) {
            $concepto = $this->conceptoIvacompraRepository->findPorCodigo($variant);
            if ($concepto !== null) {
                break;
            }
        }

        $this->conceptoPorCodigo[$codigo] = $concepto;

        return $concepto;
    }

    /**
     * @param  array<string, mixed>  $compra
     */
    private function coefMonedaCompra(array $compra): float
    {
        if ($compra === []) {
            return 1.0;
        }

        $codMon = trim((string) ($compra['com_cod_mon'] ?? '1'));
        if ($codMon === '' || $codMon === '1') {
            return 1.0;
        }

        $cot = (float) ($compra['com_cotizacion'] ?? 0);

        return $cot > 0 ? $cot : 1.0;
    }
}

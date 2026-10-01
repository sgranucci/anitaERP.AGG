<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Concepto_Ivacompra;
use App\Support\Compras\ComprobanteProveedorCuentaDebeNetoSupport;
use App\Support\Compras\ComprobanteProveedorDebeGastoSupport;
use Tests\TestCase;

class ComprobanteProveedorImpuestoInternoGastoTest extends TestCase
{
    public function test_el_impuesto_interno_sin_cuenta_usa_la_cuenta_de_gasto_del_neto(): void
    {
        $comprobante = $this->facturaCon(
            $this->concepto(1, 'G', '50', 'Gravado 21%', 900),
            1000.0,
            $this->concepto(2, 'T', '510', 'Impuesto interno', 0),
            50.0,
        );

        $lineaIi = $comprobante->comprobante_proveedor_conceptos->last();
        $this->assertSame(1050.0, ComprobanteProveedorDebeGastoSupport::totalNetoImputable($comprobante));
        $this->assertSame(
            900,
            ComprobanteProveedorCuentaDebeNetoSupport::resolverParaLinea(
                $comprobante,
                $lineaIi,
                $lineaIi->concepto_ivacompras
            )
        );
    }

    public function test_codigo_5_tipo_n_tambien_va_a_la_cuenta_cargada_en_el_renglon_de_gasto(): void
    {
        $gravado = $this->concepto(1, 'G', '50', 'Gravado', 0);
        $ii = $this->concepto(2, 'N', '5', 'Impuesto interno', 0);
        $comprobante = new Comprobante_Proveedor([
            'empresa_id' => 1,
            'total' => 1100,
        ]);
        $lineaGasto = new Comprobante_Proveedor_Concepto([
            'concepto_ivacompra_id' => 1,
            'monto' => 1000,
            'cuentacontabledebe_id' => 321,
        ]);
        $lineaGasto->setRelation('concepto_ivacompras', $gravado);
        $lineaIi = new Comprobante_Proveedor_Concepto([
            'concepto_ivacompra_id' => 2,
            'monto' => 80,
            'cuentacontabledebe_id' => null,
        ]);
        $lineaIi->setRelation('concepto_ivacompras', $ii);
        $comprobante->setRelation('comprobante_proveedor_conceptos', collect([$lineaGasto, $lineaIi]));

        $this->assertSame(1080.0, ComprobanteProveedorDebeGastoSupport::totalNetoImputable($comprobante));
        $this->assertSame(
            321,
            ComprobanteProveedorCuentaDebeNetoSupport::resolverParaLinea($comprobante, $lineaIi, $ii)
        );
    }

    public function test_el_iva_no_entra_al_neto_de_gasto(): void
    {
        $comprobante = $this->facturaCon(
            $this->concepto(1, 'G', '50', 'Gravado', 900),
            1000.0,
            $this->concepto(3, 'I', '503', 'IVA 21%', 700),
            210.0,
        );

        $this->assertSame(1000.0, ComprobanteProveedorDebeGastoSupport::totalNetoImputable($comprobante));
    }

    private function facturaCon(
        Concepto_Ivacompra $neto,
        float $montoNeto,
        Concepto_Ivacompra $otro,
        float $montoOtro,
    ): Comprobante_Proveedor {
        $comprobante = new Comprobante_Proveedor([
            'empresa_id' => 1,
            'total' => $montoNeto + $montoOtro,
        ]);
        $lineaNeto = new Comprobante_Proveedor_Concepto([
            'concepto_ivacompra_id' => (int) $neto->id,
            'monto' => $montoNeto,
        ]);
        $lineaNeto->setRelation('concepto_ivacompras', $neto);
        $lineaOtro = new Comprobante_Proveedor_Concepto([
            'concepto_ivacompra_id' => (int) $otro->id,
            'monto' => $montoOtro,
        ]);
        $lineaOtro->setRelation('concepto_ivacompras', $otro);
        $comprobante->setRelation('comprobante_proveedor_conceptos', collect([$lineaNeto, $lineaOtro]));

        return $comprobante;
    }

    private function concepto(int $id, string $tipo, string $codigo, string $nombre, int $cuentaDebe): Concepto_Ivacompra
    {
        $concepto = new Concepto_Ivacompra([
            'tipoconcepto' => $tipo,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'cuentacontabledebe_id' => $cuentaDebe > 0 ? $cuentaDebe : null,
        ]);
        $concepto->id = $id;
        $concepto->setRelation('concepto_ivacompra_empresas', collect());

        return $concepto;
    }
}

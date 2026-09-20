<?php

namespace Tests\Unit\Models\Compras;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * El neto que sale al proveedor es bruto menos retenciones. La columna de retenciones es
 * `importe`: sumar `monto` devolvía null por fila, el total daba 0 y se giraba el bruto
 * (la retención se le pagaba al proveedor y además se le debía a AFIP).
 */
class PagoproveedorNetoRetencionesTest extends TestCase
{
    private function pagoCon(float $monto, float ...$importes): Pagoproveedor
    {
        $pago = new Pagoproveedor(['monto' => $monto]);
        $pago->setRelation('pagoproveedor_retenciones', new Collection(
            array_map(
                static fn (float $importe) => new Pagoproveedor_Retencion(['importe' => $importe]),
                $importes
            )
        ));

        return $pago;
    }

    public function test_total_retenciones_suma_la_columna_importe(): void
    {
        $this->assertSame(150.0, $this->pagoCon(1000.0, 100.0, 50.0)->totalRetenciones());
    }

    public function test_neto_descuenta_las_retenciones_del_bruto(): void
    {
        $this->assertSame(850.0, $this->pagoCon(1000.0, 100.0, 50.0)->netoAPagar());
    }

    public function test_neto_igual_al_bruto_sin_retenciones(): void
    {
        $this->assertSame(1000.0, $this->pagoCon(1000.0)->netoAPagar());
    }

    public function test_caso_real_op_124828(): void
    {
        // OP 124828: bruto 2.026.655,58 con retención 66.258,68 → cheque por 1.960.396,90.
        $this->assertSame(1960396.90, $this->pagoCon(2026655.58, 66258.68)->netoAPagar());
    }

    public function test_retenciones_mayores_al_bruto_no_dan_neto_negativo(): void
    {
        $this->assertSame(0.0, $this->pagoCon(100.0, 250.0)->netoAPagar());
    }

    public function test_neto_admite_cuatro_decimales_para_el_lote_bancario(): void
    {
        $this->assertSame(0.1235, $this->pagoCon(1.0, 0.8765)->netoAPagar(4));
    }
}

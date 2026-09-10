<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\ChequeFormOldInputSupport;
use PHPUnit\Framework\TestCase;

class ChequeFormOldInputSupportTest extends TestCase
{
    public function test_mapea_cheque_emitido_con_letra_y_monto(): void
    {
        $old = [
            'numerocheque_emitidos' => ['41561955'],
            'montocheque_emitidos' => ['1500.50'],
            'cuentacaja_emitido_ids' => ['12'],
            'chequera_emitido_ids' => ['3'],
            'fechapago_emitidos' => ['2026-09-10'],
            'caracter_emitidos' => ['O'],
            'para_dep_emitidos' => ['E'],
            'negociable_emitidos' => ['N'],
            'anombrede_emitidos' => ['YAFEMA S.R.L.'],
            'moneda_emitido_ids' => ['1'],
            'cotizacioncheque_emitidos' => ['1'],
            'codigo_emitido' => ['127'],
            'cheque_emitido_ids' => [''],
        ];
        $cuenta = (object) ['codigo' => '00000127', 'nombre' => 'BCO MACRO'];

        $fila = ChequeFormOldInputSupport::mapearFilaEmitido($old, 0, $cuenta);

        $this->assertNotNull($fila);
        $this->assertSame('41561955', $fila->numerocheque);
        $this->assertEqualsWithDelta(1500.50, (float) $fila->monto, 0.001);
        $this->assertSame(12, $fila->cuentacaja_id);
        $this->assertSame('3', (string) $fila->chequera_id);
        $this->assertSame('YAFEMA S.R.L.', $fila->anombrede);
        $this->assertSame('E', $fila->para_dep);
        $this->assertSame('N', $fila->negociable);
        $this->assertSame('00000127', $fila->cuentacajas->codigo);
    }

    public function test_omite_fila_vacia(): void
    {
        $this->assertNull(ChequeFormOldInputSupport::mapearFilaEmitido([
            'numerocheque_emitidos' => [''],
            'montocheque_emitidos' => ['0'],
            'cuentacaja_emitido_ids' => [''],
            'codigo_emitido' => [''],
        ], 0));
    }

    public function test_mapea_cheque_recibido(): void
    {
        $old = [
            'numerocheque_recibidos' => ['999'],
            'montocheque_recibidos' => ['100'],
            'banco_recibido_ids' => ['4'],
            'fechapago_recibidos' => ['2026-09-01'],
            'sucursalpago_recibidos' => ['1'],
            'cuentalibradora_recibidos' => ['123'],
            'monedacheque_recibido_ids' => ['1'],
            'cotizacioncheque_recibidos' => ['1'],
        ];
        $banco = (object) ['codigo' => '007', 'nombre' => 'GALICIA'];
        $fila = ChequeFormOldInputSupport::mapearFilaRecibido($old, 0, $banco);
        $this->assertNotNull($fila);
        $this->assertSame('999', $fila->numerocheque);
        $this->assertSame(4, $fila->banco_id);
        $this->assertSame('GALICIA', $fila->bancos->nombre);
    }
}

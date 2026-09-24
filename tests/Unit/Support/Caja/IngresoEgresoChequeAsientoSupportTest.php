<?php

namespace Tests\Unit\Support\Caja;

use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Support\Caja\IngresoEgresoChequeAsientoSupport;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class IngresoEgresoChequeAsientoSupportTest extends TestCase
{
    public function test_cheque_emitido_siempre_haber_en_op_egreso(): void
    {
        $this->assertSame('H', IngresoEgresoChequeAsientoSupport::dhEmitido());
        $this->assertSame('H', IngresoEgresoChequeAsientoSupport::dhEmitido(false));
        $this->assertSame('D', IngresoEgresoChequeAsientoSupport::dhEmitido(true));
    }

    public function test_cheque_recibido_ingreso_debe_egreso_haber(): void
    {
        $this->assertSame('D', IngresoEgresoChequeAsientoSupport::dhRecibido(1));
        $this->assertSame('H', IngresoEgresoChequeAsientoSupport::dhRecibido(-1));
        $this->assertSame('H', IngresoEgresoChequeAsientoSupport::dhRecibido(1, true));
        $this->assertSame('D', IngresoEgresoChequeAsientoSupport::dhRecibido(-1, true));
    }

    /**
     * Misma pierna (otro Debe sobre línea Debe): acumula en el mismo índice.
     */
    public function test_agrega_cuenta_acumula_misma_pierna(): void
    {
        $repo = $this->createMock(CuentacontableRepositoryInterface::class);
        $asiento = [
            [
                'cuentacontable_id' => 10,
                'codigo' => '1.1.01',
                'nombre' => 'Banco',
                'moneda_id' => 1,
                'cotizacion' => 1.0,
                'centrocosto_id' => 0,
                'debe' => 500.0,
                'haber' => '',
                'd_h' => 'D',
                'observacion' => '',
                'carga_cuentacontable_manual' => 'N',
            ],
        ];

        $agrega = new ReflectionMethod(IngresoEgresoChequeAsientoSupport::class, 'agregaCuenta');
        $agrega->setAccessible(true);
        $agrega->invokeArgs(null, [&$asiento, 10, 1, 1.0, 'D', 200.0, $repo]);

        $this->assertCount(1, $asiento);
        $this->assertSame(700.0, (float) $asiento[0]['debe']);
        $this->assertSame('', $asiento[0]['haber']);
    }

    /**
     * Canje banco→banco: anulación (Debe) + reemplazo (Haber) = 2 líneas, no 1 mezclada.
     */
    public function test_agrega_cuenta_piernas_opuestas_misma_cuenta_son_dos_lineas(): void
    {
        $cuenta = (object) ['codigo' => '1.1.01', 'nombre' => 'Banco'];
        $repo = $this->createMock(CuentacontableRepositoryInterface::class);
        $repo->method('find')->with(10)->willReturn($cuenta);

        $asiento = [
            [
                'cuentacontable_id' => 10,
                'codigo' => '1.1.01',
                'nombre' => 'Banco',
                'moneda_id' => 1,
                'cotizacion' => 1.0,
                'centrocosto_id' => 0,
                'debe' => 500.0,
                'haber' => '',
                'd_h' => 'D',
                'observacion' => '',
                'carga_cuentacontable_manual' => 'N',
            ],
        ];

        $agrega = new ReflectionMethod(IngresoEgresoChequeAsientoSupport::class, 'agregaCuenta');
        $agrega->setAccessible(true);
        $agrega->invokeArgs(null, [&$asiento, 10, 1, 1.0, 'H', 500.0, $repo]);

        $this->assertCount(2, $asiento);
        $this->assertSame(500.0, (float) $asiento[0]['debe']);
        $this->assertSame('', $asiento[0]['haber']);
        $this->assertSame('', $asiento[1]['debe']);
        $this->assertSame(500.0, (float) $asiento[1]['haber']);
    }
}

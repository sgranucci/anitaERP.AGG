<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoGastoBancoSupport;
use App\Support\Compras\ComprobanteProveedorTipoTesoreria;
use PHPUnit\Framework\TestCase;

class IngresoEgresoGastoBancoSupportTest extends TestCase
{
    public function test_sin_cuenta_bancaria_no_pide_proveedor(): void
    {
        $resultado = IngresoEgresoGastoBancoSupport::elegir([
            ['cuentacaja_id' => 1, 'banco_id' => 0, 'monto' => -100, 'codigo' => '1', 'nombre' => 'Caja'],
        ]);

        $this->assertFalse($resultado['ok']);
        $this->assertFalse($resultado['ambiguo']);
        $this->assertStringContainsString('cuenta de caja del banco', $resultado['mensaje']);
    }

    public function test_un_solo_banco_aunque_haya_dos_cuentas(): void
    {
        $resultado = IngresoEgresoGastoBancoSupport::elegir([
            $this->cuenta(10, 54, 500, '128', 'MACRO USD'),
            $this->cuenta(11, 54, -1200, '127', 'MACRO PESOS'),
        ]);

        $this->assertTrue($resultado['ok']);
        $this->assertSame(11, $resultado['cuentacaja_id']);
        $this->assertSame('127', $resultado['cuenta_codigo']);
    }

    public function test_dos_bancos_pide_elegir_entre_las_cuentas_ya_cargadas(): void
    {
        $resultado = IngresoEgresoGastoBancoSupport::elegir([
            $this->cuenta(10, 54, -100, '127', 'MACRO'),
            $this->cuenta(20, 39, -80, '123', 'FRANCES'),
        ]);

        $this->assertFalse($resultado['ok']);
        $this->assertTrue($resultado['ambiguo']);
        $this->assertCount(2, $resultado['candidatos']);
        $this->assertSame([10, 20], array_column($resultado['candidatos'], 'cuentacaja_id'));
    }

    public function test_la_cuenta_elegida_pisa_la_regla_del_egreso(): void
    {
        $resultado = IngresoEgresoGastoBancoSupport::elegir([
            $this->cuenta(10, 54, -100, '127', 'MACRO'),
            $this->cuenta(20, 39, -80, '123', 'FRANCES'),
        ], 20);

        $this->assertTrue($resultado['ok']);
        $this->assertSame(20, $resultado['cuentacaja_id']);
        $this->assertSame('FRANCES', $resultado['banco_nombre']);
    }

    public function test_solo_gasto_bancario_omite_el_proveedor(): void
    {
        $this->assertTrue(IngresoEgresoGastoBancoSupport::esGastoBanco(ComprobanteProveedorTipoTesoreria::GASTO_BANCO));
        $this->assertFalse(IngresoEgresoGastoBancoSupport::esGastoBanco(ComprobanteProveedorTipoTesoreria::FONDO_FIJO));
    }

    /** @return array<string, mixed> */
    private function cuenta(int $id, int $bancoId, float $monto, string $codigo, string $banco): array
    {
        return [
            'cuentacaja_id' => $id,
            'banco_id' => $bancoId,
            'monto' => $monto,
            'codigo' => $codigo,
            'nombre' => $banco,
            'banco_nombre' => $banco,
            'banco_cuit' => '30500010084',
            'condicioniva_id' => 1,
        ];
    }
}

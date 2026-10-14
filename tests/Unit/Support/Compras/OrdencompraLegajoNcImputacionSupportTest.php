<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraLegajoNcImputacionSupport;
use PHPUnit\Framework\TestCase;

class OrdencompraLegajoNcImputacionSupportTest extends TestCase
{
    public function test_la_eleccion_aplica_a_nota_de_credito_y_debito(): void
    {
        $this->assertTrue(OrdencompraLegajoNcImputacionSupport::exigeEleccion('NC'));
        $this->assertTrue(OrdencompraLegajoNcImputacionSupport::exigeEleccion('nd'));
        $this->assertFalse(OrdencompraLegajoNcImputacionSupport::exigeEleccion('FC'));
    }

    public function test_sin_eleccion_bloquea_cada_nota(): void
    {
        $analisis = OrdencompraLegajoNcImputacionSupport::analizarPayload([
            $this->pendiente(10, 'NC A 0001-00000001'),
            $this->pendiente(11, 'NC A 0001-00000002'),
        ], [], []);

        $this->assertCount(2, $analisis['errores']);
        $this->assertSame([], $analisis['guardar']);
        $this->assertStringContainsString('00000001', $analisis['errores'][0]);
        $this->assertStringContainsString('00000002', $analisis['errores'][1]);
    }

    public function test_cuenta_de_la_oc_alcanza_sin_cuenta_manual(): void
    {
        $analisis = OrdencompraLegajoNcImputacionSupport::analizarPayload([
            $this->pendiente(10, 'NC A 0001-00000001'),
        ], [[
            'precarga_id' => 10,
            'modo' => 'oc',
            'cuentacontable_id' => '',
        ]], []);

        $this->assertSame([], $analisis['errores']);
        $this->assertSame('oc', $analisis['guardar'][0]['modo']);
        $this->assertNull($analisis['guardar'][0]['cuentacontable_id']);
    }

    public function test_otra_cuenta_exige_una_cuenta_valida_de_la_empresa(): void
    {
        $sinCuenta = OrdencompraLegajoNcImputacionSupport::analizarPayload([
            $this->pendiente(10, 'NC diferencia'),
        ], [[
            'precarga_id' => 10,
            'modo' => 'cuenta',
        ]], []);
        $this->assertNotSame([], $sinCuenta['errores']);
        $this->assertStringContainsString('no indicaste', $sinCuenta['errores'][0]);

        $ajena = OrdencompraLegajoNcImputacionSupport::analizarPayload([
            $this->pendiente(10, 'NC diferencia'),
        ], [[
            'precarga_id' => 10,
            'modo' => 'cuenta',
            'cuentacontable_id' => 99,
        ]], []);
        $this->assertStringContainsString('no existe', $ajena['errores'][0]);

        $ok = OrdencompraLegajoNcImputacionSupport::analizarPayload([
            $this->pendiente(10, 'NC diferencia'),
        ], [[
            'precarga_id' => 10,
            'modo' => 'cuenta',
            'cuentacontable_id' => 99,
        ]], [99 => true]);
        $this->assertSame([], $ok['errores']);
        $this->assertSame(99, $ok['guardar'][0]['cuentacontable_id']);
    }

    public function test_eleccion_ya_guardada_no_vuelve_a_pedirla(): void
    {
        $analisis = OrdencompraLegajoNcImputacionSupport::analizarPayload([
            $this->pendiente(10, 'NC A 0001-00000001', 'oc', 0),
            $this->pendiente(null, 'NC scan', 'cuenta', 55, 800),
        ], [], []);

        $this->assertSame([], $analisis['errores']);
        $this->assertSame([], $analisis['guardar']);
    }

    /**
     * @return array<string, mixed>
     */
    private function pendiente(
        ?int $precargaId,
        string $etiqueta,
        ?string $modo = null,
        int $cuentaId = 0,
        ?int $anitaId = null,
    ): array {
        return [
            'precarga_id' => $precargaId,
            'anita_scan_documento_id' => $anitaId,
            'etiqueta' => $etiqueta,
            'modo' => $modo,
            'cuentacontable_id' => $cuentaId,
        ];
    }
}

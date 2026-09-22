<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ComprobanteProveedorUnicidadMensajePersistenciaTest extends TestCase
{
    public function test_solo_indice_fiscal_habla_de_identificacion_fiscal(): void
    {
        $e = new RuntimeException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry "
            ."'2-14-A-3093-92916-30678814357' for key 'comprobante_proveedor.uq_comprobante_proveedor_por_cuit'"
        );

        $msg = ComprobanteProveedorUnicidadSupport::mensajeParaErrorPersistencia(
            'No se pudo guardar el comprobante',
            $e,
        );

        $this->assertStringContainsString('identificación fiscal', $msg);
        $this->assertStringContainsString('Cuentas a pagar', $msg);
    }

    public function test_otro_duplicate_entry_no_se_enmascara_como_fiscal(): void
    {
        $e = new RuntimeException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry "
            ."'1068' for key 'comprobante_proveedor.uq_comprobante_proveedor_precarga'"
        );

        $msg = ComprobanteProveedorUnicidadSupport::mensajeParaErrorPersistencia(
            'No se pudo guardar el comprobante',
            $e,
        );

        $this->assertStringNotContainsString('identificación fiscal', $msg);
        $this->assertStringContainsString('vinculado a esta precarga', $msg);
    }

    public function test_sqlstate_generico_no_se_enmascara_como_fiscal(): void
    {
        $e = new RuntimeException(
            'SQLSTATE[HY000]: General error: 1364 Field \'fechaiva\' doesn\'t have a default value'
        );

        $msg = ComprobanteProveedorUnicidadSupport::mensajeParaErrorPersistencia(
            'No se pudo guardar el comprobante',
            $e,
        );

        $this->assertStringNotContainsString('identificación fiscal', $msg);
        $this->assertStringContainsString('fechaiva', $msg);
    }

    public function test_mensaje_de_negocio_se_propaga(): void
    {
        $e = new RuntimeException('Debe indicar un CUIT válido (11 dígitos) del proveedor para registrar el comprobante.');

        $msg = ComprobanteProveedorUnicidadSupport::mensajeParaErrorPersistencia(
            'No se pudo guardar el comprobante',
            $e,
        );

        $this->assertSame(
            'No se pudo guardar el comprobante: Debe indicar un CUIT válido (11 dígitos) del proveedor para registrar el comprobante.',
            $msg,
        );
    }
}

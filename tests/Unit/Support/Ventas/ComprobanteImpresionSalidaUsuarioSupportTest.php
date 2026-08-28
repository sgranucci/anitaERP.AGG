<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Configuracion\SalidaParaProgramaSupport;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use App\Support\Ventas\ComprobanteImpresionFormulario;
use App\Support\Ventas\ComprobanteImpresionSalidaUsuarioSupport;
use PHPUnit\Framework\TestCase;

final class ComprobanteImpresionSalidaUsuarioSupportTest extends TestCase
{
    public function test_programa_unificado_es_ventas_comprobantes(): void
    {
        $this->assertSame(
            SeteoSalidaProgramaSupport::VENTAS_COMPROBANTES,
            ComprobanteImpresionSalidaUsuarioSupport::programaUnificado()
        );
        $this->assertSame(
            'Comprobantes (sesión de impresión)',
            SeteoSalidaProgramaSupport::etiqueta(SeteoSalidaProgramaSupport::VENTAS_COMPROBANTES)
        );
    }

    public function test_programa_por_formulario(): void
    {
        $this->assertSame(
            SeteoSalidaProgramaSupport::VENTAS_PEDIDO,
            ComprobanteImpresionSalidaUsuarioSupport::programaPorFormulario(ComprobanteImpresionFormulario::PEDIDO)
        );
        $this->assertSame(
            SeteoSalidaProgramaSupport::VENTAS_REMITO,
            ComprobanteImpresionSalidaUsuarioSupport::programaPorFormulario(ComprobanteImpresionFormulario::REMITO)
        );
        $this->assertSame(
            SeteoSalidaProgramaSupport::VENTAS_FACTURA,
            ComprobanteImpresionSalidaUsuarioSupport::programaPorFormulario(ComprobanteImpresionFormulario::FACTURA)
        );
        $this->assertSame(
            SeteoSalidaProgramaSupport::VENTAS_REMITO,
            ComprobanteImpresionSalidaUsuarioSupport::programaPorFormulario(ComprobanteImpresionFormulario::COT)
        );
    }

    public function test_busqueda_pone_unificado_antes_del_formulario(): void
    {
        $this->assertSame(
            [
                SeteoSalidaProgramaSupport::VENTAS_COMPROBANTES,
                SeteoSalidaProgramaSupport::VENTAS_REMITO,
                SeteoSalidaProgramaSupport::VENTAS_FACTURA,
                SeteoSalidaProgramaSupport::VENTAS_PEDIDO,
            ],
            ComprobanteImpresionSalidaUsuarioSupport::programasBusqueda(ComprobanteImpresionFormulario::REMITO)
        );
    }

    public function test_papel_sin_salida_hereda_usuario_y_nas_fija_no(): void
    {
        $this->assertTrue(ComprobanteImpresionSalidaUsuarioSupport::heredaImpresoraUsuario([
            'salida_id' => null,
            'medio' => 'IMPRESORA',
        ]));
        $this->assertFalse(ComprobanteImpresionSalidaUsuarioSupport::heredaImpresoraUsuario([
            'salida_id' => 12,
            'medio' => 'IMPRESORA',
        ]));
        $this->assertFalse(ComprobanteImpresionSalidaUsuarioSupport::heredaImpresoraUsuario([
            'salida_id' => null,
            'medio' => 'ARCHIVO',
        ]));
    }

    public function test_filtro_de_impresoras_del_unificado_incluye_factura_remito_pedido(): void
    {
        $this->assertContains(
            SeteoSalidaProgramaSupport::VENTAS_FACTURA,
            SalidaParaProgramaSupport::codigosCoincidentesPrograma(SeteoSalidaProgramaSupport::VENTAS_COMPROBANTES)
        );
        $this->assertContains(
            SeteoSalidaProgramaSupport::VENTAS_REMITO,
            SalidaParaProgramaSupport::codigosCoincidentesPrograma(SeteoSalidaProgramaSupport::VENTAS_COMPROBANTES)
        );
        $this->assertContains(
            SeteoSalidaProgramaSupport::VENTAS_PEDIDO,
            SalidaParaProgramaSupport::codigosCoincidentesPrograma(SeteoSalidaProgramaSupport::VENTAS_COMPROBANTES)
        );
    }
}

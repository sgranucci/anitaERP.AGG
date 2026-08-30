<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ComprobanteImpresionFormulario;
use App\Support\Ventas\ComprobanteImpresionPackSupport;
use PHPUnit\Framework\TestCase;

final class ComprobanteImpresionPackSupportTest extends TestCase
{
    public function test_es_original_por_codigo_y_leyenda(): void
    {
        $this->assertTrue(ComprobanteImpresionPackSupport::esOriginal([
            'copia_codigo' => 'ORI',
            'leyenda' => 'ORIGINAL',
        ]));
        $this->assertTrue(ComprobanteImpresionPackSupport::esOriginal([
            'copia_codigo' => 'original',
            'leyenda' => '',
        ]));
        $this->assertTrue(ComprobanteImpresionPackSupport::esOriginal([
            'copia_codigo' => 'X',
            'leyenda' => 'Original',
        ]));
        $this->assertFalse(ComprobanteImpresionPackSupport::esOriginal([
            'copia_codigo' => 'DUP',
            'leyenda' => 'DUPLICADO',
        ]));
        $this->assertFalse(ComprobanteImpresionPackSupport::esOriginal([
            'copia_codigo' => 'TRI',
            'leyenda' => 'TRIPLICADO',
        ]));
    }

    public function test_nas_no_va_al_pdf_de_sesion(): void
    {
        $this->assertTrue(ComprobanteImpresionPackSupport::esNas([
            'medio' => 'ARCHIVO',
        ]));
        $this->assertFalse(ComprobanteImpresionPackSupport::vaAlPdfSesion([
            'medio' => 'ARCHIVO',
            'incluir_en_pdf_sesion' => true,
        ]));
        $this->assertTrue(ComprobanteImpresionPackSupport::vaAlPdfSesion([
            'medio' => 'IMPRESORA',
            'incluir_en_pdf_sesion' => true,
        ]));
    }

    public function test_ejecutar_sesion_incluye_nas_aunque_no_esten_marcadas(): void
    {
        $pack = [
            0 => ['medio' => 'IMPRESORA', 'formulario' => ComprobanteImpresionFormulario::FACTURA],
            1 => ['medio' => 'IMPRESORA', 'formulario' => ComprobanteImpresionFormulario::REMITO],
            2 => ['medio' => 'ARCHIVO', 'formulario' => ComprobanteImpresionFormulario::FACTURA],
        ];

        $partes = ComprobanteImpresionPackSupport::idxsPapelYNas($pack, [0], false);

        $this->assertSame([0], $partes['papel']);
        $this->assertSame([2], $partes['nas']);
    }

    public function test_solo_copia_no_arrastra_el_nas(): void
    {
        $pack = [
            0 => ['medio' => 'IMPRESORA'],
            1 => ['medio' => 'ARCHIVO'],
        ];

        $partes = ComprobanteImpresionPackSupport::idxsPapelYNas($pack, [0], true);

        $this->assertSame([0], $partes['papel']);
        $this->assertSame([], $partes['nas']);
    }

    public function test_pdf_reuso_prefiere_misma_leyenda(): void
    {
        $tmpOri = tempnam(sys_get_temp_dir(), 'ori');
        $tmpDup = tempnam(sys_get_temp_dir(), 'dup');
        $this->assertIsString($tmpOri);
        $this->assertIsString($tmpDup);

        $ruta = ComprobanteImpresionPackSupport::pdfReusoParaNas(
            [
                'formulario' => ComprobanteImpresionFormulario::FACTURA,
                'documento_id' => 10,
                'leyenda' => 'DUPLICADO',
                'medio' => 'ARCHIVO',
            ],
            [
                [
                    'formulario' => ComprobanteImpresionFormulario::FACTURA,
                    'documento_id' => 10,
                    'leyenda' => 'ORIGINAL',
                    'pdf_path' => $tmpOri,
                    'medio' => 'IMPRESORA',
                ],
                [
                    'formulario' => ComprobanteImpresionFormulario::FACTURA,
                    'documento_id' => 10,
                    'leyenda' => 'DUPLICADO',
                    'pdf_path' => $tmpDup,
                    'medio' => 'IMPRESORA',
                ],
            ]
        );

        @unlink($tmpOri);
        @unlink($tmpDup);

        $this->assertSame($tmpDup, $ruta);
    }
}

<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\Flash\FlashReporteAggTablaImagenSupport;
use Tests\TestCase;

class FlashReporteAggTablaImagenSupportTest extends TestCase
{
    public function test_genera_png_de_120px_por_columna(): void
    {
        $path = sys_get_temp_dir().'/flash-tabla-'.uniqid('', true).'.png';
        $celdas = [];
        for ($r = 0; $r < 13; $r++) {
            $fila = [];
            for ($c = 0; $c < 7; $c++) {
                $fila[] = [
                    'texto' => $r === 1 && $c === 1 ? 'Slots' : ($r === 2 && $c === 1 ? '$ 1.234' : ''),
                    'negrita' => $r === 1,
                    'rojo' => false,
                    'encabezado' => $r === 1,
                ];
            }
            $celdas[] = $fila;
        }

        try {
            (new FlashReporteAggTablaImagenSupport)->generar($celdas, $path);
            $this->assertFileExists($path);
            $info = getimagesize($path);
            $this->assertIsArray($info);
            $this->assertSame(7 * FlashReporteAggTablaImagenSupport::ANCHO_COL_PX + 1, $info[0]);
            $this->assertSame(13 * FlashReporteAggTablaImagenSupport::ALTO_FILA_PX + 1, $info[1]);
            $this->assertSame('image/png', $info['mime']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

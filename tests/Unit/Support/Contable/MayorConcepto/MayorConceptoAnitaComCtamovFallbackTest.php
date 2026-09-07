<?php

namespace Tests\Unit\Support\Contable\MayorConcepto;

use App\Support\Contable\MayorConcepto\MayorConceptoAnitaBridgeReader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MayorConceptoAnitaComCtamovFallbackTest extends TestCase
{
    #[Test]
    public function remapear_ctamov_como_subdiario_preserva_clave_com_y_gasto(): void
    {
        $reader = app(MayorConceptoAnitaBridgeReader::class);

        $remap = $reader->remapearCtamovComoSubdiario((object) [
            'ctav_empresa' => 3,
            'ctav_sistema' => 'C',
            'ctav_fecha' => 20260820,
            'ctav_tipo' => 'COM',
            'ctav_letra' => 'X',
            'ctav_sucursal' => 3,
            'ctav_nro' => 166586,
            'ctav_d_h' => 'D',
            'ctav_cuenta' => 115010001,
            'ctav_importe' => 108710.4,
            'ctav_nro_asiento' => 234940,
            'ctav_desc_mov' => '166586 PAMPIN',
            'ctav_o_compra' => 223054,
        ]);

        $this->assertTrue($remap->subd_origen_ctamov);
        $this->assertSame('COM', $remap->subd_tipo);
        $this->assertSame('X', $remap->subd_letra);
        $this->assertSame(3, $remap->subd_sucursal);
        $this->assertSame(166586, $remap->subd_nro);
        $this->assertSame(115010001, $remap->subd_cuenta);
        $this->assertSame('D', $remap->subd_tipo_mov);
        $this->assertSame(108710.4, $remap->subd_importe);
        $this->assertSame(234940, $remap->subd_nro_operacion);
        $this->assertSame(223054, $remap->subd_o_compra);
    }
}

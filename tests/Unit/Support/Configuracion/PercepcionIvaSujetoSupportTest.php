<?php

namespace Tests\Unit\Support\Configuracion;

use App\Support\Configuracion\PercepcionIvaSujetoSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD). Percepción IVA solo a Responsable Inscripto (AFIP codigoexterno 1).
 */
class PercepcionIvaSujetoSupportTest extends TestCase
{
    public function test_responsable_inscripto_corresponde(): void
    {
        $condicion = (object) ['id' => 1, 'codigoexterno' => '1', 'nombre' => 'Responsable Inscripto'];

        self::assertTrue(PercepcionIvaSujetoSupport::correspondePercepcionIva($condicion));
    }

    public function test_monotributo_no_corresponde(): void
    {
        $condicion = (object) ['id' => 4, 'codigoexterno' => '6', 'nombre' => 'Monotributo'];

        self::assertFalse(PercepcionIvaSujetoSupport::correspondePercepcionIva($condicion));
    }

    public function test_consumidor_final_no_corresponde(): void
    {
        $condicion = (object) ['id' => 3, 'codigoexterno' => '5', 'nombre' => 'Consumidor Final'];

        self::assertFalse(PercepcionIvaSujetoSupport::correspondePercepcionIva($condicion));
    }

    public function test_sin_condicion_no_corresponde(): void
    {
        self::assertFalse(PercepcionIvaSujetoSupport::correspondePercepcionIva(null));
    }
}

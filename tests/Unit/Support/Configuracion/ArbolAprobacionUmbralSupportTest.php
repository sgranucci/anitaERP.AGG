<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Configuracion;

use App\Support\Configuracion\ArbolAprobacionUmbralSupport;
use PHPUnit\Framework\TestCase;

class ArbolAprobacionUmbralSupportTest extends TestCase
{
    public function test_umbral_default_y_piso_desde_niveles(): void
    {
        $this->assertSame(5_000_000.0, ArbolAprobacionUmbralSupport::umbralNivelAlto());
        $this->assertSame(
            'configuracion.arbol.umbral_doble_aprobacion',
            ArbolAprobacionUmbralSupport::PUNTO_REGLA
        );

        $niveles = [
            (object) ['desdemonto' => 0],
            (object) ['desdemonto' => 1_000_000],
            (object) ['desdemonto' => 5_000_000],
            (object) ['desdemonto' => 8_000_000],
        ];
        $this->assertSame(5_000_000.0, ArbolAprobacionUmbralSupport::umbralPisoDobleAprobacion($niveles));

        $sinAltos = [
            (object) ['desdemonto' => 100],
            (object) ['desdemonto' => 999_999],
        ];
        $this->assertSame(5_000_001.0, ArbolAprobacionUmbralSupport::umbralPisoDobleAprobacion($sinAltos));
    }
}

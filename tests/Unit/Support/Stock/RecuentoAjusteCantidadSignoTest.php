<?php

namespace Tests\Unit\Support\Stock;

use App\Models\Stock\Tipotransaccion_Stock;
use App\Support\Stock\ArticuloMovimientoCantidadSignoSupport;
use Tests\TestCase;

class RecuentoAjusteCantidadSignoTest extends TestCase
{
    public function test_accessor_signo_no_debe_usarse_para_firmar_cantidad(): void
    {
        $tipoNegativo = new Tipotransaccion_Stock;
        $tipoNegativo->setRawAttributes([
            'abreviatura' => 'RCAJN',
            'signo' => -1,
        ]);

        $this->assertSame('R', $tipoNegativo->signo);
        $this->assertSame(0, (int) $tipoNegativo->signo);

        $cantidadIncorrecta = abs(-500) * ((int) $tipoNegativo->signo === -1 ? -1 : 1);
        $this->assertSame(500.0, $cantidadIncorrecta);

        $signoDb = (int) ($tipoNegativo->getAttributes()['signo'] ?? 0);
        $cantidadCorrecta = ArticuloMovimientoCantidadSignoSupport::cantidadFirmadaSignoStock(500, $signoDb);
        $this->assertSame(-500.0, $cantidadCorrecta);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\CotConstanciaSupport;
use PHPUnit\Framework\TestCase;

final class CotConstanciaSupportTest extends TestCase
{
    public function test_etiqueta_remito_arma_tipo_letra_sucursal_y_numero(): void
    {
        $this->assertSame('REM R 1 900850', CotConstanciaSupport::etiquetaRemito('REM', 'R', 1, 900850));
        $this->assertSame('REM R 900851', CotConstanciaSupport::etiquetaRemito('', '', 0, 900851));
    }

    public function test_domicilio_texto_junta_calle_cp_y_localidad(): void
    {
        $this->assertSame(
            'MAYOR IRUSTA 2921 — CP 1407 CABA',
            CotConstanciaSupport::domicilioTexto([
                'calle' => 'MAYOR IRUSTA',
                'numero' => '2921',
                'codigo_postal' => '1407',
                'localidad' => 'CABA',
            ])
        );
    }

    public function test_reparto_de_sesion_prioriza_transporte_id(): void
    {
        $repartos = [
            ['transporte_id' => 10, 'codigo' => '96', 'nombre' => 'UNO', 'patente' => 'ab123cd', 'cuit_chofer' => '20-1'],
            ['transporte_id' => 20, 'codigo' => '98', 'nombre' => 'DOS', 'patente' => 'XY999ZZ', 'cuit_chofer' => '27-2'],
        ];

        $elegido = CotConstanciaSupport::repartoDeSesion($repartos, 20);

        $this->assertSame('98', $elegido['codigo']);
        $this->assertSame('XY999ZZ', $elegido['patente']);
        $this->assertSame('27-2', $elegido['cuit_chofer']);
    }
}

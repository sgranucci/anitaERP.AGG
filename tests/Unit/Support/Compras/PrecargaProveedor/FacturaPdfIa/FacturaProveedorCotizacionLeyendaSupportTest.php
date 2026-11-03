<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorCabeceraHeuristicaSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorImporteParserSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorMonedaOcrSupport;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrNumeroOcExtractor;
use PHPUnit\Framework\TestCase;

class FacturaProveedorCotizacionLeyendaSupportTest extends TestCase
{
    public function test_tipo_de_cambio_en_leyenda_con_importe_en_la_linea_siguiente(): void
    {
        $texto = <<<'TXT'
Observaciones
AL SOLO EFECTO IMPOSITIVO, EL TIPO DE CAMBIO APLICADO ES DE                                                       I.V.A. 21,00%                   u$s 153,42
$1.570,00 POR CADA DOLAR - Transporte: ENVIAMELO
Gravado u$s 730,58
Total u$s 884,00
TXT;

        $cabecera = $this->cabecera()->extraer($texto);

        $this->assertSame('DOLARES', $cabecera['moneda']);
        $this->assertSame(1570.0, $cabecera['cotizacion']);
    }

    public function test_tipo_de_cambio_pegado_al_rotulo_sigue_entrando(): void
    {
        $cabecera = $this->cabecera()->extraer('Son Pesos: mil. T.C.: 1.561,51. Total $ 1000,00');

        $this->assertSame('PESOS', $cabecera['moneda']);
        $this->assertSame(1561.51, $cabecera['cotizacion']);
    }

    public function test_mencion_de_dolar_sin_importe_no_inventa_cotizacion(): void
    {
        $texto = 'Para cancelar, el monto se determina según el tipo de cambio del Dólar vendedor del BNA. Total $ 1000,00';

        $cabecera = $this->cabecera()->extraer($texto);

        $this->assertSame('PESOS', $cabecera['moneda']);
        $this->assertSame(1.0, $cabecera['cotizacion']);
    }

    private function cabecera(): FacturaProveedorCabeceraHeuristicaSupport
    {
        return new FacturaProveedorCabeceraHeuristicaSupport(
            new FacturaProveedorImporteParserSupport(),
            new RecepcionProveedorOcrNumeroOcExtractor(),
            new FacturaProveedorMonedaOcrSupport(),
        );
    }
}

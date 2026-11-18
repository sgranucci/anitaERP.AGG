<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorConceptosHeuristicaSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorImporteParserSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorPieTotalesSupport;
use PHPUnit\Framework\TestCase;

/**
 * NC Ruiz Hnos A-0003-00004164: pie «IIBB 2,00 … 552,50» sin la palabra «percepción».
 */
class FacturaProveedorPieIibbCortoRuizHnosTest extends TestCase
{
    private const TEXTO = <<<'TXT'
                                                                         NOTA DE CREDITO               ORIGINAL


                                                           003           0003-00004164                 18/11/26
Fabrica argentina de hormas y suelas
Yerua 959 Lomas del mirador      CP 1752 BsAs - Republica Argentina      CUIT N 30-58359703-0 ING BRUTOS 901-914645-1
IVA RESPONSABLE INSCRIPTO

CALZADOS FERLI S.A.
C.U.I.T. 30-55158730-0

                                                                      SUBTOTAL            27.624,83
                                                                      IVA 21%              5.801,21
                                                                      IVA 10,50%               0,00
                                                                      IIBB 2,00              552,50
                                                                      TOTAL               33.978,54
                                                                      CAE: 86463336508023
                                                                      VTO: 28/11/26
TXT;

    public function test_pie_reconoce_iibb_corto_sin_palabra_percepcion(): void
    {
        $pie = $this->pie()->extraer(self::TEXTO);

        $this->assertEqualsWithDelta(27624.83, (float) $pie['subtotal'], 0.01);
        $this->assertEqualsWithDelta(5801.21, (float) $pie['iva'], 0.01);
        $this->assertEqualsWithDelta(552.50, (float) $pie['percepcion_iibb'], 0.01);
        $this->assertEqualsWithDelta(33978.54, (float) $pie['total'], 0.01);

        $iibb = $this->primeraLineaTipo($pie['lineas'] ?? [], 'percepcion_iibb');
        $this->assertNotNull($iibb);
        $this->assertEqualsWithDelta(552.50, (float) $iibb['importe'], 0.01);
    }

    public function test_conceptos_toman_importe_y_no_la_alicuota(): void
    {
        $lineas = $this->conceptos()->extraer(self::TEXTO);
        $iibb = $this->primeraLineaTipo($lineas, 'percepcion_iibb');

        $this->assertNotNull($iibb);
        $this->assertEqualsWithDelta(552.50, (float) $iibb['importe'], 0.01);
        $this->assertEqualsWithDelta(2.0, (float) $iibb['alicuota_iva'], 0.01);
    }

    public function test_membrete_ing_brutos_no_es_percepcion(): void
    {
        $soloMembrete = 'CUIT N 30-58359703-0 ING BRUTOS 901-914645-1 IVA RESPONSABLE INSCRIPTO';
        $pie = $this->pie()->extraer($soloMembrete);

        $this->assertSame(0.0, (float) ($pie['percepcion_iibb'] ?? 0));
        $this->assertSame([], array_values(array_filter(
            $pie['lineas'] ?? [],
            static fn ($l) => ($l['tipo'] ?? '') === 'percepcion_iibb'
        )));
    }

    private function pie(): FacturaProveedorPieTotalesSupport
    {
        return new FacturaProveedorPieTotalesSupport(new FacturaProveedorImporteParserSupport);
    }

    private function conceptos(): FacturaProveedorConceptosHeuristicaSupport
    {
        return new FacturaProveedorConceptosHeuristicaSupport(new FacturaProveedorImporteParserSupport);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, mixed>|null
     */
    private function primeraLineaTipo(array $lineas, string $tipo): ?array
    {
        foreach ($lineas as $linea) {
            if (($linea['tipo'] ?? '') === $tipo) {
                return $linea;
            }
        }

        return null;
    }
}

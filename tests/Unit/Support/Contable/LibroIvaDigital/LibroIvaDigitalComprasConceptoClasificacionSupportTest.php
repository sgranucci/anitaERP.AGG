<?php

namespace Tests\Unit\Support\Contable\LibroIvaDigital;

use App\Models\Compras\Columna_Ivacompra;
use App\Models\Compras\Concepto_Ivacompra;
use App\Support\Compras\ConceptoIvacompraFormulaSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasConceptoClasificacionSupport;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class LibroIvaDigitalComprasConceptoClasificacionSupportTest extends TestCase
{
    public function test_formula_anita_en_n_separa_gravado_e_iva(): void
    {
        $gravado = $this->concepto('2', 'COMPRAS 21%', 'N', '4');
        $iva = $this->concepto('101', 'IVA 21%', 'N', '7', 'con(2)*0.21');

        ConceptoIvacompraFormulaSupport::inferirTiposYTasasEnColeccion(new Collection([$gravado, $iva]));

        $clasGravado = LibroIvaDigitalComprasConceptoClasificacionSupport::clasificar($gravado);
        $clasIva = LibroIvaDigitalComprasConceptoClasificacionSupport::clasificar($iva);

        $this->assertSame('gravado', $clasGravado['destino']);
        $this->assertEqualsWithDelta(21.0, $clasGravado['tasa'], 0.001);
        $this->assertSame('iva', $clasIva['destino']);
        $this->assertEqualsWithDelta(21.0, $clasIva['tasa'], 0.001);
    }

    public function test_columna_de_libro_clasifica_exento_percepcion_y_retencion(): void
    {
        $exento = LibroIvaDigitalComprasConceptoClasificacionSupport::clasificar(
            $this->concepto('3', 'O. CONCEPTOS', 'N', '1'),
        );
        $percIva = LibroIvaDigitalComprasConceptoClasificacionSupport::clasificar(
            $this->concepto('103', 'Percepcion IVA', 'N', '6'),
        );
        $percIibb = LibroIvaDigitalComprasConceptoClasificacionSupport::clasificar(
            $this->concepto('210', 'BS AS PERCEP I.B.', 'N', '3'),
        );
        $retGan = LibroIvaDigitalComprasConceptoClasificacionSupport::clasificar(
            $this->concepto('5', 'Retencion Ganancias', 'N', '3'),
        );
        $ivaImport = LibroIvaDigitalComprasConceptoClasificacionSupport::clasificar(
            $this->concepto('104', 'IVA IMPORTACIONES', 'N', '9'),
        );

        $this->assertSame('exento', $exento['destino']);
        $this->assertSame('perc_iva', $percIva['destino']);
        $this->assertSame('perc_iibb', $percIibb['destino']);
        $this->assertSame('perc_nacional', $retGan['destino']);
        $this->assertSame('', $ivaImport['destino']);
    }

    public function test_tipo_ya_cargado_no_se_pisa(): void
    {
        $clas = LibroIvaDigitalComprasConceptoClasificacionSupport::clasificar(
            $this->concepto('50', 'Gravado test', 'G', '1', '', 21.0),
        );

        $this->assertSame('gravado', $clas['destino']);
        $this->assertEqualsWithDelta(21.0, $clas['tasa'], 0.001);
    }

    private function concepto(
        string $codigo,
        string $nombre,
        string $tipo,
        string $columna,
        string $formula = '',
        float $tasa = 0.0,
    ): Concepto_Ivacompra {
        $concepto = new Concepto_Ivacompra([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipoconcepto' => $tipo,
            'formula' => $formula,
        ]);
        $concepto->setRelation('columna_ivacompras', new Columna_Ivacompra([
            'numerocolumna' => $columna,
        ]));
        $concepto->setRelation('impuestos', (object) ['valor' => $tasa]);

        return $concepto;
    }
}

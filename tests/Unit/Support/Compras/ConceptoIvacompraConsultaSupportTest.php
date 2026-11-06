<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ConceptoIvacompraConsultaSupport;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

class ConceptoIvacompraConsultaSupportTest extends TestCase
{
    public function test_firma_listar_acepta_numero_oc_y_letra_opcionales(): void
    {
        $m = new ReflectionMethod(ConceptoIvacompraConsultaSupport::class, 'listarPorTipoTransaccion');
        $params = $m->getParameters();
        $this->assertCount(4, $params);
        $this->assertSame('numeroOc', $params[2]->getName());
        $this->assertTrue($params[2]->allowsNull());
        $this->assertSame('letra', $params[3]->getName());
        $this->assertTrue($params[3]->allowsNull());
    }

    public function test_hermano_indirecto_solo_para_tipo_directo(): void
    {
        $this->assertSame('FIB', ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('FDB'));
        $this->assertSame('FIL', ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('fdl'));
        $this->assertSame('CIB', ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('CDB'));
        $this->assertNull(ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('FIB'));
        $this->assertNull(ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('FDS'));
        $this->assertNull(ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('FNB'));
        $this->assertNull(ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('FGA'));
    }

    public function test_ids_concepto_tipo_invalido_vacio(): void
    {
        $this->assertSame([], ConceptoIvacompraConsultaSupport::idsConceptoParaTipo(0));
        $this->assertSame([], ConceptoIvacompraConsultaSupport::idsConceptoParaTipo(-1, '223960'));
    }

    public function test_letra_c_solo_monotributo_y_exento(): void
    {
        $lista = $this->conceptosEjemplo();
        $filtrada = ConceptoIvacompraConsultaSupport::filtrarPorLetraComprobante($lista, 'C');
        $nombres = $filtrada->pluck('nombre')->all();

        $this->assertSame(['Compras monotributistas', 'No gravado', 'Compras a exentos'], $nombres);
    }

    public function test_letra_a_oculta_monotributo(): void
    {
        $lista = $this->conceptosEjemplo();
        $filtrada = ConceptoIvacompraConsultaSupport::filtrarPorLetraComprobante($lista, 'A');
        $nombres = $filtrada->pluck('nombre')->all();

        $this->assertNotContains('Compras monotributistas', $nombres);
        $this->assertContains('Gravado Bienes L.21', $nombres);
        $this->assertContains('Iva Serv.N/C 21', $nombres);
        $this->assertContains('No gravado', $nombres);
    }

    public function test_letra_vacia_no_filtra(): void
    {
        $lista = $this->conceptosEjemplo();
        $filtrada = ConceptoIvacompraConsultaSupport::filtrarPorLetraComprobante($lista, null);

        $this->assertCount($lista->count(), $filtrada);
    }

    public function test_es_concepto_monotributo_por_nombre(): void
    {
        $mono = (object) ['nombre' => 'Compras monotributistas', 'codigo' => '2', 'tipoconcepto' => 'N'];
        $gravado = (object) ['nombre' => 'Gravado Bienes L.21', 'codigo' => '50', 'tipoconcepto' => 'G'];

        $this->assertTrue(ConceptoIvacompraConsultaSupport::esConceptoMonotributo($mono));
        $this->assertFalse(ConceptoIvacompraConsultaSupport::esConceptoMonotributo($gravado));
    }

    /**
     * @return Collection<int, object>
     */
    private function conceptosEjemplo(): Collection
    {
        $filas = [
            ['nombre' => 'Compras monotributistas', 'codigo' => '2', 'tipoconcepto' => 'N'],
            ['nombre' => 'No gravado', 'codigo' => '1', 'tipoconcepto' => 'E'],
            ['nombre' => 'Compras a exentos', 'codigo' => '3', 'tipoconcepto' => 'E'],
            ['nombre' => 'Gravado Bienes L.21', 'codigo' => '50', 'tipoconcepto' => 'G'],
            ['nombre' => 'Iva Serv.N/C 21', 'codigo' => '421', 'tipoconcepto' => 'I'],
            ['nombre' => 'Impuesto interno', 'codigo' => '5', 'tipoconcepto' => 'N'],
        ];

        return Collection::make($filas)->map(static function (array $f): object {
            $o = new stdClass;
            $o->nombre = $f['nombre'];
            $o->codigo = $f['codigo'];
            $o->tipoconcepto = $f['tipoconcepto'];

            return $o;
        });
    }
}

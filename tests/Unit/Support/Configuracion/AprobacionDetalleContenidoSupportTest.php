<?php

namespace Tests\Unit\Support\Configuracion;

use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Articulo;
use App\Support\Configuracion\AprobacionDetalleContenidoSupport;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class AprobacionDetalleContenidoSupportTest extends TestCase
{
    public function test_requisicion_muestra_cabecera_y_renglones(): void
    {
        $req = new Requisicion([
            'comentario' => 'Reparar bomba de planta',
            'detalle' => 'Parada programada el viernes',
        ]);
        $req->setRelation('usuarios', $this->modelo(['nombre' => 'Ana Pérez']));
        $req->setRelation('empresas', $this->modelo(['nombre' => 'Ferli']));
        $req->setRelation('centrocostos', $this->modelo(['codigo' => '100', 'nombre' => 'Mantenimiento']));
        $req->setRelation('proveedores', null);
        $req->setRelation('formapagos', null);
        $req->setRelation('requisicion_archivos', collect());

        $lin = new Requisicion_Articulo([
            'cantidad' => 10,
            'precio' => 150.5,
            'detalle' => 'Métrica 8',
        ]);
        $lin->setRelation('articulos', $this->modelo(['sku' => 'TOR-1', 'descripcion' => 'Tornillo']));
        $lin->setRelation('monedas', $this->modelo(['abreviatura' => 'ARS']));
        $lin->setRelation('centrocostos_destino', $this->modelo(['codigo' => '210', 'nombre' => 'Planta']));
        $lin->setRelation('partidagastos', $this->modelo(['codigo' => 'PG1', 'detalle' => 'Repuestos']));
        $lin->setRelation('capexs', null);
        $lin->setRelation('proveedores', $this->modelo(['nombre' => 'Bulonera Norte']));
        $lin->setRelation('color', null);
        $lin->setRelation('talle', null);
        $req->setRelation('requisicion_articulos', collect([$lin]));

        $out = AprobacionDetalleContenidoSupport::desdeArbol('RE', $req);

        $this->assertNotNull($out);
        $this->assertSame('Renglones de la requisición', $out['titulo']);
        $this->assertContains('Ana Pérez', array_column($out['notas'], 'valor'));
        $this->assertContains('Reparar bomba de planta', array_column($out['notas'], 'valor'));
        $this->assertContains('Parada programada el viernes', array_column($out['notas'], 'valor'));
        $this->assertSame('TOR-1 · Tornillo', $out['renglones'][0]['titulo']);
        $this->assertSame('10 × 150,50 ARS', $out['renglones'][0]['cantidad']);
        $this->assertSame('1.505,00 ARS', $out['renglones'][0]['importe']);
        $this->assertContains('Métrica 8', $out['renglones'][0]['lineas']);
        $this->assertContains('Destino 210 · Planta', $out['renglones'][0]['lineas']);
        $this->assertContains('Partida PG1 · Repuestos', $out['renglones'][0]['lineas']);
        $this->assertContains('Proveedor Bulonera Norte', $out['renglones'][0]['lineas']);
    }

    public function test_tipo_desconocido_no_arma_contenido(): void
    {
        $this->assertNull(AprobacionDetalleContenidoSupport::desdeArbol('XX', new Requisicion()));
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function modelo(array $attrs): Model
    {
        $m = new class extends Model
        {
            protected $guarded = [];
        };
        foreach ($attrs as $k => $v) {
            $m->setAttribute($k, $v);
        }

        return $m;
    }
}

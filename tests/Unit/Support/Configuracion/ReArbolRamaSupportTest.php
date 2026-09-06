<?php

namespace Tests\Unit\Support\Configuracion;

use App\Models\Compras\Requisicion;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Support\Configuracion\ReArbolRamaCatalog;
use App\Support\Configuracion\ReArbolRamaSupport;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class ReArbolRamaSupportTest extends TestCase
{
    public function test_catalog_normaliza_ramas(): void
    {
        $this->assertSame('A', ReArbolRamaCatalog::normalizar('a'));
        $this->assertSame('B', ReArbolRamaCatalog::normalizar(' B '));
        $this->assertNull(ReArbolRamaCatalog::normalizar(''));
        $this->assertNull(ReArbolRamaCatalog::normalizar('X'));
    }

    public function test_dual_detectado_por_niveles_con_rama(): void
    {
        $arbol = new Arbolaprobacion;
        $arbol->id = 1;
        $arbol->setRelation('arbolaprobacion_niveles', new Collection([
            $this->nivel(10, 'A'),
            $this->nivel(10, 'B'),
            $this->nivel(20, null),
        ]));

        $this->assertTrue(ReArbolRamaSupport::centrocostoTieneDualRama($arbol, 10));
        $this->assertFalse(ReArbolRamaSupport::centrocostoTieneDualRama($arbol, 20));
        $this->assertFalse(ReArbolRamaSupport::centrocostoTieneDualRama($arbol, 99));
    }

    public function test_resolver_rama_todas_en_allowlist_es_a(): void
    {
        $support = new class extends ReArbolRamaSupport
        {
            public static function centrocostoTieneDualRama(Arbolaprobacion $arbol, int $centrocostoId): bool
            {
                return true;
            }

            public static function allowlistCuentacontableIds(Arbolaprobacion $arbol, int $centrocostoId, int $empresaId): array
            {
                return [100, 200];
            }

            public static function cuentacontableIdsDesdeRequisicion(Requisicion $requisicion): array
            {
                return [100, 100, 200];
            }
        };

        $arbol = new Arbolaprobacion;
        $req = new Requisicion;
        $req->empresa_id = 1;

        $this->assertSame('A', $support::resolverRama($arbol, $req, 10));
    }

    public function test_resolver_rama_alguna_fuera_es_b(): void
    {
        $support = new class extends ReArbolRamaSupport
        {
            public static function centrocostoTieneDualRama(Arbolaprobacion $arbol, int $centrocostoId): bool
            {
                return true;
            }

            public static function allowlistCuentacontableIds(Arbolaprobacion $arbol, int $centrocostoId, int $empresaId): array
            {
                return [100];
            }

            public static function cuentacontableIdsDesdeRequisicion(Requisicion $requisicion): array
            {
                return [100, 999];
            }
        };

        $arbol = new Arbolaprobacion;
        $req = new Requisicion;
        $req->empresa_id = 1;

        $this->assertSame('B', $support::resolverRama($arbol, $req, 10));
    }

    public function test_resolver_rama_linea_sin_cuenta_es_b(): void
    {
        $support = new class extends ReArbolRamaSupport
        {
            public static function centrocostoTieneDualRama(Arbolaprobacion $arbol, int $centrocostoId): bool
            {
                return true;
            }

            public static function allowlistCuentacontableIds(Arbolaprobacion $arbol, int $centrocostoId, int $empresaId): array
            {
                return [100];
            }

            public static function cuentacontableIdsDesdeRequisicion(Requisicion $requisicion): array
            {
                return [100, 0];
            }
        };

        $arbol = new Arbolaprobacion;
        $req = new Requisicion;
        $req->empresa_id = 1;

        $this->assertSame('B', $support::resolverRama($arbol, $req, 10));
    }

    public function test_sin_dual_devuelve_null(): void
    {
        $arbol = new Arbolaprobacion;
        $arbol->id = 1;
        $arbol->setRelation('arbolaprobacion_niveles', new Collection([
            $this->nivel(10, null),
        ]));
        $req = new Requisicion;
        $req->empresa_id = 1;

        $this->assertNull(ReArbolRamaSupport::resolverRama($arbol, $req, 10));
    }

    private function nivel(int $ccId, ?string $rama): Arbolaprobacion_Nivel
    {
        $n = new Arbolaprobacion_Nivel;
        $n->centrocosto_id = $ccId;
        $n->rama = $rama;

        return $n;
    }
}

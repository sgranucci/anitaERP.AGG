<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ContaduriaInventarioRecuentoSupport;
use App\Support\Stock\ContaduriaInventarioRubroSupport;
use ReflectionMethod;
use Tests\TestCase;

class ContaduriaInventarioRecuentoSupportTest extends TestCase
{
    public function test_el_typo_aliementos_cuenta_como_alimento(): void
    {
        $this->assertSame(
            'alimento',
            ContaduriaInventarioRubroSupport::rubroDelComentario('INVENTARIOS ALIEMENTOS OCTUBRE PRINCIPAL'),
        );
    }

    public function test_de_cada_rubro_queda_solo_el_dia_mas_reciente(): void
    {
        $elegidos = $this->elegir([
            $this->recuento(148, '2026-10-01', 'alimento'),
            $this->recuento(194, '2026-10-31', 'alimento'),
            $this->recuento(149, '2026-10-01', 'bebidas'),
            $this->recuento(195, '2026-10-31', 'bebidas'),
            $this->recuento(191, '2026-10-31', 'bebidas'),
            $this->recuento(181, '2026-10-27', null),
        ]);

        $this->assertSame([194, 195, 191], array_column($elegidos, 'id'));
    }

    public function test_sin_rubro_solo_cuando_nadie_nombra_un_rubro(): void
    {
        $elegidos = $this->elegir([
            $this->recuento(10, '2026-09-30', null),
            $this->recuento(11, '2026-10-31', null),
            $this->recuento(12, '2026-10-31', null),
        ]);

        $this->assertSame([11, 12], array_column($elegidos, 'id'));
    }

    /**
     * @param  list<array{id:int,fecha:string,rubro:?string}>  $lista
     * @return list<array{id:int,fecha:string,rubro:?string}>
     */
    private function elegir(array $lista): array
    {
        $metodo = new ReflectionMethod(ContaduriaInventarioRecuentoSupport::class, 'elegirRecuentos');

        return $metodo->invoke(null, $lista);
    }

    /**
     * @return array{id:int,fecha:string,rubro:?string}
     */
    private function recuento(int $id, string $fecha, ?string $rubro): array
    {
        return ['id' => $id, 'fecha' => $fecha, 'rubro' => $rubro];
    }
}

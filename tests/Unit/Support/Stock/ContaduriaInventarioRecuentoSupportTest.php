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

    public function test_el_cierre_de_fin_de_mes_incluye_el_dia_1_siguiente(): void
    {
        $this->assertSame(
            ['2026-10-29', '2026-11-01', '2026-11-03'],
            ContaduriaInventarioRecuentoSupport::ventana('2026-10-31'),
        );
    }

    public function test_el_dia_1_al_3_siguen_siendo_el_cierre_del_mes_anterior(): void
    {
        $this->assertSame(
            ['2026-10-29', '2026-11-01', '2026-11-03'],
            ContaduriaInventarioRecuentoSupport::ventana('2026-11-03'),
        );
    }

    public function test_si_hay_recuento_del_mes_no_usa_el_2_ni_el_3(): void
    {
        [$desde, $corte, $extension] = ContaduriaInventarioRecuentoSupport::ventana('2026-11-03');
        $elegidos = $this->elegir([
            $this->recuento(17, '2026-06-30'),
            $this->recuento(181, '2026-10-27'),
            $this->recuento(187, '2026-10-31'),
            $this->recuento(190, '2026-11-02'),
        ], $desde, $corte, $extension);

        $this->assertSame([187], array_column($elegidos, 'id'));
    }

    public function test_el_2_entra_solo_cuando_el_deposito_no_tiene_cierre_del_mes(): void
    {
        [$desde, $corte, $extension] = ContaduriaInventarioRecuentoSupport::ventana('2026-11-03');
        $elegidos = $this->elegir([
            $this->recuento(16, '2026-06-30'),
            $this->recuento(217, '2026-11-02'),
        ], $desde, $corte, $extension);

        $this->assertSame([217], array_column($elegidos, 'id'));
    }

    /**
     * @param  list<array{id:int,fecha:string}>  $lista
     * @return list<array{id:int,fecha:string}>
     */
    private function elegir(array $lista, string $desde, string $corte, string $extension): array
    {
        $metodo = new ReflectionMethod(ContaduriaInventarioRecuentoSupport::class, 'elegirRecuentos');

        return $metodo->invoke(null, $lista, $desde, $corte, $extension);
    }

    /**
     * @return array{id:int,fecha:string}
     */
    private function recuento(int $id, string $fecha): array
    {
        return ['id' => $id, 'fecha' => $fecha];
    }
}

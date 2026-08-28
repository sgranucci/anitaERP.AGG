<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\FacturaPdfPaginacionSupport;
use PHPUnit\Framework\TestCase;

final class FacturaPdfPaginacionSupportTest extends TestCase
{
    public function test_pocos_items_van_en_una_sola_pagina(): void
    {
        $this->assertCount(1, FacturaPdfPaginacionSupport::paginas($this->items(2), 'admin'));
        $this->assertCount(1, FacturaPdfPaginacionSupport::paginas($this->items(16), 'admin'));
    }

    public function test_hasta_capacidad_sin_pie_no_parte_una_hoja_casi_vacia(): void
    {
        $paginas = FacturaPdfPaginacionSupport::paginas($this->items(18), 'admin');

        $this->assertCount(1, $paginas);
        $this->assertCount(18, $paginas[0]);
    }

    public function test_mas_de_una_hoja_llena_la_primera_y_deja_el_resto_al_final(): void
    {
        $paginas = FacturaPdfPaginacionSupport::paginas($this->items(21), 'admin');

        $this->assertCount(2, $paginas);
        $this->assertCount(FacturaPdfPaginacionSupport::ITEMS_ANTERIOR_ADMIN, $paginas[0]);
        $this->assertCount(1, $paginas[1]);
    }

    public function test_no_inventa_hoja_intermedia_de_un_renglon(): void
    {
        $paginas = FacturaPdfPaginacionSupport::paginas($this->items(37), 'admin');

        $this->assertCount(2, $paginas);
        $this->assertCount(20, $paginas[0]);
        $this->assertCount(17, $paginas[1]);
    }

    /**
     * @return list<array{sku: string}>
     */
    private function items(int $n): array
    {
        $filas = [];
        for ($i = 1; $i <= $n; $i++) {
            $filas[] = ['sku' => (string) $i];
        }

        return $filas;
    }
}

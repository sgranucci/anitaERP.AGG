<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorCbuPagoSupport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProveedorCbuPagoSupportTest extends TestCase
{
    public function test_rechaza_cbu_de_otro_proveedor(): void
    {
        $lista = [
            ['id' => 6600, 'cbu' => '2850651340094075988138'],
        ];

        $this->expectException(InvalidArgumentException::class);
        ProveedorCbuPagoSupport::resolverEnLista(0, '0070680930004032100157', $lista);
    }

    public function test_rechaza_cbu_ajeno_aunque_el_proveedor_no_tenga_cuentas(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ProveedorCbuPagoSupport::resolverEnLista(0, '0070680930004032100157', []);
    }

    public function test_acepta_el_cbu_cargado_en_el_proveedor(): void
    {
        $lista = [
            ['id' => 6600, 'cbu' => '2850651340094075988138'],
        ];

        $this->assertSame(
            [
                'proveedor_formapago_id' => 6600,
                'cbu_pago' => '2850651340094075988138',
            ],
            ProveedorCbuPagoSupport::resolverEnLista(0, '2850651340094075988138', $lista)
        );
    }

    public function test_sin_cbu_usa_la_unica_cuenta_del_proveedor(): void
    {
        $lista = [
            ['id' => 8120, 'cbu' => '2850651340094075988138'],
        ];

        $this->assertSame(
            [
                'proveedor_formapago_id' => 8120,
                'cbu_pago' => '2850651340094075988138',
            ],
            ProveedorCbuPagoSupport::resolverEnLista(0, '', $lista)
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja\Frances;

use App\Support\Caja\Frances\FrancesArchivoPagoArmadoSupport;
use App\Support\Caja\Frances\FrancesArchivoPagoFormatoSupport;
use App\Support\Compras\CbuSupport;
use PHPUnit\Framework\TestCase;

class FrancesArchivoPagoArmadoSupportTest extends TestCase
{
    public function test_arma_la_linea_del_frances_y_deja_afuera_transferidas(): void
    {
        $cbu = $this->cbuValido();
        $this->assertTrue(CbuSupport::esValido($cbu));

        $pagos = [
            [
                'tipo' => 'OPP', 'numero' => 15, 'sucursal' => 1, 'proveedor' => '000123',
                'fecha' => '20261020', 'mov_ext' => ' ', 'letra' => 'A',
            ],
            [
                'tipo' => 'OPP', 'numero' => 16, 'sucursal' => 1, 'proveedor' => '000123',
                'fecha' => '20261021', 'mov_ext' => 'T', 'letra' => 'A',
            ],
        ];
        $aux = [
            [
                'tipo' => 'OPP', 'numero' => 15, 'sucursal' => 1, 'proveedor' => '123',
                'fecha' => '20261020', 'tipo_ap' => 'BFM', 'monto' => 1500.5, 'letra' => 'A',
            ],
            [
                'tipo' => 'OPP', 'numero' => 16, 'sucursal' => 1, 'proveedor' => '123',
                'fecha' => '20261021', 'tipo_ap' => 'BFM', 'monto' => 10, 'letra' => 'A',
            ],
            [
                'tipo' => 'OPP', 'numero' => 15, 'sucursal' => 1, 'proveedor' => '123',
                'fecha' => '20261020', 'tipo_ap' => 'CHP', 'monto' => 99, 'letra' => 'A',
            ],
        ];
        $proveedores = [
            '000123' => ['nombre' => 'Proveedor Uno', 'cuit' => '20-12345678-6', 'cbu' => $cbu],
        ];

        $r = FrancesArchivoPagoArmadoSupport::armar($pagos, $aux, $proveedores, 'BFM');

        $this->assertSame(1, $r['cantidad']);
        $this->assertSame(1500.5, $r['total']);
        $this->assertSame('A0001-00000015', $r['filas'][0]['orden_pago']);
        $this->assertSame(
            FrancesArchivoPagoFormatoSupport::nombre30('Proveedor Uno').','.$cbu.',0000001500.50,FAC,20123456786'."\n",
            $r['archivo']
        );
        $this->assertSame('Ya marcada como transferida en Anita', $r['omitidas'][0]['motivo']);
    }

    private function cbuValido(): string
    {
        $banco = $this->conDigito('0070999', [7, 1, 3, 9, 7, 1, 3]);
        $cuenta = $this->conDigito('0200000456789', [3, 9, 7, 1, 3, 9, 7, 1, 3, 9, 7, 1, 3]);

        return $banco.$cuenta;
    }

    /**
     * @param  list<int>  $pesos
     */
    private function conDigito(string $base, array $pesos): string
    {
        $suma = 0;
        foreach ($pesos as $i => $peso) {
            $suma += (int) $base[$i] * $peso;
        }
        $dv = (10 - ($suma % 10)) % 10;

        return $base.$dv;
    }
}

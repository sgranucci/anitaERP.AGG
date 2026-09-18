<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ArticuloCuentacontableEmpresasSupport;
use PHPUnit\Framework\TestCase;

class ArticuloCuentacontableEmpresasSupportTest extends TestCase
{
    public function test_completa_kandiko_y_rebisco_desde_biyemas(): void
    {
        $plan = ArticuloCuentacontableEmpresasSupport::planificar(
            [
                $this->fila(1, 10, 1, 'VENTAS', 100, 1, '4110'),
                $this->fila(2, 10, 1, 'COMPRAS', 101, 1, '5100'),
            ],
            [
                1 => ['4110' => 100, '5100' => 101],
                2 => ['4110' => 200, '5100' => 201],
                3 => ['4110' => 300, '5100' => 301],
            ],
            [1, 2, 3],
            1
        );

        $this->assertSame(1, $plan['articulos_solo_origen']);
        $this->assertSame(1, $plan['articulos_incompletos']);
        $this->assertCount(4, $plan['altas']);
        $this->assertSame([], $plan['homologaciones']);
        $this->assertSame([], $plan['errores']);

        $claves = collect($plan['altas'])
            ->map(fn (array $a) => $a['empresa_id'].'|'.$a['tipoimputacion'].'|'.$a['cuentacontable_id'])
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['2|COMPRAS|201', '2|VENTAS|200', '3|COMPRAS|301', '3|VENTAS|300'], $claves);
    }

    public function test_homologa_fila_de_kandiko_que_apunta_a_cuenta_biyemas(): void
    {
        $plan = ArticuloCuentacontableEmpresasSupport::planificar(
            [
                $this->fila(1, 10, 1, 'VENTAS', 100, 1, '4110'),
                $this->fila(2, 10, 2, 'VENTAS', 100, 1, '4110'),
                $this->fila(3, 10, 3, 'VENTAS', 100, 1, '4110'),
            ],
            [
                1 => ['4110' => 100],
                2 => ['4110' => 200],
                3 => ['4110' => 300],
            ],
            [1, 2, 3],
            1
        );

        $this->assertSame(0, $plan['articulos_incompletos']);
        $this->assertSame([], $plan['altas']);
        $this->assertCount(2, $plan['homologaciones']);
        $porEmpresa = collect($plan['homologaciones'])->keyBy('empresa_id');
        $this->assertSame(200, $porEmpresa[2]['cuentacontable_id']);
        $this->assertSame(300, $porEmpresa[3]['cuentacontable_id']);
    }

    public function test_error_si_no_hay_cuenta_homologada(): void
    {
        $plan = ArticuloCuentacontableEmpresasSupport::planificar(
            [
                $this->fila(1, 10, 1, 'GASTOS', 100, 1, '9999'),
            ],
            [
                1 => ['9999' => 100],
                2 => [],
                3 => ['9999' => 300],
            ],
            [1, 2, 3],
            1
        );

        $this->assertCount(1, $plan['altas']);
        $this->assertSame(3, $plan['altas'][0]['empresa_id']);
        $this->assertCount(1, $plan['errores']);
        $this->assertSame(2, $plan['errores'][0]['empresa_id']);
        $this->assertSame('alta', $plan['errores'][0]['accion']);
    }

    public function test_no_toca_filas_ya_homologadas(): void
    {
        $plan = ArticuloCuentacontableEmpresasSupport::planificar(
            [
                $this->fila(1, 10, 1, 'VENTAS', 100, 1, '4110'),
                $this->fila(2, 10, 2, 'VENTAS', 200, 2, '4110'),
                $this->fila(3, 10, 3, 'VENTAS', 300, 3, '4110'),
            ],
            [
                1 => ['4110' => 100],
                2 => ['4110' => 200],
                3 => ['4110' => 300],
            ],
            [1, 2, 3],
            1
        );

        $this->assertSame([], $plan['altas']);
        $this->assertSame([], $plan['homologaciones']);
        $this->assertSame([], $plan['errores']);
        $this->assertSame(0, $plan['articulos_incompletos']);
        $this->assertSame(0, $plan['articulos_solo_origen']);
    }

    public function test_indexar_cuentas_acepta_codigo_con_ceros_a_izquierda(): void
    {
        $mapa = ArticuloCuentacontableEmpresasSupport::indexarCuentas([
            ['id' => 50, 'empresa_id' => 2, 'codigo' => '04110'],
        ]);

        $this->assertSame(50, $mapa[2]['04110']);
        $this->assertSame(50, $mapa[2]['4110']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(
        int $id,
        int $articuloId,
        int $empresaId,
        string $tipo,
        int $cuentaId,
        int $cuentaEmpresaId,
        string $codigo
    ): array {
        return [
            'id' => $id,
            'articulo_id' => $articuloId,
            'empresa_id' => $empresaId,
            'tipoimputacion' => $tipo,
            'cuentacontable_id' => $cuentaId,
            'cuenta_empresa_id' => $cuentaEmpresaId,
            'cuenta_codigo' => $codigo,
            'creousuario_id' => 7,
        ];
    }
}

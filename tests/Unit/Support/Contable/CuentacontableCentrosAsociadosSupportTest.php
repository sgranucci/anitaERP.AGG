<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\CuentacontableCentrosAsociadosSupport;
use PHPUnit\Framework\TestCase;

class CuentacontableCentrosAsociadosSupportTest extends TestCase
{
    public function test_sin_manejo_de_costo_muestra_guion(): void
    {
        $this->assertSame('—', CuentacontableCentrosAsociadosSupport::textoAsociados('N', []));
    }

    public function test_maneja_sin_vinculos_es_cualquiera(): void
    {
        $this->assertSame('Cualquiera', CuentacontableCentrosAsociadosSupport::textoAsociados('S', []));
    }

    public function test_lista_codigo_nombre_desde_centros(): void
    {
        $centros = [
            (object) ['codigo' => '10', 'nombre' => 'Administración'],
            (object) ['codigo' => '20', 'nombre' => 'Producción'],
        ];

        $this->assertSame(
            '10 - Administración, 20 - Producción',
            CuentacontableCentrosAsociadosSupport::textoAsociados('S', $centros)
        );
    }

    public function test_lista_desde_pivot_con_relacion_centrocostos(): void
    {
        $vinculos = [
            (object) ['centrocostos' => (object) ['codigo' => '5', 'nombre' => 'Bingo']],
        ];

        $this->assertSame(
            '5 - Bingo',
            CuentacontableCentrosAsociadosSupport::textoAsociados('1', $vinculos)
        );
    }

    public function test_omite_duplicados_y_vacios(): void
    {
        $centros = [
            (object) ['codigo' => '10', 'nombre' => 'Admin'],
            (object) ['codigo' => '10', 'nombre' => 'Admin'],
            (object) ['codigo' => '', 'nombre' => ''],
        ];

        $this->assertSame(
            '10 - Admin',
            CuentacontableCentrosAsociadosSupport::textoAsociados('S', $centros)
        );
    }
}

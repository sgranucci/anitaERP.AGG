<?php

namespace Tests\Unit\Sueldos;

use App\Models\User;
use App\Support\Sueldos\NovedadSueldosImportPreferenciasUsuario;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NovedadSueldosImportPreferenciasUsuarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->actingAs(User::make(['id' => 4242, 'name' => 'Test', 'email' => 'test@example.com']));
    }

    public function test_persiste_y_lee_seleccion_por_usuario(): void
    {
        NovedadSueldosImportPreferenciasUsuario::persistir([
            'modo_columnas' => 'numero',
            'csv_separador' => ';',
            'fila_encabezado' => 2,
            'empresa_id' => 7,
            'mapping' => [
                'legajo' => '1',
                'concepto_codigo' => '2',
                'valor1' => '',
            ],
        ]);

        $this->assertSame('numero', NovedadSueldosImportPreferenciasUsuario::valor('modo_columnas'));
        $this->assertSame(';', NovedadSueldosImportPreferenciasUsuario::valor('csv_separador'));
        $this->assertSame(2, NovedadSueldosImportPreferenciasUsuario::valor('fila_encabezado'));
        $this->assertSame(7, NovedadSueldosImportPreferenciasUsuario::valor('empresa_id'));
        $this->assertSame(
            ['legajo' => '1', 'concepto_codigo' => '2'],
            NovedadSueldosImportPreferenciasUsuario::mapping()
        );
    }

    public function test_persistir_desde_request_fusiona_parcial(): void
    {
        NovedadSueldosImportPreferenciasUsuario::persistir([
            'modo_columnas' => 'titulo',
            'csv_separador' => 'auto',
            'mapping' => ['legajo' => 'legajo'],
        ]);

        NovedadSueldosImportPreferenciasUsuario::persistirDesdeRequest([
            'modo_columnas' => 'numero',
            'mapping' => ['legajo' => 'A', 'concepto_codigo' => 'B'],
        ]);

        $prefs = NovedadSueldosImportPreferenciasUsuario::leer();
        $this->assertSame('numero', $prefs['modo_columnas']);
        $this->assertSame('auto', $prefs['csv_separador']);
        $this->assertSame(
            ['legajo' => 'A', 'concepto_codigo' => 'B'],
            NovedadSueldosImportPreferenciasUsuario::mapping()
        );
    }

    public function test_sin_usuario_no_persiste(): void
    {
        auth()->logout();
        NovedadSueldosImportPreferenciasUsuario::persistir([
            'modo_columnas' => 'numero',
        ]);

        $this->assertSame([], NovedadSueldosImportPreferenciasUsuario::leer());
    }
}

<?php

namespace Tests\Unit\Solicitudpago;

use App\Models\User;
use App\Support\Solicitudpago\SolicitudpagoCuotasImportPreferenciasUsuario;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SolicitudpagoCuotasImportPreferenciasUsuarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->actingAs(User::make(['id' => 5252, 'name' => 'Test', 'email' => 'test-sp-cuotas@example.com']));
    }

    public function test_persiste_y_lee_seleccion_por_usuario(): void
    {
        SolicitudpagoCuotasImportPreferenciasUsuario::persistir([
            'modo_columnas' => 'numero',
            'csv_separador' => ';',
            'fila_encabezado' => 2,
            'mapping' => [
                'nro_cuota' => '1',
                'fecha_vencimiento' => '2',
                'monto' => '3',
            ],
        ]);

        $this->assertSame('numero', SolicitudpagoCuotasImportPreferenciasUsuario::valor('modo_columnas'));
        $this->assertSame(';', SolicitudpagoCuotasImportPreferenciasUsuario::valor('csv_separador'));
        $this->assertSame(2, SolicitudpagoCuotasImportPreferenciasUsuario::valor('fila_encabezado'));
        $this->assertSame(
            ['nro_cuota' => '1', 'fecha_vencimiento' => '2', 'monto' => '3'],
            SolicitudpagoCuotasImportPreferenciasUsuario::mapping()
        );
    }

    public function test_persistir_desde_request_fusiona_parcial(): void
    {
        SolicitudpagoCuotasImportPreferenciasUsuario::persistir([
            'modo_columnas' => 'titulo',
            'csv_separador' => 'auto',
            'mapping' => ['fecha_vencimiento' => 'vencimiento'],
        ]);

        SolicitudpagoCuotasImportPreferenciasUsuario::persistirDesdeRequest([
            'modo_columnas' => 'numero',
            'mapping' => ['fecha_vencimiento' => 'A', 'monto' => 'B'],
        ]);

        $prefs = SolicitudpagoCuotasImportPreferenciasUsuario::leer();
        $this->assertSame('numero', $prefs['modo_columnas']);
        $this->assertSame('auto', $prefs['csv_separador']);
        $this->assertSame(
            ['fecha_vencimiento' => 'A', 'monto' => 'B'],
            SolicitudpagoCuotasImportPreferenciasUsuario::mapping()
        );
    }

    public function test_sin_usuario_no_persiste(): void
    {
        auth()->logout();
        SolicitudpagoCuotasImportPreferenciasUsuario::persistir([
            'modo_columnas' => 'numero',
        ]);

        $this->assertSame([], SolicitudpagoCuotasImportPreferenciasUsuario::leer());
    }
}

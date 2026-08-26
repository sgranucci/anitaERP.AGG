<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Uif;

use App\Support\Uif\ClienteUifCumplimientoSupport;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class ClienteUifCumplimientoSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cajero_pide_foto_archivos_y_firmas(): void
    {
        $eval = ClienteUifCumplimientoSupport::evaluar($this->clienteBase(), false);

        $this->assertSame('is-warning', $eval['claseBanner']);
        $this->assertSame('Pedí al cliente estos documentos y firmas', $eval['titulo']);
        $textos = array_column($eval['items'], 'texto');
        $this->assertContains('Pedí y adjuntá la foto o PDF del DNI.', $textos);
        $this->assertContains(
            'Adjuntá documentación de respaldo (declaración jurada, informes, constancias) en Archivos asociados.',
            $textos
        );
        $this->assertContains('Pedí la firma PEP y cargá la fecha de última firma.', $textos);
        $this->assertContains('Pedí la declaración jurada firmada de origen de ingresos/fondos.', $textos);
    }

    public function test_cajero_completo_solo_queda_validacion_enc_uif(): void
    {
        $cliente = $this->clienteBase([
            'fotodocumento' => 'dni.pdf',
            'cliente_archivos_uif' => [(object) ['id' => 1]],
            'fechafirmapep' => '2026-08-01',
            'fechaconfirmapep' => '2026-08-01',
            'fechavencimientodni' => '2030-01-01',
            'fechavencimientoactividad' => '2026-08-01',
            'firmodeclaracionjurada' => 'S',
        ]);

        $eval = ClienteUifCumplimientoSupport::evaluar($cliente, false);

        $this->assertSame([], $eval['items']);
    }

    public function test_supervisor_detecta_dni_vencido_y_nosis_viejo(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-25 12:00:00'));

        $cliente = $this->clienteBase([
            'fotodocumento' => 'dni.jpg',
            'cliente_archivos_uif' => [(object) ['id' => 1]],
            'fechafirmapep' => '2026-08-01',
            'fechaconfirmapep' => '2026-08-01',
            'fechavencimientodni' => '2026-01-01',
            'fechavencimientoactividad' => '2026-08-01',
            'firmodeclaracionjurada' => 'S',
            'riesgopep' => 'BAJO',
            'fechainformenosis' => '2025-01-01',
            'fechainformepep' => '2026-08-01',
        ]);

        $eval = ClienteUifCumplimientoSupport::evaluar($cliente, true);

        $this->assertSame('is-danger', $eval['claseBanner']);
        $textos = array_column($eval['items'], 'texto');
        $this->assertContains('DNI: vencido el 01-01-2026.', $textos);
        $this->assertContains('Informe NOSIS: debe renovar (último: 01-01-2025).', $textos);
        $this->assertNotContains('Pedí y adjuntá la foto o PDF del DNI.', $textos);
    }

    public function test_supervisor_completo_sin_avisos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-25 12:00:00'));

        $cliente = $this->clienteBase([
            'fotodocumento' => 'dni.jpg',
            'cliente_archivos_uif' => [(object) ['id' => 1]],
            'fechafirmapep' => '2026-08-01',
            'fechaconfirmapep' => '2026-08-01',
            'fechavencimientodni' => '2030-01-01',
            'fechavencimientoactividad' => '2026-08-01',
            'firmodeclaracionjurada' => 'S',
            'riesgopep' => 'BAJO',
            'fechainformenosis' => '2026-08-01',
            'fechainformepep' => '2026-08-01',
        ]);

        $eval = ClienteUifCumplimientoSupport::evaluar($cliente, true);

        $this->assertSame([], $eval['items']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function clienteBase(array $overrides = []): object
    {
        return (object) array_merge([
            'fotodocumento' => '',
            'cliente_archivos_uif' => [],
            'fechafirmapep' => null,
            'fechaconfirmapep' => null,
            'fechavencimientodni' => null,
            'fechavencimientoactividad' => null,
            'firmodeclaracionjurada' => 'N',
            'riesgopep' => 'BAJO',
            'fechainformenosis' => null,
            'fechainformepep' => null,
        ], $overrides);
    }
}

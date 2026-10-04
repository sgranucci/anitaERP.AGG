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

    public function test_cajero_completo_sin_avisos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-25 12:00:00'));

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

    public function test_cajero_detecta_pep_y_actividad_vencidos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 12:00:00'));

        $cliente = $this->clienteBase([
            'fotodocumento' => 'dni.pdf',
            'cliente_archivos_uif' => [(object) ['id' => 1]],
            'fechafirmapep' => '2026-03-04',
            'fechaconfirmapep' => '2026-03-04',
            'fechavencimientodni' => '2030-01-01',
            'fechavencimientoactividad' => '2026-03-04',
            'firmodeclaracionjurada' => 'S',
        ]);

        $eval = ClienteUifCumplimientoSupport::evaluar($cliente, false);

        $this->assertSame('is-danger', $eval['claseBanner']);
        $this->assertSame('Hay documentos o firmas vencidos / a renovar', $eval['titulo']);
        $textos = array_column($eval['items'], 'texto');
        $this->assertContains('PEP: debe renovar firma (última validación: 04-03-2026).', $textos);
        $this->assertContains('Actividad económica: vencimiento próximo o vencido (04-03-2026).', $textos);
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

    public function test_cuadro_enc_uif_vigente_sin_cartel(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00'));

        $cuadro = ClienteUifCumplimientoSupport::cuadroEncUif((object) [
            'fotodocumento' => 'dni.pdf',
            'fechafirmapep' => '2026-09-14',
            'fechaconfirmapep' => '2026-09-14',
            'fechavencimientodni' => '2030-08-03',
            'fechavencimientoactividad' => '2026-09-14',
            'firmodeclaracionjurada' => 'S',
            'riesgopep' => 'MEDIO',
            'fechainformenosis' => '2026-09-10',
            'fechainformepep' => '2026-09-10',
        ], ['cantidad_archivos' => 35]);

        $this->assertFalse($cuadro['hay_aviso']);
        $this->assertFalse($cuadro['solo_riesgo_alto']);
        $this->assertStringContainsString('no tiene que aparecer ningún cartel', $cuadro['resumen']);

        $porRequisito = [];
        foreach ($cuadro['filas'] as $fila) {
            $porRequisito[$fila['requisito']] = $fila;
        }
        $this->assertSame('14/09/2026', $porRequisito['Firma PEP']['cargado']);
        $this->assertSame('No. Se renueva el 15/03/2027.', $porRequisito['Firma PEP']['aviso']);
        $this->assertFalse($porRequisito['Firma PEP']['alerta']);
        $this->assertSame('No.', $porRequisito['Validación de firma PEP']['aviso']);
        $this->assertSame('Vence el 03/08/2030', $porRequisito['DNI']['cargado']);
        $this->assertSame('No. El cartel solo sale si es ALTO.', $porRequisito['Riesgo PEP']['aviso']);
        $this->assertSame('35 archivos', $porRequisito['Archivos asociados']['cargado']);
    }

    public function test_cuadro_enc_uif_solo_marca_riesgo_alto(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00'));

        $cuadro = ClienteUifCumplimientoSupport::cuadroEncUif((object) [
            'fotodocumento' => 'dni.pdf',
            'fechafirmapep' => '2026-08-31',
            'fechaconfirmapep' => '2026-08-31',
            'fechavencimientodni' => '2035-09-22',
            'fechavencimientoactividad' => '2026-08-31',
            'firmodeclaracionjurada' => 'S',
            'riesgopep' => 'ALTO',
            'fechainformenosis' => '2026-08-28',
            'fechainformepep' => '2026-08-28',
        ], ['cantidad_archivos' => 21]);

        $this->assertTrue($cuadro['hay_aviso']);
        $this->assertTrue($cuadro['solo_riesgo_alto']);
        $this->assertStringContainsString('riesgo PEP ALTO', $cuadro['resumen']);

        $riesgo = null;
        foreach ($cuadro['filas'] as $fila) {
            if ($fila['requisito'] === 'Riesgo PEP') {
                $riesgo = $fila;
            }
            if ($fila['requisito'] !== 'Riesgo PEP') {
                $this->assertFalse($fila['alerta'], $fila['requisito']);
            }
        }
        $this->assertNotNull($riesgo);
        $this->assertTrue($riesgo['alerta']);
        $this->assertSame('No. Se renueva el 01/03/2027.', $cuadro['filas'][0]['aviso']);
    }

    public function test_cuadro_enc_uif_marca_firma_vencida_y_faltantes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00'));

        $cuadro = ClienteUifCumplimientoSupport::cuadroEncUif((object) [
            'fotodocumento' => '',
            'fechafirmapep' => '2026-03-01',
            'fechaconfirmapep' => '2026-03-01',
            'fechavencimientodni' => '2026-01-15',
            'fechavencimientoactividad' => null,
            'firmodeclaracionjurada' => 'N',
            'riesgopep' => 'BAJO',
            'fechainformenosis' => '2025-01-01',
            'fechainformepep' => null,
        ], ['cantidad_archivos' => 0]);

        $this->assertTrue($cuadro['hay_aviso']);
        $this->assertFalse($cuadro['solo_riesgo_alto']);
        $alertas = array_column(array_filter($cuadro['filas'], static fn (array $fila): bool => $fila['alerta']), 'requisito');
        $this->assertContains('Validación de firma PEP', $alertas);
        $this->assertContains('Declaración jurada', $alertas);
        $this->assertContains('DNI', $alertas);
        $this->assertContains('Actividad económica', $alertas);
        $this->assertContains('Informe NOSIS', $alertas);
        $this->assertContains('Informe PEP', $alertas);
        $this->assertContains('Foto del DNI', $alertas);
        $this->assertContains('Archivos asociados', $alertas);
        $this->assertNotContains('Firma PEP', $alertas);
        $this->assertNotContains('Riesgo PEP', $alertas);
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

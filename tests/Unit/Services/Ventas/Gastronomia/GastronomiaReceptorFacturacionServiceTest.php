<?php

namespace Tests\Unit\Services\Ventas\Gastronomia;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\CuentaGastronomia;
use App\Services\Ventas\Gastronomia\GastronomiaReceptorFacturacionService;
use Tests\TestCase;

class GastronomiaReceptorFacturacionServiceTest extends TestCase
{
    public function test_sin_cliente_facturacion_resuelve_consumidor_final_con_arca_99(): void
    {
        $cuenta = new CuentaGastronomia(['cliente_id' => null]);

        $svc = app(GastronomiaReceptorFacturacionService::class);
        $rec = $svc->resolverParaFacturar($cuenta, 10.);

        $this->assertSame(GastronomiaReceptorFacturacionService::MODO_CONSUMIDOR_FINAL, $rec['modo']);
        $this->assertArrayHasKey('arca_receptor', $rec);
        $this->assertSame(99, (int) $rec['arca_receptor']['tipodoc']);
        $this->assertSame('0', (string) $rec['arca_receptor']['numerodocumento']);
    }

    public function test_cliente_canje_config_no_cuenta_como_facturacion(): void
    {
        $codigo = trim((string) config('gastronomia.canje_fidelidad_cliente_codigo', '500'));
        if ($codigo === '') {
            $this->markTestSkipped('Sin código de cliente canje configurado.');
        }

        $idCanje = (int) (\App\Models\Ventas\Cliente::query()->where('codigo', $codigo)->value('id') ?? 0);
        if ($idCanje <= 0) {
            $this->markTestSkipped('Cliente canje '.$codigo.' no existe en BD de prueba.');
        }

        $cuenta = new CuentaGastronomia([
            'cliente_id' => $idCanje,
            'cliente_interno_descuento_id' => $idCanje,
        ]);

        $svc = app(GastronomiaReceptorFacturacionService::class);
        $this->assertNull($svc->clienteIdFacturacionExplicito($cuenta));
        $this->assertTrue($svc->facturaComoConsumidorFinal($cuenta));

        $rec = $svc->resolverParaFacturar($cuenta, 0.01);
        $this->assertSame(GastronomiaReceptorFacturacionService::MODO_CONSUMIDOR_FINAL, $rec['modo']);
        $this->assertSame(99, (int) $rec['arca_receptor']['tipodoc']);
    }

    public function test_canje_fidelidad_pendiente_fuerza_consumidor_final(): void
    {
        $cuenta = new CuentaGastronomia([
            'cliente_id' => 999999,
            'canje_fidelidad_pendiente' => ['trackdata' => 'X', 'tarjeta' => ['documento' => '12345678']],
        ]);

        $svc = app(GastronomiaReceptorFacturacionService::class);
        $rec = $svc->resolverParaFacturar($cuenta, 0.01);

        $this->assertSame(GastronomiaReceptorFacturacionService::MODO_CONSUMIDOR_FINAL, $rec['modo']);
        $this->assertArrayHasKey('arca_receptor', $rec);
    }

    public function test_normalizar_cliente_id_descarta_interno_descuento(): void
    {
        $svc = app(GastronomiaReceptorFacturacionService::class);
        $this->assertNull($svc->normalizarClienteIdFacturacion(188, 188));
        $this->assertSame(42, $svc->normalizarClienteIdFacturacion(42, 188));
    }

    public function test_cliente_vip_plantilla_no_es_cliente_facturacion(): void
    {
        $clienteVip = Cliente::query()->where('codigo', 'A65VIP')->first();
        if ($clienteVip === null) {
            $this->markTestSkipped('Cliente A65VIP no existe en BD de prueba.');
        }

        $svc = app(GastronomiaReceptorFacturacionService::class);
        $this->assertTrue($svc->esClientePlantillaInternaGastronomia((int) $clienteVip->id));
        $this->assertNull($svc->normalizarClienteIdFacturacion((int) $clienteVip->id, 0));

        $cuenta = new CuentaGastronomia(['cliente_id' => (int) $clienteVip->id]);
        $this->assertTrue($svc->facturaComoConsumidorFinal($cuenta));
        $rec = $svc->resolverParaFacturar($cuenta, 7600.);
        $this->assertSame(GastronomiaReceptorFacturacionService::MODO_CONSUMIDOR_FINAL, $rec['modo']);
    }

    public function test_maestro_sin_documento_usa_arca_consumidor_final(): void
    {
        $cliente = \App\Models\Ventas\Cliente::query()
            ->with('tipodocumentos')
            ->whereHas('tipodocumentos', fn ($q) => $q->where('codigoexterno', 80))
            ->where(function ($q) {
                $q->whereNull('numerodocumento')
                    ->orWhere('numerodocumento', '')
                    ->orWhere('numerodocumento', '0');
            })
            ->first();

        if (! $cliente) {
            $this->markTestSkipped('Sin cliente de prueba con tipo CUIT y documento vacío.');
        }

        $cuenta = new CuentaGastronomia(['cliente_id' => $cliente->id]);
        $svc = app(GastronomiaReceptorFacturacionService::class);
        $rec = $svc->resolverParaFacturar($cuenta, 100.);

        $this->assertSame(GastronomiaReceptorFacturacionService::MODO_MAESTRO, $rec['modo']);
        $this->assertSame(99, (int) $rec['arca_receptor']['tipodoc']);
        $this->assertSame('0', (string) $rec['arca_receptor']['numerodocumento']);
        $this->assertNotSame('', trim((string) ($rec['arca_receptor']['nombre'] ?? '')));
    }
}

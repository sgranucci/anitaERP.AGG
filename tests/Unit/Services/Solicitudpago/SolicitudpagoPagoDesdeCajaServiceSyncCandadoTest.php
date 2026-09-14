<?php

namespace Tests\Unit\Services\Solicitudpago;

use App\Models\Caja\Caja_Movimiento;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Services\Solicitudpago\SolicitudpagoPagoDesdeCajaService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SolicitudpagoPagoDesdeCajaServiceSyncCandadoTest extends TestCase
{
    private function movimiento(array $attrs): Caja_Movimiento
    {
        $mov = new Caja_Movimiento();
        foreach ($attrs as $key => $value) {
            $mov->{$key} = $value;
        }

        return $mov;
    }

    public function test_no_marca_pagada_si_ie_esta_revertido(): void
    {
        $repo = $this->createMock(SolicitudpagoRepositoryInterface::class);
        $repo->expects($this->never())->method('findOrFail');
        $repo->expects($this->never())->method('cambiarEstado');

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'solicitudpago.pago_caja_omitido'
                    && ($context['motivo'] ?? null) === 'revertido';
            });

        $service = new SolicitudpagoPagoDesdeCajaService($repo);
        $service->sincronizarDesdeMovimiento($this->movimiento([
            'id' => 108063,
            'solicitudpago_id' => 11256,
            'caja_movimiento_origen_id' => null,
            'caja_movimiento_revertido_por_id' => 108078,
        ]));
    }

    public function test_no_marca_pagada_si_ie_es_compensatorio(): void
    {
        $repo = $this->createMock(SolicitudpagoRepositoryInterface::class);
        $repo->expects($this->never())->method('findOrFail');
        $repo->expects($this->never())->method('cambiarEstado');

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'solicitudpago.pago_caja_omitido'
                    && ($context['motivo'] ?? null) === 'compensatorio';
            });

        $service = new SolicitudpagoPagoDesdeCajaService($repo);
        $service->sincronizarDesdeMovimiento($this->movimiento([
            'id' => 108078,
            'solicitudpago_id' => 11256,
            'caja_movimiento_origen_id' => 108063,
            'caja_movimiento_revertido_por_id' => null,
        ]));
    }
}

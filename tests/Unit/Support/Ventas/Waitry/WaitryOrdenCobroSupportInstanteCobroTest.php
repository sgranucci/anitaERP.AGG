<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryOrdenCobroSupport;
use Tests\TestCase;

final class WaitryOrdenCobroSupportInstanteCobroTest extends TestCase
{
    public function test_instante_cobro_desde_payment_payments_created_at(): void
    {
        $orden = [
            'payment' => [
                'payments' => [
                    [
                        'createdAt' => [
                            'date' => '2026-06-20 05:37:14.000000',
                            'timezone_type' => 3,
                            'timezone' => 'America/Argentina/Buenos_Aires',
                        ],
                    ],
                ],
            ],
        ];

        $instante = WaitryOrdenCobroSupport::instanteCobroWaitry($orden);

        $this->assertNotNull($instante);
        $this->assertSame('2026-06-20 05:37:14', $instante->format('Y-m-d H:i:s'));
    }

    public function test_instante_cobro_null_sin_payment(): void
    {
        $this->assertNull(WaitryOrdenCobroSupport::instanteCobroWaitry(['paid' => true]));
    }
}

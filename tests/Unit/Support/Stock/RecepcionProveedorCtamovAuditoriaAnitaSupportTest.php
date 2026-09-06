<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorCtamovAuditoriaAnitaSupport;
use Tests\TestCase;

class RecepcionProveedorCtamovAuditoriaAnitaSupportTest extends TestCase
{
    public function test_alta_erp_sin_umod_no_es_modificacion(): void
    {
        $this->assertFalse(RecepcionProveedorCtamovAuditoriaAnitaSupport::fueModificadoTrasAlta([
            [
                'ctav_usuario_umod' => ' ',
                'ctav_fecha_umod' => '0',
                'ctav_hora_umod' => ' ',
            ],
        ]));
    }

    public function test_edicion_anita_con_usuario_y_fecha(): void
    {
        $this->assertTrue(RecepcionProveedorCtamovAuditoriaAnitaSupport::fueModificadoTrasAlta([
            [
                'ctav_usuario_umod' => 'egalarza',
                'ctav_fecha_umod' => '20260828',
                'ctav_hora_umod' => '12:29',
            ],
        ]));
        $this->assertSame(
            'usuario egalarza 28/08/2026 12:29',
            RecepcionProveedorCtamovAuditoriaAnitaSupport::resumenModificacion([
                [
                    'ctav_usuario_umod' => 'egalarza',
                    'ctav_fecha_umod' => '20260828',
                    'ctav_hora_umod' => '12:29',
                ],
            ]),
        );
    }

    public function test_montos_coinciden_con_tolerancia(): void
    {
        $this->assertTrue(RecepcionProveedorCtamovAuditoriaAnitaSupport::montosCoinciden(
            253168.84,
            253168.84,
            253168.84,
            253168.84,
            0.02,
        ));
        $this->assertFalse(RecepcionProveedorCtamovAuditoriaAnitaSupport::montosCoinciden(
            100.00,
            100.00,
            90.00,
            90.00,
            0.02,
        ));
    }
}

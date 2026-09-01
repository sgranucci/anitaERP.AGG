<?php

namespace Tests\Unit\Support\Caja;

use PHPUnit\Framework\TestCase;

class IngresoEgresoMontoInputTest extends TestCase
{
    public function test_monto_y_cotizacion_de_caja_son_texto_para_formato_es_ar(): void
    {
        $template = file_get_contents(dirname(__DIR__, 4).'/resources/views/caja/ingresoegreso/template.blade.php');
        $form = file_get_contents(dirname(__DIR__, 4).'/resources/views/caja/ingresoegreso/form.blade.php');

        $this->assertIsString($template);
        $this->assertIsString($form);

        $this->assertStringNotContainsString('type="number" name="montos[]"', $template);
        $this->assertStringNotContainsString('type="number" name="montos[]"', $form);
        $this->assertStringNotContainsString('type="number" name="cotizaciones[]"', $template);
        $this->assertStringNotContainsString('type="number" name="cotizaciones[]"', $form);

        $this->assertStringContainsString('type="text" inputmode="decimal" name="montos[]"', $template);
        $this->assertStringContainsString('type="text" inputmode="decimal" name="montos[]"', $form);
        $this->assertStringContainsString('type="text" inputmode="decimal" name="cotizaciones[]"', $template);
        $this->assertStringContainsString('type="text" inputmode="decimal" name="cotizaciones[]"', $form);
    }
}

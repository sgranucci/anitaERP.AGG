<?php

namespace Tests\Unit\Support\Anita;

use App\Support\Anita\AnitaTextoSanitizer;
use PHPUnit\Framework\TestCase;

class AnitaTextoSanitizerTest extends TestCase
{
    public function test_null_y_vacio_devuelven_cadena_vacia(): void
    {
        $this->assertSame('', AnitaTextoSanitizer::sanitizar(null));
        $this->assertSame('', AnitaTextoSanitizer::sanitizar(''));
    }

    public function test_texto_ascii_plano_se_conserva(): void
    {
        $this->assertSame(
            'Solicitud sala VIP',
            AnitaTextoSanitizer::sanitizar('Solicitud sala VIP'),
        );
    }

    public function test_quita_acentos_y_ene_utf8(): void
    {
        $resultado = AnitaTextoSanitizer::sanitizar('Reparación máquina línea Ñ');

        $this->assertSame('Reparacion maquina linea N', $resultado);
        $this->assertSoloAsciiImprimible($resultado);
    }

    public function test_quita_saltos_de_linea_y_tabs(): void
    {
        $this->assertSame(
            'multi linea tab',
            AnitaTextoSanitizer::sanitizar("multi\nlinea\ttab"),
        );
        $this->assertSame(
            'dos lineas',
            AnitaTextoSanitizer::sanitizar("dos\r\nlineas"),
        );
    }

    public function test_normaliza_comillas_y_guiones_tipograficos(): void
    {
        $resultado = AnitaTextoSanitizer::sanitizar('Menú niño – oferta “especial”');

        $this->assertSame('Menu nino - oferta "especial"', $resultado);
        $this->assertSoloAsciiImprimible($resultado);
    }

    public function test_reemplaza_simbolos_problematicos(): void
    {
        $resultado = AnitaTextoSanitizer::sanitizar('Café 20° … €50');

        $this->assertSame('Cafe 20 ... EUR50', $resultado);
        $this->assertSoloAsciiImprimible($resultado);
    }

    public function test_nbsp_se_reemplaza_por_espacio(): void
    {
        $this->assertSame(
            'texto con espacio',
            AnitaTextoSanitizer::sanitizar("texto\xC2\xA0con\xC2\xA0espacio"),
        );
    }

    public function test_colapsa_espacios_multiples(): void
    {
        $this->assertSame(
            'uno dos tres',
            AnitaTextoSanitizer::sanitizar('  uno   dos   tres  '),
        );
    }

    public function test_conserva_comilla_simple_ascii(): void
    {
        $this->assertSame(
            "O'Higgins repuesto",
            AnitaTextoSanitizer::sanitizar("O'Higgins repuesto"),
        );
    }

    public function test_resultado_nunca_contiene_bytes_fuera_de_ascii_imprimible(): void
    {
        $casos = [
            'Solicitud sala VIP',
            'Reparación máquina línea Ñ',
            'Café 20° "especial"',
            "Menú niño – oferta",
            "multi\nlinea\ttab",
            "O'Higgins repuesto",
            '¿¡Sí o no?',
        ];

        foreach ($casos as $entrada) {
            $this->assertSoloAsciiImprimible(
                AnitaTextoSanitizer::sanitizar($entrada),
                'entrada: '.$entrada,
            );
        }
    }

    private function assertSoloAsciiImprimible(string $texto, string $mensaje = ''): void
    {
        $prefijo = $mensaje !== '' ? $mensaje.' — ' : '';
        $this->assertSame(
            1,
            preg_match('/^[\x20-\x7E]*$/', $texto),
            $prefijo.'contiene bytes fuera de ASCII imprimible: ['.$texto.']',
        );
    }
}

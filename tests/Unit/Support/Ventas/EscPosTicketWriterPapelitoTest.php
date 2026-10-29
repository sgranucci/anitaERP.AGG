<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\EscPosTicketWriter;
use PHPUnit\Framework\TestCase;

final class EscPosTicketWriterPapelitoTest extends TestCase
{
    public function test_slot_queda_debajo_del_papelito(): void
    {
        $texto = (new EscPosTicketWriter())
            ->bloquePapelitoMonitor('301', '12')
            ->textoPlanoVistaPrevia();

        $posPapelito = mb_strpos($texto, '301');
        $posSlot = mb_strpos($texto, 'Slot 12');

        $this->assertNotFalse($posPapelito);
        $this->assertNotFalse($posSlot);
        $this->assertGreaterThan($posPapelito, $posSlot);
    }

    public function test_sin_slot_no_imprime_linea_slot(): void
    {
        $texto = (new EscPosTicketWriter())
            ->bloquePapelitoMonitor('301')
            ->textoPlanoVistaPrevia();

        $this->assertStringContainsString('301', $texto);
        $this->assertStringNotContainsString('Slot', $texto);
    }
}

<?php

namespace Tests\Unit\Services\Ticket;

use App\Services\Ticket\TicketConfiguracionService;
use PHPUnit\Framework\TestCase;

class TicketConfiguracionCcExclusionTest extends TestCase
{
    public function test_une_creador_autor_y_lista_de_exclusion(): void
    {
        $ids = TicketConfiguracionService::unirIdsExclusionCc(
            [139, 36],
            [81, '81', 0, null]
        );

        $this->assertSame([139, 36, 81], $ids);
    }

    public function test_descarta_ids_invalidos_y_duplicados(): void
    {
        $ids = TicketConfiguracionService::unirIdsExclusionCc(
            ['', false, -1],
            [81, 81]
        );

        $this->assertSame([81], $ids);
    }
}

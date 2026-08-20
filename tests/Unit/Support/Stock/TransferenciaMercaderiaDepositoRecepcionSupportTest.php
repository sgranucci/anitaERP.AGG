<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\TransferenciaMercaderiaDepositoRecepcionSupport;
use Tests\TestCase;

class TransferenciaMercaderiaDepositoRecepcionSupportTest extends TestCase
{
    public function test_existe_en_deposito_rechaza_ids_invalidos(): void
    {
        $this->assertFalse(TransferenciaMercaderiaDepositoRecepcionSupport::existeEnDeposito(0, 1, 963));
        $this->assertFalse(TransferenciaMercaderiaDepositoRecepcionSupport::existeEnDeposito(355, 0, 963));
        $this->assertFalse(TransferenciaMercaderiaDepositoRecepcionSupport::existeEnDeposito(355, 1, 0));
    }
}

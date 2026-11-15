<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas\GastronomiaCrown;

use App\Support\Ventas\GastronomiaCrown\GastronomiaAnitaResvtaCrownSupport;
use PHPUnit\Framework\TestCase;

final class GastronomiaAnitaResvtaCrownSupportTest extends TestCase
{
    public function test_bucket_medio_efectivo_y_tarjeta(): void
    {
        $this->assertSame('efectivo', GastronomiaAnitaResvtaCrownSupport::bucketMedio('Caja pesos', 'EFE'));
        $this->assertSame('efectivo', GastronomiaAnitaResvtaCrownSupport::bucketMedio('Efectivo sala', '01'));
        $this->assertSame('tarjeta', GastronomiaAnitaResvtaCrownSupport::bucketMedio('Fiserv', 'FIS'));
        $this->assertSame('tarjeta', GastronomiaAnitaResvtaCrownSupport::bucketMedio('Mercado Pago', 'GMEP'));
        $this->assertSame('tarjeta', GastronomiaAnitaResvtaCrownSupport::bucketMedio('Totalcoin', 'TC'));
        $this->assertSame('ctacte', GastronomiaAnitaResvtaCrownSupport::bucketMedio('Canje tarjeta', 'CTG'));
        $this->assertSame('habitacion', GastronomiaAnitaResvtaCrownSupport::bucketMedio('Cargo habitación', 'HOT'));
        $this->assertSame('cheque', GastronomiaAnitaResvtaCrownSupport::bucketMedio('Cheque propio', 'CH'));
    }
}

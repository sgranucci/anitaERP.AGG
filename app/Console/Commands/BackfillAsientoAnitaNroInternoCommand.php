<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Contable\AsientoAnitaNroInternoBackfillService;
use Illuminate\Console\Command;

final class BackfillAsientoAnitaNroInternoCommand extends Command
{
    protected $signature = 'contable:backfill-asiento-nro-interno
                            {--anio=2026 : Año a leer de subdiario y subhist}
                            {--empresa=1 : Código de empresa Anita}
                            {--ejecutar : Persiste el interno y engancha la factura}';

    protected $description = 'Guarda subd_nro_interno en el asiento y lo engancha a la factura con el mismo com_nro_interno';

    public function handle(AsientoAnitaNroInternoBackfillService $service): int
    {
        $anio = (int) $this->option('anio');
        $empresa = (int) $this->option('empresa');
        $ejecutar = (bool) $this->option('ejecutar');

        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN')." interno de asiento {$anio} empresa Anita {$empresa}");

        $resultado = $service->ejecutar($anio, $empresa, $ejecutar);

        $this->table(['Concepto', 'Valor'], [
            ['Filas subdiario/subhist', (string) $resultado['filas']],
            ['Operaciones con un interno', (string) $resultado['operaciones']],
            ['Operaciones con más de un interno', (string) $resultado['operaciones_ambiguas']],
            [$ejecutar ? 'Asientos actualizados' : 'Asientos a actualizar', (string) $resultado['asientos_actualizados']],
            ['Asientos que ya tenían el interno', (string) $resultado['asientos_ya_completos']],
            ['Asientos con otro interno (no se pisan)', (string) $resultado['asientos_conflicto']],
            ['Operaciones sin asiento único en el ERP', (string) $resultado['asientos_sin_match']],
            [$ejecutar ? 'Facturas enganchadas' : 'Facturas a enganchar', (string) $resultado['facturas_vinculadas']],
            ['Facturas ambiguas (no se enganchan)', (string) $resultado['facturas_ambiguas']],
            ['Facturas cuyo interno no está en un asiento', (string) $resultado['facturas_sin_asiento']],
        ]);

        if (! $ejecutar) {
            $this->comment('Dry-run: no se grabó nada. Relanzá con --ejecutar para persistir.');
        }

        return self::SUCCESS;
    }
}

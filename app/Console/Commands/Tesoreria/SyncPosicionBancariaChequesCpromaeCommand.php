<?php

namespace App\Console\Commands\Tesoreria;

use App\Support\Tesoreria\PosicionBancaria\PosicionBancariaChequeCpromaeSyncSupport;
use Illuminate\Console\Command;

class SyncPosicionBancariaChequesCpromaeCommand extends Command
{
    protected $signature = 'tesoreria:sync-cheques-cpromae
        {--anio=2026 : Año a sincronizar (fecha_cheque o fecha_emision)}
        {--empresas=1,2,3 : IDs de empresa separados por coma}';

    protected $description = 'Importa cheques CHP del año desde Anita cpromae al ERP (batch). Luego la conciliación/posición leen ERP.';

    public function handle(PosicionBancariaChequeCpromaeSyncSupport $sync): int
    {
        $anio = (int) $this->option('anio');
        $empresas = array_values(array_filter(array_map(
            static fn ($x) => (int) trim((string) $x),
            explode(',', (string) $this->option('empresas')),
        ), static fn (int $id) => $id > 0));

        $this->info("Sync cpromae → posicion_bancaria_cheque anio={$anio} empresas=".implode(',', $empresas));

        $stats = $sync->syncAnio($anio, $empresas, function ($cc, int $total, int $enAnio, ?string $err) use ($anio) {
            if ($err !== null) {
                $this->warn("  [err] emp={$cc->empresa_id} cta={$cc->codigo}: {$err}");

                return;
            }
            if ($enAnio > 0) {
                $this->line("  emp={$cc->empresa_id} cta={$cc->codigo} anita={$total} en_{$anio}={$enAnio}");
            }
        });

        $this->newLine();
        $this->table(
            ['cuentas', 'leidos', 'inserted', 'updated', 'skipped', 'errores'],
            [[
                $stats['cuentas'],
                $stats['leidos'],
                $stats['inserted'],
                $stats['updated'],
                $stats['skipped'],
                count($stats['errores']),
            ]],
        );
        foreach ($stats['errores'] as $e) {
            $this->warn($e);
        }

        return self::SUCCESS;
    }
}

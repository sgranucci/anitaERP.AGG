<?php

namespace App\Console\Commands\Tesoreria;

use App\Support\Tesoreria\PosicionBancaria\PosicionBancariaChequeAgingSupport;
use App\Support\Tesoreria\PosicionBancaria\PosicionBancariaChequeImportSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportarPosicionBancariaChequesCommand extends Command
{
    protected $signature = 'tesoreria:importar-posicion-cheques
        {excel : Ruta al Excel Posición Bancos (hojas Cheques BSA/KSA/RSA)}
        {--fecha= : Fecha de posición YYYY-MM-DD para mostrar aging (default: hoy)}
        {--desactivar-ausentes : Marca inactivos los excel_posicion que ya no están en el archivo}';

    protected $description = 'Importa cheques de la planilla Posición (BSA/KSA/RSA) al ERP (posicion_bancaria_cheque). Sin Anita online.';

    public function handle(
        PosicionBancariaChequeImportSupport $import,
        PosicionBancariaChequeAgingSupport $aging,
    ): int {
        $ruta = (string) $this->argument('excel');
        $this->info('Importando desde '.$ruta);

        $stats = $import->importarDesdeExcel($ruta, (bool) $this->option('desactivar-ausentes'));
        $this->table(
            ['inserted', 'updated', 'skipped', 'errors'],
            [[$stats['inserted'], $stats['updated'], $stats['skipped'], count($stats['errors'])]],
        );
        foreach ($stats['errors'] as $e) {
            $this->warn($e);
        }

        $fecha = $this->option('fecha')
            ? Carbon::parse((string) $this->option('fecha'))
            : Carbon::today();
        $resumen = $aging->resumir($fecha, null, null, true);
        $this->info('Aging PORTFOLIO al '.$resumen['fecha']);
        $this->line(sprintf(
            '  retenidos=%d ($%s)  transito=%d ($%s)  diferidos=%d ($%s)',
            $resumen['retenidos']['count'],
            number_format($resumen['retenidos']['importe'], 2, ',', '.'),
            $resumen['transito']['count'],
            number_format($resumen['transito']['importe'], 2, ',', '.'),
            $resumen['diferidos']['count'],
            number_format($resumen['diferidos']['importe'], 2, ',', '.'),
        ));
        foreach ($resumen['por_banco'] as $banco => $vals) {
            $this->line(sprintf(
                '  %s total=$%s (ret=$%s tr=$%s dif=$%s)',
                $banco,
                number_format($vals['total'], 2, ',', '.'),
                number_format($vals['retenidos'], 2, ',', '.'),
                number_format($vals['transito'], 2, ',', '.'),
                number_format($vals['diferidos'], 2, ',', '.'),
            ));
        }

        return self::SUCCESS;
    }
}

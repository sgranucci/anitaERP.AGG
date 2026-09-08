<?php

namespace App\Console\Commands\Tesoreria;

use App\Support\Tesoreria\PosicionBancaria\PosicionBancariaChequePlantillaExportSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerarPlantillaPosicionChequesCommand extends Command
{
    protected $signature = 'tesoreria:generar-plantilla-cheques
        {--fecha= : Fecha posición YYYY-MM-DD}
        {--base= : Plantilla base (Saldos+Resumen) a enriquecer}
        {--out= : Ruta de salida xlsx}';

    protected $description = 'Vuelve cheques desde posicion_bancaria_cheque (ERP) a hojas Cheques BSA/KSA/RSA livianas.';

    public function handle(PosicionBancariaChequePlantillaExportSupport $export): int
    {
        $fecha = $this->option('fecha')
            ? Carbon::parse((string) $this->option('fecha'))
            : Carbon::today();
        $base = $this->option('base') ?: base_path('docs/tesoreria/posicion-bancaria-diaria/Posicion_Bancos_plantilla.xlsx');
        $out = $this->option('out') ?: storage_path('app/tesoreria/Posicion_Bancos_'.$fecha->format('Ymd').'.xlsx');

        if (! is_dir(dirname($out))) {
            mkdir(dirname($out), 0775, true);
        }

        $this->info("Generando cheques ERP → {$out}");
        $stats = $export->generarArchivo($out, $fecha, is_file((string) $base) ? (string) $base : null);
        $this->table(['hojas', 'filas', 'out'], [[$stats['hojas'], $stats['filas'], $stats['out']]]);

        return self::SUCCESS;
    }
}

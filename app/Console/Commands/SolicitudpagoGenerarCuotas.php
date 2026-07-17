<?php

namespace App\Console\Commands;

use App\Services\Solicitudpago\SolicitudpagoGenerarCuotasService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SolicitudpagoGenerarCuotas extends Command
{
    protected $signature = 'solicitudpago:generar-cuotas
                            {--fecha= : Fecha de referencia Y-m-d (default: hoy)}
                            {--dias= : Días de anticipación (default: config solicitudpago.cuotas_dias_anticipacion)}
                            {--dry-run : Solo lista/cuenta sin grabar}';

    protected $description = 'Genera solicitudes de pago hijas por cuotas vencidas/próximas (Anita p-controlsolpm)';

    public function handle(SolicitudpagoGenerarCuotasService $service): int
    {
        $fechaOpt = trim((string) $this->option('fecha'));
        $hoy = $fechaOpt !== '' ? Carbon::parse($fechaOpt)->startOfDay() : Carbon::today();
        $diasOpt = $this->option('dias');
        $dias = is_numeric($diasOpt) ? (int) $diasOpt : null;
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Generar cuotas SP | ref=%s | dias=%s | %s',
            $hoy->toDateString(),
            $dias ?? config('solicitudpago.cuotas_dias_anticipacion', 6),
            $dryRun ? 'DRY-RUN' : 'EJECUCIÓN'
        ));

        $r = $service->ejecutar($hoy, $dias, $dryRun);

        $this->line('Procesadas (candidatas): '.$r['procesadas']);
        $this->line('Generadas: '.$r['generadas']);
        $this->line('Madres terminadas: '.$r['madres_terminadas']);
        if ($r['errores'] !== []) {
            $this->warn('Errores: '.count($r['errores']));
            foreach (array_slice($r['errores'], 0, 20) as $err) {
                $this->line(' - '.$err);
            }
        }

        return $r['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Compras\OrdencompraAnitaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class OrdencompraImportarFaltantesAnitaCommand extends Command
{
    protected $signature = 'ordencompra:importar-faltantes-anita
                            {--anio=2026 : Año penmp_fecha}
                            {--desde= : Fecha desde Ymd (prevalece sobre --anio)}
                            {--hasta= : Fecha hasta Ymd}
                            {--usuario= : ID usuario ERP}
                            {--dry-run : Solo informa, no escribe}';

    protected $description = 'Importa OC Anita faltantes en lote (pocas lecturas bridge), sin alinear las ya existentes';

    public function handle(OrdencompraAnitaSyncService $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $usuarioOpt = $this->option('usuario');
        $usuarioId = ($usuarioOpt !== null && $usuarioOpt !== '')
            ? (int) $usuarioOpt
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error("No se pudo autenticar usuario id {$usuarioId}.");

            return self::FAILURE;
        }

        $anio = (int) $this->option('anio');
        $desdeOpt = $this->option('desde');
        $hastaOpt = $this->option('hasta');
        $desde = ($desdeOpt !== null && $desdeOpt !== '')
            ? (int) preg_replace('/\D+/', '', (string) $desdeOpt)
            : ($anio * 10000 + 101);
        $hasta = ($hastaOpt !== null && $hastaOpt !== '')
            ? (int) preg_replace('/\D+/', '', (string) $hastaOpt)
            : ($anio * 10000 + 1231);

        $this->info(sprintf(
            'OC lote %d–%d | usuario %d | %s',
            $desde,
            $hasta,
            $usuarioId,
            $dryRun ? 'SIMULACIÓN' : 'escritura'
        ));

        try {
            $stats = $sync->importarFaltantesLote(
                $usuarioId,
                $desde,
                $hasta,
                $dryRun,
                function (int $i, int $total, int $nro) {
                    if ($i === 1 || $i % 100 === 0 || $i === $total) {
                        $this->line("   OC faltante {$i}/{$total} (nro {$nro})");
                    }
                }
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['En Anita (rango)', (string) $stats['en_anita']],
            ['Faltantes', (string) $stats['faltantes']],
            ['Importadas', (string) $stats['importados']],
            ['Ya en ERP (omitidas)', (string) $stats['omitidos']],
            ['Lecturas bridge', (string) $stats['lecturas_bridge']],
            ['Errores', (string) count($stats['errores'])],
        ]);
        foreach (array_slice($stats['errores'], 0, 40) as $err) {
            $this->warn($err);
        }
        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada.');
        }

        return $stats['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Ventas\ClienteMaestrosAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarClienteMaestrosDesdeAnita extends Command
{
    protected $signature = 'cliente:sincronizar-maestros-anita
                            {--solo= : Ejecutar solo un maestro (pais,provincia,localidad,zonavta,subzonavta)}';

    protected $description = 'Resincroniza tablas maestras de clientes desde Anita (país, provincia, localidad, zonas). Ejecutar antes de cliente:sincronizar-anita --resync.';

    public function handle(ClienteMaestrosAnitaSyncService $sync): int
    {
        $soloRaw = strtolower(trim((string) ($this->option('solo') ?? '')));
        $solo = $soloRaw !== '' ? [$soloRaw] : null;

        if ($solo !== null && ! isset(ClienteMaestrosAnitaSyncService::MAESTROS[$soloRaw])) {
            $this->error('Maestro desconocido. Opciones: '.implode(', ', array_keys(ClienteMaestrosAnitaSyncService::MAESTROS)));

            return self::FAILURE;
        }

        $this->info('Bridge: '.\App\ApiAnita::urlBridge());
        $this->info('Empresa: '.config('app.empresa'));
        $this->newLine();

        $resultados = $sync->resincronizarTodos($solo);
        $filas = [];

        foreach ($resultados as $clave => $row) {
            $cfg = ClienteMaestrosAnitaSyncService::MAESTROS[$clave];
            $this->line("→ {$row['label']} (Anita: {$cfg['tabla_anita']}, {$cfg['sistema']})…");

            if ($row['error'] !== null) {
                $this->error("  Error: {$row['error']}");
                $filas[] = [
                    $row['label'],
                    (string) $row['antes'],
                    (string) $row['despues'],
                    'ERROR',
                    $row['error'],
                ];

                continue;
            }

            $detalle = "altas {$row['insertados']}, actualizados {$row['actualizados']}, omitidos {$row['omitidos']}";
            $this->info("  {$detalle} (ERP: {$row['antes']} → {$row['despues']})");
            $filas[] = [
                $row['label'],
                (string) $row['antes'],
                (string) $row['despues'],
                'OK',
                $detalle,
            ];
        }

        $this->newLine();
        $this->table(['Maestro', 'ERP antes', 'ERP después', 'Estado', 'Detalle'], $filas);
        $this->line('Clientes: php artisan cliente:sincronizar-anita --resync');

        return self::SUCCESS;
    }
}

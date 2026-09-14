<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Compras\Ferli\ComprasMaestrosAnitaFerliSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Sync exclusivo CALZADOS FERLI de maestros previos al import de proveedores (promae).
 */
class SincronizarComprasMaestrosDesdeAnitaFerli extends Command
{
    protected $signature = 'compras:sincronizar-maestros-anita-ferli
                            {--path= : Path Anita (default ANITA_BDD_PATH /usr2/ferli)}
                            {--usuario= : ID usuario Auth (default: primer usuario)}
                            {--dry-run : Informe sin escribir en el ERP}';

    protected $description = 'CALZADOS FERLI: importa maestros de compras (tipoemp ventas, retenciones, conccomp, stubs desde promae). Ejecutar antes de proveedor:sincronizar-anita.';

    public function handle(ComprasMaestrosAnitaFerliSyncService $sync): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->error('Este comando solo aplica a EMPRESA=CALZADOS FERLI.');

            return self::FAILURE;
        }

        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $path = $this->option('path');
        $path = is_string($path) && trim($path) !== '' ? trim($path) : null;

        $this->info($dryRun
            ? 'Simulación: maestros compras Ferli (sin escribir)…'
            : 'Sincronizando maestros compras Ferli…');

        try {
            $resultado = $sync->sincronizar($dryRun, $path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Path Anita: '.$resultado['path']);
        foreach ($resultado['diagnostico'] as $linea) {
            $this->comment('• '.$linea);
        }
        $this->newLine();

        $filas = [];
        foreach ($resultado['maestros'] as $clave => $stats) {
            $filas[] = [
                $clave,
                (string) ($stats['fuente'] ?? ''),
                (string) ($stats['en_anita'] ?? 0),
                (string) ($stats['insertados'] ?? 0).($dryRun ? ' (sim)' : ''),
                (string) ($stats['omitidos'] ?? 0),
            ];
            $muestra = $stats['muestra'] ?? [];
            if (is_array($muestra) && $muestra !== []) {
                $this->line("→ {$clave}:");
                foreach ($muestra as $item) {
                    $this->line('    - '.$item);
                }
            }
        }

        $this->newLine();
        $this->table(
            ['Maestro', 'Fuente Anita', 'En Anita', 'Insertados', 'Ya en ERP'],
            $filas
        );

        $this->info($dryRun
            ? 'Simulación finalizada. Para persistir: sin --dry-run (pedir OK explícito en producción).'
            : 'Maestros sincronizados. Siguiente: php artisan proveedor:sincronizar-anita --dry-run');

        return self::SUCCESS;
    }
}

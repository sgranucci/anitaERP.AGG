<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Ventas\Ferli\VentasMaestrosAnitaFerliSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Sync exclusivo CALZADOS FERLI de maestros de ventas (PV, tipos, vendedor) desde bridge 254.
 */
class SincronizarVentasMaestrosDesdeAnitaFerli extends Command
{
    protected $signature = 'ventas:sincronizar-maestros-anita-ferli
                            {--path= : Path Anita (default ANITA_BDD_PATH /usr2/ferli)}
                            {--usuario= : ID usuario Auth (default: primer usuario)}
                            {--ejecutar : Persiste (sin este flag = dry-run)}';

    protected $description = 'CALZADOS FERLI: importa maestros de ventas (sucursal→PV, t_comp→tipotransaccion, vendedor). Dry-run por defecto.';

    public function handle(VentasMaestrosAnitaFerliSyncService $sync): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->error('Este comando solo aplica a EMPRESA=Calzados Ferli.');

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

        $dryRun = ! (bool) $this->option('ejecutar');
        $path = $this->option('path');
        $path = is_string($path) && trim($path) !== '' ? trim($path) : null;

        $this->info($dryRun
            ? 'Simulación: maestros ventas Ferli (sin escribir)…'
            : 'Sincronizando maestros ventas Ferli…');

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
                (string) ($stats['actualizados'] ?? 0).($dryRun && isset($stats['actualizados']) ? ' (sim)' : ''),
                (string) ($stats['omitidos'] ?? 0),
            ];
            $muestra = $stats['muestra'] ?? [];
            if (is_array($muestra) && $muestra !== []) {
                $this->line("→ {$clave}:");
                foreach ($muestra as $item) {
                    $this->line('    - '.$item);
                }
            }
            foreach ($stats['errores'] ?? [] as $err) {
                $this->warn('  ! '.$err);
            }
        }

        $this->newLine();
        $this->table(
            ['Maestro', 'Fuente', 'En Anita', 'Insertados', 'Actualizados', 'Omitidos'],
            $filas
        );

        $this->info($dryRun
            ? 'Simulación finalizada. Para persistir: --ejecutar (pedir OK explícito en producción).'
            : 'Maestros de ventas sincronizados.');

        return self::SUCCESS;
    }
}

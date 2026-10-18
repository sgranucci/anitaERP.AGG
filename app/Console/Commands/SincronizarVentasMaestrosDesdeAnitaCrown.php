<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Ventas\Crown\VentasMaestrosAnitaCrownSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Sync exclusivo CROWN de maestros chicos de ventas (condmae, tipoemp, zonavta, expreso, t_comp).
 */
class SincronizarVentasMaestrosDesdeAnitaCrown extends Command
{
    protected $signature = 'ventas:sincronizar-maestros-anita-crown
                            {--path= : Path Anita (default ANITA_BDD_PATH /usr2/casino)}
                            {--usuario= : ID usuario Auth (default: primer usuario)}
                            {--tandas=1,2,3,4 : Tandas a correr (1 catálogos, 2 PV, 3 listas, 4 clientes)}
                            {--ejecutar : Persiste (sin este flag = dry-run)}';

    protected $description = 'CROWN: importa maestros de ventas desde Anita (tandas 1–4). Dry-run por defecto.';

    public function handle(VentasMaestrosAnitaCrownSyncService $sync): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->error('Este comando solo aplica a EMPRESA=CROWN.');

            return self::FAILURE;
        }

        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 0);

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        $dryRun = ! (bool) $this->option('ejecutar');
        $path = $this->option('path');
        $path = is_string($path) && trim($path) !== '' ? trim($path) : null;
        $tandasRaw = (string) ($this->option('tandas') ?? '1,2,3,4');
        $tandas = array_values(array_filter(array_map('intval', explode(',', $tandasRaw)), static fn ($n) => $n >= 1 && $n <= 4));
        if ($tandas === []) {
            $this->error('Tandas inválidas. Use p. ej. --tandas=2,3,4');

            return self::FAILURE;
        }

        $this->info($dryRun
            ? 'Simulación: maestros ventas Crown tandas '.implode(',', $tandas).' (sin escribir)…'
            : 'Sincronizando maestros ventas Crown tandas '.implode(',', $tandas).'…');

        try {
            $resultado = $sync->sincronizar($dryRun, $path, $tandas);
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
            ? 'Simulación finalizada. Para persistir: --ejecutar.'
            : 'Maestros de ventas sincronizados.');

        return self::SUCCESS;
    }
}

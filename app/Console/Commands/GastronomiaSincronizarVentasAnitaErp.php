<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Services\Ventas\Gastronomia\GastronomiaSincronizarVentasAnitaErpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class GastronomiaSincronizarVentasAnitaErp extends Command
{
    protected $signature = 'gastronomia:sincronizar-ventas-anita-erp
                            {--fecha-desde= : Fecha jornada inicial Y-m-d}
                            {--fecha-hasta= : Fecha jornada final Y-m-d (opcional)}
                            {--empresa=1 : empresa_id}
                            {--puntoventa= : Código PV opcional (ej. 00020)}
                            {--usuario= : usuario_id (default: primer usuario)}
                            {--dry-run : Simula sin escribir}';

    protected $description = 'Sincroniza venta Anita ↔ ERP gastronomía: replica faltantes ERP→Anita e importa/vincula solo Anita→ERP';

    public function handle(GastronomiaSincronizarVentasAnitaErpService $service): int
    {
        $fechaDesde = trim((string) $this->option('fecha-desde'));
        if ($fechaDesde === '') {
            $this->error('Indique --fecha-desde=Y-m-d');

            return self::FAILURE;
        }

        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        $fechaHasta = $fechaHasta !== '' ? $fechaHasta : null;
        $empresaId = (int) $this->option('empresa');
        $pv = $this->option('puntoventa');
        $pv = is_string($pv) && trim($pv) !== '' ? trim($pv) : null;
        $dryRun = (bool) $this->option('dry-run');

        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Empresa %d | jornada %s%s | PV %s%s',
            $empresaId,
            $fechaDesde,
            $fechaHasta !== null ? ' → '.$fechaHasta : '',
            $pv ?? 'todos',
            $dryRun ? ' | MODO SIMULACIÓN' : '',
        ));

        try {
            $resultado = $service->sincronizar($fechaDesde, $fechaHasta, $empresaId, $usuarioId, $pv, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $erpAnita = $resultado['erp_anita'] ?? [];
        $anitaErp = $resultado['anita_erp'] ?? [];

        $this->newLine();
        $this->info('ERP → Anita (replicar cabeceras faltantes)');
        $this->table(['Concepto', 'Valor'], [
            ['Combinaciones PV/jornada', (string) ($resultado['combinaciones'] ?? 0)],
            ['Faltantes ERP detectados', (string) ($erpAnita['faltantes'] ?? 0)],
            ['Replicadas OK', (string) ($erpAnita['replicadas'] ?? 0)],
            ['Simuladas', (string) count($erpAnita['detalle'] ?? [])],
        ]);

        $this->newLine();
        $this->info('Anita → ERP (importar / vincular emisión gastronomía)');
        $this->table(['Concepto', 'Valor'], [
            ['Solo Anita detectados', (string) ($anitaErp['solo_anita_detectados'] ?? 0)],
            ['Importados', (string) ($anitaErp['importados'] ?? 0)],
            ['Vinculados (venta ERP existente)', (string) ($anitaErp['vinculados'] ?? 0)],
            ['Omitidos', (string) ($anitaErp['omitidos'] ?? 0)],
        ]);

        $detalleAnita = $anitaErp['detalle'] ?? [];
        if ($detalleAnita !== []) {
            $this->newLine();
            $this->comment('Detalle Anita → ERP');
            $this->table(
                ['Estado', 'PV', 'Número', 'Jornada', 'Clave', 'Total Anita'],
                array_map(static fn (array $f) => [
                    $f['estado'] ?? '',
                    $f['pv'] ?? '',
                    $f['numero'] ?? '',
                    $f['fecha_jornada'] ?? '',
                    $f['clave'] ?? '',
                    isset($f['total_anita']) ? number_format((float) $f['total_anita'], 2, '.', '') : '—',
                ], $detalleAnita),
            );
        }

        $detalleErp = $erpAnita['detalle'] ?? [];
        if ($detalleErp !== []) {
            $this->newLine();
            $this->comment('Detalle ERP → Anita');
            $this->table(
                ['Estado', 'Comprobante', 'PV', 'Jornada', 'Total'],
                array_map(static fn (array $f) => [
                    $f['estado'] ?? '',
                    $f['codigo'] ?? '',
                    $f['puntoventa'] ?? '',
                    $f['fecha_jornada'] ?? '',
                    isset($f['total']) ? number_format((float) $f['total'], 2, '.', '') : '—',
                ], $detalleErp),
            );
        }

        foreach ($resultado['errores'] ?? [] as $err) {
            $this->warn((string) $err);
        }

        if (($resultado['errores'] ?? []) !== []) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Simulación completada. Ejecute sin --dry-run para aplicar.');
        } else {
            $this->info('Sincronización completada.');
        }

        return self::SUCCESS;
    }
}

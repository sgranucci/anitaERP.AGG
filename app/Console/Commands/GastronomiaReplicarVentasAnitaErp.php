<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Services\Ventas\Gastronomia\GastronomiaReplicarVentasAnitaErpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class GastronomiaReplicarVentasAnitaErp extends Command
{
    protected $signature = 'gastronomia:replicar-ventas-anita-erp
                            {--fecha-desde=2026-06-01 : Fecha de jornada inicial Y-m-d}
                            {--fecha-hasta= : Fecha de jornada final Y-m-d (opcional)}
                            {--puntoventa= : Código PV CAE opcional (ej. 00003)}
                            {--empresa=1 : empresa_id}
                            {--usuario= : usuario_id para ven_usuario en Anita (default: primer usuario)}
                            {--limite=0 : Máximo de ventas a replicar (0 = sin límite)}
                            {--sin-insumos : No replicar stkmov de insumos por fórmula}
                            {--sin-stkmov : Solo cabecera venta + vencae (sin stkmov/compaux por ítem)}
                            {--dry-run : Simula sin escribir en Anita}';

    protected $description = 'Replica en Anita las ventas gastronomía del ERP sin cabecera en Informix (backfill por fecha de jornada)';

    public function handle(GastronomiaReplicarVentasAnitaErpService $service): int
    {
        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido para replicación Anita.');

            return self::FAILURE;
        }

        $fechaDesde = trim((string) $this->option('fecha-desde'));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        $fechaHasta = $fechaHasta !== '' ? $fechaHasta : null;
        $empresaId = (int) $this->option('empresa');
        $pv = $this->option('puntoventa');
        $pv = is_string($pv) && trim($pv) !== '' ? trim($pv) : null;
        $dryRun = (bool) $this->option('dry-run');
        $limite = max(0, (int) $this->option('limite'));
        $omitirStkmov = (bool) $this->option('sin-stkmov')
            || filter_var(config('gastronomia.anita_omitir_stkmov', true), FILTER_VALIDATE_BOOLEAN);
        $replicarInsumos = ! (bool) $this->option('sin-insumos') && ! $omitirStkmov;

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Empresa %d | jornada %s%s | PV %s%s%s%s',
            $empresaId,
            $fechaDesde,
            $fechaHasta !== null ? ' → '.$fechaHasta : ' → hoy',
            $pv ?? 'todos',
            $omitirStkmov ? ' | solo venta+vencae' : '',
            $dryRun ? ' | MODO SIMULACIÓN' : '',
            $limite > 0 ? ' | límite '.$limite : '',
        ));

        try {
            $resultado = $service->replicarFaltantes(
                $fechaDesde,
                $fechaHasta,
                $empresaId,
                $pv,
                $dryRun,
                $limite,
                $replicarInsumos,
                $omitirStkmov,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Resumen');
        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Combinaciones PV/jornada', (string) ($resultado['combinaciones'] ?? 0)],
                ['Faltantes detectados', (string) ($resultado['faltantes'] ?? 0)],
                ['Replicadas OK', (string) ($resultado['replicadas'] ?? 0)],
                ['Simuladas / detalle', (string) count($resultado['detalle'] ?? [])],
                ['Errores', (string) count($resultado['errores'] ?? [])],
            ],
        );

        $detalle = $resultado['detalle'] ?? [];
        if ($detalle !== []) {
            $this->newLine();
            $this->comment('Detalle');
            $this->table(
                ['Estado', 'Comprobante', 'PV', 'Jornada', 'Total', 'Obs.'],
                array_map(static fn (array $fila) => [
                    $fila['estado'] ?? '',
                    $fila['codigo'] ?? '',
                    $fila['puntoventa'] ?? '',
                    $fila['fecha_jornada'] ?? '',
                    isset($fila['total']) ? number_format((float) $fila['total'], 2, '.', '') : '—',
                    $fila['mensaje'] ?? '',
                ], $detalle),
            );
        }

        if (($resultado['errores'] ?? []) !== []) {
            $this->newLine();
            $this->error('Hubo errores en una o más ventas (ver detalle arriba).');

            return self::FAILURE;
        }

        if (($resultado['faltantes'] ?? 0) === 0) {
            $this->info('No hay ventas ERP sin cabecera en Anita en el rango indicado.');
        } elseif ($dryRun) {
            $this->info('Simulación completada. Ejecute sin --dry-run para replicar.');
        } else {
            $this->info('Replicación completada.');
        }

        return self::SUCCESS;
    }
}

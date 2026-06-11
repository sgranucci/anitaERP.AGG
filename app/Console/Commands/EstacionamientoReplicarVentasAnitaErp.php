<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Venta;
use App\Services\Caja\Estacionamiento\EstacionamientoReplicarVentasAnitaErpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class EstacionamientoReplicarVentasAnitaErp extends Command
{
    protected $signature = 'estacionamiento:replicar-ventas-anita-erp
                            {--fecha-desde=2026-06-01 : Fecha de jornada inicial Y-m-d}
                            {--fecha-hasta= : Fecha de jornada final Y-m-d (opcional)}
                            {--puntoventa= : Código PV CAE opcional (ej. 00050)}
                            {--venta-id= : Replicar una venta puntual por id (ignora rango si se indica)}
                            {--empresa=1 : empresa_id}
                            {--usuario= : usuario_id para ven_usuario en Anita (default: primer usuario)}
                            {--limite=0 : Máximo de ventas a replicar (0 = sin límite)}
                            {--dry-run : Simula sin escribir en Anita}';

    protected $description = 'Replica en Anita las ventas estacionamiento del ERP sin cabecera en Informix (backfill por fecha de jornada)';

    public function handle(EstacionamientoReplicarVentasAnitaErpService $service): int
    {
        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido para replicación Anita.');

            return self::FAILURE;
        }

        $ventaId = (int) $this->option('venta-id');
        $dryRun = (bool) $this->option('dry-run');

        $this->line('Bridge: '.ApiAnita::urlBridge());

        if ($ventaId > 0) {
            return $this->replicarVentaPuntual($service, $ventaId, $dryRun);
        }

        $fechaDesde = trim((string) $this->option('fecha-desde'));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        $fechaHasta = $fechaHasta !== '' ? $fechaHasta : null;
        $empresaId = (int) $this->option('empresa');
        $pv = $this->option('puntoventa');
        $pv = is_string($pv) && trim($pv) !== '' ? trim($pv) : null;
        $limite = max(0, (int) $this->option('limite'));

        $this->line(sprintf(
            'Empresa %d | jornada %s%s | PV %s%s%s',
            $empresaId,
            $fechaDesde,
            $fechaHasta !== null ? ' → '.$fechaHasta : ' → hoy',
            $pv ?? 'todos',
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
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->mostrarResumen($resultado, $dryRun);

        return ($resultado['errores'] ?? []) !== [] ? self::FAILURE : self::SUCCESS;
    }

    private function replicarVentaPuntual(
        EstacionamientoReplicarVentasAnitaErpService $service,
        int $ventaId,
        bool $dryRun,
    ): int {
        $venta = Venta::query()->find($ventaId);
        if (! $venta) {
            $this->error('Venta #'.$ventaId.' no encontrada.');

            return self::FAILURE;
        }

        $this->line('Venta puntual: #'.$ventaId.' '.$venta->codigo.($dryRun ? ' | MODO SIMULACIÓN' : ''));

        if ($dryRun) {
            $this->info('Simulación OK (no se escribió en Anita).');

            return self::SUCCESS;
        }

        try {
            $service->replicarVenta($venta);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Venta replicada en Anita correctamente.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function mostrarResumen(array $resultado, bool $dryRun): void
    {
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

        if (($resultado['faltantes'] ?? 0) === 0) {
            $this->info('No hay ventas ERP sin cabecera en Anita en el rango indicado.');
        } elseif ($dryRun) {
            $this->info('Simulación completada. Ejecute sin --dry-run para replicar.');
        } else {
            $this->info('Replicación completada.');
        }
    }
}

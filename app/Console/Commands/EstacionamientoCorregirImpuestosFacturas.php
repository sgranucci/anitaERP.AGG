<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Services\Caja\Estacionamiento\EstacionamientoCorregirImpuestosFacturasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class EstacionamientoCorregirImpuestosFacturas extends Command
{
    protected $signature = 'estacionamiento:corregir-impuestos-facturas
                            {--fecha-desde=2026-06-22 : Fecha de jornada inicial Y-m-d}
                            {--fecha-hasta= : Fecha de jornada final Y-m-d (opcional)}
                            {--limite=0 : Máximo de ventas a corregir (0 = sin límite)}
                            {--sin-anita : No re-replicar en Informix tras corregir ERP}
                            {--dry-run : Simula sin modificar ERP ni Anita}';

    protected $description = 'Corrige venta_impuesto de facturas estacionamiento (Exento → IVA 21%) y re-replica en Anita';

    public function handle(EstacionamientoCorregirImpuestosFacturasService $service): int
    {
        $usuarioId = (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido para la corrección.');

            return self::FAILURE;
        }

        $fechaDesde = trim((string) $this->option('fecha-desde'));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        $fechaHasta = $fechaHasta !== '' ? $fechaHasta : null;
        $limite = max(0, (int) $this->option('limite'));
        $dryRun = (bool) $this->option('dry-run');
        $replicarAnita = ! (bool) $this->option('sin-anita');

        $this->line('Bridge Anita: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Jornada %s%s | gravado_id=%d | exento_id=%d%s%s',
            $fechaDesde,
            $fechaHasta !== null ? ' → '.$fechaHasta : ' → hoy',
            (int) config('estacionamiento.impuesto_gravado_id', 3),
            (int) config('estacionamiento.impuesto_exento_id', 1),
            $dryRun ? ' | MODO SIMULACIÓN' : '',
            $replicarAnita && ! $dryRun ? ' | re-replica Anita' : '',
        ));

        try {
            $resultado = $service->corregir(
                $fechaDesde,
                $fechaHasta,
                $dryRun,
                $replicarAnita,
                $limite,
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
                ['Candidatas (exento erróneo)', (string) ($resultado['candidatas'] ?? 0)],
                ['Corregidas OK', (string) ($resultado['corregidas'] ?? 0)],
                ['Omitidas cortesía ($0,01)', (string) ($resultado['omitidas_cortesia'] ?? 0)],
                ['Omitidas ya con IVA', (string) ($resultado['omitidas_ya_ok'] ?? 0)],
                ['Errores', (string) count($resultado['errores'] ?? [])],
            ],
        );

        $errores = $resultado['errores'] ?? [];
        if ($errores !== []) {
            $this->newLine();
            $this->error('Errores (primeros 20)');
            $this->table(
                ['Venta', 'Comprobante', 'Mensaje'],
                array_map(static fn (array $fila) => [
                    (string) ($fila['venta_id'] ?? ''),
                    (string) ($fila['codigo'] ?? ''),
                    (string) ($fila['mensaje'] ?? ''),
                ], array_slice($errores, 0, 20)),
            );
        }

        if ($dryRun) {
            $this->info('Simulación completada. Ejecute sin --dry-run para aplicar.');
        } elseif (($resultado['corregidas'] ?? 0) > 0) {
            $this->info('Corrección completada.');
        } else {
            $this->comment('No hubo ventas para corregir en el rango indicado.');
        }

        return $errores !== [] ? self::FAILURE : self::SUCCESS;
    }
}

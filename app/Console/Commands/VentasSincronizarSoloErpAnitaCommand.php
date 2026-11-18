<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Services\Ventas\VentaSincronizarSoloErpAnitaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Facturas solo en ERP (sin cabecera Anita) → sincronizar a Anita.
 * Nunca borra ventas del ERP.
 */
class VentasSincronizarSoloErpAnitaCommand extends Command
{
    protected $signature = 'ventas:sincronizar-solo-erp-anita
                            {--desde= : Fecha comprobante desde Y-m-d}
                            {--hasta= : Fecha comprobante hasta Y-m-d}
                            {--empresa=0 : Filtrar por empresa_id del PV (0 = todas)}
                            {--puntoventa= : IDs o códigos PV separados por coma}
                            {--venta-id= : IDs de venta separados por coma (acota el universo)}
                            {--limite=0 : Máximo a procesar (0 = sin límite)}
                            {--incluir-gastro-intencional : También replica POS en ventana solo-ERP}
                            {--usuario= : usuario_id para Anita (default: primer usuario)}
                            {--dry-run : Simula sin escribir en Anita}
                            {--ejecutar : Persiste la réplica en Anita}';

    protected $description = 'Sincroniza a Anita las ventas que están solo en ERP (nunca las borra)';

    public function handle(VentaSincronizarSoloErpAnitaService $service): int
    {
        $dryRun = (bool) $this->option('dry-run') || ! (bool) $this->option('ejecutar');
        if (! (bool) $this->option('dry-run') && ! (bool) $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar corre en simulación. Usá --dry-run o --ejecutar.');
        }

        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        if ($desde === '' || $hasta === '') {
            $this->error('Indique --desde=Y-m-d y --hasta=Y-m-d');

            return self::FAILURE;
        }

        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        $puntoventaIds = $this->parseIds((string) $this->option('puntoventa'));
        $ventaIds = $this->parseIds((string) $this->option('venta-id'));
        $limite = max(0, (int) $this->option('limite'));
        $empresaId = max(0, (int) $this->option('empresa'));
        $incluirGastro = (bool) $this->option('incluir-gastro-intencional');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Fecha %s → %s | empresa %s | PV %s | ventas %s%s%s%s',
            $desde,
            $hasta,
            $empresaId > 0 ? (string) $empresaId : 'todas',
            $puntoventaIds === [] ? 'todos' : implode(',', $puntoventaIds),
            $ventaIds === [] ? 'universo fecha' : implode(',', $ventaIds),
            $dryRun ? ' | SIMULACIÓN' : ' | EJECUTAR',
            $incluirGastro ? ' | incluye gastro intencional' : '',
            $limite > 0 ? ' | límite '.$limite : '',
        ));

        $resultado = $service->sincronizar(
            $desde,
            $hasta,
            $dryRun,
            $empresaId,
            $puntoventaIds,
            $ventaIds,
            $limite,
            $incluirGastro,
        );

        $this->newLine();
        $this->info('Resumen');
        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Solo ERP (candidatas)', (string) $resultado['candidatas']],
                ['Ya en Anita', (string) $resultado['ya_en_anita']],
                ['Omitidas (POS solo-ERP intencional)', (string) $resultado['omitidas_intencionales']],
                [$dryRun ? 'Simuladas a replicar' : 'Replicadas OK', (string) $resultado['replicadas']],
                ['Errores', (string) count($resultado['errores'])],
            ],
        );

        $detalle = $resultado['detalle'] ?? [];
        if ($detalle !== []) {
            $this->newLine();
            $this->comment('Detalle');
            $this->table(
                ['Estado', 'Código', 'Fecha', 'PV', 'Total', 'Obs.'],
                array_map(static fn (array $f) => [
                    $f['estado'] ?? '',
                    $f['codigo'] ?? '',
                    $f['fecha'] ?? '',
                    $f['pv'] ?? '',
                    isset($f['total']) ? number_format((float) $f['total'], 2, '.', '') : '—',
                    $f['mensaje'] ?? '',
                ], $detalle),
            );
        }

        if (($resultado['errores'] ?? []) !== []) {
            $this->error('Hubo errores (ver detalle).');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Simulación OK. Ejecutá con --ejecutar para grabar en Anita.');
        } else {
            $this->info('Sincronización completada. No se borró ninguna venta del ERP.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function parseIds(string $raw): array
    {
        $out = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (ctype_digit($part)) {
                $out[] = (int) $part;

                continue;
            }
            $codigoPad = str_pad(ltrim($part, '0'), 5, '0', STR_PAD_LEFT);
            $id = (int) \App\Models\Ventas\Puntoventa::query()
                ->where(static function ($q) use ($part, $codigoPad): void {
                    $q->where('codigo', $codigoPad)->orWhere('codigo', $part);
                })
                ->value('id');
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }
}

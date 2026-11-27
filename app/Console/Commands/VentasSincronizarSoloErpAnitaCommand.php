<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Services\Ventas\VentaSincronizarSoloErpAnitaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Facturas solo en ERP (sin cabecera Anita) → sincronizar a Anita.
 * Además verifica el asiento en `ctamov` y lo graba si falta o tiene la clave del comprobante en blanco.
 * Nunca borra ventas del ERP.
 *
 * Uso: primero --dry-run (muestra qué grabaría), después el mismo comando con --ejecutar.
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
                            {--sin-ctamov : No verifica ni graba el asiento en ctamov (solo cabecera/climov)}
                            {--usuario= : usuario_id para Anita (default: primer usuario)}
                            {--dry-run : Simula sin escribir en Anita}
                            {--ejecutar : Persiste en Anita la réplica y el asiento ctamov}';

    protected $description = 'Sincroniza a Anita las ventas solo-ERP y verifica/graba su asiento en ctamov (nunca borra ventas)';

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
        $verificarCtamov = ! (bool) $this->option('sin-ctamov');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Fecha %s → %s | empresa %s | PV %s | ventas %s%s%s%s%s',
            $desde,
            $hasta,
            $empresaId > 0 ? (string) $empresaId : 'todas',
            $puntoventaIds === [] ? 'todos' : implode(',', $puntoventaIds),
            $ventaIds === [] ? 'universo fecha' : implode(',', $ventaIds),
            $dryRun ? ' | SIMULACIÓN' : ' | EJECUTAR',
            $incluirGastro ? ' | incluye gastro intencional' : '',
            $limite > 0 ? ' | límite '.$limite : '',
            $verificarCtamov ? ' | verifica ctamov' : ' | sin ctamov',
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
            $verificarCtamov,
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
                ...($verificarCtamov ? [
                    ['ctamov: asiento OK', (string) ($resultado['ctamov_ok'] ?? 0)],
                    [$dryRun ? 'ctamov: asientos a grabar/reparar (simulado)' : 'ctamov: asientos grabados OK', (string) ($dryRun ? ($resultado['ctamov_a_grabar'] ?? 0) : ($resultado['ctamov_grabados'] ?? 0))],
                    ['ctamov: para revisar a mano (no se tocan)', (string) ($resultado['ctamov_revisar'] ?? 0)],
                ] : []),
                ['Errores', (string) count($resultado['errores'])],
            ],
        );

        $detalle = $resultado['detalle'] ?? [];
        if ($detalle !== []) {
            $this->newLine();
            $this->comment('Detalle');
            $this->table(
                ['Estado', 'Ctamov', 'Asiento', 'Código', 'Fecha', 'PV', 'Total', 'Obs.'],
                array_map(static fn (array $f) => [
                    $f['estado'] ?? '',
                    $f['ctamov'] ?? '—',
                    $f['asiento'] ?? '—',
                    $f['codigo'] ?? '',
                    $f['fecha'] ?? '',
                    $f['pv'] ?? '',
                    isset($f['total']) ? number_format((float) $f['total'], 2, '.', '') : '—',
                    $f['mensaje'] ?? '',
                ], $detalle),
            );
        }

        $pendientesCtamov = (int) ($resultado['ctamov_a_grabar'] ?? 0) - (int) ($resultado['ctamov_grabados'] ?? 0);
        if ($dryRun && $verificarCtamov && ($pendientesCtamov > 0 || (int) ($resultado['ctamov_revisar'] ?? 0) > 0 || (int) ($resultado['candidatas'] ?? 0) > 0)) {
            Log::warning('ventas.sincronizar_solo_erp_anita.pendientes', [
                'desde' => $desde,
                'hasta' => $hasta,
                'solo_erp' => (int) ($resultado['candidatas'] ?? 0),
                'ctamov_a_grabar' => $pendientesCtamov,
                'ctamov_revisar' => (int) ($resultado['ctamov_revisar'] ?? 0),
                'ventas' => array_values(array_map(
                    static fn (array $f): string => ($f['codigo'] ?? '').' ['.($f['ctamov'] ?? $f['estado'] ?? '').']',
                    array_filter($detalle, static function (array $f): bool {
                        $problemas = ['simulado_grabar', 'simulado_reparar_clave', 'clave_distinta', 'lineas_distintas', 'error_lectura'];

                        return in_array($f['ctamov'] ?? '', $problemas, true)
                            || ($f['estado'] ?? '') === 'simulado_replica';
                    })
                )),
            ]);
        }

        if (($resultado['errores'] ?? []) !== []) {
            $this->error('Hubo errores (ver detalle).');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Simulación OK. Para grabar, corré el mismo comando con --ejecutar en lugar de --dry-run (mismo rango y filtros).');
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

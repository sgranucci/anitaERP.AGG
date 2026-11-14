<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Ventas\TransferenciaCrown\TransferenciaCrownCompletarCaeYReplicarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Completa CAE en ERP (gastro desde vencae de sala) y replica Importación ARCA → Anita.
 */
class TransferenciaCrownCompletarCaeYReplicarCommand extends Command
{
    protected $signature = 'ventas:transferencia-crown-completar-cae-replicar
                            {--desde=2026-10-01 : Fecha jornada desde Y-m-d}
                            {--hasta=2026-10-31 : Fecha jornada hasta Y-m-d}
                            {--sucursal= : PV(s) separados por coma (ej. 106,134). Vacío = todos}
                            {--usuario= : usuario_id para Anita (default: primer usuario)}
                            {--limite=0 : Máximo de ventas a procesar por origen (0 = sin límite)}
                            {--dry-run : Simula sin escribir ERP ni Anita}
                            {--ejecutar : Persiste CAE y replica a Anita}';

    protected $description = 'Completa CAE de transferencias gastro (desde vencae sala) y replica Importación ARCA a Anita';

    public function handle(TransferenciaCrownCompletarCaeYReplicarService $service): int
    {
        $dryRun = (bool) $this->option('dry-run') || ! (bool) $this->option('ejecutar');
        if (! (bool) $this->option('dry-run') && ! (bool) $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar corre en simulación. Usá --dry-run explícito o --ejecutar para persistir.');
        }

        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        $limite = max(0, (int) $this->option('limite'));
        $sucursales = [];
        foreach (explode(',', (string) $this->option('sucursal')) as $s) {
            $s = (int) trim($s);
            if ($s > 0) {
                $sucursales[] = $s;
            }
        }

        $this->line(sprintf(
            'Jornada %s → %s | usuario %d | PV %s%s%s',
            $desde,
            $hasta,
            $usuarioId,
            $sucursales === [] ? 'todos' : implode(',', $sucursales),
            $dryRun ? ' | SIMULACIÓN' : ' | EJECUTAR',
            $limite > 0 ? ' | límite '.$limite : '',
        ));

        $resultado = $service->ejecutar($desde, $hasta, $dryRun, $usuarioId, $limite, $sucursales);

        $g = $resultado['gastro'];
        $a = $resultado['arca'];
        $this->newLine();
        $this->info('Gastro (completar CAE desde vencae sala)');
        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Candidatas sin CAE', (string) $g['candidatas']],
                ['CAE completados', (string) $g['cae_completados']],
                ['Sin vencae en Anita sala', (string) $g['cae_faltante_anita']],
                ['Errores', (string) count($g['errores'])],
            ],
        );

        $this->info('ARCA (replicar a Anita)');
        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Candidatas Importación ARCA', (string) $a['candidatas']],
                ['Vto CAE completados', (string) $a['vto_completados']],
                ['Replicadas / simuladas', (string) $a['replicadas']],
                ['Ya en Anita', (string) $a['omitidas_ya_en_anita']],
                ['Errores', (string) count($a['errores'])],
            ],
        );

        $errores = array_merge($g['errores'], $a['errores']);
        if ($errores !== []) {
            $this->newLine();
            $this->error('Errores');
            $this->table(
                ['Origen', 'Código', 'Msg'],
                array_map(static fn (array $e) => [
                    $e['origen'] ?? '',
                    $e['codigo'] ?? '',
                    mb_substr((string) ($e['mensaje'] ?? ''), 0, 120),
                ], array_slice($errores, 0, 40)),
            );
        }

        if ($dryRun) {
            $this->info('Simulación OK. Ejecutá con --ejecutar para persistir.');
        } else {
            $this->info('Listo.');
        }

        return $errores === [] ? self::SUCCESS : self::FAILURE;
    }
}

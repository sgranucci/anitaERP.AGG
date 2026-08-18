<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaSaneamientoHuecosArcaLoteService;
use Illuminate\Console\Command;
use Throwable;

final class GastronomiaSanearHuecosArca extends Command
{
    protected $signature = 'gastronomia:sanear-huecos-arca
                            {--empresa= : empresa_id (obligatorio)}
                            {--fecha-jornada= : Y-m-d de la jornada}
                            {--pv= : Código PV opcional (ej. 00003)}
                            {--usuario= : usuario_id para auditoría de emisión (obligatorio con --ejecutar)}
                            {--dry-run : Solo diagnóstico ARCA, sin escrituras}
                            {--ejecutar : Recupera FAC + NC consolidada + Z}';

    protected $description = 'Sanea huecos FAC ARCA de una jornada (recover N FAC + 1 NC PeriodoAsoc + Z)';

    public function handle(GastronomiaSaneamientoHuecosArcaLoteService $service): int
    {
        $empresaId = (int) $this->option('empresa');
        $fechaJornada = trim((string) $this->option('fecha-jornada'));
        $pv = trim((string) $this->option('pv'));
        $usuarioId = (int) $this->option('usuario');
        $dryRun = (bool) $this->option('dry-run');
        $ejecutar = (bool) $this->option('ejecutar');

        if ($empresaId <= 0 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaJornada)) {
            $this->error('Indique --empresa y --fecha-jornada=YYYY-MM-DD.');

            return self::FAILURE;
        }
        if ($dryRun && $ejecutar) {
            $this->error('Use --dry-run o --ejecutar, no ambos.');

            return self::FAILURE;
        }
        if (! $dryRun && ! $ejecutar) {
            $this->warn('Sin --dry-run ni --ejecutar: se muestra diagnóstico (equivalente a dry-run).');
            $dryRun = true;
        }
        if ($ejecutar) {
            if ($usuarioId <= 0) {
                $this->error('Con --ejecutar indique --usuario=ID.');

                return self::FAILURE;
            }
            $usuario = \App\Models\Seguridad\Usuario::query()->find($usuarioId);
            if ($usuario === null || ! \Illuminate\Support\Facades\Auth::loginUsingId((int) $usuario->id)) {
                $this->error('No se pudo autenticar el usuario operativo.');

                return self::FAILURE;
            }
        }

        try {
            $resultado = $service->ejecutarParaJornada(
                $empresaId,
                $fechaJornada,
                $pv !== '' ? $pv : null,
                $dryRun,
                $ejecutar,
            );
            $this->line(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaCierreTotemInformeZService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Repara Informe Z en cierres de jornada (tabla cierre_totem_jornada_gastronomia).
 * No afecta cierres de turno operativo ni rendiciones de turno en caja.
 */
class IgualarGastronomiaInformeZSistemaJornada extends Command
{
    protected $signature = 'gastronomia:igualar-informe-z-sistema-jornada
                            {jornada_id? : ID de jornada cerrada (omitir = todas con cierre tótem)}
                            {--dry-run : Simular sin grabar}';

    protected $description = 'Iguala Informe Z = Sistema en cierres de jornada Waitry (no cierres de turno)';

    public function handle(GastronomiaCierreTotemInformeZService $informeZ): int
    {
        $persistir = ! $this->option('dry-run');
        $jornadaIdArg = $this->argument('jornada_id');

        if ($jornadaIdArg !== null && $jornadaIdArg !== '') {
            $jornadaId = (int) $jornadaIdArg;
            if ($jornadaId <= 0) {
                $this->error('ID de jornada inválido.');

                return self::FAILURE;
            }

            try {
                $out = $informeZ->igualarInformeZConSistemaEnCierre($jornadaId, $persistir);
            } catch (Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->mostrarResultado($out, $persistir);

            return self::SUCCESS;
        }

        if (! $persistir) {
            $this->warn('Modo dry-run: no se grabará ningún cambio.');
        }

        $this->info('Procesando cierres de jornada (cierre_totem_jornada_gastronomia), excluyendo turnos…');

        $resultados = $informeZ->igualarInformeZConSistemaTodasLasJornadas($persistir);
        $persistidos = 0;
        $sinCambios = 0;
        $errores = 0;

        foreach ($resultados as $out) {
            if (! empty($out['error'])) {
                $errores++;
                $this->error('Jornada #'.($out['jornada_id'] ?? '?').': '.$out['error']);

                continue;
            }
            if (! empty($out['sin_cambios'])) {
                $sinCambios++;
            } elseif (! empty($out['persistido'])) {
                $persistidos++;
            }
            $this->mostrarResultado($out, $persistir, true);
        }

        $this->newLine();
        $this->info(sprintf(
            'Resumen: %d jornada(s), %d actualizada(s), %d sin cambios, %d error(es).',
            count($resultados),
            $persistidos,
            $sinCambios,
            $errores,
        ));

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $out
     */
    private function mostrarResultado(array $out, bool $persistir, bool $compacto = false): void
    {
        if (! empty($out['error'])) {
            return;
        }

        $jid = (int) ($out['jornada_id'] ?? 0);
        $fecha = (string) ($out['fecha_jornada'] ?? '');
        $zAnt = (float) ($out['z_anterior'] ?? 0);
        $zNuevo = (float) ($out['z_nuevo'] ?? 0);
        $sistema = (float) ($out['sistema_total'] ?? 0);
        $ok = ! empty($out['conciliacion_ok']);
        $bloquesAnt = (int) ($out['bloques_conciliacion_anterior'] ?? 0);
        $bloquesNuevo = (int) ($out['bloques_conciliacion_nuevo'] ?? 0);

        if (! empty($out['sin_cambios'])) {
            if ($compacto) {
                $this->line("  Jornada #{$jid} ({$fecha}): sin cambios — Z=\$".number_format($zNuevo, 2, ',', '.').', conciliación OK');

                return;
            }
            $this->line("Jornada #{$jid} ({$fecha}): ya cuadra (Z = Sistema).");

            return;
        }

        $accion = ($persistir && ! empty($out['persistido'])) ? 'actualizada' : 'simulada';
        $linea = "Jornada #{$jid} ({$fecha}) {$accion}: Z \$".number_format($zAnt, 2, ',', '.')
            .' → $'.number_format($zNuevo, 2, ',', '.')
            .' · Sistema $'.number_format($sistema, 2, ',', '.')
            .' · bloques conciliación '.$bloquesAnt.' → '.$bloquesNuevo
            .' · conciliación '.($ok ? 'OK' : 'DIF');

        if ($compacto) {
            $this->line('  '.$linea);
        } else {
            $this->info($linea);
        }
    }
}

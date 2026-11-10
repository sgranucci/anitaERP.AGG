<?php

namespace App\Console\Commands;

use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Configuracion\Sala;
use App\Support\Caja\TesoreriaCrownJornadaCierreBloqueoSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;

class SanearCierreJornadaTesoreriaCrown extends Command
{
    protected $signature = 'tesoreria-crown:sanear-cierre-jornada
                            {jornada? : ID de la jornada}
                            {--sala= : Código de sala (ej. CIP)}
                            {--fecha= : Fecha Y-m-d (con --sala)}
                            {--cerrar-auditoria : Incluye en el plan pasar Auditoría → Cierre de auditoría}
                            {--ejecutar : Graba el plan de --cerrar-auditoria. Sin este flag solo diagnostica}';

    protected $description = 'Lista cajas que bloquean el cierre de una jornada Crown y, opcionalmente, cierra auditorías pendientes.';

    public function handle(): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->error('Solo corre en Crown.');

            return self::FAILURE;
        }

        $jornada = $this->resolverJornada();
        if ($jornada === null) {
            return self::FAILURE;
        }

        $jornada->load(['sala']);
        $this->info(sprintf(
            'Jornada %d — %s %s (%s)',
            $jornada->id,
            $jornada->sala->codigo ?? '?',
            $jornada->fecha->format('Y-m-d'),
            $jornada->estado
        ));

        $bloqueos = TesoreriaCrownJornadaCierreBloqueoSupport::deJornada($jornada);
        if ($bloqueos === []) {
            $this->info('Ninguna caja bloquea el cierre.');

            return self::SUCCESS;
        }

        $this->table(
            ['Asig', 'Caja', 'Cajero', 'Estado', 'Dif.', 'Comps', 'Acción', 'Sanear A→F'],
            array_map(fn (array $b) => [
                $b['asignacion_id'],
                $b['caja'],
                mb_substr($b['cajero'], 0, 28),
                $b['estado_txt'],
                $b['diferencia'] === null ? '' : number_format((float) $b['diferencia'], 2, ',', '.'),
                $b['comprobantes'],
                mb_substr($b['accion'], 0, 60),
                $b['sanear_auditoria'] ? 'sí' : '',
            ], $bloqueos)
        );
        $this->warn(sprintf('%d caja(s) bloquean el cierre de la jornada.', count($bloqueos)));

        if (! $this->option('cerrar-auditoria')) {
            $this->line('Para planear el cierre de auditorías: agregá --cerrar-auditoria (dry-run) y luego --ejecutar.');

            return self::SUCCESS;
        }

        $plan = TesoreriaCrownJornadaCierreBloqueoSupport::planCerrarAuditoria($bloqueos);
        if ($plan === []) {
            $this->warn('Ningún bloqueo está en Auditoría: no hay nada que sanear con --cerrar-auditoria.');

            return self::SUCCESS;
        }

        $this->table(
            ['Cierre', 'Asig', 'Caja', 'Diferencia'],
            array_map(fn (array $p) => [
                $p['cierre_id'],
                $p['asignacion_id'],
                $p['caja'],
                $p['diferencia'] === null ? '' : number_format((float) $p['diferencia'], 2, ',', '.'),
            ], $plan)
        );

        if (! $this->option('ejecutar')) {
            $this->warn('Dry-run: no se grabó nada. Para aplicar: --cerrar-auditoria --ejecutar');

            return self::SUCCESS;
        }

        $detalle = 'Saneamiento cierre jornada (consola)';
        $grabados = TesoreriaCrownJornadaCierreBloqueoSupport::aplicarCerrarAuditoria($plan, $detalle);
        $this->info("Auditorías cerradas: {$grabados}.");

        $restan = TesoreriaCrownJornadaCierreBloqueoSupport::deJornada($jornada->fresh());
        if ($restan === []) {
            $this->info('La jornada ya puede cerrarse desde la pantalla.');
        } else {
            $this->warn(sprintf('Todavía bloquean %d caja(s). Revisá el diagnóstico otra vez.', count($restan)));
        }

        return self::SUCCESS;
    }

    private function resolverJornada(): ?TesoreriaCrownJornada
    {
        $id = $this->argument('jornada');
        if ($id !== null && $id !== '') {
            $jornada = TesoreriaCrownJornada::query()->find((int) $id);
            if ($jornada === null) {
                $this->error("No existe la jornada {$id}.");

                return null;
            }

            return $jornada;
        }

        $salaCod = trim((string) $this->option('sala'));
        $fecha = trim((string) $this->option('fecha'));
        if ($salaCod === '' || $fecha === '') {
            $this->error('Indicá {jornada} o bien --sala=CODIGO --fecha=Y-m-d.');

            return null;
        }

        $sala = Sala::query()->where('codigo', $salaCod)->first();
        if ($sala === null) {
            $this->error("No existe la sala {$salaCod}.");

            return null;
        }

        $jornada = TesoreriaCrownJornada::query()
            ->where('sala_id', $sala->id)
            ->whereDate('fecha', $fecha)
            ->first();
        if ($jornada === null) {
            $this->error("No hay jornada de {$salaCod} en {$fecha}.");

            return null;
        }

        return $jornada;
    }
}

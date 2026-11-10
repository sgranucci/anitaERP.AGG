<?php

namespace App\Console\Commands;

use App\Mail\Caja\TesoreriaCrownAvisosResumen;
use App\Models\Caja\TesoreriaCrownAviso;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Services\Caja\TesoreriaCrownAvisoGenerador;
use App\Support\Caja\TesoreriaCrownAvisoSupport as Aviso;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class AvisosTesoreriaCrown extends Command
{
    protected $signature = 'tesoreria-crown:avisos
                            {--desde= : Primera jornada a revisar, Y-m-d (default: hace 7 días)}
                            {--hasta= : Última jornada a revisar, Y-m-d (default: hoy)}
                            {--anita : Compara además el día anterior contra Anita}
                            {--mail : Manda el resumen a los mails configurados}
                            {--ejecutar : Graba los avisos. Sin este flag solo muestra qué haría}';

    protected $description = 'Genera los avisos de auditoría de tesorería Crown (bandeja). Sin --ejecutar es dry-run.';

    public function handle(TesoreriaCrownAvisoGenerador $generador): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->warn('Solo corre en Crown.');

            return self::SUCCESS;
        }
        if (! Aviso::disponible()) {
            $this->error('Falta la tabla tesoreria_crown_aviso (migración 2026_11_09_180000).');

            return self::FAILURE;
        }
        $escribir = (bool) $this->option('ejecutar');
        $desde = trim((string) $this->option('desde')) ?: now()->subDays(7)->format('Y-m-d');
        $hasta = trim((string) $this->option('hasta')) ?: now()->format('Y-m-d');
        $inicio = now();

        $total = ['nuevos' => 0, 'actualizados' => 0, 'reabiertos' => 0, 'superados' => 0];
        $nuevos = [];
        $sumar = function (array $res) use (&$total, &$nuevos): void {
            foreach (array_keys($total) as $k) {
                $total[$k] += $res[$k];
            }
            array_push($nuevos, ...$res['nuevos_detalle']);
        };

        $jornadas = TesoreriaCrownJornada::query()
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('fecha')
            ->get();
        $candidatos = [];
        $alcances = [];
        foreach ($jornadas as $jornada) {
            array_push($candidatos, ...$generador->evaluarJornada($jornada));
            $alcances[] = TesoreriaCrownAvisoGenerador::alcanceJornada($jornada);
        }
        if ($alcances !== []) {
            $sumar($generador->aplicar($candidatos, $alcances, $escribir));
        }
        $sumar($generador->aplicar($generador->evaluarGlobal(), [TesoreriaCrownAvisoGenerador::alcanceGlobal()], $escribir));

        if ($this->option('anita')) {
            $ayer = now()->subDay()->format('Y-m-d');
            try {
                $sumar($generador->aplicar(
                    $generador->evaluarAnita($ayer, $ayer),
                    [TesoreriaCrownAvisoGenerador::alcanceAnita($ayer, $ayer)],
                    $escribir
                ));
            } catch (\Throwable $e) {
                $this->warn('Control contra Anita: '.$e->getMessage());
            }
        }

        $this->info(sprintf(
            '%s %d jornada(s) %s → %s: %d nuevos, %d actualizados, %d reabiertos, %d superados.',
            $escribir ? 'Grabado.' : 'Dry-run (nada grabado).',
            $jornadas->count(), $desde, $hasta,
            $total['nuevos'], $total['actualizados'], $total['reabiertos'], $total['superados']
        ));
        if ($nuevos !== []) {
            $this->table(['Fecha', 'Sev.', 'Regla', 'Aviso'], array_map(fn (array $a) => [
                $a['fecha'] ?? '',
                $a['severidad'],
                $a['regla'],
                mb_substr($a['titulo'], 0, 90),
            ], $nuevos));
        }

        if ($this->option('mail')) {
            $this->mandarResumen($escribir, $inicio, $nuevos);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $nuevosDryRun
     */
    private function mandarResumen(bool $escribir, $inicio, array $nuevosDryRun): void
    {
        $mails = Aviso::mails();
        if ($mails === []) {
            $this->line('Sin mails configurados: no se manda resumen.');

            return;
        }
        $nuevos = $escribir
            ? TesoreriaCrownAviso::query()
                ->where('estado', Aviso::ABIERTO)
                ->where('created_at', '>=', $inicio->copy()->subDay())
                ->orderByRaw("FIELD(severidad, 'A', 'M', 'B')")
                ->orderBy('fecha')
                ->get()
                ->map(fn (TesoreriaCrownAviso $a) => [
                    'fecha' => $a->fecha?->format('Y-m-d'),
                    'severidad' => $a->severidad,
                    'regla' => $a->regla,
                    'titulo' => $a->titulo,
                    'importe' => $a->importe,
                ])->all()
            : $nuevosDryRun;
        $abiertos = TesoreriaCrownAviso::query()
            ->where('estado', Aviso::ABIERTO)
            ->selectRaw('severidad, COUNT(*) as n')
            ->groupBy('severidad')
            ->pluck('n', 'severidad')
            ->map(fn ($n) => (int) $n)
            ->all();
        if (! $escribir) {
            $this->line('Dry-run: el resumen iría a '.implode(', ', $mails).' con '.count($nuevos).' aviso(s) nuevo(s).');

            return;
        }
        Mail::to($mails)->send(new TesoreriaCrownAvisosResumen([
            'nuevos' => $nuevos,
            'abiertos' => $abiertos,
            'total_abiertos' => array_sum($abiertos),
            'url' => route('tesoreria_crown_aviso'),
        ]));
        $this->info('Resumen enviado a '.implode(', ', $mails).'.');
    }
}

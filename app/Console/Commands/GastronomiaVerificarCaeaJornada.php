<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Ventas\Gastronomia\GastronomiaCaeaJornadaVerificacionSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GastronomiaVerificarCaeaJornada extends Command
{
    protected $signature = 'gastronomia:verificar-caea-jornada
                            {--empresa= : ID empresa (default: todas las que tengan PV CAEA gastronomía)}
                            {--fecha= : Y-m-d fecha de jornada (default: hoy)}';

    protected $description = 'Verifica integridad de comprobantes CAEA de la jornada (número, emisión, PC, PV compartido). Complementa a los conciliadores de siempre. Solo lectura.';

    public function handle(GastronomiaCaeaJornadaVerificacionSupport $support): int
    {
        $fecha = trim((string) ($this->option('fecha') ?: Carbon::now()->toDateString()));
        $empresaOpt = trim((string) ($this->option('empresa') ?? ''));

        $empresas = $empresaOpt !== ''
            ? [(int) $empresaOpt]
            : DB::table('configuracion_puntoventa_gastronomia')
                ->whereNotNull('puntoventa_caea_id')
                ->distinct()
                ->pluck('empresa_id')
                ->map(static fn ($e): int => (int) $e)
                ->sort()
                ->values()
                ->all();

        if ($empresas === []) {
            $this->warn('No hay empresas con PV CAEA gastronomía configurado.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Verificación CAEA gastronomía — jornada %s', $fecha));
        $hayError = false;

        foreach ($empresas as $empresaId) {
            $r = $support->verificar($empresaId, $fecha);
            $vc = $r['ventas_caea'];

            $this->newLine();
            $this->info(sprintf('Empresa %d — %s (jornada %s)',
                $empresaId, $r['empresa_nombre'] ?: '¿?', $r['jornada_estado'] ?? 'sin jornada'));

            if ((int) $vc['total_cant'] === 0) {
                $this->line('  Sin comprobantes CAEA en la jornada.');

                continue;
            }

            $cv = $r['caea_vigente'];
            $this->line(sprintf('  CAEA vigente: %s',
                $cv ? sprintf('%s (per %s, %s..%s, informe=%s)', $cv['nro_caea'], $cv['periodo'], $cv['vig_desde'], $cv['vig_hasta'], $cv['informe_estado']) : 'NO CARGADO'));
            $this->line(sprintf('  CAEA totales: %d comprobantes  $%s  (sin nro: %d, informado pendiente: %d)',
                $vc['total_cant'], number_format((float) $vc['total_monto'], 2), $vc['sin_cae'], $vc['informado_pendiente']));
            $this->line(sprintf('  Gastronomía: %d fac $%s (NC %d $%s)  |  Estacionamiento (PV compartido): %d $%s  |  Huérfanas: %d',
                $vc['gastro']['cant'], number_format((float) $vc['gastro']['monto'], 2),
                $vc['gastro']['nc_cant'], number_format((float) $vc['gastro']['nc_monto'], 2),
                $vc['estacionamiento']['cant'], number_format((float) $vc['estacionamiento']['monto'], 2),
                count($vc['huerfanas'])));

            if ($r['por_pc_gastro'] !== []) {
                $filas = array_map(static fn ($p) => [
                    $p['identificador_pc'], $p['pv'], $p['cant'],
                    number_format((float) $p['monto'], 2),
                    $p['nc_cant'], number_format((float) $p['nc_monto'], 2),
                    number_format((float) ($p['monto'] - $p['nc_monto']), 2),
                ], $r['por_pc_gastro']);
                $this->table(['PC salón', 'PV CAEA', 'Fac', 'Bruto', 'NC', 'NC $', 'Neto (Z−NC)'], $filas);
            }

            foreach ($r['problemas'] as $p) {
                $msg = '  ['.$p['nivel'].'] '.$p['texto'];
                if ($p['nivel'] === 'ERROR') {
                    $this->error($msg);
                    $hayError = true;
                } else {
                    $this->warn($msg);
                }
            }

            if ($r['problemas'] === []) {
                $this->line('  <info>OK</info> — integridad CAEA sin observaciones.');
            }

            if (($r['jornada_estado'] ?? null) !== 'cerrada') {
                $this->comment('  Nota: jornada no cerrada. El Z por PC y el cuadre Z − NC final se asignan al presentar la jornada en caja; volver a correr luego para el cuadre definitivo (usar además los conciliadores de siempre).');
            }
        }

        return $hayError ? self::FAILURE : self::SUCCESS;
    }
}

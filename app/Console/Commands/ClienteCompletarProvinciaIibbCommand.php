<?php

namespace App\Console\Commands;

use App\Support\Ventas\ClienteProvinciaIibbCompletarSupport;
use Illuminate\Console\Command;

/**
 * Completa provincia de domicilio (solo vacía) y sede IIBB desde Anita.
 * Default: dry-run. --ejecutar persiste.
 */
class ClienteCompletarProvinciaIibbCommand extends Command
{
    protected $signature = 'cliente:completar-provincia-iibb
                            {--ejecutar : Persiste (sin esto solo informa)}';

    protected $description = 'Completa provincia_id vacía (calle Anita) y provincia_iibb_id (clim_zonamult). No pisa domicilio cargado.';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');

        $this->comment($ejecutar
            ? 'PERSISTIR: solo provincia_id / provincia_iibb_id vacíos.'
            : 'Dry-run: no se graba nada.');

        $this->info('Leyendo climae en Anita…');
        $analisis = ClienteProvinciaIibbCompletarSupport::analizar();

        $this->newLine();
        $this->info('Universo: ERP '.$analisis['erp'].' · Anita '.$analisis['anita'].' · ERP sin fila Anita '.$analisis['sin_anita']);

        $d = $analisis['domicilio'];
        $this->newLine();
        $this->info('Domicilio (provincia_id) — solo si está vacío');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Ya tenían provincia', $d['ya_tenian']],
                ['Se completarían', $d['candidatos']],
                ['  · por código Anita', $d['por_codigo_anita']],
                ['  · por texto Anita', $d['por_texto_anita']],
                ['  · por localidad ERP', $d['por_localidad']],
                ['  · activos entre esos', $d['activos']],
                ['Siguen sin fuente', $d['sin_fuente']],
            ]
        );

        $s = $analisis['sede'];
        $this->newLine();
        $this->info('Sede IIBB (provincia_iibb_id) — solo si está vacía, desde clim_zonamult');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Ya tenían sede', $s['ya_tenian']],
                ['Se completarían', $s['candidatos']],
                ['  · activos entre esos', $s['activos']],
                ['Zona 0 / sin Anita', $s['zona_cero']],
                ['Zona sin mapa', $s['zona_sin_mapa']],
            ]
        );

        $e = $analisis['entregas'];
        $this->newLine();
        $this->info('Entregas: heredar sede del cliente si la entrega no tiene');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Total entregas', $e['total']],
                ['Ya tenían jur. IIBB', $e['ya_tenian']],
                ['Se completarían', $e['candidatos']],
                ['Sin sede de cliente', $e['sin_sede_cliente']],
            ]
        );

        $this->newLine();
        $this->info('Domicilio y sede distintos (actual o propuesto): '.$analisis['diferencian']);
        $this->line('CSV: '.$analisis['csv']);

        if ($analisis['ejemplos_domicilio'] !== []) {
            $this->newLine();
            $this->comment('Ejemplos domicilio a completar');
            $this->table(
                ['Código', 'Nombre', 'Estado', 'Texto Anita', 'Fuente', 'Nueva'],
                array_map(static fn (array $r) => [
                    $r['codigo'], $r['nombre'], $r['estado'], $r['texto_anita'], $r['fuente_domicilio'], $r['domicilio_nuevo'],
                ], $analisis['ejemplos_domicilio'])
            );
        }

        if ($analisis['ejemplos_diff'] !== []) {
            $this->newLine();
            $this->comment('Ejemplos domicilio ≠ sede (no se pisa el domicilio)');
            $this->table(
                ['Código', 'Nombre', 'Texto Anita', 'Dom. nuevo/actual', 'Sede', 'Zona'],
                array_map(static fn (array $r) => [
                    $r['codigo'], $r['nombre'], $r['texto_anita'], $r['domicilio_erp'] ?: $r['domicilio_nuevo'], $r['sede_nueva'], $r['zonamult'],
                ], $analisis['ejemplos_diff'])
            );
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->warn('Nada persistido. Para grabar: php artisan cliente:completar-provincia-iibb --ejecutar');

            return self::SUCCESS;
        }

        $ok = ClienteProvinciaIibbCompletarSupport::persistir($analisis);
        $this->info('Grabado domicilio='.$ok['domicilio'].' sede='.$ok['sede'].' entregas='.$ok['entregas']);

        return self::SUCCESS;
    }
}

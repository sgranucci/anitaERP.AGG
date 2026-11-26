<?php

namespace App\Console\Commands;

use App\Services\Stock\UnidadmedidaUnificacionService;
use Illuminate\Console\Command;

class UnificarUnidadesMedidaCommand extends Command
{
    protected $signature = 'stock:unificar-unidades-medida
                            {--ejecutar : Persistir los cambios (sin esta opción solo informa)}';

    protected $description = 'Unifica unidades de medida duplicadas y corrige la unidad alternativa (Unidades <-> Kilos)';

    public function handle(UnidadmedidaUnificacionService $servicio): int
    {
        $plan = $servicio->plan();
        $ejecutar = (bool) $this->option('ejecutar');

        $this->info(($ejecutar ? '' : '[dry-run] ').'Unidades duplicadas a unificar: '.count($plan['duplicadas']));
        $this->table(['id', 'nombre', 'abrev.', 'código', 'pasa a', 'nombre destino'], array_map(fn ($d) => [
            $d['id'], $d['nombre'], $d['abreviatura'], $d['codigo'], $d['pasa_a'], $d['pasa_a_nombre'],
        ], $plan['duplicadas']));

        $this->info('Código externo que queda en cada unidad:');
        foreach ($plan['codigos'] as $id => $codigo) {
            $this->line("  unidad {$id} => {$codigo}");
        }

        $this->table(['tabla', 'remapea principal', 'remapea alternativa', 'corrige alternativa'], array_map(fn ($t) => [
            $t['tabla'], $t['remapear_principal'], $t['remapear_alternativa'], $t['corregir_alternativa'],
        ], $plan['tablas']));

        $this->warn('Artículos con alternativa que NO se tocan (principal distinta de Unidades/Kilos):');
        foreach ($servicio->casosNoCubiertos($plan) as $par => $n) {
            $this->line("  {$par}: {$n}");
        }

        if (! $ejecutar) {
            $this->comment('Dry-run: no se modificó nada. Use --ejecutar para aplicar.');

            return self::SUCCESS;
        }

        $res = $servicio->ejecutar($plan);
        $this->info('Aplicado. Estado previo en: '.$res['csv']);
        $this->table(['tabla', 'remapea principal', 'remapea alternativa', 'corrige alternativa'], array_map(fn ($t) => [
            $t['tabla'], $t['remapear_principal'], $t['remapear_alternativa'], $t['corregir_alternativa'],
        ], $res['tablas']));

        return self::SUCCESS;
    }
}

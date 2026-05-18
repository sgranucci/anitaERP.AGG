<?php

namespace App\Console\Commands;

use App\Models\Ventas\Puntoventa;
use Illuminate\Console\Command;

/**
 * Entre los 3 primeros puntos de venta (por id), por cada empresa_id distinta se toma el primero
 * como plantilla y se copian domicilio, país, provincia, localidad, CP, email y teléfono al resto
 * de puntos de venta de esa misma empresa.
 */
class PuntoventaCopiarUbicacionDesdePrimerosTres extends Command
{
    protected $signature = 'puntoventa:copiar-ubicacion-desde-primeros-tres
                            {--dry-run : Mostrar qué se haría sin actualizar la base}';

    protected $description = 'Copia domicilio, país, provincia, localidad, CP, email y teléfono desde la plantilla (1er PV por empresa entre los 3 primeros por id) al resto de la misma empresa.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $primerosTres = Puntoventa::query()->orderBy('id')->limit(3)->get();
        if ($primerosTres->isEmpty()) {
            $this->warn('No hay puntos de venta.');

            return self::FAILURE;
        }

        $plantillaPorEmpresaId = [];
        foreach ($primerosTres as $pv) {
            if (! array_key_exists($pv->empresa_id, $plantillaPorEmpresaId)) {
                $plantillaPorEmpresaId[$pv->empresa_id] = $pv;
            }
        }

        $totalActualizados = 0;

        foreach ($plantillaPorEmpresaId as $empresaId => $plantilla) {
            $idsDestino = Puntoventa::query()
                ->where('empresa_id', $empresaId)
                ->where('id', '!=', $plantilla->id)
                ->orderBy('id')
                ->pluck('id');

            if ($idsDestino->isEmpty()) {
                $this->line("Empresa {$empresaId}: plantilla id={$plantilla->id}; sin otros registros.");

                continue;
            }

            $payload = [
                'domicilio' => $plantilla->domicilio,
                'pais_id' => $plantilla->pais_id,
                'provincia_id' => $plantilla->provincia_id,
                'localidad_id' => $plantilla->localidad_id,
                'codigopostal' => $plantilla->codigopostal,
                'email' => $plantilla->email,
                'telefono' => $plantilla->telefono,
            ];

            $this->info("Empresa {$empresaId}: plantilla puntoventa id={$plantilla->id} (codigo {$plantilla->codigo}) → ".count($idsDestino).' registro(s).');

            if ($dry) {
                $this->table(
                    ['Campo', 'Valor plantilla'],
                    collect($payload)->map(fn ($v, $k) => [$k, (string) ($v ?? '')])->values()->all()
                );
                $this->line('IDs destino: '.$idsDestino->implode(', '));
                $totalActualizados += $idsDestino->count();

                continue;
            }

            $n = Puntoventa::query()
                ->where('empresa_id', $empresaId)
                ->where('id', '!=', $plantilla->id)
                ->update($payload);

            $totalActualizados += $n;
            $this->line("  Actualizados: {$n}");
        }

        $empresasConPlantilla = array_keys($plantillaPorEmpresaId);
        $sinPlantilla = Puntoventa::query()
            ->distinct()
            ->whereNotIn('empresa_id', $empresasConPlantilla)
            ->pluck('empresa_id');
        if ($sinPlantilla->isNotEmpty()) {
            $this->warn('Empresa(s) sin ningún PV entre los 3 primeros por id (no se modificaron): '.$sinPlantilla->sort()->implode(', '));
        }

        if ($dry) {
            $this->warn('Modo dry-run: no se escribió nada. Quite --dry-run para aplicar.');
        } else {
            $this->info("Listo. Filas actualizadas en total: {$totalActualizados}.");
        }

        return self::SUCCESS;
    }
}

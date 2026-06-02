<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Caja\RendicionGastronomiaCaja;
use App\Services\Caja\RendicionGastronomiaAnitaSyncService;
use Illuminate\Console\Command;

class RendicionGastronomiaResincronizarAnita extends Command
{
    protected $signature = 'rendicion-gastronomia:resincronizar-anita
                            {--rendicion= : ID rendicion_gastronomia_caja (ej. 27)}
                            {--turno= : turno_operativo_gastronomia_id (usa la rendición de turno asociada)}
                            {--solo-limpiar : Solo elimina duplicados en Anita, sin regrabar valores}';

    protected $description = 'Limpia rendgastro/rendvalor huérfanos por turno en Informix y re-sincroniza (sin editar desde Caja)';

    public function handle(RendicionGastronomiaAnitaSyncService $syncService): int
    {
        $rendicionId = (int) $this->option('rendicion');
        $turnoId = (int) $this->option('turno');

        if ($rendicionId <= 0 && $turnoId <= 0) {
            $this->error('Indique --rendicion=ID o --turno=ID del turno operativo.');

            return self::FAILURE;
        }

        $rendicion = $this->resolverRendicion($rendicionId, $turnoId);
        if ($rendicion === null) {
            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line('Rendición ERP #'.$rendicion->id.' | turno operativo #'.$rendicion->turno_operativo_gastronomia_id
            .' | código Anita '.($rendicion->codigo ?: '—'));

        try {
            $resultado = $syncService->limpiarHuerfanosYResincronizar(
                $rendicion,
                ! $this->option('solo-limpiar'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Concepto', 'Valor'],
            [
                ['nro_oper en Anita (antes)', implode(', ', $resultado['nro_oper_antes']) ?: '—'],
                ['nro_oper eliminados (huérfanos)', implode(', ', $resultado['eliminados']) ?: '—'],
                ['nro_oper canónico', (string) $resultado['nro_oper_canonico']],
                ['nro_oper en Anita (después)', implode(', ', $resultado['nro_oper_despues']) ?: '—'],
                ['Re-sincronizado', $resultado['resincronizado'] ? 'sí' : 'no (solo limpieza)'],
            ],
        );

        if ($resultado['eliminados'] === [] && count($resultado['nro_oper_despues']) <= 1) {
            $this->info('Sin duplicados detectados para ese turno.');
        } else {
            $this->info('Limpieza completada.');
        }

        return self::SUCCESS;
    }

    private function resolverRendicion(int $rendicionId, int $turnoId): ?RendicionGastronomiaCaja
    {
        if ($rendicionId > 0) {
            $rendicion = RendicionGastronomiaCaja::query()
                ->where('tipo', RendicionGastronomiaCaja::TIPO_TURNO)
                ->find($rendicionId);

            if ($rendicion === null) {
                $this->error('No existe rendición de turno #'.$rendicionId.'.');

                return null;
            }

            return $rendicion;
        }

        $rendicion = RendicionGastronomiaCaja::query()
            ->where('tipo', RendicionGastronomiaCaja::TIPO_TURNO)
            ->where('turno_operativo_gastronomia_id', $turnoId)
            ->orderByDesc('id')
            ->first();

        if ($rendicion === null) {
            $this->error('No hay rendición de caja para el turno operativo #'.$turnoId.'.');

            return null;
        }

        $this->comment('Usando rendición #'.$rendicion->id.' del turno #'.$turnoId.'.');

        return $rendicion;
    }
}

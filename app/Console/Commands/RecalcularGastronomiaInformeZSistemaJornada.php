<?php

namespace App\Console\Commands;

use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Services\Ventas\Gastronomia\GastronomiaCierreTotemInformeZService;
use Illuminate\Console\Command;
use Throwable;

class RecalcularGastronomiaInformeZSistemaJornada extends Command
{
    protected $signature = 'gastronomia:recalcular-informe-z-sistema
                            {jornada_id? : ID jornada (default: última cerrada con cierre tótem)}';

    protected $description = 'Recalcula columna Sistema del Informe Z en un cierre tótem ya guardado (reconsulta Waitry, conserva Z ingresado)';

    public function handle(GastronomiaCierreTotemInformeZService $informeZ): int
    {
        $jornadaId = $this->argument('jornada_id');
        if ($jornadaId === null || $jornadaId === '') {
            $jornadaId = CierreTotemJornadaGastronomia::query()
                ->join('jornada_gastronomia', 'jornada_gastronomia.id', '=', 'cierre_totem_jornada_gastronomia.jornada_gastronomia_id')
                ->where('jornada_gastronomia.estado', JornadaGastronomia::ESTADO_CERRADA)
                ->orderByDesc('cierre_totem_jornada_gastronomia.id')
                ->value('jornada_gastronomia_id');
        }

        $jornadaId = (int) $jornadaId;
        if ($jornadaId <= 0) {
            $this->error('No hay jornada con cierre tótem.');

            return self::FAILURE;
        }

        try {
            $out = $informeZ->recalcularSistemaInformeZEnCierre($jornadaId);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Jornada #'.$jornadaId.' · cierre tótem #'.($out['cierre_totem_id'] ?? '—'));
        $this->line('Sistema anterior: $'.number_format((float) ($out['sistema_anterior'] ?? 0), 2, ',', '.'));
        $this->line('Sistema nuevo:    $'.number_format((float) ($out['sistema_nuevo'] ?? 0), 2, ',', '.'));
        $this->line('Z ingresado:      $'.number_format((float) ($out['z_ingresado'] ?? 0), 2, ',', '.'));
        $this->line('Órdenes Posnet tótem: '.(int) ($out['cantidad_ordenes'] ?? 0));
        $this->line('Conciliación OK: '.(! empty($out['conciliacion_ok']) ? 'sí' : 'no'));

        foreach ($out['por_totem'] ?? [] as $bloque) {
            $this->line(sprintf(
                '  %s (#%s): $%s · %d comandas',
                $bloque['ubicacion'] ?? 'Tótem',
                $bloque['totem_id'] ?? '—',
                number_format((float) ($bloque['total_ingreso'] ?? 0), 2, ',', '.'),
                (int) ($bloque['cantidad_ordenes'] ?? 0),
            ));
        }

        return self::SUCCESS;
    }
}

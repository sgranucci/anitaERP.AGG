<?php

namespace App\Console\Commands\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Services\Compras\OrdencompraLegajoAutorizadoNotificacionService;
use App\Support\Compras\OrdencompraLegajoGastronomiaSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnviarRecordatorioLegajoGastronomia extends Command
{
    protected $signature = 'compras:recordatorio-legajo-gastronomia
                            {--dias= : Días en Gastronomía para avisar (default: config compras.legajo.recordatorio_dias)}
                            {--simular : Lista sin enviar mails}';

    protected $description = 'Avisa a Compras los legajos que llevan N días o más en Gastronomía sin autorización.';

    public function handle(OrdencompraLegajoAutorizadoNotificacionService $notificacion): int
    {
        if (! (bool) config('compras.legajo.recordatorio_habilitado', true) && ! $this->option('simular')) {
            $this->info('Recordatorio deshabilitado (compras.legajo.recordatorio_habilitado).');

            return self::SUCCESS;
        }

        $sectorId = OrdencompraLegajoGastronomiaSupport::sectorGastronomiaId();
        if ($sectorId <= 0) {
            $this->warn('No existe el sector GASTRONOMIA.');

            return self::SUCCESS;
        }

        $umbral = $this->option('dias');
        $diasMin = $umbral !== null && $umbral !== ''
            ? max(1, (int) $umbral)
            : max(1, (int) config('compras.legajo.recordatorio_dias', 3));

        $ocs = Ordencompra::query()
            ->with(['proveedores:id,nombre', 'empresas:id,nombre'])
            ->where('sector_legajocompra_id', $sectorId)
            ->orderBy('id')
            ->get();

        $this->line('OC en Gastronomía: '.$ocs->count().' (umbral '.$diasMin.' días)');
        if ($ocs->isEmpty()) {
            return self::SUCCESS;
        }

        $pendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'), true)
        ]['nombre'] ?? 'Pendiente';

        $enviados = 0;
        foreach ($ocs as $oc) {
            $hist = Ordencompra_Historia::query()
                ->where('ordencompra_id', $oc->id)
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->value('fecha');
            $desde = $hist ? Carbon::parse($hist) : ($oc->created_at ? Carbon::parse($oc->created_at) : null);
            $dias = OrdencompraLegajoGastronomiaSupport::diasEnUbicacion($desde);
            if ($dias < $diasMin) {
                continue;
            }

            $referente = (string) (Arbolaprobacion_Movimiento::query()
                ->with('destinatariousuarios:id,nombre')
                ->where('ordencompra_id', $oc->id)
                ->where('circuito_oc', OrdencompraLegajoGastronomiaSupport::CIRCUITO_SECTOR)
                ->where('estado', $pendiente)
                ->orderByDesc('id')
                ->first()
                ?->destinatariousuarios
                ?->nombre ?? '');

            $this->line(sprintf(
                'OC %s — %s días — %s — referente %s',
                $oc->numeroordencompra,
                $dias,
                $oc->proveedores->nombre ?? '',
                $referente !== '' ? $referente : '—'
            ));

            if ($this->option('simular')) {
                continue;
            }

            $mails = $notificacion->notificarRecordatorio($oc, $dias, $referente);
            if ($mails !== []) {
                $enviados++;
            }
        }

        if ($this->option('simular')) {
            $this->info('Simulación: no se enviaron avisos.');

            return self::SUCCESS;
        }

        $this->info('Mails enviados: '.$enviados);

        return self::SUCCESS;
    }
}

<?php

namespace App\Services\Caja\Flash;

use App\Exports\Caja\Flash\FlashCrownPeriodoExport;
use App\Mail\Caja\Flash\FlashCrownDistribucion;
use App\Models\Caja\Flash\FlashCrown;
use App\Models\Caja\Flash\FlashCrownSuscripcion;
use App\Models\Configuracion\Sala;
use App\Support\Caja\Flash\FlashCrownPerfilVistaSupport;
use App\Support\Caja\Flash\FlashCrownSuscripcionSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class FlashCrownDistribucionService
{
    public function __construct(
        private FlashCrownSuscripcionSupport $suscripcionSupport,
    ) {}

    /**
     * @return array{estado: string, mensaje: string, destinatarios: list<string>, dias: int}
     */
    public function enviar(FlashCrownSuscripcion $suscripcion, bool $dryRun = false, ?Carbon $ahora = null): array
    {
        $ahora = $ahora ?? Carbon::now();
        $destinatarios = $this->suscripcionSupport->destinatariosResueltos($suscripcion);
        if ($destinatarios === []) {
            return $this->resultado(FlashCrownSuscripcion::ESTADO_ERROR, 'Sin destinatarios válidos.', []);
        }

        $periodo = $this->suscripcionSupport->periodoEfectivo($suscripcion, $ahora);
        $filas = FlashCrown::query()
            ->with(['sala', 'empresa'])
            ->whereDate('fecha', '>=', $periodo['desde']->toDateString())
            ->whereDate('fecha', '<=', $periodo['hasta']->toDateString())
            ->when($suscripcion->solo_validados, fn ($q) => $q->where('validado', true))
            ->whereIn('sala_id', Sala::query()->where('codigo', '!=', 'AC')->pluck('id'))
            ->orderBy('fecha')
            ->orderBy('sala_id')
            ->get();

        $dias = $filas->pluck('fecha')->map(fn ($f) => $f->format('Y-m-d'))->unique()->count();
        if ($dias === 0) {
            $msg = $suscripcion->solo_validados
                ? 'No hay flash Crown validados en el período; no se envió nada.'
                : 'No hay flash Crown en el período; no se envió nada.';

            return $this->resultado(FlashCrownSuscripcion::ESTADO_OMITIDA, $msg, $destinatarios, 0);
        }

        $perfil = FlashCrownPerfilVistaSupport::normalizar($suscripcion->perfil_vista ?? null);
        $nombre = sprintf(
            'flash_crown_%s_%s.xlsx',
            $periodo['desde']->format('Ymd'),
            $periodo['hasta']->format('Ymd')
        );
        $path = storage_path('app/tmp/'.$nombre);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        try {
            Excel::store(
                new FlashCrownPeriodoExport($filas, $periodo['desde'], $periodo['hasta'], $perfil),
                'tmp/'.$nombre
            );
        } catch (\Throwable $e) {
            Log::error('Flash Crown: no se pudo generar Excel', ['error' => $e->getMessage()]);

            return $this->resultado(
                FlashCrownSuscripcion::ESTADO_ERROR,
                'No se pudo armar el Excel: '.$e->getMessage(),
                $destinatarios,
                $dias
            );
        }

        if ($dryRun) {
            @unlink($path);

            return $this->resultado(
                FlashCrownSuscripcion::ESTADO_OK,
                sprintf('Simulación: %d día(s), iría a %s.', $dias, implode(', ', $destinatarios)),
                $destinatarios,
                $dias
            );
        }

        try {
            Mail::to($destinatarios)->send(new FlashCrownDistribucion(
                $suscripcion,
                $periodo['desde'],
                $periodo['hasta'],
                [
                    'path' => $path,
                    'nombre' => $nombre,
                    'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'dias' => $dias,
                ]
            ));
        } catch (\Throwable $e) {
            @unlink($path);

            return $this->resultado(
                FlashCrownSuscripcion::ESTADO_ERROR,
                'Error al enviar mail: '.$e->getMessage(),
                $destinatarios,
                $dias
            );
        }

        @unlink($path);
        $this->suscripcionSupport->marcarResultado(
            $suscripcion,
            FlashCrownSuscripcion::ESTADO_OK,
            sprintf('Enviado a %s (%d día(s)).', implode(', ', $destinatarios), $dias)
        );

        return $this->resultado(
            FlashCrownSuscripcion::ESTADO_OK,
            sprintf('Enviado a %s (%d día(s)).', implode(', ', $destinatarios), $dias),
            $destinatarios,
            $dias
        );
    }

    /**
     * @param  list<string>  $destinatarios
     * @return array{estado: string, mensaje: string, destinatarios: list<string>, dias: int}
     */
    private function resultado(string $estado, string $mensaje, array $destinatarios, int $dias = 0): array
    {
        return [
            'estado' => $estado,
            'mensaje' => $mensaje,
            'destinatarios' => $destinatarios,
            'dias' => $dias,
        ];
    }
}

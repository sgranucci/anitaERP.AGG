<?php

namespace App\Services\Caja\Flash;

use App\Mail\Caja\Flash\FlashReporteAggDistribucion;
use App\Models\Caja\Flash\FlashReporteSuscripcion;
use App\Support\Caja\Flash\FlashReporteAggSuscripcionSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FlashReporteAggDistribucionService
{
    public function __construct(
        private FlashReporteAggExcelService $excelService,
        private FlashReporteAggSuscripcionSupport $suscripcionSupport,
    ) {}

    /**
     * @return array{estado: string, mensaje: string, destinatarios: list<string>, dias: int, adjuntos: list<string>}
     */
    public function enviar(FlashReporteSuscripcion $suscripcion, bool $dryRun = false, ?Carbon $ahora = null): array
    {
        $ahora = $ahora ?? Carbon::now();
        $destinatarios = $this->suscripcionSupport->destinatariosResueltos($suscripcion);
        if ($destinatarios === []) {
            return $this->resultado(
                FlashReporteSuscripcion::ESTADO_ERROR,
                'Sin destinatarios válidos: revisá los mails del envío.',
                []
            );
        }

        $periodo = $this->suscripcionSupport->periodoEfectivo($suscripcion, $ahora);

        try {
            $archivo = $this->excelService->generar($periodo['desde'], $periodo['hasta']);
        } catch (\Throwable $e) {
            Log::error('Flash Report AGG: no se pudo generar el Excel', [
                'suscripcion_id' => $suscripcion->id,
                'error' => $e->getMessage(),
            ]);

            return $this->resultado(
                FlashReporteSuscripcion::ESTADO_ERROR,
                'No se pudo armar el Excel: '.$e->getMessage(),
                $destinatarios
            );
        }

        $dias = (int) ($archivo['dias'] ?? 0);
        if ($dias === 0) {
            $this->limpiar($archivo);

            return $this->resultado(
                FlashReporteSuscripcion::ESTADO_OMITIDA,
                'No hay flash_caja en el período; no se envió nada.',
                $destinatarios
            );
        }

        if ($dryRun) {
            $this->limpiar($archivo);

            return $this->resultado(
                FlashReporteSuscripcion::ESTADO_OK,
                sprintf(
                    'Simulación: %d día(s), iría a %s (%s).',
                    $dias,
                    implode(', ', $destinatarios),
                    $archivo['nombre']
                ),
                $destinatarios,
                $dias,
                [$archivo['nombre']]
            );
        }

        try {
            Mail::to($destinatarios)->send(new FlashReporteAggDistribucion(
                $suscripcion,
                $periodo['desde'],
                $periodo['hasta'],
                $archivo,
            ));
        } catch (\Throwable $e) {
            Log::error('Flash Report AGG: falló el envío', [
                'suscripcion_id' => $suscripcion->id,
                'error' => $e->getMessage(),
            ]);
            $this->limpiar($archivo);

            return $this->resultado(
                FlashReporteSuscripcion::ESTADO_ERROR,
                'No se pudo enviar el mail: '.$e->getMessage(),
                $destinatarios,
                $dias
            );
        }

        $this->limpiar($archivo);

        return $this->resultado(
            FlashReporteSuscripcion::ESTADO_OK,
            sprintf(
                'Enviado a %s (%d día(s), %s).',
                implode(', ', $destinatarios),
                $dias,
                $archivo['nombre']
            ),
            $destinatarios,
            $dias,
            [$archivo['nombre']]
        );
    }

    /**
     * @param  array{path?: string}  $archivo
     */
    private function limpiar(array $archivo): void
    {
        $path = (string) ($archivo['path'] ?? '');
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param  list<string>  $destinatarios
     * @param  list<string>  $adjuntos
     * @return array{estado: string, mensaje: string, destinatarios: list<string>, dias: int, adjuntos: list<string>}
     */
    private function resultado(
        string $estado,
        string $mensaje,
        array $destinatarios,
        int $dias = 0,
        array $adjuntos = [],
    ): array {
        return [
            'estado' => $estado,
            'mensaje' => $mensaje,
            'destinatarios' => $destinatarios,
            'dias' => $dias,
            'adjuntos' => $adjuntos,
        ];
    }
}

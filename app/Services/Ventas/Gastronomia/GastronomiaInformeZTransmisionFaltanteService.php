<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Mail\Ventas\GastronomiaInformeZTransmisionFaltante;
use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Support\Ventas\Waitry\WaitryInformeZTransmisionFaltanteSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class GastronomiaInformeZTransmisionFaltanteService
{
    public function __construct(
        private readonly GastronomiaCierreTotemJornadaService $cierreTotemJornadaService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function verificarYPersistir(int $jornadaId, bool $enviarMail = true): array
    {
        $jornada = JornadaGastronomia::query()->with('empresa')->find($jornadaId);
        if ($jornada === null) {
            return ['ok' => false, 'error' => 'Jornada no encontrada'];
        }

        if ((string) ($jornada->estado ?? '') !== JornadaGastronomia::ESTADO_CERRADA) {
            return ['ok' => false, 'error' => 'La jornada no está cerrada'];
        }

        $cierre = CierreTotemJornadaGastronomia::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->orderByDesc('id')
            ->first();

        if ($cierre === null) {
            return ['ok' => false, 'error' => 'Sin cierre tótem'];
        }

        if (! $this->cierreTotemJornadaService->habilitado()) {
            return ['ok' => false, 'error' => 'Cierre tótem deshabilitado'];
        }

        $detalle = is_array($cierre->detalle_json) ? $cierre->detalle_json : [];
        $totalZ = round((float) ($detalle['resumen_informe_z']['total_general']['total_ingreso'] ?? 0), 2);
        $ordenesSnapshot = is_array($detalle[WaitryInformeZTransmisionFaltanteSupport::CLAVE_ORDENES_SNAPSHOT] ?? null)
            ? $detalle[WaitryInformeZTransmisionFaltanteSupport::CLAVE_ORDENES_SNAPSHOT]
            : [];

        $empresaId = (int) $jornada->empresa_id;
        $consulta = $this->cierreTotemJornadaService->datosTramoInformeZ($jornada);
        $ordenesFrescas = WaitryInformeZTransmisionFaltanteSupport::compactarOrdenesDesdeLineas(
            is_array($consulta['lineas_informe_z'] ?? null) ? $consulta['lineas_informe_z'] : [],
            $empresaId,
        );

        $tolerancia = max(0.0, (float) config(
            'gastronomia.informe_z_transmision_faltante.tolerancia',
            config('gastronomia.cierre_totem_informe_z_tolerancia', 0.02),
        ));

        $analisis = WaitryInformeZTransmisionFaltanteSupport::analizar(
            $totalZ,
            $ordenesSnapshot,
            $ordenesFrescas,
            $tolerancia,
        );

        $analisis['jornada_id'] = (int) $jornada->id;
        $analisis['cierre_totem_id'] = (int) $cierre->id;
        $analisis['empresa_id'] = $empresaId;
        $analisis['empresa_nombre'] = (string) ($jornada->empresa?->nombre ?? '');
        $analisis['fecha_jornada'] = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        $analisis['fecha_jornada_fmt'] = $jornada->fecha_jornada?->format('d/m/Y') ?? '';
        $analisis['cierre_jornada_en'] = $jornada->cierre_en?->format('Y-m-d H:i:s');
        $analisis['cierre_jornada_en_fmt'] = $jornada->cierre_en?->format('d/m/Y H:i') ?? '—';
        $analisis['mail_enviado'] = false;

        $detalle[WaitryInformeZTransmisionFaltanteSupport::CLAVE_DETALLE] = $analisis;
        $cierre->detalle_json = $detalle;
        $cierre->save();

        if ($enviarMail && ! empty($analisis['tiene_diferencias'])) {
            $analisis['mail_enviado'] = $this->enviarMail($analisis);
            $detalle[WaitryInformeZTransmisionFaltanteSupport::CLAVE_DETALLE] = $analisis;
            $cierre->detalle_json = $detalle;
            $cierre->save();
        }

        Log::info('gastronomia.informe_z_transmision_faltante.verificado', [
            'jornada_id' => $jornadaId,
            'tiene_diferencias' => (bool) ($analisis['tiene_diferencias'] ?? false),
            'total_faltante' => $analisis['total_faltante'] ?? 0,
            'cantidad_comandas' => $analisis['cantidad_comandas'] ?? 0,
        ]);

        return ['ok' => true, 'analisis' => $analisis];
    }

    /**
     * @param  array<string, mixed>  $analisis
     */
    public function enviarMail(array $analisis): bool
    {
        $destinatarios = self::destinatariosEmail();
        if ($destinatarios === []) {
            Log::warning('gastronomia.informe_z_transmision_faltante.mail_sin_destinatarios');

            return false;
        }

        try {
            Mail::to($destinatarios)->send(new GastronomiaInformeZTransmisionFaltante($analisis));

            return true;
        } catch (Throwable $e) {
            Log::error('gastronomia.informe_z_transmision_faltante.mail_fallo', [
                'error' => $e->getMessage(),
                'jornada_id' => $analisis['jornada_id'] ?? null,
            ]);

            return false;
        }
    }

    /**
     * @return list<string>
     */
    public static function destinatariosEmail(): array
    {
        $raw = (string) config('gastronomia.informe_z_transmision_faltante.email', '');
        if ($raw === '') {
            return [];
        }

        $emails = array_map('trim', explode(',', $raw));

        return array_values(array_filter(
            $emails,
            static fn (string $e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL),
        ));
    }
}

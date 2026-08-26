<?php

declare(strict_types=1);

namespace App\Services\Caja\Flash;

use App\Repositories\Caja\Flash\FlashCajaRepositoryInterface;
use App\Support\Caja\Flash\FlashCajaAybCierreWaitrySupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recalcula solo AyB de un flash ya grabado (cierre Waitry / corrección) y lo manda a Anita.
 */
final class FlashCajaAybRecalculoService
{
    public const ESTADO_ACTUALIZADO = 'actualizado';

    public const ESTADO_OMITIDO_NO_EXISTE = 'omitido_no_existe';

    public const ESTADO_OMITIDO_IGUAL = 'omitido_igual';

    public const ESTADO_ERROR = 'error';

    public function __construct(
        private readonly FlashCajaCalculoService $calculoService,
        private readonly FlashCajaRepositoryInterface $repository,
        private readonly FlashCajaAnitaExportService $exportAnitaService,
    ) {}

    /**
     * @return array{
     *   nivel: string,
     *   mensaje: string,
     *   tiene_caea: bool,
     *   monto_caea: float,
     *   cantidad: int,
     *   ayb_referencia: float,
     *   ayb_erp: float
     * }
     */
    public function estadoFormulario(int $empresaId, string $fecha, ?float $aybReferencia = null): array
    {
        $caea = FlashCajaAybCierreWaitrySupport::resumenCaea($empresaId, $fecha);
        $aybErp = $this->calculoService->totalAyB($empresaId, $fecha)['neto'];

        return FlashCajaAybCierreWaitrySupport::armarAviso(
            $caea,
            $aybReferencia ?? $aybErp,
            $aybErp,
        );
    }

    /**
     * Si el CAEA ya está y el AyB del form quedó corto, lo reemplaza por el ERP.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *   payload: array<string, mixed>,
     *   aviso: string,
     *   nivel: string,
     *   ayb_ajustado: bool,
     *   ayb_anterior: float,
     *   ayb_nuevo: float
     * }
     */
    public function aplicarAybAlPayload(array $payload): array
    {
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $fecha = (string) ($payload['fecha'] ?? '');
        $aybForm = round((float) ($payload['ayb'] ?? 0), 2);
        $estado = $this->estadoFormulario($empresaId, $fecha, $aybForm);
        $aybNuevo = $aybForm;
        $ajustado = false;

        if ($estado['nivel'] === FlashCajaAybCierreWaitrySupport::NIVEL_CORTO) {
            $aybNuevo = (float) $estado['ayb_erp'];
            $payload['ayb'] = $aybNuevo;
            $ajustado = true;
            $estado = FlashCajaAybCierreWaitrySupport::armarAviso(
                [
                    'tiene' => $estado['tiene_caea'],
                    'monto' => $estado['monto_caea'],
                    'cantidad' => $estado['cantidad'],
                ],
                $aybNuevo,
                $aybNuevo,
            );
            $estado['mensaje'] = 'Se completó el AyB con el CAEA de cierre Waitry ($ '
                .number_format((float) $estado['monto_caea'], 2, ',', '.')
                .'). Quedó en $ '.number_format($aybNuevo, 2, ',', '.').'.';
        }

        return [
            'payload' => $payload,
            'aviso' => (string) $estado['mensaje'],
            'nivel' => (string) $estado['nivel'],
            'ayb_ajustado' => $ajustado,
            'ayb_anterior' => $aybForm,
            'ayb_nuevo' => $aybNuevo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recalcularAybSiExiste(
        int $empresaId,
        string $fecha,
        string $origen = 'cierre_waitry',
        bool $dryRun = false,
        ?int $usuarioId = null,
    ): array {
        $fechaSql = Carbon::parse($fecha)->toDateString();
        $base = [
            'empresa_id' => $empresaId,
            'fecha' => $fechaSql,
            'origen' => $origen,
            'dry_run' => $dryRun,
        ];

        $flash = $this->repository->findPorEmpresaFecha($empresaId, $fechaSql);
        if ($flash === null) {
            return $base + [
                'estado' => self::ESTADO_OMITIDO_NO_EXISTE,
                'mensaje' => 'No hay flash para esa empresa/fecha; no se toca AyB.',
            ];
        }

        $aybAnterior = round((float) $flash->ayb, 2);
        $aybNuevo = $this->calculoService->totalAyB($empresaId, $fechaSql)['neto'];
        $base['flash_id'] = (int) $flash->id;
        $base['ayb_anterior'] = $aybAnterior;
        $base['ayb_nuevo'] = $aybNuevo;

        if (abs($aybNuevo - $aybAnterior) <= FlashCajaAybCierreWaitrySupport::TOLERANCIA) {
            return $base + [
                'estado' => self::ESTADO_OMITIDO_IGUAL,
                'mensaje' => 'AyB del flash ya coincide con gastronomía ERP ($ '
                    .number_format($aybNuevo, 2, ',', '.').').',
            ];
        }

        if ($dryRun) {
            return $base + [
                'estado' => self::ESTADO_ACTUALIZADO,
                'mensaje' => 'Dry-run: se actualizaría AyB de $ '
                    .number_format($aybAnterior, 2, ',', '.')
                    .' a $ '.number_format($aybNuevo, 2, ',', '.')
                    .' (flash id '.$flash->id.') y se enviaría a Anita.',
            ];
        }

        try {
            $update = ['ayb' => $aybNuevo];
            if ($usuarioId !== null && $usuarioId > 0) {
                $update['actualizousuario_id'] = $usuarioId;
            }
            $this->repository->update($update, $flash->id);
            $actual = $this->repository->findOrFail($flash->id);
            $syncAnita = $this->exportAnitaService->enviarModificacionEnAnita($actual);

            Log::info('flash.ayb.recalculo.ok', [
                'origen' => $origen,
                'empresa_id' => $empresaId,
                'fecha' => $fechaSql,
                'flash_id' => $flash->id,
                'ayb_anterior' => $aybAnterior,
                'ayb_nuevo' => $aybNuevo,
                'anita' => $syncAnita['resultado'] ?? null,
            ]);

            return $base + [
                'estado' => self::ESTADO_ACTUALIZADO,
                'mensaje' => 'AyB actualizado de $ '
                    .number_format($aybAnterior, 2, ',', '.')
                    .' a $ '.number_format($aybNuevo, 2, ',', '.')
                    .'. '.($syncAnita['mensaje'] ?? ''),
                'anita_sync' => $syncAnita['resultado'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('flash.ayb.recalculo.error', [
                'origen' => $origen,
                'empresa_id' => $empresaId,
                'fecha' => $fechaSql,
                'flash_id' => $flash->id,
                'error' => $e->getMessage(),
            ]);

            return $base + [
                'estado' => self::ESTADO_ERROR,
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  list<array{empresa_id: int, fecha: string}>  $dias
     * @return list<array<string, mixed>>
     */
    public function recalcularVarios(array $dias, string $origen, bool $dryRun = false, ?int $usuarioId = null): array
    {
        $out = [];
        foreach ($dias as $dia) {
            $out[] = $this->recalcularAybSiExiste(
                (int) ($dia['empresa_id'] ?? 0),
                (string) ($dia['fecha'] ?? ''),
                $origen,
                $dryRun,
                $usuarioId,
            );
        }

        return $out;
    }

    public function recalcularDesdeJornadaWaitry(int $empresaId, string $fechaJornada): array
    {
        return $this->recalcularAybSiExiste($empresaId, $fechaJornada, 'cierre_waitry');
    }
}

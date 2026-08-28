<?php

declare(strict_types=1);

namespace App\Services\Caja\Flash;

use App\Repositories\Caja\Flash\FlashCajaRepositoryInterface;
use App\Support\Caja\Flash\FlashCajaAybCierreWaitrySupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Actualiza el vending de un flash ya grabado (rendición tardía) y reexporta a Anita.
 * Informix no tiene columna de vending: flash_ayb se reescribe como AyB + vending.
 */
final class FlashCajaVendingRecalculoService
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
     * @return array<string, mixed>
     */
    public function recalcularVendingSiExiste(
        int $empresaId,
        string $fecha,
        string $origen = 'rendicion_vending',
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
                'mensaje' => 'No hay flash para esa empresa/fecha; no se toca vending.',
            ];
        }

        $vendingAnterior = round((float) $flash->vending, 2);
        $vendingNuevo = $this->calculoService->detalleVending($empresaId, $fechaSql)['total'];
        $base['flash_id'] = (int) $flash->id;
        $base['vending_anterior'] = $vendingAnterior;
        $base['vending_nuevo'] = $vendingNuevo;

        if (abs($vendingNuevo - $vendingAnterior) <= FlashCajaAybCierreWaitrySupport::TOLERANCIA) {
            return $base + [
                'estado' => self::ESTADO_OMITIDO_IGUAL,
                'mensaje' => 'Vending del flash ya coincide con las rendiciones ERP ($ '
                    .number_format($vendingNuevo, 2, ',', '.').').',
            ];
        }

        if ($dryRun) {
            return $base + [
                'estado' => self::ESTADO_ACTUALIZADO,
                'mensaje' => 'Dry-run: se actualizaría vending de $ '
                    .number_format($vendingAnterior, 2, ',', '.')
                    .' a $ '.number_format($vendingNuevo, 2, ',', '.')
                    .' (flash id '.$flash->id.') y se enviaría AyB+vending a Anita.',
            ];
        }

        try {
            $update = ['vending' => $vendingNuevo];
            if ($usuarioId !== null && $usuarioId > 0) {
                $update['actualizousuario_id'] = $usuarioId;
            }
            $this->repository->update($update, $flash->id);
            $actual = $this->repository->findOrFail($flash->id);
            $syncAnita = $this->exportAnitaService->enviarModificacionEnAnita($actual);

            Log::info('flash.vending.recalculo.ok', [
                'origen' => $origen,
                'empresa_id' => $empresaId,
                'fecha' => $fechaSql,
                'flash_id' => $flash->id,
                'vending_anterior' => $vendingAnterior,
                'vending_nuevo' => $vendingNuevo,
                'anita' => $syncAnita['resultado'] ?? null,
            ]);

            return $base + [
                'estado' => self::ESTADO_ACTUALIZADO,
                'mensaje' => 'Vending actualizado de $ '
                    .number_format($vendingAnterior, 2, ',', '.')
                    .' a $ '.number_format($vendingNuevo, 2, ',', '.')
                    .'. '.($syncAnita['mensaje'] ?? ''),
                'anita_sync' => $syncAnita['resultado'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('flash.vending.recalculo.error', [
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
}

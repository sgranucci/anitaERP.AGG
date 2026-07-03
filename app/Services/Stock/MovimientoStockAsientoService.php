<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Stock\MovimientoStockAsientoSupport;
use App\Support\Stock\MovimientoStockCuadreContableSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MovimientoStockAsientoService
{
    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly CuentacontableRepositoryInterface $cuentacontableRepository,
        private readonly CentrocostoRepositoryInterface $centrocostoRepository,
        private readonly TipoasientoRepositoryInterface $tipoasientoRepository,
    ) {}

    public function debeGenerarAsiento(?Tipotransaccion_Stock $tipo): bool
    {
        if (! (bool) ($tipo?->maneja_contabilidad ?? false)) {
            return false;
        }

        // Contabilidad de transferencias TRCONT: solo vía TransferenciaMercaderiaAsientoService.
        if (($tipo->operacion ?? '') === 'T') {
            return false;
        }

        return true;
    }

    /**
     * Falla rápido antes de borrar/regrabar ítems si el asiento no cuadraría.
     *
     * @param  array<string, mixed>  $data
     */
    public function assertCuadreAntesDeGrabar(
        array $data,
        Tipotransaccion_Stock $tipo,
        ?MovimientoStock $existente = null
    ): void {
        if (! $this->debeGenerarAsiento($tipo)) {
            return;
        }

        $movimiento = $this->construirMovimientoDesdePayload($data, $tipo, $existente);
        $this->assertCuadreMovimiento($movimiento);
    }

    public function assertCuadreMovimiento(MovimientoStock $movimiento): void
    {
        if (! $this->debeGenerarAsiento($movimiento->tipotransaccion_stock)) {
            return;
        }

        $preview = MovimientoStockAsientoSupport::armarPreview($movimiento, $this->tipoasientoRepository);
        MovimientoStockCuadreContableSupport::assertPreview($preview);
    }

    public function generarAsiento(MovimientoStock $movimiento): ?int
    {
        if (! $this->debeGenerarAsiento($movimiento->tipotransaccion_stock)) {
            return null;
        }

        $preview = MovimientoStockAsientoSupport::armarPreview($movimiento, $this->tipoasientoRepository);
        MovimientoStockCuadreContableSupport::assertPreview($preview);

        $payload = $preview['payload_asiento'];
        $payload['movimientostock_id'] = (int) $movimiento->id;

        $asiento = $this->asientoRepository->create($payload);
        if ($asiento === 'Error' || ! $asiento) {
            throw new \RuntimeException('Error al grabar asiento contable del movimiento de stock.');
        }

        $asientoId = (int) $asiento->id;
        $this->asientoMovimientoRepository->create($payload, $asientoId);
        MovimientoStockCuadreContableSupport::assertPersistido(
            $asientoId,
            $preview,
            $this->asientoMovimientoRepository
        );

        $movimiento->asiento_id = $asientoId;
        $movimiento->setRelation('asientos', $asiento);
        $this->sincronizarCtamovAnitaMovimiento($movimiento, $preview);

        return $asientoId;
    }

    public function recuadrarAsientoExistente(MovimientoStock $movimiento): void
    {
        $asientoId = (int) ($movimiento->asiento_id ?? 0);
        if ($asientoId <= 0) {
            throw new \RuntimeException('El movimiento no tiene asiento contable asociado.');
        }

        if (! $this->debeGenerarAsiento($movimiento->tipotransaccion_stock)) {
            return;
        }

        $preview = MovimientoStockAsientoSupport::armarPreview($movimiento, $this->tipoasientoRepository);
        MovimientoStockCuadreContableSupport::assertPreview($preview);

        $payload = $preview['payload_asiento'];
        $payload['movimientostock_id'] = (int) $movimiento->id;

        $this->asientoMovimientoRepository->update($payload, $asientoId);
        MovimientoStockCuadreContableSupport::assertPersistido(
            $asientoId,
            $preview,
            $this->asientoMovimientoRepository
        );

        $this->sincronizarCtamovAnitaMovimiento($movimiento->fresh(['asientos']), $preview);
    }

    /**
     * Reemplaza contab.ctamov en Anita (delete + insert) coherente con asiento_movimiento ERP.
     *
     * @param  array<string, mixed>|null  $preview
     */
    public function sincronizarCtamovAnitaMovimiento(MovimientoStock $movimiento, ?array $preview = null): void
    {
        if (! $this->debeGenerarAsiento($movimiento->tipotransaccion_stock)) {
            return;
        }

        $asientoId = (int) ($movimiento->asiento_id ?? 0);
        if ($asientoId <= 0) {
            throw new \RuntimeException('El movimiento no tiene asiento contable asociado.');
        }

        $movimiento->loadMissing('asientos');
        $asiento = $movimiento->asientos;
        if (! $asiento) {
            throw new \RuntimeException('No se encontró el asiento id '.$asientoId.' del movimiento de stock.');
        }

        $preview ??= MovimientoStockAsientoSupport::armarPreview(
            $movimiento->loadMissing([
                'tipotransaccion_stock',
                'articulos_movimiento.articulos.articulo_cuentacontables',
            ]),
            $this->tipoasientoRepository
        );
        $payload = $preview['payload_asiento'];

        $fechaAsiento = $asiento->fecha;
        if ($fechaAsiento instanceof \DateTimeInterface) {
            $fechaAsiento = $fechaAsiento->format('Y-m-d');
        } else {
            $fechaAsiento = \Carbon\Carbon::parse((string) $fechaAsiento)->format('Y-m-d');
        }

        $dataAnita = array_merge($payload, [
            'numeroasiento' => $asiento->numeroasiento,
            'empresa_id' => (int) $asiento->empresa_id,
            'tipoasiento_id' => (int) $asiento->tipoasiento_id,
            'fecha' => $fechaAsiento,
        ]);

        $this->asientoRepository->sincronizarCtamovAnita($dataAnita);
    }

    public function anularAsiento(MovimientoStock $movimiento): void
    {
        $asientoId = (int) ($movimiento->asiento_id ?? 0);
        if ($asientoId <= 0) {
            return;
        }

        $this->asientoRepository->delete($asientoId);
    }

    /**
     * Rollback compensatorio: elimina ctamov Anita de un asiento nuevo que no llegó a commitearse en ERP.
     */
    public function revertirCtamovAnita(int $empresaId, string $numeroAsiento): void
    {
        try {
            $this->asientoRepository->eliminarCtamovAnitaPorNumero($empresaId, $numeroAsiento);
        } catch (\Throwable $e) {
            Log::warning('MovimientoStockAsiento: rollback ctamov Anita falló', [
                'empresa_id' => $empresaId,
                'numeroasiento' => $numeroAsiento,
                'mensaje' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function previewParaVista(MovimientoStock $movimiento): array
    {
        if (! $this->debeGenerarAsiento($movimiento->tipotransaccion_stock)) {
            return ['activo' => false];
        }

        if ((int) ($movimiento->asiento_id ?? 0) > 0) {
            return $this->previewAsientoGrabado($movimiento);
        }

        try {
            $preview = MovimientoStockAsientoSupport::armarPreview($movimiento, $this->tipoasientoRepository);

            return [
                'activo' => true,
                'error' => null,
                'es_preview' => true,
                'total_movimiento' => $preview['total_movimiento'],
                'total_debe' => $preview['total_debe'],
                'total_haber' => $preview['total_haber'],
                'advertencias' => $preview['advertencias'],
                'lineas' => $this->formatearLineasPayload($preview['payload_asiento']),
            ];
        } catch (\Throwable $e) {
            return [
                'activo' => true,
                'error' => $e->getMessage(),
                'es_preview' => true,
                'lineas' => [],
            ];
        }
    }

    /**
     * Preview on-the-fly desde formulario (crear / editar sin guardar).
     *
     * @return array<string, mixed>
     */
    public function previewDesdeRequest(Request $request, ?MovimientoStock $existente = null): array
    {
        $tipoId = (int) ($request->input('tipotransaccion_stock_id') ?: $request->input('tipotransaccion_id'));
        $tipo = $tipoId > 0 ? Tipotransaccion_Stock::query()->find($tipoId) : null;

        if (! $this->debeGenerarAsiento($tipo)) {
            return ['activo' => false];
        }

        if ($existente && (int) ($existente->asiento_id ?? 0) > 0) {
            return $this->previewAsientoGrabado($existente);
        }

        try {
            $movimiento = $this->construirMovimientoDesdeRequest($request, $existente, $tipo);

            return $this->previewParaVista($movimiento);
        } catch (\Throwable $e) {
            return [
                'activo' => true,
                'error' => $e->getMessage(),
                'es_preview' => true,
                'lineas' => [],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function previewAsientoGrabado(MovimientoStock $movimiento): array
    {
        $movimiento->loadMissing([
            'asientos.tipoasientos',
            'asientos.asiento_movimientos.cuentacontables',
            'asientos.asiento_movimientos.centrocostos',
        ]);

        $asiento = $movimiento->asientos;
        if (! $asiento) {
            return [
                'activo' => true,
                'error' => 'El movimiento indica asiento id '.(int) $movimiento->asiento_id.' pero no se encontró.',
                'es_preview' => false,
                'lineas' => [],
            ];
        }

        $lineas = [];
        $totales = ['debe' => 0.0, 'haber' => 0.0];
        foreach ($asiento->asiento_movimientos as $mov) {
            $monto = (float) ($mov->monto ?? 0);
            $debe = $monto > 0 ? $monto : null;
            $haber = $monto < 0 ? abs($monto) : null;
            if ($debe !== null) {
                $totales['debe'] += $debe;
            }
            if ($haber !== null) {
                $totales['haber'] += $haber;
            }
            $lineas[] = [
                'cuenta_codigo' => optional($mov->cuentacontables)->codigo ?? '—',
                'cuenta_nombre' => optional($mov->cuentacontables)->nombre ?? '',
                'centrocosto_codigo' => optional($mov->centrocostos)->codigo ?? '',
                'debe' => $debe,
                'haber' => $haber,
                'observacion' => (string) ($mov->observacion ?? ''),
            ];
        }

        return [
            'activo' => true,
            'error' => null,
            'es_preview' => false,
            'asiento_id' => (int) $asiento->id,
            'numeroasiento' => (string) $asiento->numeroasiento,
            'fecha' => optional($asiento->fecha)->format('d/m/Y'),
            'tipo_asiento' => optional($asiento->tipoasientos)->nombre ?? '',
            'total_debe' => round($totales['debe'], 2),
            'total_haber' => round($totales['haber'], 2),
            'lineas' => $lineas,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function construirMovimientoDesdePayload(
        array $data,
        Tipotransaccion_Stock $tipo,
        ?MovimientoStock $existente = null
    ): MovimientoStock {
        $movimiento = $existente ?? new MovimientoStock;
        $movimiento->fecha = $data['fecha'] ?? $movimiento->fecha ?? now()->format('Y-m-d');
        $movimiento->codigo = $data['codigo'] ?? $movimiento->codigo ?? '';
        $movimiento->centrocosto_destino_id = (int) ($data['centrocosto_destino_id'] ?? $movimiento->centrocosto_destino_id ?? 0) ?: null;
        $movimiento->asiento_id = $existente?->asiento_id;
        $movimiento->setRelation('tipotransaccion_stock', $tipo);

        $articulosIds = $data['articulos_id'] ?? [];
        $cantidades = $data['cantidades'] ?? [];
        $depositoId = (int) ($data['deposito_id'] ?? 0);

        $lineas = collect();
        foreach ($articulosIds as $i => $articuloId) {
            $articuloId = (int) $articuloId;
            if ($articuloId <= 0) {
                continue;
            }
            $linea = new Articulo_Movimiento([
                'articulo_id' => $articuloId,
                'cantidad' => (float) ($cantidades[$i] ?? 0),
                'deposito_id' => $depositoId > 0 ? $depositoId : null,
            ]);
            $articulo = Articulo::query()
                ->with('articulo_cuentacontables')
                ->find($articuloId);
            $linea->setRelation('articulos', $articulo);
            $lineas->push($linea);
        }

        if ($lineas->isEmpty() && $existente) {
            $existente->loadMissing(['articulos_movimiento.articulos.articulo_cuentacontables']);
            $movimiento->setRelation('articulos_movimiento', $existente->articulos_movimiento);

            return $movimiento;
        }

        $movimiento->setRelation('articulos_movimiento', $lineas);

        return $movimiento;
    }

    private function construirMovimientoDesdeRequest(
        Request $request,
        ?MovimientoStock $existente,
        ?Tipotransaccion_Stock $tipo
    ): MovimientoStock {
        return $this->construirMovimientoDesdePayload(
            $request->all(),
            $tipo ?? new Tipotransaccion_Stock,
            $existente
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function formatearLineasPayload(array $payload): array
    {
        $lineas = [];
        $cuentas = $payload['cuentacontable_ids'] ?? [];
        $debes = $payload['debes'] ?? [];
        $haberes = $payload['haberes'] ?? [];
        $centros = $payload['centrocosto_ids'] ?? [];
        $observaciones = $payload['observaciones'] ?? [];

        foreach ($cuentas as $i => $cuentaId) {
            $cuenta = $this->cuentacontableRepository->find((int) $cuentaId);
            $ccId = (int) ($centros[$i] ?? 0);
            $ccCodigo = '';
            if ($ccId > 0) {
                $cc = $this->centrocostoRepository->find($ccId);
                $ccCodigo = (string) ($cc->codigo ?? '');
            }

            $debe = (float) ($debes[$i] ?? 0);
            $haber = (float) ($haberes[$i] ?? 0);

            $lineas[] = [
                'cuenta_codigo' => $cuenta->codigo ?? '—',
                'cuenta_nombre' => $cuenta->nombre ?? '',
                'centrocosto_codigo' => $ccCodigo,
                'debe' => $debe > 0 ? $debe : null,
                'haber' => $haber > 0 ? $haber : null,
                'observacion' => (string) ($observaciones[$i] ?? ''),
            ];
        }

        return $lineas;
    }
}

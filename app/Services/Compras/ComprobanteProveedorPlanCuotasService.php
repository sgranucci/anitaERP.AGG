<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Models\Compras\Condicionpago;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Support\Compras\ComprobanteProveedorCondicionPagoNcNdSupport;
use App\Support\Compras\ComprobanteProveedorCuotasTotalSupport;
use App\Support\Compras\ComprobanteProveedorEscrituraLock;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorFechaContableSupport;
use App\Support\Compras\ComprobanteProveedorIngresoEgresoEdicionSupport;
use App\Support\Compras\ComprobanteProveedorPagoSupport;
use App\Support\Compras\ComprobanteProveedorPlanCuotasSupport;
use App\Support\Compras\ComprobanteProveedorVencimientoCondicionSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ComprobanteProveedorPlanCuotasService
{
    public function __construct(
        private ComprobanteProveedorAnitaSyncService $anitaSync,
    ) {}

    /**
     * @return list<Comprobante_Proveedor>
     */
    public function buscar(string $texto): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }

        $q = Comprobante_Proveedor::query()
            ->with([
                'tipotransaccion_compras',
                'proveedores',
                'monedas',
                'condicionpagos',
                'comprobante_proveedor_cuotas',
            ])
            ->where('estado', '!=', ComprobanteProveedorEstados::ANULADO);

        if (ctype_digit($texto)) {
            $n = (int) $texto;
            $q->where(function ($w) use ($n) {
                $w->where('numerocomprobante', $n)->orWhere('id', $n);
            });
        } else {
            $q->where(function ($w) use ($texto) {
                $w->whereHas('proveedores', function ($p) use ($texto) {
                    $p->where('codigo', 'like', $texto.'%')
                        ->orWhere('nombre', 'like', '%'.$texto.'%');
                })->orWhere('letra', 'like', $texto.'%');
            });
        }

        return $q->orderByDesc('id')->limit(40)->get()->all();
    }

    public function etiqueta(Comprobante_Proveedor $comprobante): string
    {
        $comprobante->loadMissing('tipotransaccion_compras');

        return sprintf(
            '%s %s-%04d-%s',
            $comprobante->tipotransaccion_compras?->abreviatura ?? 'FAC',
            $comprobante->letra,
            (int) $comprobante->sucursal,
            $comprobante->numerocomprobante
        );
    }

    /**
     * @param  list<array<string, mixed>>  $filasManuales
     */
    public function aplicar(int $comprobanteId, string $accion, ?int $condicionpagoId, array $filasManuales): string
    {
        return ComprobanteProveedorEscrituraLock::ejecutar($comprobanteId, function () use ($comprobanteId, $accion, $condicionpagoId, $filasManuales) {
            $comprobante = $this->cargar($comprobanteId);
            $this->assertEditable($comprobante);
            $snapshot = $this->snapshot($comprobante);
            $plan = $this->armarPlan($comprobante, $accion, $condicionpagoId, $filasManuales);
            $condicionNueva = $this->condicionResultante($accion, $condicionpagoId, $comprobante);

            ComprobanteProveedorCondicionPagoNcNdSupport::assertPermitida(
                (int) $comprobante->tipotransaccion_compra_id,
                $condicionNueva,
                count($plan),
            );

            $escrito = false;
            try {
                DB::transaction(function () use ($comprobante, $plan, $condicionNueva) {
                    $this->escribir($comprobante, $plan, $condicionNueva);
                });
                $escrito = true;
                $fresco = $comprobante->fresh(['comprobante_proveedor_cuotas', 'condicionpagos', 'proveedores', 'tipotransaccion_compras', 'monedas']);
                if ($fresco && (int) $fresco->anita_nro_interno > 0) {
                    $this->anitaSync->resyncPlanCuotas($fresco);
                }
            } catch (Throwable $e) {
                if ($escrito) {
                    $this->restaurar($comprobanteId, $snapshot, $e);
                }
                throw $e instanceof RuntimeException
                    ? $e
                    : new RuntimeException('No se pudo cambiar el plan de cuotas: '.$e->getMessage(), 0, $e);
            }

            $etiqueta = $this->etiqueta($comprobante->fresh());

            return match ($accion) {
                'unificar' => $etiqueta.' quedó en una sola cuota, con el vencimiento de la primera y la cotización de la factura.',
                'condicion' => $etiqueta.' quedó con el plan de la condición de pago elegida.',
                default => $etiqueta.' quedó con el plan de cuotas cargado a mano.',
            };
        });
    }

    /**
     * @param  array{condicionpago_id: ?int, cuotas: list<array<string, mixed>>}  $snapshot
     */
    private function restaurar(int $comprobanteId, array $snapshot, Throwable $origen): void
    {
        try {
            $comprobante = $this->cargar($comprobanteId);
            DB::transaction(function () use ($comprobante, $snapshot) {
                $this->escribir($comprobante, $snapshot['cuotas'], $snapshot['condicionpago_id']);
            });
            $fresco = $comprobante->fresh(['comprobante_proveedor_cuotas', 'condicionpagos', 'proveedores', 'tipotransaccion_compras', 'monedas']);
            if ($fresco && (int) $fresco->anita_nro_interno > 0) {
                $this->anitaSync->resyncPlanCuotas($fresco);
            }
        } catch (Throwable $restauracion) {
            throw new RuntimeException(
                'El plan nuevo no se pudo publicar en Anita ('.$origen->getMessage()
                .') y tampoco se pudo volver al plan anterior ('.$restauracion->getMessage().').',
                0,
                $origen
            );
        }

        throw new RuntimeException(
            'No se publicó el plan en Anita y se dejó el anterior: '.$origen->getMessage(),
            0,
            $origen
        );
    }

    private function cargar(int $comprobanteId): Comprobante_Proveedor
    {
        $comprobante = Comprobante_Proveedor::query()
            ->with([
                'comprobante_proveedor_cuotas',
                'tipotransaccion_compras',
                'proveedores',
                'monedas',
                'condicionpagos',
            ])
            ->find($comprobanteId);

        if (! $comprobante) {
            throw new RuntimeException('Comprobante de proveedor inexistente.');
        }

        return $comprobante;
    }

    private function assertEditable(Comprobante_Proveedor $comprobante): void
    {
        if ($comprobante->estado === ComprobanteProveedorEstados::ANULADO) {
            throw new RuntimeException('El comprobante está anulado.');
        }

        ComprobanteProveedorIngresoEgresoEdicionSupport::assertPuedeModificarDesdeComprobante($comprobante);
        ComprobanteProveedorPagoSupport::assertSinPagosAplicados(
            (int) $comprobante->id,
            'cambiar el plan de cuotas'
        );

        foreach ($comprobante->comprobante_proveedor_cuotas as $cuota) {
            if ((float) ($cuota->total_pagado ?? 0) > 0.01) {
                throw new RuntimeException('Hay cuotas con importe pagado. Solo se rearma una factura impaga.');
            }
        }
    }

    /**
     * @return array{condicionpago_id: ?int, cuotas: list<array<string, mixed>>}
     */
    private function snapshot(Comprobante_Proveedor $comprobante): array
    {
        $cuotas = [];
        foreach ($comprobante->comprobante_proveedor_cuotas->sortBy('numero_cuota') as $cuota) {
            $cuotas[] = [
                'numero_cuota' => (int) $cuota->numero_cuota,
                'fechavencimiento' => ComprobanteProveedorVencimientoCondicionSupport::fechaYmd($cuota->fechavencimiento),
                'monto' => round((float) $cuota->monto, 2),
                'moneda_id' => (int) ($cuota->moneda_id ?: $comprobante->moneda_id ?: 1),
                'cotizacion' => (float) ($cuota->cotizacion ?: $comprobante->cotizacion ?: 1),
                'formapago_id' => (int) ($cuota->formapago_id ?: 1),
                'detalle' => $cuota->detalle,
                'ordencompra_comprobante_cuota_id' => $cuota->ordencompra_comprobante_cuota_id,
            ];
        }

        return [
            'condicionpago_id' => $comprobante->condicionpago_id ? (int) $comprobante->condicionpago_id : null,
            'cuotas' => $cuotas,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filasManuales
     * @return list<array<string, mixed>>
     */
    private function armarPlan(
        Comprobante_Proveedor $comprobante,
        string $accion,
        ?int $condicionpagoId,
        array $filasManuales,
    ): array {
        $monedaId = (int) ($comprobante->moneda_id ?: 1);
        $cotizacion = (float) ($comprobante->cotizacion ?: 1);
        $total = (float) ($comprobante->total ?? 0);
        $actuales = $this->snapshot($comprobante)['cuotas'];

        if ($accion === 'unificar') {
            return ComprobanteProveedorPlanCuotasSupport::planUnificado($actuales, $total, $monedaId, $cotizacion);
        }

        if ($accion === 'condicion') {
            if (! $condicionpagoId || ! Condicionpago::query()->whereKey($condicionpagoId)->exists()) {
                throw new RuntimeException('Elegí una condición de pago.');
            }
            $fecha = $comprobante->fechacomprobante?->format('Y-m-d') ?? ComprobanteProveedorFechaContableSupport::fechaYmd($comprobante);
            $formapago = (int) ($actuales[0]['formapago_id'] ?? 1) ?: 1;

            return ComprobanteProveedorPlanCuotasSupport::planDesdeCondicion(
                $condicionpagoId,
                $fecha,
                $total,
                $monedaId,
                $cotizacion,
                $formapago,
            );
        }

        if ($accion !== 'manual') {
            throw new RuntimeException('Acción de plan de cuotas no reconocida.');
        }

        return ComprobanteProveedorPlanCuotasSupport::planManual($filasManuales, $total, $monedaId, $cotizacion);
    }

    private function condicionResultante(string $accion, ?int $condicionpagoId, Comprobante_Proveedor $comprobante): ?int
    {
        if ($accion === 'unificar') {
            return null;
        }
        if ($accion === 'condicion') {
            return $condicionpagoId;
        }

        return $comprobante->condicionpago_id ? (int) $comprobante->condicionpago_id : null;
    }

    /**
     * @param  list<array<string, mixed>>  $plan
     */
    private function escribir(Comprobante_Proveedor $comprobante, array $plan, ?int $condicionpagoId): void
    {
        $actual = Comprobante_Proveedor::query()->whereKey($comprobante->id)->lockForUpdate()->first();
        if (! $actual) {
            throw new RuntimeException('Comprobante de proveedor inexistente.');
        }

        ComprobanteProveedorPagoSupport::assertSinPagosAplicados((int) $actual->id, 'cambiar el plan de cuotas');
        ComprobanteProveedorPagoSupport::liberarTrazasDePagosCompensados((int) $actual->id);
        ComprobanteProveedorCuotasTotalSupport::assertCuadraConTotal((float) $actual->total, $plan);

        $monedaId = (int) ($actual->moneda_id ?: 1);
        $cotizacion = (float) ($actual->cotizacion ?: 1);
        $signo = $this->signoDeuda($actual);

        $ccIds = ComprobanteProveedorPagoSupport::idsCuentacorrienteDeuda((int) $actual->id);

        DB::table('comprobante_proveedor_cuota')
            ->where('comprobante_proveedor_id', $actual->id)
            ->update(['proveedor_cuentacorriente_id' => null]);

        if ($ccIds !== []) {
            EloquentAuditDeleteSupport::each(
                Proveedor_Cuentacorriente::query()->whereIn('id', $ccIds)
            );
        }

        EloquentAuditDeleteSupport::each(
            Comprobante_Proveedor_Cuota::query()->where('comprobante_proveedor_id', $actual->id)
        );

        $fechaCc = ComprobanteProveedorFechaContableSupport::fechaYmd($actual);
        $primerVto = $plan[0]['fechavencimiento'] ?? $fechaCc;

        $actual->forceFill([
            'condicionpago_id' => $condicionpagoId,
            'fechavencimiento' => $primerVto,
        ])->save();

        $regenerarCc = $actual->estado === ComprobanteProveedorEstados::CONTABILIZADO || $ccIds !== [];

        foreach ($plan as $cuota) {
            $fila = Comprobante_Proveedor_Cuota::query()->create([
                'comprobante_proveedor_id' => $actual->id,
                'numero_cuota' => (int) ($cuota['numero_cuota'] ?? 1),
                'fechavencimiento' => $cuota['fechavencimiento'],
                'monto' => round((float) $cuota['monto'], 2),
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'formapago_id' => (int) ($cuota['formapago_id'] ?? 1) ?: 1,
                'detalle' => $cuota['detalle'] ?? null,
                'ordencompra_comprobante_cuota_id' => $cuota['ordencompra_comprobante_cuota_id'] ?? null,
                'total_pagado' => 0,
            ]);

            if (! $regenerarCc || abs((float) $fila->monto) < 0.0001) {
                continue;
            }

            $cc = Proveedor_Cuentacorriente::query()->create([
                'fecha' => $fechaCc,
                'fechavencimiento' => $cuota['fechavencimiento'],
                'proveedor_id' => $actual->proveedor_id,
                'total' => round((float) $fila->monto * $signo, 4),
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'empresa_id' => $actual->empresa_id,
                'comprobante_proveedor_id' => $actual->id,
                'comprobante_proveedor_cuota_id' => $fila->id,
            ]);
            $fila->proveedor_cuentacorriente_id = $cc->id;
            $fila->save();
        }
    }

    private function signoDeuda(Comprobante_Proveedor $comprobante): int
    {
        $tipo = (float) ($comprobante->tipotransaccion_compras?->signo ?? 1);

        return $tipo < 0 ? -1 : 1;
    }
}

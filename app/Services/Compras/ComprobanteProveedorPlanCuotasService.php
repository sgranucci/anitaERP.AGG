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
use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
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
            $this->assertEditable($comprobante, $accion);
            if ($accion === 'unificar' && ComprobanteProveedorPagoSupport::tienePagosAplicados($comprobanteId)) {
                return $this->unificarPendientes($comprobante);
            }
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

    private function assertEditable(Comprobante_Proveedor $comprobante, string $accion): void
    {
        if ($comprobante->estado === ComprobanteProveedorEstados::ANULADO) {
            throw new RuntimeException('El comprobante está anulado.');
        }

        ComprobanteProveedorIngresoEgresoEdicionSupport::assertPuedeModificarDesdeComprobante($comprobante);
        if ($accion === 'unificar') {
            return;
        }

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
     * @return array{
     *     impago: bool,
     *     puede_unificar: bool,
     *     advertencia_pagos: bool,
     *     mensaje_bloqueo: ?string,
     *     mensaje_estado: ?string,
     *     aplicado: array<int, float>,
     *     texto_confirmar_unificar: string
     * }
     */
    public function resumenParaEdicion(Comprobante_Proveedor $comprobante): array
    {
        $textoLibre = 'Se unifica en una cuota con el vencimiento de la primera, la moneda y la cotización de la factura. La condición de pago queda vacía para que esa fecha no se recalcule.';
        $textoPendiente = 'Se conservan las cuotas con pagos aplicados. El saldo que falta se unifica en una cuota, con el vencimiento de la primera cuota pendiente. La condición de pago queda vacía.';
        $tienePagos = ComprobanteProveedorPagoSupport::tienePagosAplicados((int) $comprobante->id);
        $mapa = $this->mapaAplicado($comprobante);
        $pendientes = 0;
        $hayAplicacionEnCuota = false;
        foreach ($comprobante->comprobante_proveedor_cuotas as $cuota) {
            $aplicado = $mapa['por_cuota'][(int) $cuota->id] ?? 0.0;
            if ($aplicado > 0.01) {
                $hayAplicacionEnCuota = true;
            }
            if (round(abs((float) $cuota->monto) - $aplicado, 2) > 0.01) {
                $pendientes++;
            }
        }

        $puede = ! $tienePagos;
        $mensaje = null;
        if ($tienePagos && $mapa['huerfano']) {
            $mensaje = 'Este comprobante tiene un pago aplicado que no está en una cuota. No se puede rearmar el plan.';
        } elseif ($tienePagos && ! $hayAplicacionEnCuota) {
            $mensaje = 'Este comprobante tiene pagos aplicados. No se puede rearmar el plan.';
        } elseif ($tienePagos && $pendientes > 1) {
            $puede = true;
        }

        $estado = null;
        if ($mensaje === null && $tienePagos && $hayAplicacionEnCuota && $pendientes < 2) {
            $estado = $pendientes === 1
                ? 'La cuota con pago aplicado quedó como está. El saldo pendiente ya está en una sola cuota.'
                : 'Todas las cuotas tienen el pago aplicado.';
        }

        return [
            'impago' => ! $tienePagos,
            'puede_unificar' => $puede,
            'advertencia_pagos' => $tienePagos && $puede,
            'mensaje_bloqueo' => $mensaje,
            'mensaje_estado' => $estado,
            'aplicado' => $mapa['por_cuota'],
            'texto_confirmar_unificar' => $tienePagos && $puede ? $textoPendiente : $textoLibre,
        ];
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

    private function unificarPendientes(Comprobante_Proveedor $comprobante): string
    {
        $comprobanteId = (int) $comprobante->id;
        $armado = null;
        $snapshot = null;
        $escrito = false;

        try {
            DB::transaction(function () use ($comprobanteId, &$armado, &$snapshot) {
                $actual = Comprobante_Proveedor::query()->whereKey($comprobanteId)->lockForUpdate()->first();
                if (! $actual) {
                    throw new RuntimeException('Comprobante de proveedor inexistente.');
                }
                $actual->load(['comprobante_proveedor_cuotas', 'tipotransaccion_compras']);
                $ccIds = [];
                foreach ($actual->comprobante_proveedor_cuotas as $cuota) {
                    $ccId = (int) ($cuota->proveedor_cuentacorriente_id ?? 0);
                    if ($ccId > 0) {
                        $ccIds[] = $ccId;
                    }
                }
                if ($ccIds !== []) {
                    Proveedor_Cuentacorriente::query()->whereIn('id', $ccIds)->lockForUpdate()->get();
                }

                $armado = $this->armarUnificacionPendientes($actual);
                $snapshot = $this->snapshotRestauracion($actual, $armado);
                $this->escribirUnificacionPendientes($actual, $armado);
            });
            $escrito = true;

            $fresco = $comprobante->fresh([
                'comprobante_proveedor_cuotas',
                'condicionpagos',
                'proveedores',
                'tipotransaccion_compras',
                'monedas',
            ]);
            if ($fresco && (int) $fresco->anita_nro_interno > 0 && is_array($armado)) {
                $this->anitaSync->publicarPlanConservandoCuotasPagadas(
                    $fresco,
                    $armado['numeros_borrar'],
                    $armado['numeros_actualizar'],
                    [(int) $armado['nueva']['numero_cuota']],
                );
            }
        } catch (Throwable $e) {
            if ($escrito && is_array($snapshot) && is_array($armado)) {
                $this->restaurarUnificacionPendientes($comprobanteId, $snapshot, $e);
            }
            throw $e instanceof RuntimeException
                ? $e
                : new RuntimeException('No se pudo unificar las cuotas pendientes: '.$e->getMessage(), 0, $e);
        }

        $fecha = (string) ($armado['nueva']['fechavencimiento'] ?? '');
        $fechaTexto = $fecha !== '' ? date('d/m/Y', strtotime($fecha)) : '';

        return $this->etiqueta($comprobante->fresh())
            .' quedó con las cuotas ya pagadas y el saldo pendiente en una cuota'
            .($fechaTexto !== '' ? ' al '.$fechaTexto : '')
            .'.';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restaurarUnificacionPendientes(int $comprobanteId, array $snapshot, Throwable $origen): void
    {
        try {
            DB::transaction(function () use ($comprobanteId, $snapshot) {
                $actual = Comprobante_Proveedor::query()->whereKey($comprobanteId)->lockForUpdate()->first();
                if (! $actual) {
                    throw new RuntimeException('Comprobante de proveedor inexistente.');
                }
                $actual->load('tipotransaccion_compras');
                $ids = [];
                foreach ($snapshot['cuotas'] as $cuota) {
                    $ids[(int) $cuota['id']] = true;
                }
                $vigentes = Comprobante_Proveedor_Cuota::query()
                    ->where('comprobante_proveedor_id', $actual->id)
                    ->lockForUpdate()
                    ->get();
                foreach ($vigentes as $cuota) {
                    if (! isset($ids[(int) $cuota->id])) {
                        $this->borrarCuotaImpaga($cuota);
                    }
                }
                foreach ($snapshot['cuotas'] as $fila) {
                    $this->restaurarCuota($actual, $fila);
                }
                $actual->forceFill([
                    'condicionpago_id' => $snapshot['condicionpago_id'],
                    'fechavencimiento' => $snapshot['fechavencimiento'],
                ])->save();
            });

            $fresco = Comprobante_Proveedor::query()
                ->with([
                    'comprobante_proveedor_cuotas',
                    'condicionpagos',
                    'proveedores',
                    'tipotransaccion_compras',
                    'monedas',
                    'empresas',
                    'provinciaDestino',
                    'ordencompras',
                ])
                ->find($comprobanteId);
            if ($fresco && (int) $fresco->anita_nro_interno > 0) {
                $borrar = array_values(array_unique(array_merge(
                    $snapshot['numeros_borrar'],
                    [(int) $snapshot['numero_nueva']]
                )));
                $this->anitaSync->publicarPlanConservandoCuotasPagadas(
                    $fresco,
                    $borrar,
                    $snapshot['numeros_actualizar'],
                    $snapshot['numeros_borrar'],
                );
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

    /**
     * @return array<string, mixed>
     */
    private function armarUnificacionPendientes(Comprobante_Proveedor $comprobante): array
    {
        $mapa = $this->mapaAplicado($comprobante);
        if ($mapa['huerfano']) {
            throw new RuntimeException(
                'Este comprobante tiene un pago aplicado que no está en una cuota. No se puede unificar el saldo pendiente.'
            );
        }

        $filas = [];
        $crearCc = $comprobante->estado === ComprobanteProveedorEstados::CONTABILIZADO;
        foreach ($comprobante->comprobante_proveedor_cuotas as $cuota) {
            $ccId = (int) ($cuota->proveedor_cuentacorriente_id ?? 0);
            if ($ccId > 0) {
                $crearCc = true;
            }
            $filas[] = [
                'id' => (int) $cuota->id,
                'numero_cuota' => (int) $cuota->numero_cuota,
                'fechavencimiento' => ComprobanteProveedorVencimientoCondicionSupport::fechaYmd($cuota->fechavencimiento),
                'monto' => round((float) $cuota->monto, 2),
                'aplicado' => $mapa['por_cuota'][(int) $cuota->id] ?? 0.0,
                'formapago_id' => (int) ($cuota->formapago_id ?: 1) ?: 1,
                'proveedor_cuentacorriente_id' => $ccId,
                'moneda_id' => (int) ($cuota->moneda_id ?: $comprobante->moneda_id ?: 1),
                'cotizacion' => (float) ($cuota->cotizacion ?: $comprobante->cotizacion ?: 1),
                'detalle' => $cuota->detalle,
                'ordencompra_comprobante_cuota_id' => $cuota->ordencompra_comprobante_cuota_id,
                'total_pagado' => round((float) ($cuota->total_pagado ?? 0), 4),
            ];
        }

        $plan = ComprobanteProveedorPlanCuotasSupport::planUnificadoConservandoAplicaciones(
            $filas,
            (float) ($comprobante->total ?? 0),
            (int) ($comprobante->moneda_id ?: 1),
            (float) ($comprobante->cotizacion ?: 1),
        );
        ComprobanteProveedorCondicionPagoNcNdSupport::assertPermitida(
            (int) $comprobante->tipotransaccion_compra_id,
            null,
            count($plan['conservadas']) + 1,
        );

        $originales = [];
        foreach ($filas as $fila) {
            $originales[(int) $fila['id']] = $fila;
        }
        $conservadas = [];
        foreach ($plan['conservadas'] as $conservada) {
            $original = $originales[(int) $conservada['id']];
            $conservadas[] = [
                'id' => (int) $conservada['id'],
                'numero_cuota' => (int) $conservada['numero_cuota'],
                'monto' => round((float) $conservada['monto'], 2),
                'actualizar_monto' => abs((float) $conservada['monto'] - (float) $original['monto']) > 0.01,
                'proveedor_cuentacorriente_id' => (int) $original['proveedor_cuentacorriente_id'],
            ];
        }
        $idsConservados = [];
        foreach ($conservadas as $conservada) {
            $idsConservados[(int) $conservada['id']] = true;
        }
        $eliminar = [];
        foreach ($filas as $fila) {
            if (! isset($idsConservados[(int) $fila['id']])) {
                $eliminar[] = $fila;
            }
        }

        return [
            'conservadas' => $conservadas,
            'eliminar_ids' => array_map(static fn (array $fila): int => (int) $fila['id'], $eliminar),
            'nueva' => $plan['nueva'],
            'numeros_borrar' => $plan['numeros_borrar'],
            'numeros_actualizar' => $plan['numeros_actualizar'],
            'crear_cc' => $crearCc,
            'fecha_cc' => ComprobanteProveedorFechaContableSupport::fechaYmd($comprobante),
        ];
    }

    /**
     * @param  array<string, mixed>  $armado
     * @return array<string, mixed>
     */
    private function snapshotRestauracion(Comprobante_Proveedor $comprobante, array $armado): array
    {
        $cuotas = [];
        foreach ($comprobante->comprobante_proveedor_cuotas->sortBy('numero_cuota') as $cuota) {
            $cc = null;
            $ccId = (int) ($cuota->proveedor_cuentacorriente_id ?? 0);
            if ($ccId > 0) {
                $ccModel = Proveedor_Cuentacorriente::query()->find($ccId);
                if ($ccModel) {
                    $cc = [
                        'fecha' => ComprobanteProveedorVencimientoCondicionSupport::fechaYmd($ccModel->fecha),
                        'fechavencimiento' => ComprobanteProveedorVencimientoCondicionSupport::fechaYmd($ccModel->fechavencimiento),
                        'proveedor_id' => (int) $ccModel->proveedor_id,
                        'total' => round((float) $ccModel->total, 4),
                        'moneda_id' => (int) $ccModel->moneda_id,
                        'cotizacion' => (float) $ccModel->cotizacion,
                        'empresa_id' => (int) $ccModel->empresa_id,
                    ];
                }
            }
            $cuotas[] = [
                'id' => (int) $cuota->id,
                'numero_cuota' => (int) $cuota->numero_cuota,
                'fechavencimiento' => ComprobanteProveedorVencimientoCondicionSupport::fechaYmd($cuota->fechavencimiento),
                'monto' => round((float) $cuota->monto, 2),
                'moneda_id' => (int) ($cuota->moneda_id ?: 1),
                'cotizacion' => (float) ($cuota->cotizacion ?: 1),
                'formapago_id' => (int) ($cuota->formapago_id ?: 1) ?: 1,
                'detalle' => $cuota->detalle,
                'ordencompra_comprobante_cuota_id' => $cuota->ordencompra_comprobante_cuota_id,
                'total_pagado' => round((float) ($cuota->total_pagado ?? 0), 4),
                'cc' => $cc,
            ];
        }

        return [
            'condicionpago_id' => $comprobante->condicionpago_id ? (int) $comprobante->condicionpago_id : null,
            'fechavencimiento' => ComprobanteProveedorVencimientoCondicionSupport::fechaYmd($comprobante->fechavencimiento),
            'cuotas' => $cuotas,
            'numero_nueva' => (int) $armado['nueva']['numero_cuota'],
            'numeros_borrar' => $armado['numeros_borrar'],
            'numeros_actualizar' => $armado['numeros_actualizar'],
        ];
    }

    /**
     * @param  array<string, mixed>  $armado
     */
    private function escribirUnificacionPendientes(Comprobante_Proveedor $actual, array $armado): void
    {
        foreach ($armado['conservadas'] as $conservada) {
            if (empty($conservada['actualizar_monto'])) {
                continue;
            }
            $cuota = Comprobante_Proveedor_Cuota::query()->whereKey((int) $conservada['id'])->lockForUpdate()->first();
            if (! $cuota || (int) $cuota->comprobante_proveedor_id !== (int) $actual->id) {
                throw new RuntimeException('No se encontró la cuota con pago aplicado.');
            }
            $cuota->monto = round((float) $conservada['monto'], 2);
            $cuota->save();
            $ccId = (int) ($cuota->proveedor_cuentacorriente_id ?? 0);
            if ($ccId <= 0) {
                continue;
            }
            $cc = Proveedor_Cuentacorriente::query()->whereKey($ccId)->lockForUpdate()->first();
            if (! $cc) {
                continue;
            }
            $signoCc = (float) $cc->total < 0 ? -1 : 1;
            $cc->total = round((float) $conservada['monto'] * $signoCc, 4);
            $cc->save();
        }

        if ($armado['eliminar_ids'] !== []) {
            $aBorrar = Comprobante_Proveedor_Cuota::query()
                ->where('comprobante_proveedor_id', $actual->id)
                ->whereIn('id', $armado['eliminar_ids'])
                ->lockForUpdate()
                ->get();
            foreach ($aBorrar as $cuota) {
                $this->borrarCuotaImpaga($cuota);
            }
        }

        $nueva = $armado['nueva'];
        $monedaId = (int) ($actual->moneda_id ?: 1);
        $cotizacion = (float) ($actual->cotizacion ?: 1);
        $fila = Comprobante_Proveedor_Cuota::query()->create([
            'comprobante_proveedor_id' => $actual->id,
            'numero_cuota' => (int) $nueva['numero_cuota'],
            'fechavencimiento' => $nueva['fechavencimiento'],
            'monto' => round((float) $nueva['monto'], 2),
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'formapago_id' => (int) ($nueva['formapago_id'] ?? 1) ?: 1,
            'detalle' => null,
            'ordencompra_comprobante_cuota_id' => null,
            'total_pagado' => 0,
        ]);

        if (! empty($armado['crear_cc']) && abs((float) $fila->monto) >= 0.0001) {
            $cc = Proveedor_Cuentacorriente::query()->create([
                'fecha' => $armado['fecha_cc'],
                'fechavencimiento' => $nueva['fechavencimiento'],
                'proveedor_id' => $actual->proveedor_id,
                'total' => round((float) $fila->monto * $this->signoDeuda($actual), 4),
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'empresa_id' => $actual->empresa_id,
                'comprobante_proveedor_id' => $actual->id,
                'comprobante_proveedor_cuota_id' => $fila->id,
            ]);
            $fila->proveedor_cuentacorriente_id = $cc->id;
            $fila->save();
        }

        $fechas = [(string) $nueva['fechavencimiento']];
        foreach ($armado['conservadas'] as $conservada) {
            $cuota = Comprobante_Proveedor_Cuota::query()->find((int) $conservada['id']);
            $fecha = $cuota
                ? ComprobanteProveedorVencimientoCondicionSupport::fechaYmd($cuota->fechavencimiento)
                : null;
            if ($fecha !== null) {
                $fechas[] = $fecha;
            }
        }
        sort($fechas);

        $actual->forceFill([
            'condicionpago_id' => null,
            'fechavencimiento' => $fechas[0] ?? $nueva['fechavencimiento'],
        ])->save();
    }

    private function borrarCuotaImpaga(Comprobante_Proveedor_Cuota $cuota): void
    {
        $ccId = (int) ($cuota->proveedor_cuentacorriente_id ?? 0);
        if ($ccId > 0) {
            $tieneAplicacion = DB::table('proveedor_cuentacorriente_aplicacion')
                ->where('proveedor_cuentacorriente_id', $ccId)
                ->orWhere('proveedor_cuentacorriente_aplicado_id', $ccId)
                ->exists();
            if ($tieneAplicacion) {
                throw new RuntimeException(
                    'La cuota '.$cuota->numero_cuota.' tiene una aplicación y no se puede reemplazar.'
                );
            }
            $tieneOp = DB::table('pagoproveedor_comprobante')
                ->where('proveedor_cuentacorriente_id', $ccId)
                ->orWhere('proveedor_cuentacorriente_dc_id', $ccId)
                ->exists();
            if ($tieneOp) {
                throw new RuntimeException(
                    'La cuota '.$cuota->numero_cuota.' está en una orden de pago y no se puede reemplazar.'
                );
            }
            $cc = Proveedor_Cuentacorriente::query()->whereKey($ccId)->lockForUpdate()->first();
            if ($cc) {
                $cc->comprobante_proveedor_cuota_id = null;
                $cc->save();
                $cuota->proveedor_cuentacorriente_id = null;
                $cuota->save();
                $cc->delete();
            }
        }

        $cuota->delete();
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function restaurarCuota(Comprobante_Proveedor $comprobante, array $fila): void
    {
        $cuota = Comprobante_Proveedor_Cuota::query()->whereKey((int) $fila['id'])->first();
        if ($cuota && (int) $cuota->comprobante_proveedor_id === (int) $comprobante->id) {
            $cuota->forceFill([
                'numero_cuota' => (int) $fila['numero_cuota'],
                'fechavencimiento' => $fila['fechavencimiento'],
                'monto' => $fila['monto'],
                'moneda_id' => $fila['moneda_id'],
                'cotizacion' => $fila['cotizacion'],
                'formapago_id' => $fila['formapago_id'],
                'detalle' => $fila['detalle'],
                'total_pagado' => $fila['total_pagado'],
            ])->save();
            $cc = is_array($fila['cc'] ?? null) && (int) ($cuota->proveedor_cuentacorriente_id ?? 0) > 0
                ? Proveedor_Cuentacorriente::query()->find((int) $cuota->proveedor_cuentacorriente_id)
                : null;
            if ($cc && is_array($fila['cc'])) {
                $cc->forceFill([
                    'total' => $fila['cc']['total'],
                    'fechavencimiento' => $fila['cc']['fechavencimiento'],
                ])->save();
            }

            return;
        }

        $nueva = Comprobante_Proveedor_Cuota::query()->create([
            'comprobante_proveedor_id' => $comprobante->id,
            'numero_cuota' => (int) $fila['numero_cuota'],
            'fechavencimiento' => $fila['fechavencimiento'],
            'monto' => $fila['monto'],
            'moneda_id' => $fila['moneda_id'],
            'cotizacion' => $fila['cotizacion'],
            'formapago_id' => $fila['formapago_id'],
            'detalle' => $fila['detalle'],
            'ordencompra_comprobante_cuota_id' => $fila['ordencompra_comprobante_cuota_id'],
            'total_pagado' => $fila['total_pagado'],
        ]);
        if (! is_array($fila['cc'] ?? null)) {
            return;
        }
        $cc = Proveedor_Cuentacorriente::query()->create([
            'fecha' => $fila['cc']['fecha'],
            'fechavencimiento' => $fila['cc']['fechavencimiento'],
            'proveedor_id' => $fila['cc']['proveedor_id'],
            'total' => $fila['cc']['total'],
            'moneda_id' => $fila['cc']['moneda_id'],
            'cotizacion' => $fila['cc']['cotizacion'],
            'empresa_id' => $fila['cc']['empresa_id'],
            'comprobante_proveedor_id' => $comprobante->id,
            'comprobante_proveedor_cuota_id' => $nueva->id,
        ]);
        $nueva->proveedor_cuentacorriente_id = $cc->id;
        $nueva->save();
    }

    /**
     * @return array{por_cuota: array<int, float>, huerfano: bool}
     */
    private function mapaAplicado(Comprobante_Proveedor $comprobante): array
    {
        $comprobante->loadMissing('comprobante_proveedor_cuotas');
        $ccDeCuota = [];
        foreach ($comprobante->comprobante_proveedor_cuotas as $cuota) {
            $ccId = (int) ($cuota->proveedor_cuentacorriente_id ?? 0);
            if ($ccId > 0) {
                $ccDeCuota[$ccId] = $ccId;
            }
        }
        $deudaIds = ComprobanteProveedorPagoSupport::idsCuentacorrienteDeuda((int) $comprobante->id);
        $todos = array_values(array_unique(array_merge(array_values($ccDeCuota), $deudaIds)));
        $sumas = $this->sumasCc('proveedor_cuentacorriente_aplicacion', 'total', $todos);
        $ops = $this->sumasCc('pagoproveedor_comprobante', 'montoaplicado', $todos);
        $totales = $todos === []
            ? collect()
            : Proveedor_Cuentacorriente::query()->whereIn('id', $todos)->pluck('total', 'id');

        $aplicadoDe = function (int $ccId, float $tope) use ($sumas, $ops, $totales): float {
            $aplicado = 0.0;
            if ($ccId > 0 && $totales->has($ccId)) {
                $saldo = ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
                    (float) $totales[$ccId],
                    $sumas[$ccId] ?? 0.0
                );
                $aplicado = round(abs((float) $totales[$ccId]) - $saldo, 2);
            }
            if ($aplicado <= 0.01 && $ccId > 0 && abs($ops[$ccId] ?? 0.0) > 0.01) {
                $aplicado = round(abs($ops[$ccId]), 2);
            }
            if ($tope > 0) {
                $aplicado = min($tope, $aplicado);
            }

            return round(max(0, $aplicado), 2);
        };

        $porCuota = [];
        foreach ($comprobante->comprobante_proveedor_cuotas as $cuota) {
            $monto = round(abs((float) $cuota->monto), 2);
            $ccId = (int) ($cuota->proveedor_cuentacorriente_id ?? 0);
            $aplicado = $aplicadoDe($ccId, $monto);
            $pagado = round(abs((float) ($cuota->total_pagado ?? 0)), 2);
            if ($pagado > $aplicado) {
                $aplicado = min($monto, $pagado);
            }
            $porCuota[(int) $cuota->id] = $aplicado;
        }

        $huerfano = false;
        foreach ($deudaIds as $ccId) {
            if (isset($ccDeCuota[$ccId])) {
                continue;
            }
            if ($aplicadoDe((int) $ccId, 0.0) > 0.01) {
                $huerfano = true;
                break;
            }
        }

        return [
            'por_cuota' => $porCuota,
            'huerfano' => $huerfano,
        ];
    }

    /**
     * @param  list<int>  $ccIds
     * @return array<int, float>
     */
    private function sumasCc(string $tabla, string $columna, array $ccIds): array
    {
        if ($ccIds === []) {
            return [];
        }

        $rows = DB::table($tabla)
            ->select('proveedor_cuentacorriente_id', DB::raw('SUM('.$columna.') as neto'))
            ->whereIn('proveedor_cuentacorriente_id', $ccIds)
            ->groupBy('proveedor_cuentacorriente_id')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->proveedor_cuentacorriente_id] = (float) $row->neto;
        }

        return $out;
    }

    private function signoDeuda(Comprobante_Proveedor $comprobante): int
    {
        $tipo = (float) ($comprobante->tipotransaccion_compras?->signo ?? 1);

        return $tipo < 0 ? -1 : 1;
    }
}

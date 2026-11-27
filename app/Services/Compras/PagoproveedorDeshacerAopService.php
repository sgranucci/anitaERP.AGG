<?php

namespace App\Services\Compras;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cheque;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Comprobante;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Contable\Asiento;
use App\Repositories\Compras\PagoproveedorRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionBorradoSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Auth;
use DB;
use RuntimeException;

/**
 * Deshace un AOP (compensatorio de una OP revertida por error).
 *
 * Alcance deliberadamente acotado: solo AOP sin caja, sin cheques y sin retenciones, cuya OPP
 * original sigue apuntando a ese AOP. Cualquier otro caso se bloquea con un motivo legible.
 *
 * Orden: Anita primero (borrado por clave, idempotente) y después ERP en una transacción con
 * bloqueo. Si ERP falla, el AOP sigue vivo acá y reintentar la acción es seguro.
 */
class PagoproveedorDeshacerAopService
{
    private const ESTADOS_RESTAURABLES = ['CONFIRMADA', 'PAGADA', 'CONCILIADA'];

    public function __construct(
        private PagoproveedorRepositoryInterface $pagoproveedorRepository,
        private AsientoRepositoryInterface $asientoRepository,
        private ProveedorCuentacorrienteAplicacionAnitaSyncService $cuentacorrienteAnitaSyncService,
    ) {}

    /**
     * Solo lectura. Devuelve lo que cambiaría y los motivos de bloqueo (si los hay).
     *
     * @return array{puede_deshacer: bool, errores: list<string>, aop: array<string, mixed>, origen: array<string, mixed>, cambios: array<string, mixed>}
     */
    public function planificar(int $aopId): array
    {
        $errores = [];
        $aop = Pagoproveedor::query()->find($aopId);

        if ($aop === null) {
            return $this->resultadoPlan(['El AOP no existe.'], [], [], []);
        }

        $aop->loadMissing(['proveedores']);
        $aopData = [
            'id' => (int) $aop->id,
            'tipo' => (string) $aop->tipocomprobante,
            'numero' => (string) $aop->numerotransaccion,
            'etiqueta' => $aop->etiquetaComprobante(),
            'proveedor' => (string) ($aop->proveedores?->nombre ?? ''),
            'fecha' => $this->fechaYmd($aop->fecha),
            'monto' => (float) $aop->monto,
            'estado' => (string) $aop->estado,
        ];

        if (strtoupper((string) $aop->tipocomprobante) !== 'AOP') {
            $errores[] = 'El comprobante seleccionado no es un AOP.';
        }
        if ((string) $aop->estado !== 'REVERTIDA') {
            $errores[] = 'El AOP no está en estado REVERTIDA.';
        }

        $origen = (int) ($aop->pagoproveedor_origen_id ?? 0) > 0
            ? Pagoproveedor::query()->find((int) $aop->pagoproveedor_origen_id)
            : null;

        if ($origen === null) {
            $errores[] = 'No se encuentra la OPP original de este AOP.';

            return $this->resultadoPlan($errores, $aopData, [], []);
        }

        $origenData = [
            'id' => (int) $origen->id,
            'tipo' => (string) $origen->tipocomprobante,
            'numero' => (string) $origen->numerotransaccion,
            'etiqueta' => $origen->etiquetaComprobante(),
            'fecha' => $this->fechaYmd($origen->fecha),
            'estado_actual' => (string) $origen->estado,
            'detalle_actual' => (string) $origen->detalle,
        ];

        if ((int) $origen->pagoproveedor_revertido_por_id !== (int) $aop->id) {
            $errores[] = 'La OPP original no apunta a este AOP: fue modificada después de la reversión.';
        }

        $estadoRestaurar = $this->estadoAnteriorDeOrigen((int) $origen->id);
        if (! in_array($estadoRestaurar, self::ESTADOS_RESTAURABLES, true)) {
            $errores[] = 'No se pudo determinar el estado anterior de la OPP original ('.($estadoRestaurar ?? 'sin historial').').';
        }

        // Lo que el AOP dejó fuera de la cuenta corriente, caja o retenciones no se deshace solo.
        if (Caja_Movimiento::query()->where('pagoproveedor_id', (int) $aop->id)->exists()
            || (int) ($aop->caja_movimiento_id ?? 0) > 0) {
            $errores[] = 'El AOP tiene movimiento de caja asociado. Este caso no se deshace desde la pantalla.';
        }
        if (Cheque::query()->where('pagoproveedor_id', (int) $aop->id)->exists()
            || Cheque::query()->where('pagoproveedor_id', (int) $origen->id)->exists()) {
            $errores[] = 'La OP tiene cheques asociados. Este caso no se deshace desde la pantalla.';
        }
        if (Pagoproveedor_Retencion::query()->whereIn('pagoproveedor_id', [(int) $aop->id, (int) $origen->id])->exists()) {
            $errores[] = 'La OP tiene retenciones. Este caso no se deshace desde la pantalla.';
        }

        $asientos = Asiento::query()->where('pagoproveedor_id', (int) $aop->id)->get(['id', 'numeroasiento', 'anita_origen']);
        foreach ($asientos as $asiento) {
            if (trim((string) ($asiento->anita_origen ?? '')) !== '') {
                $errores[] = 'El asiento '.$asiento->numeroasiento.' del AOP viene de Anita y no se borra desde la pantalla.';
            }
        }

        try {
            PeriodoContableCierreSupport::assertOperacionPermitida(
                (int) $aop->empresa_id,
                $this->fechaYmd($aop->fecha),
                PeriodoContableCierreSupport::ALCANCE_CAJA
            );
        } catch (RuntimeException $e) {
            $errores[] = $e->getMessage();
        }

        // Lectura en Anita: 0 filas es válido (reintento tras un corte); más de una no se borra a ciegas.
        $anitaFilas = null;
        if (strtoupper((string) $aop->tipocomprobante) === 'AOP') {
            try {
                $anitaFilas = $this->cuentacorrienteAnitaSyncService->contarPromovCabeceraAop($aop);
            } catch (\Throwable $e) {
                $errores[] = 'No se pudo consultar Anita: '.$e->getMessage();
            }
        }
        if ($anitaFilas !== null && $anitaFilas > 1) {
            $errores[] = 'En Anita hay '.$anitaFilas.' cabeceras AOP con la misma clave; se esperaba una. No se borra.';
        }

        $cambios = [
            'anita_filas_aop' => $anitaFilas,
            'cc_aplicaciones_aop' => Proveedor_Cuentacorriente_Aplicacion::query()->where('pagoproveedor_id', (int) $aop->id)->count(),
            'cc_filas_aop' => Proveedor_Cuentacorriente::query()->where('pagoproveedor_id', (int) $aop->id)->count(),
            'comprobantes_aop' => Pagoproveedor_Comprobante::query()->where('pagoproveedor_id', (int) $aop->id)->count(),
            'asientos_aop' => $asientos->map(fn ($a) => (string) $a->numeroasiento)->values()->all(),
            'estados_aop' => Pagoproveedor_Estado::query()->where('pagoproveedor_id', (int) $aop->id)->count(),
            'restaurar_estado' => $estadoRestaurar,
            'restaurar_detalle' => $this->detalleRestaurado((string) $origen->detalle),
        ];

        return $this->resultadoPlan($errores, $aopData, $origenData, $cambios);
    }

    /**
     * @return array{mensaje: string, aop_id: int, pagoproveedor_id: int, estado_restaurado: string}
     */
    public function deshacer(int $aopId): array
    {
        $plan = $this->planificar($aopId);
        if (! $plan['puede_deshacer']) {
            throw new RuntimeException(implode(' ', $plan['errores']));
        }

        $aop = Pagoproveedor::query()->findOrFail($aopId);
        $origenId = (int) $aop->pagoproveedor_origen_id;

        // 1) Anita: solo la cabecera promov de tipo AOP (incluirAop=false → tipo de la fila, AOP).
        //    Si falla, se corta acá y el ERP no cambió.
        $this->cuentacorrienteAnitaSyncService->eliminarPromovCabeceraPorPago($aop, false);

        // 2) ERP: todo en una transacción, revalidando sobre filas bloqueadas.
        $estadoRestaurado = (string) $plan['cambios']['restaurar_estado'];
        $detalleRestaurado = (string) $plan['cambios']['restaurar_detalle'];

        DB::transaction(function () use ($aopId, $origenId, $estadoRestaurado, $detalleRestaurado) {
            $aopBloqueado = Pagoproveedor::query()->whereKey($aopId)->lockForUpdate()->first();
            $origenBloqueado = Pagoproveedor::query()->whereKey($origenId)->lockForUpdate()->first();
            if ($aopBloqueado === null || $origenBloqueado === null) {
                throw new RuntimeException('El AOP o la OPP original ya no existen.');
            }
            if ((int) $origenBloqueado->pagoproveedor_revertido_por_id !== (int) $aopBloqueado->id
                || (string) $aopBloqueado->estado !== 'REVERTIDA') {
                throw new RuntimeException('El estado cambió mientras se deshacía el AOP. Revise la OP antes de reintentar.');
            }

            // Cuenta corriente del AOP: aplicaciones primero (el modelo exige el permiso de borrado).
            ProveedorCuentacorrienteAplicacionBorradoSupport::permitir(function () use ($aopBloqueado) {
                ProveedorCuentacorrienteAplicacionBorradoSupport::borrar(
                    Proveedor_Cuentacorriente_Aplicacion::query()->where('pagoproveedor_id', (int) $aopBloqueado->id)
                );
                EloquentAuditDeleteSupport::each(
                    Proveedor_Cuentacorriente::query()->where('pagoproveedor_id', (int) $aopBloqueado->id)
                );
            });

            EloquentAuditDeleteSupport::each(
                Pagoproveedor_Comprobante::query()->where('pagoproveedor_id', (int) $aopBloqueado->id)
            );

            // Quitar la referencia al asiento antes de borrarlo (la FK del AOP apunta a él).
            $this->pagoproveedorRepository->update(['asiento_id' => null], (int) $aopBloqueado->id);

            foreach (Asiento::query()->where('pagoproveedor_id', (int) $aopBloqueado->id)->get() as $asiento) {
                $this->asientoRepository->delete((int) $asiento->id);
            }

            EloquentAuditDeleteSupport::each(
                Pagoproveedor_Estado::query()->where('pagoproveedor_id', (int) $aopBloqueado->id)
            );

            $this->pagoproveedorRepository->update(['estado' => 'BAJA'], (int) $aopBloqueado->id);
            $this->pagoproveedorRepository->delete((int) $aopBloqueado->id);

            // OPP original: vuelve a estar viva, con su historial intacto y una fila de constancia.
            $this->pagoproveedorRepository->update([
                'estado' => $estadoRestaurado,
                'pagoproveedor_revertido_por_id' => null,
                'detalle' => $detalleRestaurado,
            ], (int) $origenBloqueado->id);

            Pagoproveedor_Estado::query()->create([
                'pagoproveedor_id' => (int) $origenBloqueado->id,
                'fecha' => now(),
                'estado' => $estadoRestaurado,
                'usuario_id' => Auth::id(),
                'observacion' => 'Anulación de OP deshecha: se borró el AOP '.$aopBloqueado->numerotransaccion
                    .' (id '.$aopBloqueado->id.') y se restauró la OP.',
            ]);
        });

        return [
            'mensaje' => 'ok',
            'aop_id' => $aopId,
            'pagoproveedor_id' => $origenId,
            'estado_restaurado' => $estadoRestaurado,
        ];
    }

    /**
     * Estado anterior a la reversión: último estado del historial de la OPP que no sea REVERTIDA.
     */
    private function estadoAnteriorDeOrigen(int $origenId): ?string
    {
        $estado = Pagoproveedor_Estado::query()
            ->where('pagoproveedor_id', $origenId)
            ->where('estado', '!=', 'REVERTIDA')
            ->orderByDesc('id')
            ->value('estado');

        return $estado !== null ? strtoupper((string) $estado) : null;
    }

    /**
     * Quita el prefijo que puso la reversión ("REVERTIDA: …" o "REVERTIDA").
     */
    private function detalleRestaurado(string $detalleActual): string
    {
        $detalle = trim($detalleActual);
        if (strcasecmp($detalle, 'REVERTIDA') === 0) {
            return '';
        }

        return trim((string) preg_replace('/^REVERTIDA:\s*/iu', '', $detalle, 1));
    }

    /**
     * @param  list<string>  $errores
     * @param  array<string, mixed>  $aop
     * @param  array<string, mixed>  $origen
     * @param  array<string, mixed>  $cambios
     * @return array{puede_deshacer: bool, errores: list<string>, aop: array<string, mixed>, origen: array<string, mixed>, cambios: array<string, mixed>}
     */
    private function resultadoPlan(array $errores, array $aop, array $origen, array $cambios): array
    {
        return [
            'puede_deshacer' => $errores === [],
            'errores' => $errores,
            'aop' => $aop,
            'origen' => $origen,
            'cambios' => $cambios,
        ];
    }

    private function fechaYmd($fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        return (string) ($fecha ?: date('Y-m-d'));
    }
}

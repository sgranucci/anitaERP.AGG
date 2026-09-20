<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Retención de pago al estilo MRBR/ZLSPR de SAP: la factura se contabiliza y queda en la cuenta
 * del proveedor, pero ninguna propuesta de pago la toma hasta que alguien la libere y explique
 * por qué. Es la alternativa a devolver el legajo a Compras: cierra el circuito contable sin
 * habilitar el pago de una diferencia no resuelta.
 */
class ComprobanteProveedorBloqueoPagoService
{
    /**
     * Bloqueo puesto por el control de importes, sin usuario detrás.
     */
    public function bloquearPorControlAutomatico(Comprobante_Proveedor $comprobante, string $motivo): void
    {
        $this->aplicarBloqueo($comprobante, $motivo, null);
    }

    public function bloquearManual(Comprobante_Proveedor $comprobante, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Indique el motivo del bloqueo para pago.',
            ]);
        }

        $this->aplicarBloqueo($comprobante, $motivo, Auth::id() ? (int) Auth::id() : null);
    }

    /**
     * Liberación explícita. Si la diferencia que originó el bloqueo sigue vigente se avisa en el
     * rastro, pero no se impide liberar: la decisión de pagar una diferencia es del negocio.
     */
    public function liberar(Comprobante_Proveedor $comprobante, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Indique el motivo de la liberación para pago.',
            ]);
        }

        DB::transaction(function () use ($comprobante, $motivo) {
            $fresco = Comprobante_Proveedor::query()
                ->whereKey($comprobante->getKey())
                ->lockForUpdate()
                ->first();

            if (! $fresco || ! $this->estaBloqueado($fresco)) {
                throw ValidationException::withMessages([
                    'comprobante_proveedor_id' => 'El comprobante no está bloqueado para pago.',
                ]);
            }

            $fresco->forceFill([
                'bloqueado_pago' => false,
                'liberado_pago_at' => now(),
                'liberado_pago_user_id' => Auth::id() ? (int) Auth::id() : null,
                'liberado_pago_motivo' => mb_substr($motivo, 0, 500),
            ])->save();

            Log::info('comprobante_proveedor.bloqueo_pago.liberado', [
                'comprobante_proveedor_id' => (int) $fresco->id,
                'user_id' => Auth::id() ? (int) Auth::id() : null,
                'motivo' => $motivo,
            ]);
        });

        $comprobante->refresh();
    }

    public function estaBloqueado(Comprobante_Proveedor $comprobante): bool
    {
        return (bool) ($comprobante->getAttributes()['bloqueado_pago'] ?? false);
    }

    /**
     * Frena el armado de una OP que incluya facturas retenidas.
     *
     * @param  list<int>  $cuentacorrienteIds
     */
    public function assertNingunaBloqueada(array $cuentacorrienteIds): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $cuentacorrienteIds),
            static fn (int $id) => $id > 0
        )));
        if ($ids === []) {
            return;
        }

        $bloqueadas = DB::table('proveedor_cuentacorriente as cc')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'cc.comprobante_proveedor_id')
            ->whereIn('cc.id', $ids)
            ->where('cp.bloqueado_pago', true)
            ->select('cp.letra', 'cp.sucursal', 'cp.numerocomprobante', 'cp.bloqueado_pago_motivo')
            ->get();

        if ($bloqueadas->isEmpty()) {
            return;
        }

        $detalle = $bloqueadas->map(static function ($cp) {
            $nro = trim(implode('-', array_filter([
                strtoupper((string) ($cp->letra ?? '')),
                (string) ($cp->sucursal ?? ''),
                (string) ($cp->numerocomprobante ?? ''),
            ], static fn ($v) => (string) $v !== '')));

            return $nro.' ('.trim((string) ($cp->bloqueado_pago_motivo ?? 'sin motivo registrado')).')';
        })->implode('; ');

        throw ValidationException::withMessages([
            'idcuentacorrientes' => 'No se puede pagar: '.$bloqueadas->count()
                .' comprobante(s) están bloqueados para pago: '.$detalle
                .'. Libere el bloqueo con su motivo antes de incluirlos en una orden de pago.',
        ]);
    }

    private function aplicarBloqueo(Comprobante_Proveedor $comprobante, string $motivo, ?int $userId): void
    {
        $comprobante->forceFill([
            'bloqueado_pago' => true,
            'bloqueado_pago_motivo' => mb_substr($motivo, 0, 500),
            'bloqueado_pago_at' => now(),
            'bloqueado_pago_user_id' => $userId,
            // Un bloqueo nuevo invalida la liberación anterior.
            'liberado_pago_at' => null,
            'liberado_pago_user_id' => null,
            'liberado_pago_motivo' => null,
        ])->save();

        Log::info('comprobante_proveedor.bloqueo_pago.aplicado', [
            'comprobante_proveedor_id' => (int) $comprobante->id,
            'automatico' => $userId === null,
            'motivo' => $motivo,
        ]);
    }
}

<?php

namespace App\Support\Compras;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Comprobante;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Detecta pagos (OP) aplicados sobre la cuenta corriente de un comprobante de proveedor.
 *
 * La fila de deuda NO lleva pagoproveedor_id: el pago crea otra fila CC y/o
 * registros en pagoproveedor_comprobante / proveedor_cuentacorriente_aplicacion.
 *
 * Revertir una OP no borra esa traza: compensa con una AOP de signo opuesto.
 * El comprobante queda impago cuando el neto de esas filas vuelve a cero.
 */
final class ComprobanteProveedorPagoSupport
{
    private const TOLERANCIA = 0.01;

    /** @return list<int> */
    public static function idsCuentacorrienteDeuda(int $comprobanteId): array
    {
        if ($comprobanteId <= 0) {
            return [];
        }

        $desdeCuotas = DB::table('comprobante_proveedor_cuota')
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->whereNotNull('proveedor_cuentacorriente_id')
            ->pluck('proveedor_cuentacorriente_id');

        $desdeCc = DB::table('proveedor_cuentacorriente')
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->where(function ($q) {
                $q->whereNull('pagoproveedor_id')
                    ->orWhere('pagoproveedor_id', 0);
            })
            ->pluck('id');

        return $desdeCuotas
            ->merge($desdeCc)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function tienePagosAplicados(int $comprobanteId): bool
    {
        if ($comprobanteId <= 0) {
            return false;
        }

        $ccIds = self::idsCuentacorrienteDeuda($comprobanteId);
        if ($ccIds !== []) {
            $netoAplicado = (float) DB::table('proveedor_cuentacorriente_aplicacion')
                ->whereIn('proveedor_cuentacorriente_id', $ccIds)
                ->sum('total');
            if (abs($netoAplicado) > self::TOLERANCIA) {
                return true;
            }

            $netoOp = (float) DB::table('pagoproveedor_comprobante')
                ->whereIn('proveedor_cuentacorriente_id', $ccIds)
                ->sum('montoaplicado');
            if (abs($netoOp) > self::TOLERANCIA) {
                return true;
            }
        }

        // Filas de la OP/AOP que todavía apuntan a esta factura. Una reversión
        // deja el par con neto cero; un pago vigente no.
        $netoPago = (float) DB::table('proveedor_cuentacorriente')
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->where('pagoproveedor_id', '>', 0)
            ->sum('total');

        return abs($netoPago) > self::TOLERANCIA;
    }

    /**
     * Suelta la traza de una OP ya revertida (neto cero) antes de borrar la deuda.
     *
     * Cierra la OPP contra su AOP, así el saldo de cada una sigue en cero, y
     * desvincula ese par de la factura. Sin esto, la FK de pagoproveedor_comprobante
     * impide borrar la cuenta corriente y, si se borrara en cascada, la OP volvería
     * a quedar abierta.
     */
    public static function liberarTrazasDePagosCompensados(int $comprobanteId): void
    {
        if ($comprobanteId <= 0) {
            return;
        }

        DB::transaction(function () use ($comprobanteId) {
            self::liberarTrazasDePagosCompensadosEnTransaccion($comprobanteId);
        });
    }

    public static function assertSinPagosAplicados(int $comprobanteId, string $accion = 'modificar'): void
    {
        if (self::tienePagosAplicados($comprobanteId)) {
            throw new \RuntimeException(
                'No se puede '.$accion.': el comprobante ya tiene pagos aplicados (parcial o total).'
            );
        }
    }

    private static function liberarTrazasDePagosCompensadosEnTransaccion(int $comprobanteId): void
    {
        if (self::tienePagosAplicados($comprobanteId)) {
            throw new RuntimeException(
                'No se puede reeditar: el comprobante todavía tiene pagos aplicados.'
            );
        }

        $deudaIds = self::idsCuentacorrienteDeuda($comprobanteId);
        if ($deudaIds !== []) {
            Proveedor_Cuentacorriente::query()->whereIn('id', $deudaIds)->lockForUpdate()->get();
        }

        $pagosCc = Proveedor_Cuentacorriente::query()
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->where('pagoproveedor_id', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($pagosCc->isNotEmpty()) {
            self::cerrarParesOpContraAop($pagosCc, $deudaIds);
            self::assertDeudaSoloTieneElParCompensado($deudaIds, $pagosCc->pluck('id')->map(fn ($id) => (int) $id)->all());
        } elseif ($deudaIds !== []) {
            self::assertDeudaSoloTieneElParCompensado($deudaIds, []);
        }

        self::borrarVinculosDeudaCompensada($deudaIds);

        if ($pagosCc->isNotEmpty()) {
            Proveedor_Cuentacorriente::query()
                ->whereIn('id', $pagosCc->pluck('id')->all())
                ->update([
                    'comprobante_proveedor_id' => null,
                    'comprobante_proveedor_cuota_id' => null,
                ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Proveedor_Cuentacorriente>  $pagosCc
     * @param  list<int>  $deudaIds
     */
    private static function cerrarParesOpContraAop($pagosCc, array $deudaIds): void
    {
        $pagoIds = $pagosCc->pluck('pagoproveedor_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $pagos = Pagoproveedor::query()->whereIn('id', $pagoIds)->get()->keyBy(fn ($pago) => (int) $pago->id);
        $usados = [];

        foreach ($pagosCc as $ccAop) {
            $pagoAop = $pagos->get((int) $ccAop->pagoproveedor_id);
            $origenId = (int) ($pagoAop->pagoproveedor_origen_id ?? 0);
            if ($origenId <= 0) {
                continue;
            }

            $ccOrig = $pagosCc->first(function ($cc) use ($origenId, $ccAop, $usados) {
                if (isset($usados[(int) $cc->id])) {
                    return false;
                }
                if ((int) $cc->pagoproveedor_id !== $origenId) {
                    return false;
                }

                return abs(abs((float) $cc->total) - abs((float) $ccAop->total)) <= self::TOLERANCIA;
            });
            if ($ccOrig === null) {
                throw new RuntimeException(
                    'No se pudo emparejar la anulación de la OP '.$ccAop->pagoproveedor_id
                    .' para reeditar el comprobante.'
                );
            }

            $usados[(int) $ccOrig->id] = true;
            $usados[(int) $ccAop->id] = true;
            if ($deudaIds === []) {
                continue;
            }

            $etiqOrig = $pagos->get((int) $ccOrig->pagoproveedor_id)?->etiquetaComprobante() ?? 'OPP';
            $etiqAop = $pagoAop?->etiquetaComprobante() ?? 'AOP';

            Proveedor_Cuentacorriente_Aplicacion::query()
                ->where('proveedor_cuentacorriente_id', (int) $ccOrig->id)
                ->whereIn('proveedor_cuentacorriente_aplicado_id', $deudaIds)
                ->update([
                    'proveedor_cuentacorriente_aplicado_id' => (int) $ccAop->id,
                    'comprobante_proveedor_aplicado_id' => null,
                    'comprobanteaplicado' => $etiqAop,
                ]);

            Proveedor_Cuentacorriente_Aplicacion::query()
                ->where('proveedor_cuentacorriente_id', (int) $ccAop->id)
                ->whereIn('proveedor_cuentacorriente_aplicado_id', $deudaIds)
                ->update([
                    'proveedor_cuentacorriente_aplicado_id' => (int) $ccOrig->id,
                    'comprobante_proveedor_aplicado_id' => null,
                    'comprobanteaplicado' => $etiqOrig,
                ]);
        }
    }

    /**
     * @param  list<int>  $deudaIds
     * @param  list<int>  $pagoCcIds
     */
    private static function assertDeudaSoloTieneElParCompensado(array $deudaIds, array $pagoCcIds): void
    {
        if ($deudaIds === []) {
            return;
        }

        $sigueApuntando = Proveedor_Cuentacorriente_Aplicacion::query()
            ->whereIn('proveedor_cuentacorriente_aplicado_id', $deudaIds)
            ->whereNotIn('proveedor_cuentacorriente_id', $deudaIds)
            ->exists();
        if ($sigueApuntando) {
            throw new RuntimeException(
                'La factura sigue referenciada por una aplicación de pago y no se puede reeditar.'
            );
        }

        $otros = Proveedor_Cuentacorriente_Aplicacion::query()
            ->whereIn('proveedor_cuentacorriente_id', $deudaIds)
            ->whereNotNull('proveedor_cuentacorriente_aplicado_id')
            ->when($pagoCcIds !== [], fn ($q) => $q->whereNotIn('proveedor_cuentacorriente_aplicado_id', $pagoCcIds))
            ->exists();
        if ($otros) {
            throw new RuntimeException(
                'La factura tiene aplicaciones que no son la reversión de la OP y no se puede reeditar.'
            );
        }
    }

    /**
     * @param  list<int>  $deudaIds
     */
    private static function borrarVinculosDeudaCompensada(array $deudaIds): void
    {
        if ($deudaIds === []) {
            return;
        }

        Proveedor_Cuentacorriente_Aplicacion::query()
            ->whereIn('proveedor_cuentacorriente_id', $deudaIds)
            ->delete();

        Pagoproveedor_Comprobante::query()
            ->whereIn('proveedor_cuentacorriente_id', $deudaIds)
            ->delete();
    }
}

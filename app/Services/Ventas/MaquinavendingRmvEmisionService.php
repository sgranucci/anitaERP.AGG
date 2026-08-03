<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;
use App\Repositories\Ventas\VentaRepositoryInterface;
use App\Support\Ventas\MaquinavendingRmvMontosSupport;
use App\Support\Ventas\MaquinavendingRmvTipoSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

/**
 * Emite RMV interno (letra Z, PV manual) al cierre contable vending.
 * Equivalente a p-vtagastro.c graba_comprobante / graba_venta para EXPENDEDORAS.
 */
class MaquinavendingRmvEmisionService
{
    public function __construct(
        private readonly VentaRepositoryInterface $ventaRepository,
    ) {
    }

    /**
     * @param  Collection<int, RendicionMaquinavendingCaja>  $rendiciones
     * @return array{venta_id: int, codigo: string, numerocomprobante: int}
     */
    public function emitirParaGrupo(Collection $rendiciones, string $fechaDia): array
    {
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay rendiciones para emitir RMV.');
        }

        $primera = $rendiciones->first();
        $puntoventaId = (int) ($primera->puntoventa_cae_id ?? 0);
        if ($puntoventaId <= 0) {
            throw new InvalidArgumentException('El grupo no tiene punto de venta para RMV.');
        }

        $puntoventa = Puntoventa::query()->find($puntoventaId);
        if ($puntoventa === null) {
            throw new InvalidArgumentException('Punto de venta #'.$puntoventaId.' inexistente.');
        }

        $modo = strtoupper(trim((string) ($puntoventa->modofacturacion ?? '')));
        if ($modo !== 'M') {
            throw new InvalidArgumentException(
                'El PV '.$puntoventa->codigo.' debe ser modo normal/manual (M) para RMV interno.',
            );
        }

        foreach ($rendiciones as $rendicion) {
            if ((int) ($rendicion->venta_id ?? 0) > 0) {
                throw new InvalidArgumentException(
                    'La rendición #'.$rendicion->id.' ya tiene RMV vinculado (venta #'.$rendicion->venta_id.').',
                );
            }
        }

        $montos = MaquinavendingRmvMontosSupport::desdeRendiciones($rendiciones);
        if ($montos['total'] <= 0.0001) {
            throw new InvalidArgumentException('El total a facturar del grupo es cero; no se emite RMV.');
        }

        $tipo = MaquinavendingRmvTipoSupport::tipo();
        $cliente = Cliente::query()->find((int) config('facturacion.CLIENTE_CONSUMIDOR_FINAL_ID', 1));
        if ($cliente === null) {
            throw new RuntimeException('Cliente consumidor final (000000) no encontrado.');
        }

        $empresaId = (int) ($primera->empresa_id ?? $puntoventa->empresa_id ?? 0);
        $ultimo = $this->ventaRepository->traeUltimoComprobanteVenta(
            (int) $tipo->id,
            $puntoventaId,
            $empresaId > 0 ? $empresaId : null,
        );
        $numero = (int) ($ultimo->numerocomprobante ?? 0) + 1;

        $digitosSuc = (int) config('facturacion.DIGITOS_SUCURSAL', 5);
        $digitosComp = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);
        $letra = MaquinavendingRmvTipoSupport::LETRA;
        $codigo = $tipo->abreviatura.' '.$letra.'-'
            .str_pad((string) $puntoventa->codigo, $digitosSuc, '0', STR_PAD_LEFT).'-'
            .str_pad((string) $numero, $digitosComp, '0', STR_PAD_LEFT);

        $ids = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $leyenda = 'RMV vending '
            .date('d/m/Y', strtotime($fechaDia))
            .' — PV '.$puntoventa->codigo
            .' — rend. '.implode(', ', $ids);

        $venta = Venta::query()->create([
            'fecha' => $fechaDia,
            'fechajornada' => $fechaDia,
            'tipotransaccion_id' => (int) $tipo->id,
            'puntoventa_id' => $puntoventaId,
            'numerocomprobante' => $numero,
            'actividad_arca_id' => 1,
            'cliente_id' => (int) $cliente->id,
            'condicionventa_id' => $cliente->condicionventa_id,
            'total' => $montos['total'],
            'moneda_id' => 1,
            'cotizacion' => 1,
            'estado' => ' ',
            'usuario_id' => (int) (Auth::id() ?: 1),
            'leyenda' => $leyenda,
            'descuento' => 0,
            'descuentointegrado' => ' ',
            'codigo' => $codigo,
            'nombre' => MaquinavendingRmvTipoSupport::NOMBRE_CLIENTE,
            'domicilio' => (string) ($cliente->domicilio ?? ''),
            'localidad_id' => $cliente->localidad_id,
            'provincia_id' => $cliente->provincia_id,
            'pais_id' => $cliente->pais_id,
            'condicioniva_id' => (int) ($cliente->condicioniva_id ?? 3),
        ]);

        $this->crearImpuestos(
            (int) $venta->id,
            $montos['total'],
            $montos['gravado'],
            $montos['iva'],
            $montos['exento'],
        );

        return [
            'venta_id' => (int) $venta->id,
            'codigo' => $codigo,
            'numerocomprobante' => $numero,
        ];
    }

    public function anularSiExiste(?int $ventaId): void
    {
        if ($ventaId === null || $ventaId <= 0) {
            return;
        }

        $venta = Venta::query()->find($ventaId);
        if ($venta === null) {
            return;
        }

        $venta->loadMissing('tipotransacciones');
        if (! MaquinavendingRmvTipoSupport::esRmv($venta->tipotransacciones)) {
            throw new InvalidArgumentException(
                'La venta #'.$ventaId.' no es RMV; no se anula desde cierre vending.',
            );
        }

        Venta_Impuesto::query()->where('venta_id', $ventaId)->delete();
        $venta->delete();
    }

    /**
     * Recalcula venta_impuestos del RMV con neto = total/1.21 (alineado al asiento).
     *
     * @return array{venta_id: int, codigo: string, gravado: float, iva: float, exento: float, total: float}
     */
    public function recalcularImpuestos(Venta $venta): array
    {
        $venta->loadMissing('tipotransacciones');
        if (! MaquinavendingRmvTipoSupport::esRmv($venta->tipotransacciones)) {
            throw new InvalidArgumentException('La venta #'.$venta->id.' no es RMV.');
        }

        $rendiciones = RendicionMaquinavendingCaja::query()
            ->with(['maquinavendingRendicion.articulos.articulo'])
            ->where('venta_id', $venta->id)
            ->get();

        if ($rendiciones->isEmpty()) {
            $montos = MaquinavendingRmvMontosSupport::partirTotalConIva((float) $venta->total);
        } else {
            $montos = MaquinavendingRmvMontosSupport::desdeRendiciones($rendiciones);
        }

        Venta_Impuesto::query()->where('venta_id', $venta->id)->delete();
        $this->crearImpuestos(
            (int) $venta->id,
            $montos['total'],
            $montos['gravado'],
            $montos['iva'],
            $montos['exento'],
        );

        if (abs((float) $venta->total - $montos['total']) > 0.0001) {
            $venta->update(['total' => $montos['total']]);
        }

        return [
            'venta_id' => (int) $venta->id,
            'codigo' => (string) $venta->codigo,
            'gravado' => $montos['gravado'],
            'iva' => $montos['iva'],
            'exento' => $montos['exento'],
            'total' => $montos['total'],
        ];
    }

    private function crearImpuestos(
        int $ventaId,
        float $total,
        float $gravado,
        float $iva,
        float $exento,
    ): void {
        if ($gravado > 0.0001) {
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => 'Subtotal',
                'importe' => $gravado,
                'baseimponible' => $gravado,
                'tasa' => 0,
            ]);
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => 'Gravado al 21.000%',
                'importe' => $gravado,
                'baseimponible' => $gravado,
                'tasa' => 21,
                'impuesto_id' => 3,
            ]);
        }

        if ($iva > 0.0001) {
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => 'Iva 21.000%',
                'importe' => $iva,
                'baseimponible' => $gravado,
                'tasa' => 21,
                'impuesto_id' => 3,
            ]);
        }

        if ($exento > 0.0001) {
            Venta_Impuesto::query()->create([
                'venta_id' => $ventaId,
                'concepto' => 'Exento',
                'importe' => $exento,
                'baseimponible' => 0,
                'tasa' => 0,
                'impuesto_id' => 1,
            ]);
        }

        Venta_Impuesto::query()->create([
            'venta_id' => $ventaId,
            'concepto' => 'Total',
            'importe' => $total,
            'baseimponible' => 0,
            'tasa' => 0,
        ]);
    }
}

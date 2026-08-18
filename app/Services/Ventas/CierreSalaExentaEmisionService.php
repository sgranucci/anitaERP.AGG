<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;
use App\Repositories\Ventas\VentaRepositoryInterface;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

/**
 * Emite FBI/FSL internos 100% exentos en ventas ERP (sin bridge Anita venta).
 * Paridad p-vtabingo.c / p-vtamaquina.c graba_comprobante + graba_venta.
 */
class CierreSalaExentaEmisionService
{
    public function __construct(
        private readonly VentaRepositoryInterface $ventaRepository,
    ) {
    }

    /**
     * @return array{
     *   venta_id: int,
     *   codigo: string,
     *   numerocomprobante: int,
     *   tipo: string,
     *   letra: string,
     *   sucursal: int,
     *   nro: int,
     *   monto: float
     * }
     */
    public function emitir(
        Tipotransaccion $tipo,
        string $letra,
        string $nombreCliente,
        int $empresaId,
        string $fechaDia,
        float $monto,
        int $codigoSucursal,
        string $leyenda,
    ): array {
        $monto = round($monto, 2);
        if ($monto <= 0.0001) {
            throw new InvalidArgumentException(
                'No hay monto para emitir '.$tipo->abreviatura.' exenta.',
            );
        }

        $puntoventa = $this->resolverPuntoventa($empresaId, $codigoSucursal);
        $modo = strtoupper(trim((string) ($puntoventa->modofacturacion ?? '')));
        if ($modo !== 'M') {
            throw new InvalidArgumentException(
                'El PV '.$puntoventa->codigo.' debe ser modo normal/manual (M) para '
                .$tipo->abreviatura.' interno.',
            );
        }

        $cliente = Cliente::query()->find((int) config('facturacion.CLIENTE_CONSUMIDOR_FINAL_ID', 1));
        if ($cliente === null) {
            throw new RuntimeException('Cliente consumidor final (000000) no encontrado.');
        }

        $ultimo = $this->ventaRepository->traeUltimoComprobanteVenta(
            (int) $tipo->id,
            (int) $puntoventa->id,
            $empresaId,
        );
        $numero = (int) ($ultimo->numerocomprobante ?? 0) + 1;

        $digitosSuc = (int) config('facturacion.DIGITOS_SUCURSAL', 5);
        $digitosComp = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);
        $codigoPv = (int) ltrim((string) $puntoventa->codigo, '0');
        if ($codigoPv <= 0) {
            $codigoPv = (int) $puntoventa->codigo;
        }
        $codigo = $tipo->abreviatura.' '.$letra.'-'
            .str_pad((string) $codigoPv, $digitosSuc, '0', STR_PAD_LEFT).'-'
            .str_pad((string) $numero, $digitosComp, '0', STR_PAD_LEFT);

        $venta = Venta::query()->create([
            'fecha' => $fechaDia,
            'fechajornada' => $fechaDia,
            'tipotransaccion_id' => (int) $tipo->id,
            'puntoventa_id' => (int) $puntoventa->id,
            'numerocomprobante' => $numero,
            'actividad_arca_id' => 1,
            'cliente_id' => (int) $cliente->id,
            'condicionventa_id' => $cliente->condicionventa_id,
            'total' => $monto,
            'moneda_id' => 1,
            'cotizacion' => 1,
            'estado' => ' ',
            'usuario_id' => (int) (Auth::id() ?: 1),
            'leyenda' => $leyenda,
            'descuento' => 0,
            'descuentointegrado' => ' ',
            'codigo' => $codigo,
            'nombre' => $nombreCliente,
            'domicilio' => (string) ($cliente->domicilio ?? ''),
            'localidad_id' => $cliente->localidad_id,
            'provincia_id' => $cliente->provincia_id,
            'pais_id' => $cliente->pais_id,
            'condicioniva_id' => (int) ($cliente->condicioniva_id ?? 3),
        ]);

        Venta_Impuesto::query()->create([
            'venta_id' => (int) $venta->id,
            'concepto' => 'Exento',
            'importe' => $monto,
            'baseimponible' => 0,
            'tasa' => 0,
            'impuesto_id' => 1,
        ]);
        Venta_Impuesto::query()->create([
            'venta_id' => (int) $venta->id,
            'concepto' => 'Total',
            'importe' => $monto,
            'baseimponible' => 0,
            'tasa' => 0,
        ]);

        return [
            'venta_id' => (int) $venta->id,
            'codigo' => $codigo,
            'numerocomprobante' => $numero,
            'tipo' => (string) $tipo->abreviatura,
            'letra' => $letra,
            'sucursal' => $codigoPv,
            'nro' => $numero,
            'monto' => $monto,
        ];
    }

    public function anularSiExiste(?int $ventaId, callable $esTipoEsperado, string $etiquetaTipo): void
    {
        if ($ventaId === null || $ventaId <= 0) {
            return;
        }

        $venta = Venta::query()->find($ventaId);
        if ($venta === null) {
            return;
        }

        $venta->loadMissing('tipotransacciones');
        if (! $esTipoEsperado($venta->tipotransacciones)) {
            throw new InvalidArgumentException(
                'La venta #'.$ventaId.' no es '.$etiquetaTipo.'; no se anula desde este cierre.',
            );
        }

        EloquentAuditDeleteSupport::each(
            Venta_Impuesto::query()->where('venta_id', $ventaId)
        );
        $venta->delete();
    }

    private function resolverPuntoventa(int $empresaId, int $codigoSucursal): Puntoventa
    {
        if ($codigoSucursal <= 0) {
            throw new InvalidArgumentException('Sucursal / PV de cierre no configurado para la empresa.');
        }

        $variantes = array_values(array_unique([
            (string) $codigoSucursal,
            str_pad((string) $codigoSucursal, 4, '0', STR_PAD_LEFT),
            str_pad((string) $codigoSucursal, 5, '0', STR_PAD_LEFT),
        ]));

        $puntoventa = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('codigo', $variantes)
            ->first();

        if ($puntoventa === null) {
            throw new InvalidArgumentException(
                'No hay punto de venta ERP con código '.$codigoSucursal
                .' para empresa #'.$empresaId.' (modo M, bingo/máquinas).',
            );
        }

        return $puntoventa;
    }
}

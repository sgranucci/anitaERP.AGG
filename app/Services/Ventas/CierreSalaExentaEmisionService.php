<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Configuracion\Actividad_Arca;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\CierreSalaExentaNumeracionSupport;
use App\Support\Ventas\VentaNumeracionEmpresaSupport;
use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Emite FBI/FSL internos 100% exentos en ventas ERP (sin bridge Anita venta).
 * Paridad p-vtabingo.c / p-vtamaquina.c graba_comprobante + graba_venta.
 */
class CierreSalaExentaEmisionService
{
    private const INTENTOS_NUMERO = 8;

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
        $puntoventa = Puntoventa::query()
            ->whereKey((int) $puntoventa->id)
            ->lockForUpdate()
            ->firstOrFail();

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

        $codigoPv = (int) ltrim((string) $puntoventa->codigo, '0');
        if ($codigoPv <= 0) {
            $codigoPv = (int) $puntoventa->codigo;
        }

        $sucursalCodigo = (string) $codigoPv;
        $numero = 0;
        $codigo = '';
        $venta = null;
        $ultimoIntento = 0;

        for ($intento = 1; $intento <= self::INTENTOS_NUMERO; $intento++) {
            $numero = CierreSalaExentaNumeracionSupport::siguienteNumero(
                (string) $tipo->abreviatura,
                $letra,
                $codigoPv,
                $empresaId,
                $fechaDia,
                (int) $puntoventa->id,
                $ultimoIntento,
            );
            $codigo = VentaNumeracionEmpresaSupport::formatearCodigoVenta(
                (string) $tipo->abreviatura,
                $letra,
                $sucursalCodigo,
                $numero,
            );

            try {
                $venta = Venta::query()->create([
                    'fecha' => $fechaDia,
                    'fechajornada' => $fechaDia,
                    'tipotransaccion_id' => (int) $tipo->id,
                    'puntoventa_id' => (int) $puntoventa->id,
                    'numerocomprobante' => $numero,
                    'actividad_arca_id' => $this->resolverActividadArcaId($puntoventa),
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
                break;
            } catch (Throwable $e) {
                if (
                    ! VentaNumerocomprobanteUnicidadSupport::esViolacionNumerocomprobante($e)
                    || $intento === self::INTENTOS_NUMERO
                ) {
                    throw $e;
                }
                $ultimoIntento = $numero;
            }
        }

        if ($venta === null) {
            throw new RuntimeException('No se pudo emitir '.$tipo->abreviatura.' (numeración).');
        }

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

    /**
     * IVA Simple agrupa por actividad ARCA. Bingo/máquinas = 920009 (apuestas),
     * no gastronomía. Preferir la del PV; si falta, actividad de apuestas.
     */
    private function resolverActividadArcaId(Puntoventa $puntoventa): int
    {
        $desdePv = (int) ($puntoventa->actividad_arca_id ?? 0);
        if ($desdePv > 0) {
            return $desdePv;
        }

        $id = (int) (Actividad_Arca::query()
            ->where('codigoarca', '920009')
            ->value('id') ?? 0);

        if ($id <= 0) {
            throw new RuntimeException(
                'Falta actividad ARCA 920009 (Servicios de apuestas) para FBI/FSL.',
            );
        }

        return $id;
    }
}

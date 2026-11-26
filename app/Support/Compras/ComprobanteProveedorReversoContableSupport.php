<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Tipotransaccion_Compra;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Gates y leyenda del reverso contable compensatorio (NC en mes abierto).
 *
 * No edita ni borra la factura original: crea una NC interna cuyo asiento es el espejo
 * del asiento de la factura (ComprobanteProveedorReversoAsientoEspejoSupport). El
 * comprobante de la NC se carga como exento / no gravado, sin IVA: no va a libros de IVA.
 *
 * Dos modos, según el estado de la factura:
 * - aplicar_a_factura: factura sin pagos. La NC se aplica en cuenta corriente a la factura.
 * - a_cuenta: factura pagada en total con órdenes de pago. La NC queda como crédito
 *   abierto del proveedor (saldo a favor) para aplicarlo después a otros pagos.
 */
final class ComprobanteProveedorReversoContableSupport
{
    public const SLUG_PERMISO = 'revertir-contable-comprobante-proveedor';

    public const MODO_APLICAR_A_FACTURA = 'aplicar_a_factura';

    public const MODO_A_CUENTA = 'a_cuenta';

    private const TOLERANCIA = 0.01;

    public const PREFIJO_LEYENDA = 'Reverso contable de CP#';

    public const ABREV_TIPO_DEFAULT = 'NCJ';

    public const LETRA_INTERNA = 'X';

    public const SUCURSAL_INTERNA = 0;

    /**
     * Chequeos baratos para mostrar el botón en el index (sin consultas extra).
     */
    public static function puedeMostrarBoton(object $comprobante): bool
    {
        if ((string) ($comprobante->estado ?? '') !== ComprobanteProveedorEstados::CONTABILIZADO) {
            return false;
        }

        if ((int) ($comprobante->asiento_id ?? 0) <= 0) {
            return false;
        }

        if (ComprobanteProveedorOrigenEntrada::esIngresoEgreso($comprobante->origen_entrada ?? null)) {
            return false;
        }

        $signo = self::signoTipo($comprobante);
        if ($signo !== 'S') {
            return false;
        }

        return true;
    }

    /**
     * Valida el reverso y devuelve el modo que corresponde a la factura.
     *
     * @return self::MODO_*
     *
     * @throws RuntimeException
     */
    public static function assertPuedeRevertir(Comprobante_Proveedor $comprobante): string
    {
        if (! self::puedeMostrarBoton($comprobante)) {
            throw new RuntimeException(
                'Solo se puede revertir una factura contabilizada con asiento, '
                .'de signo suma, que no venga de ingresos/egresos.'
            );
        }

        $id = (int) $comprobante->id;

        if (self::yaFueReversado($id)) {
            throw new RuntimeException(
                'Ya existe una NC de reverso contable para el comprobante #'.$id.'.'
            );
        }

        if (abs((float) ($comprobante->total ?? 0)) < self::TOLERANCIA) {
            throw new RuntimeException('La factura no tiene importe para revertir.');
        }

        $lineasAsiento = (int) DB::table('asiento_movimiento')
            ->where('asiento_id', (int) $comprobante->asiento_id)
            ->count();
        if ($lineasAsiento < 2) {
            throw new RuntimeException(
                'El asiento de la factura no tiene líneas en el ERP: no hay nada que dar vuelta.'
            );
        }

        if (! ComprobanteProveedorPagoSupport::tienePagosAplicados($id)) {
            return self::MODO_APLICAR_A_FACTURA;
        }

        self::assertPagadaEnTotalSoloConOrdenesDePago($id);

        return self::MODO_A_CUENTA;
    }

    /**
     * Factura con pagos: solo se acepta si la deuda quedó cancelada en total y todas sus
     * aplicaciones son de órdenes de pago. Un pago parcial o una NC aplicada a mano no entran.
     *
     * @throws RuntimeException
     */
    public static function assertPagadaEnTotalSoloConOrdenesDePago(int $comprobanteId): void
    {
        $deudaIds = ComprobanteProveedorPagoSupport::idsCuentacorrienteDeuda($comprobanteId);
        if ($deudaIds === []) {
            throw new RuntimeException(
                'No se encontró la deuda de la factura en cuenta corriente: no se puede verificar el pago.'
            );
        }

        $deudas = DB::table('proveedor_cuentacorriente')
            ->whereIn('id', $deudaIds)
            ->pluck('total', 'id');
        $aplicados = DB::table('proveedor_cuentacorriente_aplicacion')
            ->whereIn('proveedor_cuentacorriente_id', $deudaIds)
            ->selectRaw('proveedor_cuentacorriente_id, SUM(total) as aplicado')
            ->groupBy('proveedor_cuentacorriente_id')
            ->pluck('aplicado', 'proveedor_cuentacorriente_id');

        foreach ($deudaIds as $deudaId) {
            $saldo = ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
                (float) ($deudas[$deudaId] ?? 0),
                isset($aplicados[$deudaId]) ? (float) $aplicados[$deudaId] : 0.0
            );
            if ($saldo >= self::TOLERANCIA) {
                throw new RuntimeException(
                    'La factura tiene un pago parcial (saldo pendiente '
                    .number_format($saldo, 2, ',', '.').'). '
                    .'El reverso a cuenta solo se hace con la factura pagada en total.'
                );
            }
        }

        $aplicadaFueraDeOp = DB::table('proveedor_cuentacorriente_aplicacion')
            ->whereIn('proveedor_cuentacorriente_id', $deudaIds)
            ->where(function ($q) {
                $q->whereNull('pagoproveedor_id')->orWhere('pagoproveedor_id', 0);
            })
            ->exists();
        if ($aplicadaFueraDeOp) {
            throw new RuntimeException(
                'La factura tiene aplicaciones que no son de una orden de pago (por ejemplo una NC aplicada). '
                .'Hay que desaplicarlas antes de revertir.'
            );
        }
    }

    /**
     * Concepto del comprobante de la NC: exento / no gravado, sin IVA.
     * Se resuelve por el código de Anita (ComprobanteProveedorConceptoIvaTipos).
     *
     * @throws RuntimeException
     */
    public static function resolverConceptoExento(): Concepto_Ivacompra
    {
        $codigo = ComprobanteProveedorConceptoIvaTipos::CODIGOS_EXENTO_NO_GRAVADO[0];

        $concepto = Concepto_Ivacompra::query()
            ->where('tipoconcepto', 'E')
            ->where('codigo', $codigo)
            ->whereNull('empresa_id')
            ->orderBy('id')
            ->first();

        if ($concepto === null) {
            throw new RuntimeException(
                'No hay un concepto IVA exento / no gravado (código '.$codigo.') para cargar la NC de reverso.'
            );
        }

        return $concepto;
    }

    public static function textoModo(string $modo): string
    {
        return $modo === self::MODO_A_CUENTA
            ? 'La factura está pagada: la NC queda como crédito del proveedor (a cuenta) para aplicar a otros pagos. '
                .'No se aplica a esta factura.'
            : 'La NC se aplica en cuenta corriente a esta factura.';
    }

    public static function yaFueReversado(int $comprobanteOrigenId): bool
    {
        if ($comprobanteOrigenId <= 0) {
            return false;
        }

        return Comprobante_Proveedor::query()
            ->where('leyenda', 'like', self::leyendaLike($comprobanteOrigenId))
            ->where('estado', '!=', ComprobanteProveedorEstados::ANULADO)
            ->exists();
    }

    /**
     * @param  iterable<int, int|string>  $comprobanteIds
     * @return array<int, true> id origen → true
     */
    public static function mapaYaReversados(iterable $comprobanteIds): array
    {
        $ids = [];
        foreach ($comprobanteIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $likes = [];
        foreach ($ids as $id) {
            $likes[] = self::leyendaLike($id);
        }

        $out = [];
        $rows = Comprobante_Proveedor::query()
            ->where('estado', '!=', ComprobanteProveedorEstados::ANULADO)
            ->where(function ($q) use ($likes) {
                foreach ($likes as $like) {
                    $q->orWhere('leyenda', 'like', $like);
                }
            })
            ->get(['id', 'leyenda']);

        foreach ($rows as $row) {
            $origen = self::origenIdDesdeLeyenda((string) ($row->leyenda ?? ''));
            if ($origen !== null && isset($ids[$origen])) {
                $out[$origen] = true;
            }
        }

        return $out;
    }

    public static function leyendaPara(object $origen): string
    {
        $tipoRel = $origen->tipotransaccion_compras ?? null;
        $tipo = strtoupper(trim((string) ($tipoRel->abreviatura ?? 'FAC')));
        $etiqueta = sprintf(
            '%s %s-%04d-%d',
            $tipo,
            (string) ($origen->letra ?? ''),
            (int) ($origen->sucursal ?? 0),
            (int) ($origen->numerocomprobante ?? 0)
        );

        return mb_substr(
            self::PREFIJO_LEYENDA.(int) $origen->id.' ('.$etiqueta.')',
            0,
            255
        );
    }

    public static function leyendaLike(int $comprobanteOrigenId): string
    {
        return self::PREFIJO_LEYENDA.$comprobanteOrigenId.'%';
    }

    public static function origenIdDesdeLeyenda(string $leyenda): ?int
    {
        if (preg_match('/^'.preg_quote(self::PREFIJO_LEYENDA, '/').'(\d+)/', $leyenda, $m) !== 1) {
            return null;
        }

        $id = (int) $m[1];

        return $id > 0 ? $id : null;
    }

    public static function resolverTipoNc(?string $abreviaturaPreferida = null): Tipotransaccion_Compra
    {
        $preferida = strtoupper(trim((string) ($abreviaturaPreferida ?: self::ABREV_TIPO_DEFAULT)));
        $candidatos = array_values(array_unique(array_filter([
            $preferida,
            self::ABREV_TIPO_DEFAULT,
            'NCP',
        ])));

        foreach ($candidatos as $abrev) {
            $tipo = Tipotransaccion_Compra::query()
                ->where('abreviatura', $abrev)
                ->where('estado', 'A')
                ->first();
            if ($tipo === null) {
                continue;
            }
            if ((string) $tipo->signo !== 'R') {
                continue;
            }
            if (! TipotransaccionCompraAsientoSupport::generaDesdeTipo($tipo)) {
                continue;
            }

            return $tipo;
        }

        throw new RuntimeException(
            'No hay un tipo de NC activo con asiento contable (se buscó '
            .implode('/', $candidatos).'). Configurá el ABM de tipos de compra.'
        );
    }

    private static function signoTipo(object $comprobante): string
    {
        if (isset($comprobante->tipotransaccion_compras) && $comprobante->tipotransaccion_compras !== null) {
            return (string) ($comprobante->tipotransaccion_compras->signo ?? 'S');
        }

        $tipoId = (int) ($comprobante->tipotransaccion_compra_id ?? 0);
        if ($tipoId <= 0) {
            return 'S';
        }

        $raw = Tipotransaccion_Compra::query()->whereKey($tipoId)->value('signo');

        return match ((int) $raw) {
            -1 => 'R',
            0 => 'N',
            default => 'S',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use RuntimeException;

/**
 * Gates y leyenda del reverso contable compensatorio (NC en mes abierto).
 *
 * No edita ni borra la factura original: crea una NC con asiento, mismos conceptos
 * e importa, y la aplica en cuenta corriente.
 */
final class ComprobanteProveedorReversoContableSupport
{
    public const SLUG_PERMISO = 'revertir-contable-comprobante-proveedor';

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
     * @throws RuntimeException
     */
    public static function assertPuedeRevertir(Comprobante_Proveedor $comprobante): void
    {
        if (! self::puedeMostrarBoton($comprobante)) {
            throw new RuntimeException(
                'Solo se puede revertir una factura contabilizada con asiento, '
                .'de signo suma, que no venga de ingresos/egresos.'
            );
        }

        $id = (int) $comprobante->id;
        if (ComprobanteProveedorPagoSupport::tienePagosAplicados($id)) {
            throw new RuntimeException(
                'La factura tiene pagos u otras aplicaciones en cuenta corriente. '
                .'Primero hay que deshacer esas aplicaciones (o revertir la OP).'
            );
        }

        if (self::yaFueReversado($id)) {
            throw new RuntimeException(
                'Ya existe una NC de reverso contable para el comprobante #'.$id.'.'
            );
        }

        $conceptos = $comprobante->comprobante_proveedor_conceptos ?? collect();
        $conMonto = $conceptos->filter(
            static fn ($l) => (int) ($l->concepto_ivacompra_id ?? 0) > 0
                && abs((float) ($l->monto ?? 0)) >= 0.0001
        );
        if ($conMonto->isEmpty()) {
            throw new RuntimeException(
                'El comprobante no tiene conceptos IVA con monto para clonar en la NC.'
            );
        }
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

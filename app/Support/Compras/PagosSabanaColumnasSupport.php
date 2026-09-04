<?php

namespace App\Support\Compras;

/**
 * Columnas fijas y dinámicas del listado sábana de pagos (l-movim.c ENCABPC + extras ERP).
 */
final class PagosSabanaColumnasSupport
{
    public const TIPO_TEXTO = 'texto';

    public const TIPO_IMPORTE = 'importe';

    public const TIPO_FECHA = 'fecha';

    public const TIPO_ENTERO = 'entero';

    /**
     * Columnas de identidad / siempre visibles.
     *
     * @return list<array{clave: string, etiqueta: string, tipo: string, fijo: bool}>
     */
    public static function fijas(): array
    {
        return [
            ['clave' => 'proveedor_codigo', 'etiqueta' => 'N.Pro.', 'tipo' => self::TIPO_TEXTO, 'fijo' => true],
            ['clave' => 'proveedor_nombre', 'etiqueta' => 'Proveedor', 'tipo' => self::TIPO_TEXTO, 'fijo' => true],
            ['clave' => 'tip', 'etiqueta' => 'Tip', 'tipo' => self::TIPO_TEXTO, 'fijo' => true],
            ['clave' => 'numero_op', 'etiqueta' => 'O.P.', 'tipo' => self::TIPO_TEXTO, 'fijo' => true],
            ['clave' => 'fecha', 'etiqueta' => 'Fecha', 'tipo' => self::TIPO_FECHA, 'fijo' => true],
            ['clave' => 'tipo_medio', 'etiqueta' => 'TIPO', 'tipo' => self::TIPO_TEXTO, 'fijo' => true],
            ['clave' => 'total_pago', 'etiqueta' => 'Total Pago', 'tipo' => self::TIPO_IMPORTE, 'fijo' => true],
        ];
    }

    /**
     * Medios de pago: se muestran solo si hay importe/texto en el rango.
     *
     * @return list<array{clave: string, etiqueta: string, tipo: string, fijo: bool}>
     */
    public static function dinamicasImporte(): array
    {
        return [
            ['clave' => 'efectivo', 'etiqueta' => 'Efectivo', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'transferencia', 'etiqueta' => 'Transferencia', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'ch_propios', 'etiqueta' => 'Ch.Propios', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'ch_terceros', 'etiqueta' => 'Ch.Terceros', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'doc_propios', 'etiqueta' => 'Doc.Propios', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'doc_terceros', 'etiqueta' => 'Doc.Terceros', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'retencion_iva', 'etiqueta' => 'Retencion IVA', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'retencion_gan', 'etiqueta' => 'Retencion Gan.', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'retencion_ibr', 'etiqueta' => 'Retencion IBR', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'retencion_suss', 'etiqueta' => 'Retencion SUSS', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'creditos', 'etiqueta' => 'Creditos', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'adelantos', 'etiqueta' => 'Adelantos', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'varios', 'etiqueta' => 'Varios', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
            ['clave' => 'intercompany', 'etiqueta' => 'Intercompany', 'tipo' => self::TIPO_IMPORTE, 'fijo' => false],
        ];
    }

    /**
     * @return list<array{clave: string, etiqueta: string, tipo: string, fijo: bool}>
     */
    public static function dinamicasTexto(): array
    {
        return [
            ['clave' => 'comprobantes', 'etiqueta' => 'Comprobantes', 'tipo' => self::TIPO_TEXTO, 'fijo' => false],
            ['clave' => 'ch_prop_emi', 'etiqueta' => 'Ch.Prop.Emi.', 'tipo' => self::TIPO_TEXTO, 'fijo' => false],
            ['clave' => 'banco', 'etiqueta' => 'Banco', 'tipo' => self::TIPO_TEXTO, 'fijo' => false],
            ['clave' => 'ch_terc_ent', 'etiqueta' => 'Ch.Terc.Ent.', 'tipo' => self::TIPO_TEXTO, 'fijo' => false],
            ['clave' => 'doc_prop_emit', 'etiqueta' => 'D.Prop.Emit.', 'tipo' => self::TIPO_TEXTO, 'fijo' => false],
            ['clave' => 'doc_terc_entr', 'etiqueta' => 'D.Terc.Entr.', 'tipo' => self::TIPO_TEXTO, 'fijo' => false],
        ];
    }

    /**
     * Extras ERP (siempre visibles).
     *
     * @return list<array{clave: string, etiqueta: string, tipo: string, fijo: bool}>
     */
    public static function extras(): array
    {
        return [
            ['clave' => 'centros_costo', 'etiqueta' => 'Centro de costo', 'tipo' => self::TIPO_TEXTO, 'fijo' => true],
            ['clave' => 'ordenes_compra', 'etiqueta' => 'Orden de compra', 'tipo' => self::TIPO_TEXTO, 'fijo' => true],
            ['clave' => 'detalle', 'etiqueta' => 'Detalle', 'tipo' => self::TIPO_TEXTO, 'fijo' => true],
            ['clave' => 'empresa', 'etiqueta' => 'Empr.', 'tipo' => self::TIPO_TEXTO, 'fijo' => true],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{clave: string, etiqueta: string, tipo: string, fijo: bool}>
     */
    public static function resolverVisibles(array $filas): array
    {
        $usadas = [];
        foreach (array_merge(self::dinamicasImporte(), self::dinamicasTexto()) as $col) {
            $clave = $col['clave'];
            $usadas[$clave] = false;
            foreach ($filas as $fila) {
                if (($fila['tipo_fila'] ?? '') === 'header_empresa') {
                    continue;
                }
                if ($col['tipo'] === self::TIPO_IMPORTE) {
                    if (abs((float) ($fila[$clave] ?? 0)) >= 0.005) {
                        $usadas[$clave] = true;
                        break;
                    }
                } elseif (trim((string) ($fila[$clave] ?? '')) !== '') {
                    $usadas[$clave] = true;
                    break;
                }
            }
        }

        $visibles = self::fijas();
        foreach (self::dinamicasImporte() as $col) {
            if ($usadas[$col['clave']] ?? false) {
                $visibles[] = $col;
            }
        }
        foreach (self::dinamicasTexto() as $col) {
            if ($usadas[$col['clave']] ?? false) {
                $visibles[] = $col;
            }
        }

        return array_merge($visibles, self::extras());
    }
}

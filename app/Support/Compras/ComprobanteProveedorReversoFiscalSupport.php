<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Compras\Tipotransaccion_Compra_Concepto_Ivacompra;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAnitaArmadoSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reverso contable FISCAL: NC o ND que entra al IVA compras y al Libro IVA Digital.
 *
 * A diferencia del reverso interno (NCJ, subdiario N), el comprobante usa un tipo fiscal
 * (subdiario C) y conceptos reales. El asiento sigue siendo el espejo del de la factura
 * (ComprobanteProveedorReversoAsientoEspejoSupport): la contabilidad no cambia.
 *
 * Tratamiento del IVA en los conceptos de la NC/ND:
 * - copiar: cada concepto del original (neto, IVA, percepciones) se repite con su monto.
 *   Corresponde cuando el IVA original fue computable (crédito fiscal).
 * - no_computable: neto + IVA del original van como no gravado; las percepciones se copian.
 *   Corresponde cuando el IVA original no fue computable: no se revierte crédito.
 *
 * Sentido del documento: origen NC (signo R) → ND; origen factura/ND (signo S) → NC.
 * Numeración: letra y punto de venta del original, número en serie propia desde SERIE_INICIO.
 */
final class ComprobanteProveedorReversoFiscalSupport
{
    public const TRATAMIENTO_COPIAR = 'copiar';

    public const TRATAMIENTO_NO_COMPUTABLE = 'no_computable';

    /** Serie propia del reverso: evita pisar la numeración real del proveedor. */
    public const SERIE_INICIO = 99000000;

    /** Impuestos que se repiten tal cual en el tratamiento no_computable (percepciones). */
    private const PERCEPCIONES = ['B', 'P', 'S'];

    /** Neto e IVA: en no_computable se colapsan en un único no gravado. */
    private const NETO_E_IVA = ['G', 'I', 'E', 'N'];

    private const TOLERANCIA = 0.01;

    /**
     * Modo de aplicación según el signo del origen. Factura/ND (S): mismo criterio del reverso
     * interno (aplicar a la factura o dejar a cuenta). NC (R): la ND queda como deuda abierta
     * del proveedor y no se aplica automáticamente.
     *
     * @return string modo de ComprobanteProveedorReversoContableSupport
     *
     * @throws RuntimeException
     */
    public static function modo(Comprobante_Proveedor $origen): string
    {
        $signo = self::signoRaw($origen);

        if ($signo === 1) {
            return ComprobanteProveedorReversoContableSupport::assertPuedeRevertir($origen);
        }

        if ($signo !== -1) {
            throw new RuntimeException('Solo se revierten facturas/ND (signo suma) o NC (signo resta).');
        }

        self::assertBasicoOrigenNc($origen);

        return ComprobanteProveedorReversoContableSupport::MODO_A_CUENTA;
    }

    /**
     * El tipo del reverso tiene que ser fiscal y del sentido opuesto al origen.
     *
     * @throws RuntimeException
     */
    public static function resolverTipo(string $abreviatura, Comprobante_Proveedor $origen): Tipotransaccion_Compra
    {
        $abrev = strtoupper(trim($abreviatura));
        $tipo = Tipotransaccion_Compra::query()
            ->where('abreviatura', $abrev)
            ->where('estado', 'A')
            ->first();
        if ($tipo === null) {
            throw new RuntimeException('No hay un tipo de comprobante activo "'.$abrev.'".');
        }

        $signoOrigen = self::signoRaw($origen);
        if ($signoOrigen === 0) {
            throw new RuntimeException('El origen tiene signo nulo: no se puede revertir.');
        }
        $signoEsperado = $signoOrigen === 1 ? -1 : 1;
        if ((int) $tipo->getRawOriginal('signo') !== $signoEsperado) {
            throw new RuntimeException(
                'El tipo '.$abrev.' tiene que ser '.($signoEsperado < 0 ? 'NC' : 'ND').' para revertir este origen.'
            );
        }

        if (strtoupper(trim((string) $tipo->getRawOriginal('subdiario'))) !== 'C') {
            throw new RuntimeException('El tipo '.$abrev.' no es fiscal (subdiario C): no iría al IVA compras.');
        }

        if (strtoupper(trim((string) $tipo->getRawOriginal('asientocontable'))) !== 'S') {
            throw new RuntimeException('El tipo '.$abrev.' no genera asiento contable.');
        }

        return $tipo;
    }

    /**
     * Conceptos del comprobante de reverso, a partir de los conceptos del original.
     *
     * @return list<array{concepto_ivacompra_id:int, monto:float, cuentacontabledebe_id:?int, centrocosto_id:?int}>
     *
     * @throws RuntimeException
     */
    public static function conceptosNc(Comprobante_Proveedor $origen, string $tratamiento): array
    {
        if (! in_array($tratamiento, [self::TRATAMIENTO_COPIAR, self::TRATAMIENTO_NO_COMPUTABLE], true)) {
            throw new RuntimeException('Tratamiento de IVA inválido: '.$tratamiento.'.');
        }

        $filas = DB::table('comprobante_proveedor_concepto as k')
            ->join('concepto_ivacompra as c', 'c.id', '=', 'k.concepto_ivacompra_id')
            ->where('k.comprobante_proveedor_id', (int) $origen->id)
            ->orderBy('k.orden')
            ->orderBy('k.id')
            ->get([
                'k.concepto_ivacompra_id',
                'k.monto',
                'k.cuentacontabledebe_id',
                'k.centrocosto_id',
                'c.tipoconcepto',
            ]);
        if ($filas->isEmpty()) {
            throw new RuntimeException('El original no tiene conceptos de IVA para copiar.');
        }

        $fila = static fn ($r): array => [
            'concepto_ivacompra_id' => (int) $r->concepto_ivacompra_id,
            'monto' => round((float) $r->monto, 2),
            'cuentacontabledebe_id' => $r->cuentacontabledebe_id !== null ? (int) $r->cuentacontabledebe_id : null,
            'centrocosto_id' => $r->centrocosto_id !== null ? (int) $r->centrocosto_id : null,
        ];

        if ($tratamiento === self::TRATAMIENTO_COPIAR) {
            return $filas->map($fila)->values()->all();
        }

        $neto = 0.0;
        $ancla = null;
        $percepciones = [];
        foreach ($filas as $r) {
            $tipo = strtoupper(trim((string) $r->tipoconcepto));
            if (in_array($tipo, self::PERCEPCIONES, true)) {
                $percepciones[] = $fila($r);

                continue;
            }
            if (! in_array($tipo, self::NETO_E_IVA, true)) {
                throw new RuntimeException(
                    'El original tiene un concepto tipo '.$tipo.' que no admite el tratamiento no_computable.'
                );
            }
            $neto += (float) $r->monto;
            // Cuenta y centro de costo del neto: del gravado si existe; si no, del primer concepto.
            if ($ancla === null || ($tipo === 'G' && $ancla['tipo'] !== 'G')) {
                $ancla = ['tipo' => $tipo, 'fila' => $fila($r)];
            }
        }

        $out = [];
        if (abs($neto) >= self::TOLERANCIA) {
            $noGravado = ComprobanteProveedorReversoContableSupport::resolverConceptoExento();
            $out[] = [
                'concepto_ivacompra_id' => (int) $noGravado->id,
                'monto' => round($neto, 2),
                'cuentacontabledebe_id' => $ancla['fila']['cuentacontabledebe_id'] ?? null,
                'centrocosto_id' => $ancla['fila']['centrocosto_id'] ?? null,
            ];
        }

        return array_values(array_merge($out, $percepciones));
    }

    /**
     * Todos los conceptos tienen que estar habilitados para el tipo del reverso.
     *
     * @param  list<array{concepto_ivacompra_id:int}>  $conceptos
     *
     * @throws RuntimeException
     */
    public static function assertConceptosPermitidosPorTipo(Tipotransaccion_Compra $tipo, array $conceptos): void
    {
        $ids = array_values(array_unique(array_map(
            static fn (array $c): int => (int) $c['concepto_ivacompra_id'],
            $conceptos
        )));

        $permitidos = Tipotransaccion_Compra_Concepto_Ivacompra::query()
            ->where('tipotransaccion_compra_id', (int) $tipo->id)
            ->pluck('concepto_ivacompra_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $fuera = array_values(array_diff($ids, $permitidos));
        if ($fuera !== []) {
            throw new RuntimeException(
                'El tipo '.$tipo->abreviatura.' no admite los conceptos IVA ids: '.implode(', ', $fuera).'.'
            );
        }
    }

    /**
     * Próximo número de la serie propia para ese proveedor, letra y punto de venta.
     * $reservados permite simular varios reversos en secuencia (dry-run) sin duplicar.
     *
     * @param  array<string, int>  $reservados
     */
    public static function proximoNumero(Comprobante_Proveedor $origen, string $letra, int $sucursal, array &$reservados = []): int
    {
        $clave = (int) $origen->proveedor_id.'|'.$letra.'|'.$sucursal;

        $max = (int) Comprobante_Proveedor::query()
            ->where('proveedor_id', (int) $origen->proveedor_id)
            ->where('letra', $letra)
            ->where('sucursal', $sucursal)
            ->where('numerocomprobante', '>=', self::SERIE_INICIO)
            ->max('numerocomprobante');

        $base = max(self::SERIE_INICIO, $max, (int) ($reservados[$clave] ?? 0));
        $numero = $base + 1;
        $reservados[$clave] = $numero;

        return $numero;
    }

    /**
     * Gate del origen NC (signo R): contabilizada, sin reverso previo y con pagos solo de OP
     * en total. No cambia el asiento: solo valida que se pueda dar vuelta.
     *
     * @throws RuntimeException
     */
    private static function assertBasicoOrigenNc(Comprobante_Proveedor $origen): void
    {
        $id = (int) $origen->id;

        if ((string) $origen->estado !== ComprobanteProveedorEstados::CONTABILIZADO) {
            throw new RuntimeException('La NC origen tiene que estar contabilizada.');
        }
        if ((int) ($origen->asiento_id ?? 0) <= 0) {
            throw new RuntimeException('La NC origen no tiene asiento contable.');
        }
        if (ComprobanteProveedorOrigenEntrada::esIngresoEgreso($origen->origen_entrada ?? null)) {
            throw new RuntimeException('Un comprobante de ingresos/egresos no se revierte desde acá.');
        }
        if (ComprobanteProveedorReversoContableSupport::yaFueReversado($id)) {
            throw new RuntimeException('Ya existe un reverso para el comprobante #'.$id.'.');
        }
        if (abs((float) ($origen->total ?? 0)) < self::TOLERANCIA) {
            throw new RuntimeException('El comprobante origen no tiene importe para revertir.');
        }
        if (ComprobanteProveedorPagoSupport::tienePagosAplicados($id)) {
            ComprobanteProveedorReversoContableSupport::assertPagadaEnTotalSoloConOrdenesDePago($id);
        }
    }

    /** Signo crudo del tipo: 1 factura/ND, -1 NC, 0 sin signo. */
    public static function signoRaw(Comprobante_Proveedor $origen): int
    {
        $origen->loadMissing('tipotransaccion_compras');

        return (int) ($origen->tipotransaccion_compras?->getRawOriginal('signo') ?? 1);
    }

    /**
     * Chequeo de lectura: el comprobante del reverso tiene que entrar al Libro IVA Digital.
     */
    public static function informableIvaCompras(Tipotransaccion_Compra $tipo, string $letra): bool
    {
        return LibroIvaDigitalComprasAnitaArmadoSupport::esTipoInformableIvaCompras($tipo, $letra);
    }
}

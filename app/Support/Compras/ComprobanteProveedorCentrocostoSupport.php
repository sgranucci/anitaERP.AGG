<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorAbreviaturaTipoSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorProrrateoMultiCcSupport;
use App\Support\Contable\AsientoCentrocostoObligatorioSupport;
use App\Support\Contable\CuentaCentrocostoAsignadosSupport;
use Throwable;

/**
 * Centro de costo de la factura / asiento de compras: destino de la OC.
 *
 * Prioridad: centrocostodestino_id de las líneas → cabecera origen.
 * Al grabar Anita, penmp_ccosto_dest usa esta misma prioridad (no copiar el origen).
 * La tolerancia factura vs COM también toma este CC (no el origen de cabecera).
 * El default del proveedor no se usa si hay OC.
 *
 * IVA prorrateado multi-CC (FPB/…): cada concepto I se abre solo en los centros
 * cuyo tipoiva coincide con el fino origen (FIB→Indirecto, FNB→No computable, FDB→Directo).
 */
final class ComprobanteProveedorCentrocostoSupport
{
    /**
     * @param  Comprobante_Proveedor|object|null  $comprobante
     */
    public static function resolverDesdeComprobante(?object $comprobante): int
    {
        $oc = $comprobante->ordencompras ?? null;
        $ccOc = self::resolverDesdeOc($oc instanceof Ordencompra || is_object($oc) ? $oc : null);
        if ($ccOc > 0) {
            return $ccOc;
        }

        // Solo sin OC (comprobante suelto). Sin CC del proveedor → 0 (no inventar id 1).
        $ccProveedor = (int) ($comprobante->proveedores?->centrocostocompra_id ?? 0);

        return $ccProveedor > 0 ? $ccProveedor : 0;
    }

    public static function resolverDesdeOc(?object $oc): int
    {
        if (! $oc) {
            return 0;
        }

        $lineas = $oc->ordencompra_articulos ?? null;
        if ($lineas !== null) {
            foreach ($lineas as $linea) {
                $ccLinea = (int) ($linea->centrocostodestino_id ?? 0);
                if ($ccLinea > 0) {
                    return $ccLinea;
                }
            }
        }

        return (int) ($oc->centrocosto_id ?? 0);
    }

    /**
     * Cuenta que no maneja centro (proveedor, percepción, IVA sin el flag).
     *
     * Toma centro solo si la matriz de la cuenta tiene uno solo, aunque manejaccosto
     * esté en N. Si no tiene ninguno, queda en 0. No hereda el centro de compra del
     * proveedor.
     */
    public static function paraCuentaSinGasto(int $cuentaId): int
    {
        return CuentaCentrocostoAsignadosSupport::unicoAsignado($cuentaId);
    }

    /**
     * Impuesto / IVA / percepción cuando la cuenta maneja centro de costo.
     *
     * Un solo centro destino en la OC: ese. Varios (OC prorrateada): el importe se
     * abre en cada uno con el peso de los artículos (cantidad × precio), el mismo
     * criterio que el neto. Si el concepto ya vino abierto por centro, se respeta
     * el de la línea y no se vuelve a partir.
     *
     * Con $inicialTipoIva (I/D/N) en factura multi-CC, solo entran los centros de
     * ese tratamiento: el IVA no computable no se reparte a un CC Indirecto.
     *
     * @param  array<int, float>  $pesosGasto  centro => importe del gasto (Agregar cuenta, o el neto si hay uno solo)
     * @return list<array{centrocosto_id:int, importe:float}>
     */
    public static function repartirImpuesto(
        int $cuentaId,
        float $importe,
        ?object $comprobante,
        int $centrocostoLinea = 0,
        bool $conceptoYaRepartido = false,
        array $pesosGasto = [],
        ?string $inicialTipoIva = null,
    ): array {
        $oc = is_object($comprobante) ? ($comprobante->ordencompras ?? null) : null;
        $pesosOc = self::pesosDestinoOc(is_object($oc) ? $oc : null);
        $pesos = self::pesosPositivos($pesosOc);
        if ($pesos === []) {
            $pesos = self::pesosIvaSinOc($pesosGasto, $centrocostoLinea);
        } elseif ($inicialTipoIva !== null && $inicialTipoIva !== '') {
            $filtrados = self::filtrarPesosPorInicialTipoIva($pesos, $inicialTipoIva);
            if ($filtrados !== []) {
                $pesos = $filtrados;
            }
        }

        return self::trozosImpuesto(
            self::cuentaManejaCentrocosto($cuentaId),
            self::paraCuentaSinGasto($cuentaId),
            $pesos,
            $importe,
            $centrocostoLinea,
            $conceptoYaRepartido,
            self::resolverDesdeComprobante($comprobante),
        );
    }

    /**
     * Inicial I/D/N del fino origen del concepto IVA en una OC multi-CC.
     * Null si no hay prorrateo activo o el concepto no es IVA de ese prorrateo.
     */
    public static function inicialTipoIvaConceptoIva(?object $comprobante, int $conceptoIvacompraId): ?string
    {
        if ($conceptoIvacompraId <= 0 || ! is_object($comprobante)) {
            return null;
        }

        $oc = $comprobante->ordencompras ?? null;
        if (! ($oc instanceof Ordencompra)) {
            $ocId = (int) ($oc->id ?? $comprobante->ordencompra_id ?? 0);
            if ($ocId <= 0) {
                return null;
            }
            $oc = Ordencompra::query()
                ->with([
                    'ordencompra_articulos.centrocostos_destino:id,codigo,tipoiva',
                    'ordencompra_articulos.articulos.tipoarticulos',
                    'ordencompra_articulos.articulos.categorias',
                    'centrocostos:id,codigo,tipoiva',
                ])
                ->find($ocId);
        }
        if (! ($oc instanceof Ordencompra)) {
            return null;
        }

        try {
            $centros = PrecargaProveedorProrrateoMultiCcSupport::centrosConPesoDesdeOrdencompraErp($oc);
            if (count($centros) < 2) {
                return null;
            }

            $abrevCp = strtoupper(trim((string) ($comprobante->tipotransaccion_compras->abreviatura ?? '')));
            $familia = $abrevCp !== ''
                ? PrecargaProveedorProrrateoMultiCcSupport::familiaDesdeAbreviatura($abrevCp)
                : 'FC';
            $tipoItem = PrecargaProveedorAbreviaturaTipoSupport::tipoItemDesdeOrdencompra($oc);

            $res = app(PrecargaProveedorProrrateoMultiCcSupport::class)
                ->resolverDesdeCentros($familia, $tipoItem, $centros);
            if (! ($res['activo'] ?? false)) {
                return null;
            }

            foreach ($res['conceptos'] ?? [] as $concepto) {
                if ((int) ($concepto['concepto_ivacompra_id'] ?? 0) !== $conceptoIvacompraId) {
                    continue;
                }
                if (strtoupper(trim((string) ($concepto['tipoconcepto'] ?? ''))) !== 'I') {
                    continue;
                }
                $fino = strtoupper(trim((string) ($concepto['fino_origen'] ?? '')));
                if (strlen($fino) < 2) {
                    return null;
                }
                $inicial = $fino[1];

                return in_array($inicial, ['I', 'D', 'N'], true) ? $inicial : null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Deja solo centros cuyo tipoiva empieza con I, D o N.
     *
     * @param  array<int, float>  $pesos  centrocosto_id => peso
     * @return array<int, float>
     */
    public static function filtrarPesosPorInicialTipoIva(array $pesos, string $inicialTipoIva): array
    {
        $inicial = strtoupper(substr(trim($inicialTipoIva), 0, 1));
        $pesos = self::pesosPositivos($pesos);
        if ($inicial === '' || $pesos === []) {
            return $pesos;
        }

        $tipos = Centrocosto::query()
            ->whereIn('id', array_keys($pesos))
            ->pluck('tipoiva', 'id');

        $mapa = [];
        foreach ($tipos as $ccId => $tipoIva) {
            $mapa[(int) $ccId] = (string) $tipoIva;
        }

        return self::filtrarPesosPorTipoIvaConocido($pesos, $mapa, $inicial);
    }

    /**
     * @param  array<int, float>  $pesos
     * @param  array<int, string>  $tipoIvaPorCcId
     * @return array<int, float>
     */
    public static function filtrarPesosPorTipoIvaConocido(
        array $pesos,
        array $tipoIvaPorCcId,
        string $inicialTipoIva,
    ): array {
        $inicial = strtoupper(substr(trim($inicialTipoIva), 0, 1));
        $out = [];
        foreach (self::pesosPositivos($pesos) as $ccId => $peso) {
            $tipo = (string) ($tipoIvaPorCcId[$ccId] ?? '');
            if (strtoupper(substr(trim($tipo), 0, 1)) === $inicial) {
                $out[$ccId] = $peso;
            }
        }

        return $out;
    }

    /**
     * Sin OC prorrateada, el IVA sigue las cuentas de gasto.
     * Varias (Agregar cuenta): se abre en la misma proporción.
     * Una sola: se usa si el renglón de IVA todavía no tiene centro propio.
     * Si el gasto no aporta centros, queda vacío: el IVA puede abrirse igual con la
     * herramienta «Prorratear CC» (varios renglones del mismo concepto con CC distinto).
     *
     * @param  array<int, float>  $pesosGasto
     * @return array<int, float>
     */
    public static function pesosIvaSinOc(array $pesosGasto, int $centrocostoLinea): array
    {
        $gasto = self::pesosPositivos($pesosGasto);
        if (count($gasto) >= 2) {
            return $gasto;
        }
        if (count($gasto) === 1 && $centrocostoLinea <= 0) {
            return $gasto;
        }

        return [];
    }

    /**
     * @param  array<int, float>  $pesosOc  centrocosto_id => peso
     * @return list<array{centrocosto_id:int, importe:float}>
     */
    public static function trozosImpuesto(
        bool $manejaCentrocosto,
        int $unicoAsignado,
        array $pesosOc,
        float $importe,
        int $centrocostoLinea = 0,
        bool $conceptoYaRepartido = false,
        int $centrocostoComprobante = 0,
    ): array {
        $importe = round($importe, 2);
        if (! $manejaCentrocosto) {
            return [[
                'centrocosto_id' => max(0, $unicoAsignado),
                'importe' => $importe,
            ]];
        }

        if ($conceptoYaRepartido) {
            return [[
                'centrocosto_id' => max(0, $centrocostoLinea),
                'importe' => $importe,
            ]];
        }

        $pesos = self::pesosPositivos($pesosOc);
        if (count($pesos) >= 2) {
            return self::repartirImporte($importe, $pesos);
        }
        if (count($pesos) === 1) {
            return [[
                'centrocosto_id' => (int) array_key_first($pesos),
                'importe' => $importe,
            ]];
        }

        $cc = $centrocostoLinea > 0 ? $centrocostoLinea : $centrocostoComprobante;
        if ($cc <= 0) {
            $cc = max(0, $unicoAsignado);
        }

        return [[
            'centrocosto_id' => $cc,
            'importe' => $importe,
        ]];
    }

    /**
     * Peso por centro destino de la OC. El orden es el de la primera aparición
     * en los renglones: el último se queda con el redondeo.
     *
     * @return array<int, float>
     */
    public static function pesosDestinoOc(?object $oc): array
    {
        if (! is_object($oc)) {
            return [];
        }

        $lineas = $oc->ordencompra_articulos ?? null;
        if ($lineas === null) {
            return [];
        }

        $pesos = [];
        foreach ($lineas as $linea) {
            $ccId = (int) ($linea->centrocostodestino_id ?? 0);
            if ($ccId <= 0) {
                continue;
            }
            $peso = max(0.0, (float) ($linea->cantidad ?? 0) * (float) ($linea->precio ?? 0));
            if (! array_key_exists($ccId, $pesos)) {
                $pesos[$ccId] = 0.0;
            }
            $pesos[$ccId] += $peso > 0 ? $peso : 1.0;
        }

        return $pesos;
    }

    /**
     * @param  array<int, float>  $pesos
     * @return list<array{centrocosto_id:int, importe:float}>
     */
    public static function repartirImporte(float $importe, array $pesos): array
    {
        $importe = round($importe, 2);
        $items = self::pesosPositivos($pesos);
        if ($items === []) {
            return [[
                'centrocosto_id' => 0,
                'importe' => $importe,
            ]];
        }
        if (count($items) === 1) {
            return [[
                'centrocosto_id' => (int) array_key_first($items),
                'importe' => $importe,
            ]];
        }

        $total = array_sum($items);
        $asignado = 0.0;
        $out = [];
        $i = 0;
        $ultimo = count($items) - 1;
        foreach ($items as $ccId => $peso) {
            if ($i === $ultimo) {
                $parte = round($importe - $asignado, 2);
            } else {
                $parte = round($importe * ($peso / $total), 2);
                if (abs($parte) < 0.005) {
                    $i++;

                    continue;
                }
                $asignado = round($asignado + $parte, 2);
            }
            $out[] = [
                'centrocosto_id' => (int) $ccId,
                'importe' => $parte,
            ];
            $i++;
        }

        return $out;
    }

    public static function cuentaManejaCentrocosto(int $cuentaId): bool
    {
        if ($cuentaId <= 0) {
            return false;
        }

        static $cache = [];
        if (! array_key_exists($cuentaId, $cache)) {
            $flag = Cuentacontable::query()->whereKey($cuentaId)->value('manejaccosto');
            $cache[$cuentaId] = AsientoCentrocostoObligatorioSupport::maneja($flag);
        }

        return $cache[$cuentaId];
    }

    /**
     * @param  array<int, float>  $pesos
     * @return array<int, float>
     */
    private static function pesosPositivos(array $pesos): array
    {
        $out = [];
        foreach ($pesos as $ccId => $peso) {
            $ccId = (int) $ccId;
            $peso = (float) $peso;
            if ($ccId > 0 && $peso > 0) {
                $out[$ccId] = $peso;
            }
        }

        return $out;
    }
}

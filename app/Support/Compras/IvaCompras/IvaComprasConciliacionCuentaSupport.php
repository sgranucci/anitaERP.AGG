<?php

declare(strict_types=1);

namespace App\Support\Compras\IvaCompras;

use Illuminate\Support\Facades\DB;

/**
 * Cuentas contables para conciliación IVA compras (solo IVA / percepciones).
 *
 * Fuente: config/iva_compras.php → conciliacion.*
 * No se vuelcan cuentas de tipoconcepto G/N/E: el neto del libro no cuadra
 * contra un set fijo de gastos (cada comprobante imputa distinto).
 */
final class IvaComprasConciliacionCuentaSupport
{
    public const TOLERANCIA_DEFAULT = 0.05;

    public const TOLERANCIA_DIARIA = 1.0;

    public const FUENTE_CONFIG = 'iva_compras';

    /**
     * @return array{
     *   iva_credito: list<int>,
     *   perc_iva: list<int>,
     *   perc_iibb: list<int>,
     *   detalle: list<array{rol: string, id: int, codigo: string, nombre: string, fuente: string}>
     * }
     */
    public static function cuentasConciliacionEmpresa(int $empresaId): array
    {
        $iva = [];
        $percIva = [];
        $percIibb = [];
        $detalle = [];

        foreach (self::codigosConfig('cuentas_iva_credito_por_empresa', $empresaId) as $codigo) {
            self::agregarPorCodigo($iva, $detalle, 'iva_credito', $empresaId, $codigo, 'IVA crédito fiscal');
        }
        foreach (self::codigosConfig('cuentas_perc_iva_por_empresa', $empresaId) as $codigo) {
            self::agregarPorCodigo($percIva, $detalle, 'perc_iva', $empresaId, $codigo, 'Percepción IVA');
        }
        foreach (self::codigosConfig('cuentas_perc_iibb_por_empresa', $empresaId) as $codigo) {
            self::agregarPorCodigo($percIibb, $detalle, 'perc_iibb', $empresaId, $codigo, 'Percepción IIBB');
        }

        return [
            'iva_credito' => array_values(array_unique($iva)),
            'perc_iva' => array_values(array_unique($percIva)),
            'perc_iibb' => array_values(array_unique($percIibb)),
            'detalle' => $detalle,
        ];
    }

    /**
     * @return list<string>
     */
    private static function codigosConfig(string $clave, int $empresaId): array
    {
        $map = (array) config('iva_compras.conciliacion.'.$clave, []);
        $codigos = $map[$empresaId] ?? $map[(string) $empresaId] ?? [];

        $out = [];
        foreach ((array) $codigos as $codigo) {
            $codigo = trim((string) $codigo);
            if ($codigo !== '') {
                $out[] = $codigo;
            }
        }

        return $out;
    }

    public static function cuadra(float $erp, float $contable, float $tolerancia = self::TOLERANCIA_DEFAULT): bool
    {
        return abs(round($erp, 2) - round($contable, 2)) <= $tolerancia;
    }

    /**
     * @param  list<int>  $bucket
     * @param  list<array{rol: string, id: int, codigo: string, nombre: string, fuente: string}>  $detalle
     */
    private static function agregarPorCodigo(
        array &$bucket,
        array &$detalle,
        string $rol,
        int $empresaId,
        string $codigo,
        string $nombreDefault,
    ): void {
        if ($codigo === '') {
            return;
        }

        $fila = DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->first(['id', 'nombre']);
        $id = (int) ($fila->id ?? 0);
        if ($id <= 0 || in_array($id, $bucket, true)) {
            return;
        }

        $bucket[] = $id;
        $detalle[] = [
            'rol' => $rol,
            'id' => $id,
            'codigo' => $codigo,
            'nombre' => (string) ($fila->nombre ?? $nombreDefault),
            'fuente' => self::FUENTE_CONFIG,
        ];
    }
}

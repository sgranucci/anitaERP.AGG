<?php

namespace App\Support\Compras;

use App\ApiAnita;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Contable\Asiento;
use App\Models\Contable\Cuentacontable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cuenta de proveedores **realmente imputada** en el documento de un movimiento de CC.
 *
 * Antes de deducir MN/ME por la moneda de la OC (`ProveedorCuentaContableMonedaSupport`)
 * conviene mirar qué cuenta quedó contabilizada: una NC sin OC puede haberse imputado
 * a una cuenta distinta de la que sugiere su moneda, y en ese caso la aplicación
 * reclasifica de verdad (o no reclasifica nada, aunque la regla de moneda diga que sí).
 *
 * Orden: asiento del ERP → subdiario de Anita → null (el caller cae en la regla de moneda).
 * Solo se acepta una cuenta si su código es uno de los configurados como cuenta de
 * proveedores del proveedor, para no confundirla con una cuenta de gasto del documento.
 */
final class ProveedorCuentacorrienteCuentaApImputadaSupport
{
    private const SISTEMA_CONTAB = 'contab';

    /** @var array<string, int|null> cache por request: clave documento => cuenta id */
    private static array $cache = [];

    public static function olvidarCache(): void
    {
        self::$cache = [];
    }

    /**
     * Misma cuenta (por código) en la empresa del movimiento. El proveedor tiene una sola
     * cuenta configurada aunque opere en varias empresas, así que la deducción por moneda
     * puede devolver la cuenta de otra empresa y volverla incomparable con la imputada.
     */
    public static function normalizarEmpresa(int $cuentaId, int $empresaId): int
    {
        if ($cuentaId <= 0 || $empresaId <= 0) {
            return $cuentaId;
        }

        $cuenta = Cuentacontable::query()->find($cuentaId, ['id', 'empresa_id', 'codigo']);
        if ($cuenta === null || (int) $cuenta->empresa_id === $empresaId) {
            return $cuentaId;
        }

        return self::cuentaIdPorCodigo(trim((string) $cuenta->codigo), $empresaId) ?? $cuentaId;
    }

    public static function cuenta(Proveedor_Cuentacorriente $cc): ?int
    {
        $clave = self::claveCache($cc);
        if ($clave === null) {
            return null;
        }
        if (array_key_exists($clave, self::$cache)) {
            return self::$cache[$clave];
        }

        return self::$cache[$clave] = self::resolver($cc);
    }

    private static function resolver(Proveedor_Cuentacorriente $cc): ?int
    {
        $cc->loadMissing([
            'proveedores',
            'comprobante_proveedores.tipotransaccion_compras',
            'comprobante_proveedores.empresas',
            'pagoproveedores',
        ]);

        $codigosAp = self::codigosCuentasCandidatas($cc->proveedores, (int) $cc->empresa_id);
        if ($codigosAp === []) {
            return null;
        }

        $desdeErp = self::desdeAsientoErp($cc, $codigosAp);
        if ($desdeErp !== null) {
            return $desdeErp;
        }

        return self::desdeSubdiarioAnita($cc, $codigosAp);
    }

    /**
     * @param  array<string, string>  $codigosAp
     */
    private static function desdeAsientoErp(Proveedor_Cuentacorriente $cc, array $codigosAp): ?int
    {
        $query = Asiento::query();
        $comprobanteId = (int) ($cc->comprobante_proveedor_id ?? 0);
        $pagoId = (int) ($cc->pagoproveedor_id ?? 0);

        if ($comprobanteId > 0) {
            $query->where('comprobante_proveedor_id', $comprobanteId);
        } elseif ($pagoId > 0) {
            $query->where('pagoproveedor_id', $pagoId);
        } else {
            return null;
        }

        $asientoIds = $query->pluck('id');
        if ($asientoIds->isEmpty()) {
            return null;
        }

        $movimientos = Cuentacontable::query()
            ->join('asiento_movimiento as am', 'am.cuentacontable_id', '=', 'cuentacontable.id')
            ->whereIn('am.asiento_id', $asientoIds)
            ->get(['cuentacontable.id', 'cuentacontable.codigo']);

        foreach ($movimientos as $cuenta) {
            if (isset($codigosAp[self::normalizarCodigo((string) $cuenta->codigo)])) {
                return (int) $cuenta->id;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $codigosAp
     */
    private static function desdeSubdiarioAnita(Proveedor_Cuentacorriente $cc, array $codigosAp): ?int
    {
        $clave = self::claveAnita($cc);
        if ($clave === null) {
            return null;
        }

        try {
            $filas = ApiAnita::decodificarListaFilas((new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => self::SISTEMA_CONTAB,
                'tabla' => 'subdiario',
                'campos' => 'subd_cuenta,subd_contrapartida,subd_tipo_mov,subd_importe',
                'whereArmado' => " WHERE subd_empresa = '".$clave['empresa']."'"
                    ." AND subd_tipo = '".$clave['tipo']."'"
                    ." AND subd_letra = '".$clave['letra']."'"
                    .' AND subd_sucursal = '.$clave['sucursal']
                    .' AND subd_nro = '.$clave['nro'],
            ]));
        } catch (Throwable $e) {
            Log::warning('cc_proveedor.cuenta_imputada.anita_fallo', [
                'proveedor_cuentacorriente_id' => (int) $cc->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        // La cuenta de proveedores del documento viaja como contrapartida; se revisa
        // igual subd_cuenta por si el asiento se cargó al revés.
        foreach (['subd_contrapartida', 'subd_cuenta'] as $campo) {
            foreach ($filas as $fila) {
                $row = is_array($fila) ? $fila : get_object_vars($fila);
                $codigo = self::normalizarCodigo((string) ($row[$campo] ?? ''));
                if ($codigo === '' || ! isset($codigosAp[$codigo])) {
                    continue;
                }

                $cuentaId = self::cuentaIdPorCodigo($codigosAp[$codigo], (int) $cc->empresa_id);
                if ($cuentaId !== null) {
                    return $cuentaId;
                }
            }
        }

        return null;
    }

    /**
     * @return array{empresa:string, tipo:string, letra:string, sucursal:int, nro:int}|null
     */
    private static function claveAnita(Proveedor_Cuentacorriente $cc): ?array
    {
        $comprobante = $cc->comprobante_proveedores;
        if ($comprobante instanceof Comprobante_Proveedor) {
            return self::clave(
                (string) ($comprobante->empresas?->codigo ?? $comprobante->empresa_id ?? ''),
                (string) ($comprobante->tipotransaccion_compras?->abreviatura ?? ''),
                (string) $comprobante->letra,
                (int) $comprobante->sucursal,
                (int) $comprobante->numerocomprobante
            );
        }

        $pago = $cc->pagoproveedores;
        if ($pago instanceof Pagoproveedor) {
            $pago->loadMissing('empresas');

            return self::clave(
                (string) ($pago->empresas?->codigo ?? $pago->empresa_id ?? ''),
                (string) $pago->tipocomprobante,
                (string) $pago->letra,
                (int) $pago->sucursal,
                (int) $pago->numerotransaccion
            );
        }

        return null;
    }

    /**
     * @return array{empresa:string, tipo:string, letra:string, sucursal:int, nro:int}|null
     */
    private static function clave(
        string $empresa,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
    ): ?array {
        $empresa = trim($empresa);
        $tipo = substr(trim($tipo), 0, 3);
        if ($empresa === '' || $tipo === '' || $nro <= 0) {
            return null;
        }

        return [
            'empresa' => str_replace("'", "''", $empresa),
            'tipo' => str_replace("'", "''", $tipo),
            'letra' => str_replace("'", "''", strtoupper(substr(trim($letra), 0, 1))),
            'sucursal' => $sucursal,
            'nro' => $nro,
        ];
    }

    /**
     * Cuentas que pueden hacer de contrapartida de proveedores en el documento:
     * las del proveedor (MN, ME y compras) más la de anticipos de la empresa.
     * Indexadas por código normalizado para comparar contra Anita.
     *
     * @return array<string, string>
     */
    private static function codigosCuentasCandidatas(?Proveedor $proveedor, int $empresaId): array
    {
        $ids = array_values(array_filter([
            (int) ($proveedor?->cuentacontable_id ?? 0),
            (int) ($proveedor?->cuentacontableme_id ?? 0),
            (int) ($proveedor?->cuentacontablecompra_id ?? 0),
            (int) (ProveedorAnticipoCuentaContableSupport::cuentaAnticipoId($empresaId) ?? 0),
        ]));
        if ($ids === []) {
            return [];
        }

        $codigos = [];
        foreach (Cuentacontable::query()->whereIn('id', $ids)->pluck('codigo') as $codigo) {
            $normalizado = self::normalizarCodigo((string) $codigo);
            if ($normalizado !== '') {
                $codigos[$normalizado] = trim((string) $codigo);
            }
        }

        return $codigos;
    }

    private static function cuentaIdPorCodigo(string $codigo, int $empresaId): ?int
    {
        $query = Cuentacontable::query()->where('codigo', $codigo);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $id = (int) ($query->value('id') ?? 0);

        return $id > 0 ? $id : null;
    }

    private static function normalizarCodigo(string $codigo): string
    {
        return ltrim(trim($codigo), '0');
    }

    private static function claveCache(Proveedor_Cuentacorriente $cc): ?string
    {
        $comprobanteId = (int) ($cc->comprobante_proveedor_id ?? 0);
        if ($comprobanteId > 0) {
            return 'comp:'.$comprobanteId;
        }

        $pagoId = (int) ($cc->pagoproveedor_id ?? 0);

        return $pagoId > 0 ? 'pago:'.$pagoId : null;
    }
}

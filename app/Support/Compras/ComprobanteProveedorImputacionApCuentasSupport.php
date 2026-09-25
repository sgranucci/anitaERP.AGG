<?php

namespace App\Support\Compras;

use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Empresa;

/**
 * Catálogo de cuentas del trío AP MN / AP ME / anticipo.
 *
 * Solo suma lo imputado a cuenta de proveedores (códigos MN/ME de config,
 * por empresa) y anticipo. No toma las cuentas del maestro de proveedores:
 * ahí hay basura (gastos, MN metida en ME) que distorsiona el control.
 */
final class ComprobanteProveedorImputacionApCuentasSupport
{
    /**
     * @param  list<int>  $empresaIds
     * @return array{
     *     mn: array<int, true>,
     *     me: array<int, true>,
     *     anticipo: array<int, true>,
     *     anticipo_por_empresa: array<int, int>,
     *     codigo_mn: array<int, true>,
     *     codigo_me: array<int, true>,
     *     codigo_anticipo: array<int, true>
     * }
     */
    public static function armar(array $empresaIds = []): array
    {
        $mn = [];
        $me = [];
        $anticipo = [];
        $anticipoPorEmpresa = [];

        $empresas = $empresaIds !== []
            ? $empresaIds
            : Empresa::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($empresas as $empresaId) {
            $anticipoId = ProveedorAnticipoCuentaContableSupport::cuentaAnticipoId((int) $empresaId);
            if ($anticipoId !== null && $anticipoId > 0) {
                $anticipo[$anticipoId] = true;
                $anticipoPorEmpresa[(int) $empresaId] = $anticipoId;
            }
        }

        $codigosMn = self::codigosConfigMn();
        $codigosMe = self::codigosConfigMe();

        if ($codigosMn !== [] || $codigosMe !== []) {
            Cuentacontable::query()
                ->select(['id', 'codigo'])
                ->orderBy('id')
                ->chunkById(500, function ($cuentas) use (&$mn, &$me, $codigosMn, $codigosMe) {
                    foreach ($cuentas as $cuenta) {
                        $codigo = self::normalizarCodigo((string) ($cuenta->codigo ?? ''));
                        if ($codigo === '') {
                            continue;
                        }
                        $id = (int) $cuenta->id;
                        $codigoInt = (int) $codigo;
                        if (isset($codigosMn[$codigoInt])) {
                            $mn[$id] = true;
                        }
                        if (isset($codigosMe[$codigoInt])) {
                            $me[$id] = true;
                        }
                    }
                });
        }

        $codigoMnCfg = (string) (array_key_first($codigosMn) ?: '');
        $codigoMeCfg = (string) (array_key_first($codigosMe) ?: '');
        $codigos = self::codigosPorCubeta($mn, $me, $anticipo, $codigoMnCfg, $codigoMeCfg);

        // Asegurar todos los códigos de config (aunque no haya fila en plan ERP).
        foreach (array_keys($codigosMn) as $codigo) {
            $codigos['mn'][(int) $codigo] = true;
        }
        foreach (array_keys($codigosMe) as $codigo) {
            $codigos['me'][(int) $codigo] = true;
        }

        return [
            'mn' => $mn,
            'me' => $me,
            'anticipo' => $anticipo,
            'anticipo_por_empresa' => $anticipoPorEmpresa,
            'codigo_mn' => $codigos['mn'],
            'codigo_me' => $codigos['me'],
            'codigo_anticipo' => $codigos['anticipo'],
        ];
    }

    /**
     * @return array<int, true>
     */
    public static function codigosConfigMn(): array
    {
        return self::mapaCodigosDesdeConfig(
            (int) config('comprobante_proveedor_anita.conciliacion_mayor_cc.cuenta_mn', 211010001),
            (array) config('comprobante_proveedor_anita.conciliacion_mayor_cc.cuentas_mn_extra', [])
        );
    }

    /**
     * @return array<int, true>
     */
    public static function codigosConfigMe(): array
    {
        return self::mapaCodigosDesdeConfig(
            (int) config('comprobante_proveedor_anita.conciliacion_mayor_cc.cuenta_me', 211010011),
            (array) config('comprobante_proveedor_anita.conciliacion_mayor_cc.cuentas_me_extra', [])
        );
    }

    /**
     * @param  list<int|string>  $extras
     * @return array<int, true>
     */
    private static function mapaCodigosDesdeConfig(int $principal, array $extras): array
    {
        $out = [];
        foreach (array_merge([$principal], $extras) as $codigo) {
            $n = (int) self::normalizarCodigo((string) $codigo);
            if ($n > 0) {
                $out[$n] = true;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, true>  $mn
     * @param  array<int, true>  $me
     * @param  array<int, true>  $anticipo
     * @return array{mn: array<int, true>, me: array<int, true>, anticipo: array<int, true>}
     */
    public static function codigosPorCubeta(array $mn, array $me, array $anticipo, string $codigoMnCfg, string $codigoMeCfg): array
    {
        $out = ['mn' => [], 'me' => [], 'anticipo' => []];
        $ids = array_values(array_unique(array_merge(
            array_map('intval', array_keys($mn)),
            array_map('intval', array_keys($me)),
            array_map('intval', array_keys($anticipo)),
        )));
        $ids = array_values(array_filter($ids, static fn (int $id) => $id > 0));

        $porId = $ids === []
            ? collect()
            : Cuentacontable::query()->whereIn('id', $ids)->pluck('codigo', 'id');

        foreach (['mn' => $mn, 'me' => $me, 'anticipo' => $anticipo] as $cubeta => $mapa) {
            foreach (array_keys($mapa) as $id) {
                $codigo = self::normalizarCodigo((string) ($porId[(int) $id] ?? ''));
                if ($codigo !== '') {
                    $out[$cubeta][(int) $codigo] = true;
                }
            }
        }

        if ($codigoMnCfg !== '') {
            $out['mn'][(int) $codigoMnCfg] = true;
        }
        if ($codigoMeCfg !== '') {
            $out['me'][(int) $codigoMeCfg] = true;
        }

        return $out;
    }

    /**
     * @param  array{codigo_mn?: array<int, true>, codigo_me?: array<int, true>, codigo_anticipo?: array<int, true>}  $catalogo
     * @return list<int>
     */
    public static function codigosAp(array $catalogo): array
    {
        return array_values(array_unique(array_filter(array_map(
            'intval',
            array_merge(
                array_keys($catalogo['codigo_mn'] ?? []),
                array_keys($catalogo['codigo_me'] ?? []),
                array_keys($catalogo['codigo_anticipo'] ?? []),
            )
        ), static fn (int $c) => $c > 0)));
    }

    public static function cubetaEsperadaComprobante(
        int $cuentaProveedorId,
        array $catalogo,
    ): ?string {
        return ComprobanteProveedorImputacionApSupport::clasificarCuenta($cuentaProveedorId, $catalogo);
    }

    public static function cubetaEsperadaOpa(int $empresaId, array $catalogo): string
    {
        $anticipoId = (int) ($catalogo['anticipo_por_empresa'][$empresaId] ?? 0);
        if ($anticipoId > 0) {
            return ComprobanteProveedorImputacionApSupport::CUBETA_ANTICIPO;
        }

        return ComprobanteProveedorImputacionApSupport::CUBETA_MN;
    }

    public static function normalizarCodigo(string $codigo): string
    {
        return preg_replace('/\D+/', '', $codigo) ?? '';
    }
}

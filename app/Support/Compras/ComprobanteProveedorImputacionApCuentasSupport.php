<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Empresa;

/**
 * Catálogo de cuentas del trío AP MN / AP ME / anticipo (por id de cuentacontable).
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

        Proveedor::query()
            ->select(['id', 'cuentacontable_id', 'cuentacontableme_id', 'cuentacontablecompra_id'])
            ->orderBy('id')
            ->chunkById(400, function ($proveedores) use (&$mn, &$me) {
                foreach ($proveedores as $p) {
                    $idMn = (int) ($p->cuentacontable_id ?: $p->cuentacontablecompra_id ?: 0);
                    if ($idMn > 0) {
                        $mn[$idMn] = true;
                    }
                    $idMe = (int) ($p->cuentacontableme_id ?: 0);
                    if ($idMe > 0) {
                        $me[$idMe] = true;
                    }
                }
            });

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

        $codigoMn = self::normalizarCodigo((string) config('comprobante_proveedor_anita.conciliacion_mayor_cc.cuenta_mn', 211010001));
        $codigoMe = self::normalizarCodigo((string) config('comprobante_proveedor_anita.conciliacion_mayor_cc.cuenta_me', 211010011));

        Cuentacontable::query()
            ->select(['id', 'codigo'])
            ->orderBy('id')
            ->chunkById(500, function ($cuentas) use (&$mn, &$me, $codigoMn, $codigoMe) {
                foreach ($cuentas as $cuenta) {
                    $codigo = self::normalizarCodigo((string) ($cuenta->codigo ?? ''));
                    if ($codigo === '') {
                        continue;
                    }
                    $id = (int) $cuenta->id;
                    if ($codigoMn !== '' && $codigo === $codigoMn) {
                        $mn[$id] = true;
                    }
                    if ($codigoMe !== '' && $codigo === $codigoMe) {
                        $me[$id] = true;
                    }
                }
            });

        $codigos = self::codigosPorCubeta($mn, $me, $anticipo, $codigoMn, $codigoMe);

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

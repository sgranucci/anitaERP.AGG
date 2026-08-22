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
     *     anticipo_por_empresa: array<int, int>
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

        return [
            'mn' => $mn,
            'me' => $me,
            'anticipo' => $anticipo,
            'anticipo_por_empresa' => $anticipoPorEmpresa,
        ];
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

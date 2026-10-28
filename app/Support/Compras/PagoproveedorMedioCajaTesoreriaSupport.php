<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Caja\Cuentacaja;
use App\Support\Contable\CuentacontableEmpresaHomologacionSupport;

/**
 * El haber del asiento puede ser una cuenta de tesorería (fondo fijo, banco).
 * Eso no crea solo el movimiento de caja: el medio se carga en la grilla de caja.
 */
final class PagoproveedorMedioCajaTesoreriaSupport
{
    /**
     * Cuentas de caja cuya cuenta contable (la propia o la homologada a la empresa)
     * está entre los haberes del asiento.
     *
     * @param  list<int>  $cuentasHaberIds
     * @return list<array{cuentacontable_id: int, cuentas: list<array{id: int, codigo: string, nombre: string}>}>
     */
    public static function tesoreriaEnHaber(int $empresaId, array $cuentasHaberIds): array
    {
        $ids = [];
        foreach ($cuentasHaberIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        /** @var array<int, array<int, array{id: int, codigo: string, nombre: string}>> $porCuenta */
        $porCuenta = [];
        $cajas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->operativa()
            ->where('cuentacontable_id', '>', 0)
            ->get(['id', 'codigo', 'nombre', 'cuentacontable_id']);

        foreach ($cajas as $caja) {
            $base = (int) $caja->cuentacontable_id;
            $homologada = CuentacontableEmpresaHomologacionSupport::idParaEmpresa($base, $empresaId);
            $ficha = [
                'id' => (int) $caja->id,
                'codigo' => trim((string) $caja->codigo),
                'nombre' => trim((string) $caja->nombre),
            ];
            foreach (array_unique([$base, $homologada]) as $cuentaId) {
                if (! isset($ids[$cuentaId])) {
                    continue;
                }
                $porCuenta[$cuentaId][(int) $caja->id] = $ficha;
            }
        }

        $out = [];
        foreach (array_keys($ids) as $cuentaId) {
            if (empty($porCuenta[$cuentaId])) {
                continue;
            }
            $out[] = [
                'cuentacontable_id' => $cuentaId,
                'cuentas' => array_values($porCuenta[$cuentaId]),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{cuentacontable_id: int, cuentas: list<array{id: int, codigo: string, nombre: string}>}>  $tesoreria
     */
    public static function mensajeFaltaMedio(array $tesoreria): string
    {
        $partes = [];
        foreach ($tesoreria as $item) {
            $cajas = [];
            foreach ($item['cuentas'] as $caja) {
                $cajas[] = trim($caja['codigo'].' '.$caja['nombre']);
            }
            $partes[] = implode(' o ', $cajas);
        }

        return 'La orden de pago acredita una cuenta de tesorería en el asiento ('
            .implode('; ', $partes)
            .') pero no tiene medio de caja ni cheque. Cargue esa cuenta en Medios de pago: '
            .'cambiar la cuenta del asiento a mano no genera el movimiento de caja ni el pago en Anita.';
    }
}

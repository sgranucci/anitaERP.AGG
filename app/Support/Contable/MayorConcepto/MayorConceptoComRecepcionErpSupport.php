<?php

namespace App\Support\Contable\MayorConcepto;

use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Support\Facades\DB;

/**
 * Fallback de subdiario COM desde recepción ERP cuando Anita contab/subdiario no devuelve líneas.
 */
class MayorConceptoComRecepcionErpSupport
{
    /**
     * @param  list<string>  $clavesCom  ej. COM|X|1|164348
     * @return array<string, list<object>>
     */
    public function lineasGastoPorClavesCom(int $empresaId, array $clavesCom, MayorConceptoMemoriaMotor $motor): array
    {
        if ($empresaId <= 0 || $clavesCom === []) {
            return [];
        }

        $porClave = [];
        $busquedas = [];

        foreach ($clavesCom as $claveCom) {
            $parsed = $this->parsearClaveCom($claveCom);
            if ($parsed === null) {
                continue;
            }

            $busquedas[$claveCom] = $parsed;
            $porClave[$claveCom] = [];
        }

        if ($busquedas === []) {
            return [];
        }

        $recepciones = Recepcion_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->where('anita_tipo', 'COM')
            ->whereNotNull('asiento_id')
            ->where(function ($query) use ($busquedas) {
                foreach ($busquedas as $parsed) {
                    $query->orWhere(function ($sub) use ($parsed) {
                        $sub->where('anita_letra', $parsed['letra'])
                            ->where('anita_sucursal', $parsed['sucursal'])
                            ->where('anita_nro', $parsed['nro']);
                    });
                }
            })
            ->get(['id', 'asiento_id', 'anita_letra', 'anita_sucursal', 'anita_nro']);

        if ($recepciones->isEmpty()) {
            return $porClave;
        }

        $clavePorRecepcion = [];
        foreach ($recepciones as $recepcion) {
            $clave = $this->armarClaveCom(
                'COM',
                trim((string) ($recepcion->anita_letra ?? ' ')),
                (int) ($recepcion->anita_sucursal ?? 0),
                (int) ($recepcion->anita_nro ?? 0),
            );
            if (! isset($busquedas[$clave])) {
                continue;
            }

            $clavePorRecepcion[(int) $recepcion->asiento_id] = $clave;
        }

        if ($clavePorRecepcion === []) {
            return $porClave;
        }

        // No filtrar cuentacontable.empresa_id: en multiempresa el plan de cuentas
        // suele tener el gasto (ej. 115010001) bajo empresa 1 aunque la recepción
        // sea Kandiko/Rebisco. El asiento ya está acotado por la recepción.
        $movimientos = DB::table('asiento_movimiento as am')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->whereIn('am.asiento_id', array_keys($clavePorRecepcion))
            ->where('am.monto', '>', 0)
            ->get([
                'am.asiento_id',
                'am.monto',
                'cc.codigo as cuenta_codigo',
            ]);

        foreach ($movimientos as $mov) {
            $clave = $clavePorRecepcion[(int) ($mov->asiento_id ?? 0)] ?? '';
            if ($clave === '') {
                continue;
            }

            $cuenta = (int) ($mov->cuenta_codigo ?? 0);
            $importe = (float) ($mov->monto ?? 0);
            if (! $this->esLineaGastoCom($cuenta, $importe, $motor)) {
                continue;
            }

            $porClave[$clave][] = (object) [
                'subd_cuenta' => $cuenta,
                'subd_tipo_mov' => 'D',
                'subd_importe' => $importe,
            ];
        }

        foreach ($porClave as $clave => $lineas) {
            $porClave[$clave] = $this->deduplicarLineas($lineas);
        }

        return $porClave;
    }

    /**
     * @return list<object>
     */
    public function lineasGastoDesdeClaveCom(int $empresaId, string $claveCom, MayorConceptoMemoriaMotor $motor): array
    {
        return $this->lineasGastoPorClavesCom($empresaId, [$claveCom], $motor)[$claveCom] ?? [];
    }

  /**
     * @return array{letra: string, sucursal: int, nro: int}|null
     */
    private function parsearClaveCom(string $claveCom): ?array
    {
        [$tipo, $letra, $sucursal, $nro] = array_pad(explode('|', $claveCom, 4), 4, '');
        if (strtoupper(trim($tipo)) !== 'COM' || (int) $nro <= 0) {
            return null;
        }

        return [
            'letra' => trim($letra) === '' ? ' ' : trim($letra),
            'sucursal' => (int) $sucursal,
            'nro' => (int) $nro,
        ];
    }

    private function armarClaveCom(string $tipo, string $letra, int $sucursal, int $nro): string
    {
        return strtoupper(trim($tipo)).'|'.(trim($letra) === '' ? ' ' : trim($letra)).'|'.$sucursal.'|'.$nro;
    }

    private function esLineaGastoCom(int $cuenta, float $importe, MayorConceptoMemoriaMotor $motor): bool
    {
        if ($cuenta <= 0 || $importe <= 0) {
            return false;
        }

        if ($motor->esProveedor($cuenta) || $motor->esDisponibilidad($cuenta)) {
            return false;
        }

        if ($cuenta === 521130001) {
            return false;
        }

        if ($cuenta >= 214010000 && $cuenta < 215000000) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<object>  $lineas
     * @return list<object>
     */
    private function deduplicarLineas(array $lineas): array
    {
        $vistas = [];
        $unicas = [];

        foreach ($lineas as $linea) {
            $clave = (int) ($linea->subd_cuenta ?? 0)
                .'|'.number_format((float) ($linea->subd_importe ?? 0), 2, '.', '');
            if (isset($vistas[$clave])) {
                continue;
            }
            $vistas[$clave] = true;
            $unicas[] = $linea;
        }

        return $unicas;
    }
}

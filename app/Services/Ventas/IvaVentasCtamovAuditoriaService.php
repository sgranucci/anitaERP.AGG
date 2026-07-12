<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Support\Ventas\IvaVentas\IvaVentasConciliacionCuentaSupport;

/**
 * Lee ctamov (contabilidad Anita) por rango de fechas para el reporte IVA ventas
 * y totaliza ventas / IVA por día. Convención de signo alineada a asiento_movimiento:
 * haber (H) = crédito ventas / IVA débito (+); debe (D) = − (IVA crédito fiscal netea).
 */
final class IvaVentasCtamovAuditoriaService
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   habilitada: bool,
     *   por_dia: array<string, array{ventas: float, iva: float}>,
     *   total: array{ventas: float, iva: float},
     *   lineas: int,
     *   errores: list<string>,
     *   codigos: array{ventas: list<int>, iva: list<int>}
     * }
     */
    public function auditar(int $empresaId, array $filtros): array
    {
        $desde = $this->aYmd((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = $this->aYmd((string) ($filtros['fecha_hasta'] ?? ''));
        if ($empresaId <= 0 || $desde === 0 || $hasta === 0) {
            return $this->vacio();
        }

        $codigos = IvaVentasConciliacionCuentaSupport::codigosCtamovConciliacion($empresaId);
        $todos = array_values(array_unique(array_merge($codigos['ventas'], $codigos['iva'])));
        if ($todos === []) {
            return $this->vacio(['Sin cuentas configuradas para auditar ctamov.'], $codigos);
        }

        $setVentas = array_flip($codigos['ventas']);
        $setIva = array_flip($codigos['iva']);

        $errores = [];
        $filas = $this->listarCtamov($empresaId, $desde, $hasta, $todos, $errores);

        $porDia = [];
        $totalVentas = 0.0;
        $totalIva = 0.0;
        $lineas = 0;

        foreach ($filas as $fila) {
            $cuenta = (int) preg_replace('/\D+/', '', (string) ($fila->ctav_cuenta ?? ''));
            if ($cuenta <= 0) {
                continue;
            }

            $enIva = isset($setIva[$cuenta]);
            $enVentas = isset($setVentas[$cuenta]);
            if (! $enIva && ! $enVentas) {
                continue;
            }

            $dia = $this->fechaDesdeAnita((string) ($fila->ctav_fecha ?? ''));
            if ($dia === null) {
                continue;
            }

            $importe = (float) ($fila->ctav_importe ?? 0);
            $dh = strtoupper(trim((string) ($fila->ctav_d_h ?? 'D')));
            $valor = round(($dh === 'H' ? 1.0 : -1.0) * $importe, 2);

            if (! isset($porDia[$dia])) {
                $porDia[$dia] = ['ventas' => 0.0, 'iva' => 0.0];
            }

            if ($enIva) {
                $porDia[$dia]['iva'] = round($porDia[$dia]['iva'] + $valor, 2);
                $totalIva = round($totalIva + $valor, 2);
            } else {
                $porDia[$dia]['ventas'] = round($porDia[$dia]['ventas'] + $valor, 2);
                $totalVentas = round($totalVentas + $valor, 2);
            }
            $lineas++;
        }

        return [
            'habilitada' => true,
            'por_dia' => $porDia,
            'total' => ['ventas' => $totalVentas, 'iva' => $totalIva],
            'lineas' => $lineas,
            'errores' => $errores,
            'codigos' => $codigos,
        ];
    }

    /**
     * @param  array{ventas: list<int>, iva: list<int>}  $codigos
     * @param  list<string>  $errores
     * @return array<string, mixed>
     */
    private function vacio(array $errores = [], array $codigos = ['ventas' => [], 'iva' => []]): array
    {
        return [
            'habilitada' => false,
            'por_dia' => [],
            'total' => ['ventas' => 0.0, 'iva' => 0.0],
            'lineas' => 0,
            'errores' => $errores,
            'codigos' => $codigos,
        ];
    }

    /**
     * @param  list<int>  $codigos
     * @param  list<string>  $errores
     * @return list<object>
     */
    private function listarCtamov(int $empresaId, int $desde, int $hasta, array $codigos, array &$errores): array
    {
        $inCodigos = implode(',', array_map('intval', $codigos));

        $raw = $this->api->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'ctamov',
            'campos' => 'ctav_empresa,ctav_fecha,ctav_cuenta,ctav_d_h,ctav_importe',
            'whereArmado' => ' WHERE ctav_empresa='.$empresaId
                .' AND ctav_fecha BETWEEN '.$desde.' AND '.$hasta
                .' AND ctav_cuenta IN ('.$inCodigos.')',
        ]);

        $msg = ApiAnita::extraerMensajeError($raw);
        if ($msg !== null) {
            $errores[] = 'ctamov IVA ventas: '.$msg;

            return [];
        }

        return ApiAnita::decodificarListaFilas($raw);
    }

    private function aYmd(string $fecha): int
    {
        $digits = preg_replace('/\D+/', '', $fecha);

        return $digits !== null && strlen($digits) === 8 ? (int) $digits : 0;
    }

    private function fechaDesdeAnita(string $fechaEntera): ?string
    {
        $digits = preg_replace('/\D+/', '', $fechaEntera);
        if ($digits === null || strlen($digits) !== 8) {
            return null;
        }

        return substr($digits, 0, 4).'-'.substr($digits, 4, 2).'-'.substr($digits, 6, 2);
    }
}

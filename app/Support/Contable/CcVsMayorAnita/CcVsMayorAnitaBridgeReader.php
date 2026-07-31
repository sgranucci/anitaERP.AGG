<?php

declare(strict_types=1);

namespace App\Support\Contable\CcVsMayorAnita;

use App\ApiAnita;

/**
 * Lecturas Anita bridge para control CC (climov/aplmov) vs mayor (subdiario).
 */
final class CcVsMayorAnitaBridgeReader
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {
    }

    /**
     * @return list<object>
     */
    public function listarClimov(int $fechaYmd): array
    {
        return $this->listarConRetry(
            'ventas',
            'climov',
            'cliv_cliente,cliv_tipo,cliv_letra,cliv_sucursal,cliv_nro,cliv_ref_tipo,cliv_ref_letra,'
            .'cliv_ref_sucursal,cliv_ref_nro,cliv_fecha,cliv_monto,cliv_t_cobrado,cliv_estado,cliv_cod_mon,cliv_cotizacion',
            ' WHERE cliv_fecha = '.$fechaYmd,
        );
    }

    /**
     * @return list<object>
     */
    public function listarAplmov(int $fechaYmd): array
    {
        return $this->listarConRetry(
            'ventas',
            'aplmov',
            'aplv_tipo,aplv_letra,aplv_sucursal,aplv_nro,aplv_nro_cuota,aplv_monto,aplv_fecha,'
            .'aplv_tipo_cob,aplv_letra_cob,aplv_sucursal_cob,aplv_nro_cob,aplv_fecha_aplic,aplv_cod_mon,aplv_cotizacion',
            ' WHERE aplv_fecha = '.$fechaYmd.' OR aplv_fecha_aplic = '.$fechaYmd,
        );
    }

    /**
     * @return array{filas: list<object>, error: ?string}
     */
    public function listarSubdiarioConMeta(string $sistema, int $fechaYmd, int $cuentaCodigo = 0): array
    {
        $sistema = strtolower(trim($sistema));
        if ($sistema === '') {
            $sistema = (string) config('anita.subdiario_sistema', 'ventas');
        }

        $where = ' WHERE subd_fecha = '.$fechaYmd;
        if ($cuentaCodigo > 0) {
            // Mismo criterio que AnitaMayorAnaliticoSupport: pierna cuenta O contrapartida.
            $where .= ' AND (subd_cuenta = '.$cuentaCodigo.' OR subd_contrapartida = '.$cuentaCodigo.')';
        }

        $intentos = max(3, (int) config('anita.bridge_list_reintentos', 8));
        $ultimoErr = null;
        $mejor = [];

        for ($i = 1; $i <= $intentos; $i++) {
            $raw = (string) $this->api->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => 'subdiario',
                'campos' => 'subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_emisor,'
                    .'subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_importe,subd_desc_mov',
                'whereArmado' => $where,
            ]);
            $ultimoErr = ApiAnita::extraerMensajeError($raw);
            if ($ultimoErr === null) {
                $filas = ApiAnita::decodificarListaFilas($raw);
                if (count($filas) > count($mejor)) {
                    $mejor = $filas;
                }
                // Con filtro de cuenta, un día operativo con CC suele tener filas; reintentar [].
                if ($filas !== [] || $i === $intentos) {
                    if ($mejor !== []) {
                        return ['filas' => $mejor, 'error' => null];
                    }

                    return [
                        'filas' => [],
                        'error' => 'Bridge devolvió subdiario vacío en '.$sistema
                            .' tras '.$intentos.' intentos (fecha '.$fechaYmd
                            .($cuentaCodigo > 0 ? ', cuenta '.$cuentaCodigo : '').').',
                    ];
                }
            } elseif ($i === $intentos) {
                return ['filas' => $mejor, 'error' => $ultimoErr];
            }
            usleep(350000);
        }

        return ['filas' => $mejor, 'error' => $ultimoErr ?? 'Sin respuesta subdiario'];
    }

    /**
     * @return list<object>
     */
    public function listarSubdiario(string $sistema, int $fechaYmd, int $cuentaCodigo = 0): array
    {
        return $this->listarSubdiarioConMeta($sistema, $fechaYmd, $cuentaCodigo)['filas'];
    }

    /**
     * @return list<object>
     */
    private function listarConRetry(string $sistema, string $tabla, string $campos, string $whereArmado): array
    {
        $intentos = max(1, (int) config('anita.bridge_list_reintentos', 6));
        $raw = '';

        for ($i = 1; $i <= $intentos; $i++) {
            $raw = (string) $this->api->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $whereArmado,
            ]);

            if (ApiAnita::extraerMensajeError($raw) === null) {
                $filas = ApiAnita::decodificarListaFilas($raw);
                if ($filas !== [] || $i === $intentos) {
                    return $filas;
                }
            } elseif ($i === $intentos) {
                return [];
            }

            usleep(200000);
        }

        return [];
    }
}

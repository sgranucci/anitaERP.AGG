<?php

namespace App\Support\Compras;

use App\ApiAnita;
use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;

/**
 * Suma Haber−Debe de ctamov Anita (AP MN/ME/anticipo) por nro de asiento.
 */
final class ComprobanteProveedorImputacionApCtamovSupport
{
    /**
     * `ctav_desc_mov` último: el bridge parte por `|` y un `|` en la descripción corre el resto.
     */
    private const CAMPOS = 'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_cuenta,ctav_d_h,'
        .'ctav_importe,ctav_cotizacion,ctav_cod_mon,ctav_desc_mov';

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {}

    /**
     * @param  list<array{empresa_anita:int, numeroasiento:int, fecha:?string}>  $claves
     * @param  array{codigo_mn?: array<int, true>, codigo_me?: array<int, true>, codigo_anticipo?: array<int, true>}  $catalogo
     * @return array<string, array{trio: float, lineas: int, encontrado: bool}>
     */
    public function sumarTrioPorAsiento(array $claves, array $catalogo): array
    {
        $out = [];
        $porEmpresa = [];
        foreach ($claves as $clave) {
            $empresa = (int) ($clave['empresa_anita'] ?? 0);
            $nro = (int) ($clave['numeroasiento'] ?? 0);
            if ($empresa <= 0 || $nro <= 0) {
                continue;
            }
            $key = self::clave($empresa, $nro);
            $out[$key] = ['trio' => 0.0, 'lineas' => 0, 'encontrado' => false];
            $porEmpresa[$empresa][$nro] = (string) ($clave['fecha'] ?? '');
        }

        $codigosAp = ComprobanteProveedorImputacionApCuentasSupport::codigosAp($catalogo);
        if ($codigosAp === [] || $porEmpresa === []) {
            return $out;
        }

        foreach ($porEmpresa as $empresaAnita => $asientos) {
            $nros = array_map('intval', array_keys($asientos));
            foreach (array_chunk($nros, 40) as $lote) {
                foreach ($this->listarLote((int) $empresaAnita, $lote, $codigosAp) as $linea) {
                    $nro = (int) ($linea->ctav_nro_asiento ?? 0);
                    $key = self::clave((int) $empresaAnita, $nro);
                    if (! isset($out[$key])) {
                        continue;
                    }

                    $imputacion = AnitaSubdiarioMayorSupport::imputacionLineaCtamov($linea);
                    if ($imputacion === null) {
                        continue;
                    }

                    $cuenta = (int) $imputacion['cuenta'];
                    if (ComprobanteProveedorImputacionApSupport::clasificarCodigo($cuenta, $catalogo) === null) {
                        continue;
                    }

                    $dhData = AnitaSubdiarioMayorSupport::debeHaberDesdeDh(
                        (string) $imputacion['dh'],
                        (float) $imputacion['importe'],
                    );
                    $fecha = $asientos[$nro] ?? '';
                    $ars = ComprobanteProveedorImputacionApSupport::aPesosTolerante(
                        (float) $dhData['neto_haber'],
                        (int) ($linea->ctav_cod_mon ?? 1),
                        $linea->ctav_cotizacion ?? 1,
                        $fecha !== '' ? $fecha : null,
                        'ctamov asiento '.$nro
                    );

                    $out[$key]['trio'] = round($out[$key]['trio'] + $ars, 2);
                    $out[$key]['lineas']++;
                    $out[$key]['encontrado'] = true;
                }
            }
        }

        return $out;
    }

    public static function clave(int $empresaAnita, int $numeroasiento): string
    {
        return $empresaAnita.':'.$numeroasiento;
    }

    /**
     * @param  list<int>  $nrosAsiento
     * @param  list<int>  $codigosAp
     * @return list<object>
     */
    private function listarLote(int $empresaAnita, array $nrosAsiento, array $codigosAp): array
    {
        $nrosAsiento = array_values(array_filter(array_map('intval', $nrosAsiento), static fn (int $n) => $n > 0));
        if ($empresaAnita <= 0 || $nrosAsiento === [] || $codigosAp === []) {
            return [];
        }

        $whereCuentas = implode(' OR ', array_map(
            static fn (int $c) => 'ctav_cuenta = '.$c,
            $codigosAp
        ));
        $whereAsientos = implode(',', $nrosAsiento);
        $where = ' WHERE ctav_empresa = '.$empresaAnita
            .' AND ctav_nro_asiento IN ('.$whereAsientos.')'
            .' AND ('.$whereCuentas.')';

        $payload = [
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'ctamov',
            'campos' => self::CAMPOS,
            'whereArmado' => $where,
            'orderBy' => 'ctav_nro_asiento, ctav_nro_linea',
        ];

        $intentos = max(1, (int) config('comprobante_proveedor_anita.imputacion_ap_diaria.anita_reintentos_bridge', 3));
        for ($i = 1; $i <= $intentos; $i++) {
            $raw = $this->api->apiCall($payload);
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

<?php

declare(strict_types=1);

namespace App\Support\Contable\Anita;

use App\ApiAnita;

/**
 * Mayor analítico Anita por cuentas: subdiario + ctamov (patrón l-mayor.c).
 */
final class AnitaMayorAnaliticoSupport
{
    private const SUBDIARIO_CAMPOS = 'subd_fecha,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_importe,'
        .'subd_desc_mov,subd_nro_operacion,subd_nro_asiento';

    private const CTAMOV_CAMPOS = 'ctav_fecha,ctav_nro_asiento,ctav_nro_linea,ctav_cuenta,ctav_d_h,'
        .'ctav_importe,ctav_desc_mov';

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {
    }

    /**
     * @param  list<int>  $codigosCuenta  Códigos numéricos Anita (ej. 214010013)
     * @return list<array<string, mixed>>
     */
    public function listarMovimientosPeriodo(
        int $empresaAnita,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        array $codigosCuenta,
    ): array {
        $codigosCuenta = array_values(array_unique(array_filter(
            array_map('intval', $codigosCuenta),
            static fn (int $codigo) => $codigo > 0,
        )));

        if ($empresaAnita <= 0 || $fechaDesdeYmd <= 0 || $fechaHastaYmd <= 0 || $codigosCuenta === []) {
            return [];
        }

        $codigosSet = array_fill_keys($codigosCuenta, true);
        $subdiario = $this->listarSubdiario($empresaAnita, $fechaDesdeYmd, $fechaHastaYmd, $codigosCuenta);
        $ctamov = $this->listarCtamov($empresaAnita, $fechaDesdeYmd, $fechaHastaYmd, $codigosCuenta);

        $out = [];

        foreach ($subdiario as $linea) {
            foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($linea) as $imputacion) {
                $cuenta = (int) $imputacion['cuenta'];
                if (! isset($codigosSet[$cuenta])) {
                    continue;
                }

                $dhData = AnitaSubdiarioMayorSupport::debeHaberDesdeDh(
                    (string) $imputacion['dh'],
                    (float) $imputacion['importe'],
                );

                $out[] = [
                    'fecha' => $this->fechaAnitaAIso((int) ($linea->subd_fecha ?? 0)),
                    'asiento_id' => (int) ($linea->subd_nro_operacion ?? $linea->subd_nro_asiento ?? 0),
                    'cuenta_codigo' => (string) $cuenta,
                    'cuenta_nombre' => '',
                    'debe' => $dhData['debe'],
                    'haber' => $dhData['haber'],
                    'neto_haber' => $dhData['neto_haber'],
                    'detalle' => trim((string) ($linea->subd_desc_mov ?? '')),
                    'origen' => 'anita_subdiario',
                ];
            }
        }

        foreach ($ctamov as $linea) {
            $imputacion = AnitaSubdiarioMayorSupport::imputacionLineaCtamov($linea);
            if ($imputacion === null) {
                continue;
            }

            $cuenta = (int) $imputacion['cuenta'];
            if (! isset($codigosSet[$cuenta])) {
                continue;
            }

            $dhData = AnitaSubdiarioMayorSupport::debeHaberDesdeDh(
                (string) $imputacion['dh'],
                (float) $imputacion['importe'],
            );

            $out[] = [
                'fecha' => $this->fechaAnitaAIso((int) ($linea->ctav_fecha ?? 0)),
                'asiento_id' => (int) ($linea->ctav_nro_asiento ?? 0),
                'cuenta_codigo' => (string) $cuenta,
                'cuenta_nombre' => '',
                'debe' => $dhData['debe'],
                'haber' => $dhData['haber'],
                'neto_haber' => $dhData['neto_haber'],
                'detalle' => trim((string) ($linea->ctav_desc_mov ?? '')),
                'origen' => 'anita_ctamov',
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return [$a['fecha'], $a['asiento_id'], $a['cuenta_codigo']]
                <=> [$b['fecha'], $b['asiento_id'], $b['cuenta_codigo']];
        });

        return $out;
    }

    /**
     * @param  list<int>  $codigosCuenta
     * @return list<object>
     */
    private function listarSubdiario(int $empresaAnita, int $fechaDesdeYmd, int $fechaHastaYmd, array $codigosCuenta): array
    {
        $whereCuentas = $this->whereCuentasSubdiario($codigosCuenta);

        return $this->listarBridge(
            'contab',
            'subdiario',
            self::SUBDIARIO_CAMPOS,
            ' WHERE subd_empresa='.$empresaAnita
            .' AND subd_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
            .' AND '.$whereCuentas,
        );
    }

    /**
     * @param  list<int>  $codigosCuenta
     * @return list<object>
     */
    private function listarCtamov(int $empresaAnita, int $fechaDesdeYmd, int $fechaHastaYmd, array $codigosCuenta): array
    {
        $whereCuentas = $this->whereCuentasCtamov($codigosCuenta);

        return $this->listarBridge(
            'contab',
            'ctamov',
            self::CTAMOV_CAMPOS,
            ' WHERE ctav_empresa='.$empresaAnita
            .' AND ctav_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
            .' AND '.$whereCuentas,
        );
    }

    /**
     * @param  list<int>  $codigosCuenta
     */
    private function whereCuentasSubdiario(array $codigosCuenta): string
    {
        $partes = [];
        foreach ($codigosCuenta as $codigo) {
            $codigo = (int) $codigo;
            $partes[] = 'subd_cuenta = '.$codigo;
            $partes[] = 'subd_contrapartida = '.$codigo;
        }

        return '('.implode(' OR ', $partes).')';
    }

    /**
     * @param  list<int>  $codigosCuenta
     */
    private function whereCuentasCtamov(array $codigosCuenta): string
    {
        $partes = array_map(static fn (int $codigo) => 'ctav_cuenta = '.$codigo, $codigosCuenta);

        return '('.implode(' OR ', $partes).')';
    }

    /**
     * @return list<object>
     */
    private function listarBridge(string $sistema, string $tabla, string $campos, string $whereArmado): array
    {
        $raw = $this->api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $whereArmado,
            'orderBy' => $tabla === 'subdiario'
                ? 'subd_fecha, subd_nro_operacion'
                : 'ctav_fecha, ctav_nro_asiento, ctav_nro_linea',
        ]);

        if (ApiAnita::extraerMensajeError($raw) !== null) {
            return [];
        }

        return ApiAnita::decodificarListaFilas($raw);
    }

    private function fechaAnitaAIso(int $fechaAnita): string
    {
        if ($fechaAnita <= 0) {
            return '';
        }

        $s = str_pad((string) $fechaAnita, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}

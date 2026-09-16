<?php

declare(strict_types=1);

namespace App\Support\Caja\Macro;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;

/**
 * Textos RTN de retenciones al estilo p-enviamacro.c:
 *  tipo 1 Ganancias, 2 IVA, 3 SUSS, 4 IBR, 90 comprobantes.
 *  zona 1 = cabecera / zona 2 = detalle (90 usa zona 99).
 */
final class MacroArchivoPagoRetencionTextoSupport
{
    public const TIPO_GANANCIAS = 1;

    public const TIPO_IVA = 2;

    public const TIPO_SUSS = 3;

    public const TIPO_IBR = 4;

    public const TIPO_COMPROBANTES = 90;

    /**
     * @param  list<string>  $lineasTexto  líneas crudas del certificado / listado
     * @return list<array{orden_pago:string,tipo_id:int,zona_id:int,secuencia_id:int,texto:string,usuario:string}>
     */
    public static function partirLineasComoAnita(
        string $ordenPago,
        int $tipoId,
        array $lineasTexto,
        string $usuario,
    ): array {
        $out = [];
        $seq1 = 1;
        $seq2 = 1;
        $headerIva = true;
        $headerIbr = true;

        foreach ($lineasTexto as $raw) {
            $texto = rtrim((string) $raw, "\r\n");
            if ($texto === '') {
                continue;
            }

            $zona = 2;
            $seq = &$seq2;

            switch ($tipoId) {
                case self::TIPO_GANANCIAS:
                    if (strncmp($texto, 'COMPROBANTE', 11) === 0) {
                        $zona = 1;
                        $seq = &$seq1;
                    }
                    break;
                case self::TIPO_IVA:
                    if ($headerIva) {
                        $zona = 1;
                        $seq = &$seq1;
                        if (strncmp($texto, str_repeat('-', 80), 80) === 0) {
                            $headerIva = false;
                        }
                    }
                    break;
                case self::TIPO_SUSS:
                    if (strncmp($texto, 'COMPROBANTE DE RETENCION DE SISTEMA', 35) === 0) {
                        $zona = 1;
                        $seq = &$seq1;
                    }
                    break;
                case self::TIPO_IBR:
                    if ($headerIbr) {
                        $zona = 1;
                        $seq = &$seq1;
                        $headerIbr = false;
                    }
                    break;
                case self::TIPO_COMPROBANTES:
                    $zona = 99;
                    break;
            }

            $out[] = [
                'orden_pago' => $ordenPago,
                'tipo_id' => $tipoId,
                'zona_id' => $zona,
                'secuencia_id' => $seq++,
                'texto' => $texto,
                'usuario' => $usuario,
            ];
        }

        return $out;
    }

    /**
     * Comprobantes aplicados (tipo 90), igual que p-enviamacro.
     *
     * @param  list<array{tipo_apli:string,letra:string,sucursal:int,nro:int,monto_ap:float}>  $comps
     * @return list<array<string,mixed>>
     */
    public static function desdeComprobantes(string $ordenPago, array $comps, string $usuario): array
    {
        if ($comps === []) {
            return [];
        }
        $lineas = [
            'COMPROBANTES APLICADOS',
            'Nro.de documento    Importe Bruto',
        ];
        foreach ($comps as $c) {
            $lineas[] = sprintf(
                '%3.3s%s-%05d-%08d %.2f',
                $c['tipo_apli'],
                ($c['letra'] !== '' ? $c['letra'] : ' '),
                (int) $c['sucursal'],
                (int) $c['nro'],
                (float) $c['monto_ap']
            );
        }

        return self::partirLineasComoAnita($ordenPago, self::TIPO_COMPROBANTES, $lineas, $usuario);
    }

    /**
     * Retenciones ERP (pagoproveedor_retencion) → textos RTN.
     *
     * @return list<array<string,mixed>>
     */
    public static function desdePagoproveedor(string $ordenPago, Pagoproveedor $op, string $usuario): array
    {
        $op->loadMissing(['proveedores', 'pagoproveedor_retenciones.provincias']);
        $prov = $op->proveedores;
        $nombre = (string) ($prov->nombre ?? '');
        $cuit = MacroArchivoPagoFormatoSupport::cuit11((string) ($prov->nroinscripcion ?? ''));
        $fecha = $op->fecha instanceof \DateTimeInterface
            ? $op->fecha->format('d/m/Y')
            : date('d/m/Y', strtotime((string) $op->fecha) ?: time());

        $out = [];
        foreach ($op->pagoproveedor_retenciones as $ret) {
            if ((float) $ret->importe < 0.005) {
                continue;
            }
            $tipoId = match ((string) $ret->tiporetencion) {
                Pagoproveedor_Retencion::TIPO_GANANCIAS => self::TIPO_GANANCIAS,
                Pagoproveedor_Retencion::TIPO_IVA => self::TIPO_IVA,
                Pagoproveedor_Retencion::TIPO_SUSS => self::TIPO_SUSS,
                Pagoproveedor_Retencion::TIPO_IIBB => self::TIPO_IBR,
                default => 0,
            };
            if ($tipoId === 0) {
                continue;
            }
            $lineas = self::textoCertificadoErp($tipoId, $ret, $ordenPago, $nombre, $cuit, $fecha);
            array_push($out, ...self::partirLineasComoAnita($ordenPago, $tipoId, $lineas, $usuario));
        }

        return $out;
    }

    /**
     * Retenciones Anita (filas retmov / retimov / retsmov / retibrmov).
     *
     * @param  array{
     *   ganancias?:list<object>,
     *   iva?:list<object>,
     *   suss?:list<object>,
     *   ibr?:list<object>
     * }  $bloques
     * @return list<array<string,mixed>>
     */
    public static function desdeAnitaBloques(
        string $ordenPago,
        array $bloques,
        string $usuario,
        string $proveedorNombre = '',
        string $cuit = '',
    ): array {
        $out = [];

        $gan = $bloques['ganancias'] ?? [];
        if ($gan !== []) {
            $lineas = self::textoGananciasAnita($ordenPago, $gan, $proveedorNombre, $cuit);
            array_push($out, ...self::partirLineasComoAnita($ordenPago, self::TIPO_GANANCIAS, $lineas, $usuario));
        }

        $iva = $bloques['iva'] ?? [];
        if ($iva !== []) {
            $lineas = self::textoIvaAnita($ordenPago, $iva, $proveedorNombre, $cuit);
            array_push($out, ...self::partirLineasComoAnita($ordenPago, self::TIPO_IVA, $lineas, $usuario));
        }

        $suss = $bloques['suss'] ?? [];
        if ($suss !== []) {
            $lineas = self::textoSussAnita($ordenPago, $suss, $proveedorNombre, $cuit);
            array_push($out, ...self::partirLineasComoAnita($ordenPago, self::TIPO_SUSS, $lineas, $usuario));
        }

        $ibr = $bloques['ibr'] ?? [];
        if ($ibr !== []) {
            $lineas = self::textoIbrAnita($ordenPago, $ibr, $proveedorNombre, $cuit);
            array_push($out, ...self::partirLineasComoAnita($ordenPago, self::TIPO_IBR, $lineas, $usuario));
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function textoCertificadoErp(
        int $tipoId,
        Pagoproveedor_Retencion $ret,
        string $ordenPago,
        string $nombre,
        string $cuit,
        string $fecha,
    ): array {
        $nro = (int) ($ret->nro_certificado ?? 0);
        $base = number_format((float) $ret->base_calculo, 2, ',', '.');
        $ali = number_format((float) $ret->alicuota, 2, ',', '.');
        $imp = number_format((float) $ret->importe, 2, ',', '.');
        $regimen = trim((string) ($ret->codigo_regimen ?: $ret->codigo_retencion ?: ''));
        $provincia = trim((string) ($ret->provincias->nombre ?? ''));

        return match ($tipoId) {
            self::TIPO_GANANCIAS => [
                'COMPROBANTE DE RETENCION DE GANANCIAS Nro. '.$nro,
                sprintf('Proveedor: %-30.30s CUIT: %s', $nombre, $cuit),
                sprintf('OP: %s Fecha: %s', $ordenPago, $fecha),
                sprintf('Regimen: %s Alicuota: %s%%', $regimen !== '' ? $regimen : '-', $ali),
                sprintf('Base imponible: %s Retencion: %s', $base, $imp),
            ],
            self::TIPO_IVA => [
                'COMPROBANTE DE RETENCION DE IVA Nro. '.$nro,
                sprintf('Proveedor: %-30.30s CUIT: %s', $nombre, $cuit),
                sprintf('OP: %s Fecha: %s', $ordenPago, $fecha),
                sprintf('Regimen: %s Alicuota: %s%%', $regimen !== '' ? $regimen : '-', $ali),
                sprintf('Base: %s Retencion: %s', $base, $imp),
                str_repeat('-', 80),
                sprintf('Detalle certificado %-10s Base %s Alic. %s%% Imp. %s', (string) $nro, $base, $ali, $imp),
            ],
            self::TIPO_SUSS => [
                'COMPROBANTE DE RETENCION DE SISTEMA DE SEGURIDAD SOCIAL Nro. '.$nro,
                sprintf('Proveedor: %-30.30s CUIT: %s', $nombre, $cuit),
                sprintf('OP: %s Fecha: %s', $ordenPago, $fecha),
                sprintf('Base: %s Alicuota: %s%% Retencion: %s', $base, $ali, $imp),
            ],
            self::TIPO_IBR => [
                'COMPROBANTE DE RETENCION DE INGRESOS BRUTOS Nro. '.$nro
                    .($provincia !== '' ? ' ('.$provincia.')' : ''),
                sprintf('Proveedor: %-30.30s CUIT: %s', $nombre, $cuit),
                sprintf('OP: %s Fecha: %s', $ordenPago, $fecha),
                sprintf('Base: %s Alicuota: %s%% Retencion: %s', $base, $ali, $imp),
            ],
            default => [],
        };
    }

    /**
     * @param  list<object>  $filas
     * @return list<string>
     */
    private static function textoGananciasAnita(string $ordenPago, array $filas, string $nombre, string $cuit): array
    {
        $lineas = [];
        foreach ($filas as $f) {
            $ret = (float) ($f->retv_retencion ?? 0);
            if (abs($ret) < 0.005) {
                continue;
            }
            $nro = (int) ($f->retv_nro_retencion ?? 0);
            $fecha = self::fechaAnita((string) ($f->retv_fecha ?? ''));
            $nombreF = trim((string) ($f->retv_nombre_prov ?? $nombre));
            $cuitF = MacroArchivoPagoFormatoSupport::cuit11((string) ($f->retv_cuit_prov ?? $cuit));
            $lineas[] = 'COMPROBANTE DE RETENCION DE GANANCIAS Nro. '.$nro;
            $lineas[] = sprintf('Proveedor: %-30.30s CUIT: %s', $nombreF, $cuitF);
            $lineas[] = sprintf('OP: %s Fecha: %s Cod.regimen: %s', $ordenPago, $fecha, (string) ($f->retv_codigo_ret ?? ''));
            $lineas[] = sprintf(
                'Gravado: %s Pago actual: %s Sujeto: %s',
                self::num($f->retv_gravado ?? 0),
                self::num($f->retv_pago_actual ?? 0),
                self::num($f->retv_sujeto ?? 0)
            );
            $lineas[] = sprintf(
                'Alicuota: %s%% Retencion: %s Ya retenido: %s',
                self::num($f->retv_porc_ret ?? 0),
                self::num($ret),
                self::num($f->retv_ret_anterior ?? 0)
            );
        }

        return $lineas;
    }

    /**
     * @param  list<object>  $filas
     * @return list<string>
     */
    private static function textoIvaAnita(string $ordenPago, array $filas, string $nombre, string $cuit): array
    {
        $lineas = [
            'COMPROBANTE DE RETENCION DE IVA',
            sprintf('Proveedor: %-30.30s CUIT: %s', $nombre, $cuit),
            'OP: '.$ordenPago,
            str_repeat('-', 80),
        ];
        foreach ($filas as $f) {
            $ret = (float) ($f->retiv_retencion ?? 0);
            if (abs($ret) < 0.005) {
                continue;
            }
            $comp = sprintf(
                '%3.3s%s-%05d-%08d',
                strtoupper(substr(trim((string) ($f->retiv_tipo_comp ?? '')), 0, 3)),
                (string) ($f->retiv_letra_comp ?? ' '),
                (int) ($f->retiv_suc_comp ?? 0),
                (int) ($f->retiv_nro_comp ?? 0)
            );
            $lineas[] = sprintf(
                'Cert.%d Comp:%s Grav:%s Base:%s Alic:%s%% Ret:%s',
                (int) ($f->retiv_nro_ret ?? 0),
                $comp,
                self::num($f->retiv_gravado ?? 0),
                self::num($f->retiv_sujeto ?? 0),
                self::num($f->retiv_porc_ret ?? 0),
                self::num($ret)
            );
        }

        return $lineas;
    }

    /**
     * @param  list<object>  $filas
     * @return list<string>
     */
    private static function textoSussAnita(string $ordenPago, array $filas, string $nombre, string $cuit): array
    {
        $lineas = [];
        foreach ($filas as $f) {
            $ret = (float) ($f->retsv_retencion ?? 0);
            if (abs($ret) < 0.005) {
                continue;
            }
            $nro = (int) ($f->retsv_nro_ret ?? 0);
            $lineas[] = 'COMPROBANTE DE RETENCION DE SISTEMA DE SEGURIDAD SOCIAL Nro. '.$nro;
            $lineas[] = sprintf('Proveedor: %-30.30s CUIT: %s', $nombre, $cuit);
            $lineas[] = sprintf(
                'OP: %s Fecha: %s Cod:%s Grav:%s Base:%s Alic:%s%% Ret:%s',
                $ordenPago,
                self::fechaAnita((string) ($f->retsv_fecha ?? '')),
                (string) ($f->retsv_codigo_ret ?? ''),
                self::num($f->retsv_gravado ?? 0),
                self::num($f->retsv_base_calculo ?? $f->retsv_gravado ?? 0),
                self::num($f->retsv_porc_ret ?? 0),
                self::num($ret)
            );
        }

        return $lineas;
    }

    /**
     * @param  list<object>  $filas
     * @return list<string>
     */
    private static function textoIbrAnita(string $ordenPago, array $filas, string $nombre, string $cuit): array
    {
        $lineas = [];
        $primero = true;
        foreach ($filas as $f) {
            $ret = (float) ($f->retibr_retencion ?? 0);
            if (abs($ret) < 0.005) {
                continue;
            }
            if ($primero) {
                $lineas[] = 'COMPROBANTE DE RETENCION DE INGRESOS BRUTOS Nro. '
                    .(int) ($f->retibr_nro_ret ?? 0);
                $primero = false;
            }
            $comp = sprintf(
                '%3.3s%s-%05d-%08d',
                strtoupper(substr(trim((string) ($f->retibr_tipo_comp ?? '')), 0, 3)),
                (string) ($f->retibr_letra_comp ?? ' '),
                (int) ($f->retibr_suc_comp ?? 0),
                (int) ($f->retibr_nro_comp ?? 0)
            );
            $lineas[] = sprintf(
                'Prov.%d Comp:%s Grav:%s Alic:%s%% Ret:%s OP:%s %s',
                (int) ($f->retibr_provincia ?? 0),
                $comp,
                self::num($f->retibr_gravado ?? 0),
                self::num($f->retibr_porc_ret ?? 0),
                self::num($ret),
                $ordenPago,
                $nombre !== '' ? $nombre : $cuit
            );
        }

        return $lineas;
    }

    private static function fechaAnita(string $ymd): string
    {
        $ymd = preg_replace('/\D+/', '', $ymd) ?? '';
        if (strlen($ymd) !== 8) {
            return $ymd;
        }

        return substr($ymd, 6, 2).'/'.substr($ymd, 4, 2).'/'.substr($ymd, 0, 4);
    }

    private static function num(mixed $v): string
    {
        return number_format((float) $v, 2, ',', '.');
    }
}

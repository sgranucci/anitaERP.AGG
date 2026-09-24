<?php

declare(strict_types=1);

namespace App\Support\Caja\Macro;

/**
 * Formatos diskette Banco Macro (p-enviamacro.c):
 * - BNF.TXT beneficiarios
 * - OPG.TXT órdenes (transferencias / cheques)
 * - RTN.TXT retenciones / textos
 *
 * Separador de campos: TAB (ASCII 9). Importes con coma decimal.
 */
final class MacroArchivoPagoFormatoSupport
{
    public const SEP = "\t";

    public const ARCHIVO_BNF = 'BNF.TXT';

    public const ARCHIVO_OPG = 'OPG.TXT';

    public const ARCHIVO_RTN = 'RTN.TXT';

    /** Modalidad OPG: transferencia CBU Macro (mismo banco). */
    public const MODALIDAD_TRANSF_MACRO = 2;

    /** Modalidad OPG: transferencia CBU otros bancos. */
    public const MODALIDAD_TRANSF_OTROS = 4;

    /** Modalidad OPG: cheque al día. */
    public const MODALIDAD_CHEQUE = 6;

    /** Modalidad OPG: cheque diferido. */
    public const MODALIDAD_CHEQUE_DIFERIDO = 8;

    /**
     * @param  list<array<string,mixed>>  $beneficiarios
     * @param  list<array<string,mixed>>  $ordenes
     * @param  list<array<string,mixed>>  $retenciones
     * @return array<string, string> nombre => contenido
     */
    public static function generarArchivos(array $beneficiarios, array $ordenes, array $retenciones): array
    {
        return [
            self::ARCHIVO_BNF => self::generarBnf($beneficiarios),
            self::ARCHIVO_OPG => self::generarOpg($ordenes),
            self::ARCHIVO_RTN => self::generarRtn($retenciones),
        ];
    }

    /**
     * @param  array<string, string>  $archivos
     */
    public static function empaquetarZip(array $archivos): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'macro_zip_');
        if ($tmp === false) {
            return null;
        }
        @unlink($tmp);
        $zipPath = $tmp.'.zip';
        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        foreach ($archivos as $nombre => $contenido) {
            $zip->addFromString($nombre, $contenido);
        }
        $zip->close();
        $bin = file_get_contents($zipPath);
        @unlink($zipPath);

        return $bin === false ? null : $bin;
    }

    /**
     * BNF: tipo 10 + CUIT + cond. IIBB/Gan/IVA + nombre + domicilio + CP + email.
     *
     * Diseño Macro (16 columnas TAB): condiciones fiscales exactamente 2 dígitos,
     * razón social máx. 40 bytes, CP numérico 4 (default 1001).
     *
     * @param  list<array{
     *   cuit:string,
     *   ing_bruto:int,
     *   ganancia:int,
     *   iva:int,
     *   nombre:string,
     *   proveedor_codigo:string,
     *   domicilio?:string,
     *   cod_postal?:string,
     *   email?:string
     * }>  $filas
     */
    public static function generarBnf(array $filas): string
    {
        $out = '';
        $sep = self::SEP;
        foreach ($filas as $f) {
            $cuit = self::cuit11((string) ($f['cuit'] ?? ''));
            // Macro: bnf_numdoc 11 numérico obligatorio
            if (strlen($cuit) !== 11) {
                continue;
            }
            // Truncado por bytes (como %30.30s / %6.6s de Anita), no por caracteres UTF-8:
            // si no, razón social (máx. 40) se pasa con tildes/ñ y el banco rechaza el registro.
            $nombre = self::padBytes((string) ($f['nombre'] ?? ''), 30);
            $prov = self::padBytes((string) ($f['proveedor_codigo'] ?? ''), 6);
            $domRaw = trim(self::sanitizarCampoArchivo((string) ($f['domicilio'] ?? '')));
            $dom = self::padBytes($domRaw !== '' ? $domRaw : 'NO INFORMADA', 60);
            $cp = self::codigoPostal4((string) ($f['cod_postal'] ?? ''));
            $email = self::padBytes((string) ($f['email'] ?? ''), 40);

            // fprintf Anita:
            // "%d%c%s%c%02d%c%02d%c%02d%c%30.30s %6.6s   %c%60.60s%c%c%c%4.4s%c%c%-40.40s%c%c%c%c\n"
            $out .= '10'.$sep
                .$cuit.$sep
                .self::codigoFiscal2((int) ($f['ing_bruto'] ?? 2), 2).$sep
                .self::codigoFiscal2((int) ($f['ganancia'] ?? 2), 2).$sep
                .self::codigoFiscal2((int) ($f['iva'] ?? 1), 1).$sep
                .$nombre.' '.$prov.'   '.$sep
                .$dom.$sep
                .$sep
                .$sep
                .$cp.$sep
                .$sep
                .$email.$sep
                .$sep
                .$sep
                .$sep
                ."\n";
        }

        return $out;
    }

    /**
     * OPG transferencia o cheque.
     *
     * @param  list<array{
     *   cuit:string,
     *   sucursal_banco:int,
     *   orden_pago:string,
     *   nombre:string,
     *   importe:float,
     *   cuenta_debito:string,
     *   referencia_cbu_o_cheque:string,
     *   modalidad:int,
     *   flag_entrega:int,
     *   fecha_pago:string,
     *   fecha_cheque?:string
     * }>  $filas
     */
    public static function generarOpg(array $filas): string
    {
        $out = '';
        $sep = self::SEP;
        foreach ($filas as $f) {
            $cuit = self::cuit11((string) ($f['cuit'] ?? ''));
            $importe = self::importeConComa((float) ($f['importe'] ?? 0));
            if ($cuit === '' || $importe === '' || abs((float) ($f['importe'] ?? 0)) < 0.005) {
                continue;
            }
            $fechaPago = self::fechaDdMmYyyy((string) ($f['fecha_pago'] ?? ''));
            $fechaCheque = trim((string) ($f['fecha_cheque'] ?? ''));
            $fechaChequeFmt = $fechaCheque !== ''
                ? self::fechaDdMmYyyy($fechaCheque)
                : str_repeat(' ', 10);

            // fprintf Anita:
            // "%d%c%s%c%d%c%-30.30s%c%-30.30s%c%-15.15s%c%-s%c%-22.22s%c%d%c%d%c%10.10s%c%10.10s%c%c%c\n"
            $out .= '10'.$sep
                .$cuit.$sep
                .(int) ($f['sucursal_banco'] ?? 0).$sep
                .self::pad((string) ($f['orden_pago'] ?? ''), 30).$sep
                .self::pad((string) ($f['nombre'] ?? ''), 30).$sep
                .self::pad($importe, 15).$sep
                .(string) ($f['cuenta_debito'] ?? '').$sep
                .self::pad((string) ($f['referencia_cbu_o_cheque'] ?? ''), 22).$sep
                .(int) ($f['modalidad'] ?? self::MODALIDAD_TRANSF_OTROS).$sep
                .(int) ($f['flag_entrega'] ?? 0).$sep
                .$fechaPago.$sep
                .$fechaChequeFmt.$sep
                .$sep
                .$sep
                ."\n";
        }

        return $out;
    }

    /**
     * RTN: textos de retención / comprobantes por OP.
     *
     * @param  list<array{
     *   orden_pago:string,
     *   tipo_id:int,
     *   zona_id:int,
     *   secuencia_id:int,
     *   texto:string,
     *   usuario:string
     * }>  $filas
     */
    public static function generarRtn(array $filas): string
    {
        $out = '';
        $sep = self::SEP;
        foreach ($filas as $f) {
            $orden = self::pad((string) ($f['orden_pago'] ?? ''), 30);
            $zona = (int) ($f['zona_id'] ?? 2);
            $texto = self::sanitizarTextoRetencion((string) ($f['texto'] ?? ''));
            $ancho = $zona === 1 ? 96 : 120;
            $texto = self::pad($texto, $ancho);
            $usr = rtrim((string) ($f['usuario'] ?? ''));

            $out .= $orden.$sep
                .(int) ($f['tipo_id'] ?? 0).$sep
                .$zona.$sep
                .(int) ($f['secuencia_id'] ?? 0).$sep
                .$texto.$sep
                .$usr
                ."\n";
        }

        return $out;
    }

    public static function modalidadTransferencia(string $cbuDestino, ?int $codigoBancoProveedor = null): int
    {
        $macro = (int) config('macro.codigo_banco', 285);
        if ($codigoBancoProveedor !== null && $codigoBancoProveedor > 0) {
            return $codigoBancoProveedor === $macro
                ? self::MODALIDAD_TRANSF_MACRO
                : self::MODALIDAD_TRANSF_OTROS;
        }
        $digits = preg_replace('/\D+/', '', $cbuDestino) ?? '';
        $pref = (int) substr($digits, 0, 3);

        return $pref === $macro ? self::MODALIDAD_TRANSF_MACRO : self::MODALIDAD_TRANSF_OTROS;
    }

    public static function modalidadCheque(string $fechaEmisionYmd, string $fechaChequeYmd): int
    {
        $emi = self::ymdSoloDigitos($fechaEmisionYmd);
        $che = self::ymdSoloDigitos($fechaChequeYmd);
        if ($emi !== '' && $che !== '' && $emi === $che) {
            return self::MODALIDAD_CHEQUE;
        }

        return self::MODALIDAD_CHEQUE_DIFERIDO;
    }

    public static function ordenPagoTransferencia(string $tipo, int $sucursal, int $numero): string
    {
        return sprintf('%3.3s-%04d-%08d', strtoupper(substr(trim($tipo), 0, 3)), $sucursal, $numero);
    }

    public static function ordenPagoCheque(string $tipo, int $sucursal, int $numero, int $nroCheque): string
    {
        return sprintf(
            '%3.3s-%04d-%08d-%08d',
            strtoupper(substr(trim($tipo), 0, 3)),
            $sucursal,
            $numero,
            $nroCheque
        );
    }

    /**
     * Cuenta débito Macro 15 dígitos: `3` + sucursal(3) + CBU[10..20](11),
     * igual al número que usaba p-enviamacro (ej. CBU …094012429091 → 365109401242909).
     */
    public static function cuentaDebitoDesdeCbu(string $cbu, int $sucursalBanco = 0): string
    {
        $digits = preg_replace('/\D+/', '', $cbu) ?? '';
        if (strlen($digits) < 21) {
            return '';
        }
        $suc = $sucursalBanco > 0 ? $sucursalBanco : (int) config('macro.sucursal_default', 651);
        if ($suc <= 0) {
            $suc = 651;
        }

        return '3'.sprintf('%03d', $suc).substr($digits, 10, 11);
    }

    public static function cuentaDebitoEmpresa(int $empresaAnitaOErp): string
    {
        $override = trim((string) config('macro.cuenta_debito_default', ''));
        if ($override !== '') {
            return preg_replace('/\D+/', '', $override) ?? $override;
        }
        $map = (array) config('macro.cuentas_debito', []);
        $cuenta = (string) ($map[$empresaAnitaOErp] ?? '');

        return preg_replace('/\D+/', '', $cuenta) ?? $cuenta;
    }

    public static function usuarioRetencionEmpresa(int $empresaAnitaOErp): string
    {
        $map = (array) config('macro.usuarios_retencion', []);
        $usr = trim((string) ($map[$empresaAnitaOErp] ?? ''));

        return substr($usr, 0, 10);
    }

    /**
     * Tipos de comprobante a incluir según filtro UI (OPP también trae IEV).
     *
     * @return list<string>|null  null = OP% + extras; lista = igualdad IN
     */
    public static function tiposComprobanteFiltro(string $tipoOp): ?array
    {
        $tipoOp = strtoupper(substr(trim($tipoOp), 0, 3));
        $extras = array_values(array_filter(array_map(
            static fn ($t) => strtoupper(substr(trim((string) $t), 0, 3)),
            (array) config('macro.tipos_op_extra_con_opp', ['IEV'])
        )));

        if ($tipoOp === '' || $tipoOp === '0') {
            return null; // OP% + extras
        }
        if ($tipoOp === 'OPP') {
            return array_values(array_unique(array_merge(['OPP'], $extras)));
        }

        return [$tipoOp];
    }

    public static function cuit11(string $cuit): string
    {
        $n = preg_replace('/\D+/', '', $cuit) ?? '';

        return substr($n, 0, 11);
    }

    public static function importeConComa(float $importe): string
    {
        return number_format(abs($importe), 2, ',', '');
    }

    public static function fechaDdMmYyyy(string $fecha): string
    {
        $ymd = self::ymdSoloDigitos($fecha);
        if (strlen($ymd) !== 8) {
            return '00/00/0000';
        }

        return substr($ymd, 6, 2).'/'.substr($ymd, 4, 2).'/'.substr($ymd, 0, 4);
    }

    public static function ymdSoloDigitos(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return '';
        }
        if (preg_match('/^\d{8}$/', $fecha) === 1) {
            return $fecha;
        }
        $ts = strtotime($fecha);

        return $ts ? date('Ymd', $ts) : '';
    }

    /**
     * Códigos IIBB / Gan / IVA como p-enviamacro.c (promae).
     *
     * @return array{ing_bruto:int,ganancia:int,iva:int}
     */
    public static function condicionesFiscales(
        string $retIbr,
        string $condIva,
        string $condGan,
    ): array {
        $ing = match ($retIbr) {
            '1', '2', '3', '4' => 1, // convenio / local / conv sin bsas / caba (valores típicos Anita)
            'E' => 3, // exento
            'N' => 2, // no retiene
                default => match (true) {
                in_array($retIbr, ['C', 'L', 'S', 'A'], true) => 1,
                $retIbr === 'X' => 3,
                $retIbr === '0' => 2,
                // Evitar 999: Macro exige cib_id de 2 dígitos; %02d de 999 sale "999" y rompe el diseño.
                default => 2,
            },
        };

        // Anita PROM_*: CONVENIO/LOCAL/etc son chars; en bridge a veces vienen numéricos.
        // Mapeo explícito si el caller ya resolvió a int.
        if (ctype_digit($retIbr)) {
            $ing = match ((int) $retIbr) {
                1, 2, 3, 4 => 1,
                5 => 3, // exento approx
                6 => 2,
                default => 2,
            };
        }

        $iva = match ($condIva) {
            '1', 'I' => 1, // inscripto
            '2', 'N' => 2, // no inscripto
            '4', 'E' => 4, // exento
            '7', 'M' => 7, // monotributo
            default => ctype_digit($condIva) ? (int) $condIva : 1,
        };

        $gan = ($condGan === '1') ? 1 : 2;
        if ($condIva === '7' || $condIva === 'M' || $iva === 7) {
            $gan = 4;
        }

        return ['ing_bruto' => $ing, 'ganancia' => $gan, 'iva' => $iva];
    }

    public static function sanitizarTextoRetencion(string $texto): string
    {
        $texto = str_replace(["\r", "\n"], ' ', $texto);
        $out = '';
        $len = strlen($texto);
        for ($i = 0; $i < $len; $i++) {
            $c = $texto[$i];
            $ord = ord($c);
            $out .= ($ord < 33 || $ord > 125) ? ' ' : $c;
        }

        return $out;
    }

    /**
     * CP Macro: 4 numérico obligatorio (diseño fija default "1001").
     */
    public static function codigoPostal4(string $cp): string
    {
        $digits = preg_replace('/\D+/', '', $cp) ?? '';
        if ($digits === '') {
            return '1001';
        }

        return str_pad(substr($digits, 0, 4), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Condiciones IIBB/Gan/IVA: exactamente 2 dígitos (0–99). Fuera de rango → default.
     */
    public static function codigoFiscal2(int $codigo, int $default): string
    {
        if ($codigo < 0 || $codigo > 99) {
            $codigo = $default;
        }

        return sprintf('%02d', $codigo);
    }

    /**
     * Quita TAB/CR/LF (rompen columnas) y controles; deja UTF-8 printable.
     */
    public static function sanitizarCampoArchivo(string $texto): string
    {
        $texto = str_replace(["\t", "\r", "\n", "\0"], ' ', $texto);
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $texto) ?? $texto;

        return $texto;
    }

    private static function pad(string $valor, int $len): string
    {
        return self::padBytes($valor, $len);
    }

    /**
     * Pad/truncate por bytes (espejo de %.Ns de Anita), no por caracteres mb.
     */
    private static function padBytes(string $valor, int $len): string
    {
        $valor = self::sanitizarCampoArchivo($valor);
        if (strlen($valor) > $len) {
            $valor = function_exists('mb_strcut')
                ? (string) mb_strcut($valor, 0, $len, 'UTF-8')
                : substr($valor, 0, $len);
        }

        return str_pad($valor, $len, ' ', STR_PAD_RIGHT);
    }
}

<?php

namespace App\Support\Stock;

/**
 * ZPL etiqueta Surmar (espejo Anita eti_prod.fc «Etiquetas proveedor»).
 * Línea UMD: «{abrev separa}: {cant_unid} - Nro.: {nro_apertura}»
 * Lote: «Lote Nro.: {lote}/{nro_apertura}»
 */
final class SurmarEtiquetaZplSupport
{
    /**
     * @param  array{
     *   id:int,
     *   codigo_articulo?:string,
     *   descripcion?:string,
     *   proveedor?:string,
     *   peso_bruto?:float,
     *   peso_neto?:float,
     *   cant_pieza?:float,
     *   umd_separa?:string,
     *   cant_unid_separa?:int,
     *   nro_apertura?:int,
     *   lote?:string,
     *   fecha?:string|null,
     *   fecha_vto?:string|null
     * }  $d
     */
    public static function generar(array $d): string
    {
        $id = (int) ($d['id'] ?? 0);
        $codigo = self::trimCerosIzq((string) ($d['codigo_articulo'] ?? ''));
        $desc = self::zplSafe(mb_substr((string) ($d['descripcion'] ?? ''), 0, 40));
        $prov = self::zplSafe(mb_substr((string) ($d['proveedor'] ?? ''), 0, 20));
        $bruto = number_format((float) ($d['peso_bruto'] ?? 0), 2, '.', '');
        $netoNum = (float) ($d['peso_neto'] ?? 0);
        $piezasNum = (float) ($d['cant_pieza'] ?? 0);
        $neto = number_format($netoNum, 2, '.', '');
        $piezas = number_format($piezasNum, 2, '.', '');
        $promedio = self::formatoPesoPromedio($netoNum, $piezasNum);
        $umdSepara = self::zplSafe(mb_substr((string) ($d['umd_separa'] ?? 'UN'), 0, 3));
        $cantUnid = (int) ($d['cant_unid_separa'] ?? 1);
        if ($cantUnid < 1) {
            $cantUnid = 1;
        }
        $nroApertura = (int) ($d['nro_apertura'] ?? 1);
        if ($nroApertura < 1) {
            $nroApertura = 1;
        }
        $lote = self::zplSafe((string) ($d['lote'] ?? ''));
        $fecha = (string) ($d['fecha'] ?? '');
        $fvto = (string) ($d['fecha_vto'] ?? '');

        [$lin1, $lin2] = self::partirDescripcion($desc, 17);

        $lines = [
            '^XA',
            '^SZ2',
            '^JMA',
            '^MCY',
            '^PW800',
            '^LH5,30',
            '^FO5,10',
            '^ARN,70,70^FDArticulo: '.$codigo.'^FS',
        ];

        if ($prov !== '') {
            $lines[] = '^FO5,120';
            $lines[] = '^ARN,60,60^FDProv: '.$prov.'^FS';
        }

        $lines[] = '^FO5,200';
        $lines[] = '^ARN,80,80^FD'.$lin1.'^FS';
        $lines[] = '^FO5,280';
        $lines[] = '^ARN,80,80^FD'.$lin2.'^FS';
        $lines[] = '^FO5,350';
        $lines[] = '^ARN,60,60^FDPESO BRUTO^FS';
        $lines[] = '^FO40,430';
        $lines[] = '^ARN,90,90^FD'.$bruto.'^FS';
        $lines[] = '^FO500,350';
        $lines[] = '^ARN,60,60^FDPESO NETO^FS';
        $lines[] = '^FO510,430';
        $lines[] = '^ARN,90,90^FD'.$neto.'^FS';
        $lines[] = '^FO5,520';
        $lines[] = '^ARN,60,60^FDPIEZAS: '.$piezas.'^FS';
        if ($promedio !== '') {
            // Misma fila que piezas (derecha): peso neto / piezas
            $lines[] = '^FO400,520';
            $lines[] = '^ARN,60,60^FDProm: '.$promedio.'^FS';
        }
        // Anita: «BIN: 5 - Nro.: 2» (unidad de separación + total + apertura)
        $lines[] = '^FO5,590';
        $lines[] = '^ARN,60,60^FD'.$umdSepara.': '.$cantUnid.' - Nro.: '.$nroApertura.'^FS';

        if ($fecha !== '') {
            $lines[] = '^FO100,650';
            $lines[] = '^ARN,90,90^FDFecha : '.$fecha.'^FS';
        }
        if ($fvto !== '') {
            $lines[] = '^FO100,750';
            $lines[] = '^ARN,90,90^FDF.Vto.: '.$fvto.'^FS';
        }

        $loteTxt = $lote !== '' ? $lote.'/'.$nroApertura : (string) $nroApertura;
        $lines[] = '^FO5,820';
        $lines[] = '^ARN,60,60^FDLote Nro.: '.$loteTxt.'^FS';
        // ERP: barcode = id físico (lectura operativa). Anita eti_prod.fc usa sku-nint-nap-tipo-orden.
        $lines[] = '^FO100,950^BY2,2.2,10^B3N,N,80,Y,N^FD'.$id.'^FS';

        $qr = self::qrJson(
            $d,
            $codigo,
            $desc,
            $cantUnid,
            $umdSepara,
            $nroApertura,
            $netoNum,
            (float) ($d['peso_bruto'] ?? 0),
            $loteTxt,
            $fecha,
            $fvto,
            $promedio !== '' ? (float) $promedio : null,
        );

        $lines[] = '^FO575,0^BQN,2,3^FDMA,'.self::zplSafe($qr).'^FS';
        $lines[] = '^XZ';

        return implode("\n", $lines)."\n";
    }

    /**
     * Datos de preview HTML / PDF (misma info que el ZPL).
     *
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    public static function datosPreview(array $d): array
    {
        $cantUnid = max(1, (int) ($d['cant_unid_separa'] ?? 1));
        $nroApertura = max(1, (int) ($d['nro_apertura'] ?? 1));
        $lote = (string) ($d['lote'] ?? '');
        $umd = mb_substr((string) ($d['umd_separa'] ?? 'UN'), 0, 3);
        $codigo = self::trimCerosIzq((string) ($d['codigo_articulo'] ?? ''));
        $desc = (string) ($d['descripcion'] ?? '');
        $bruto = (float) ($d['peso_bruto'] ?? 0);
        $neto = (float) ($d['peso_neto'] ?? 0);
        $piezasNum = (float) ($d['cant_pieza'] ?? 0);
        $promedio = self::formatoPesoPromedio($neto, $piezasNum);
        $fecha = (string) ($d['fecha'] ?? '');
        $fvto = (string) ($d['fecha_vto'] ?? '');
        $loteTxt = $lote !== '' ? $lote.'/'.$nroApertura : (string) $nroApertura;
        $id = (int) ($d['id'] ?? 0);
        $qrJson = self::qrJson(
            $d,
            $codigo,
            $desc,
            $cantUnid,
            $umd,
            $nroApertura,
            $neto,
            $bruto,
            $loteTxt,
            $fecha,
            $fvto,
            $promedio !== '' ? (float) $promedio : null,
        );

        return [
            'id' => $id,
            'codigo_articulo' => $codigo,
            'descripcion' => $desc,
            'proveedor' => (string) ($d['proveedor'] ?? ''),
            'peso_bruto' => number_format($bruto, 2, '.', ''),
            'peso_neto' => number_format($neto, 2, '.', ''),
            'cant_pieza' => number_format($piezasNum, 2, '.', ''),
            'peso_promedio' => $promedio,
            'umd_separa' => $umd,
            'cant_unid_separa' => $cantUnid,
            'nro_apertura' => $nroApertura,
            'linea_separa' => $umd.': '.$cantUnid.' - Nro.: '.$nroApertura,
            'lote' => $loteTxt,
            'fecha' => $fecha,
            'fecha_vto' => $fvto,
            'barcode_valor' => (string) $id,
            'qr_json' => $qrJson,
            'barcode_png_base64' => $id > 0 ? self::code39PngBase64((string) $id) : '',
            'qr_png_base64' => self::qrPngBase64($qrJson),
        ];
    }

    /**
     * Peso promedio por pieza (neto / piezas). Vacío si no hay piezas.
     */
    public static function formatoPesoPromedio(float $pesoNeto, float $cantPieza): string
    {
        if ($cantPieza <= 0.0000001) {
            return '';
        }

        return number_format($pesoNeto / $cantPieza, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private static function qrJson(
        array $d,
        string $codigo,
        string $desc,
        int $cantUnid,
        string $umdSepara,
        int $nroApertura,
        float $neto,
        float $bruto,
        string $loteTxt,
        string $fecha,
        string $fvto,
        ?float $pesoPromedio = null,
    ): string {
        $payload = [
            'v' => 2,
            'etiqueta_id' => (int) ($d['id'] ?? 0),
            'codigo_articulo' => $codigo,
            'nombre' => $desc,
            'cantidad' => $cantUnid,
            'tipo_unidad' => $umdSepara,
            'nro_apertura' => $nroApertura,
            'kilos' => $neto,
            'peso_bruto' => $bruto,
            'lote' => $loteTxt,
            'vencimiento' => $fvto,
        ];
        if ($fecha !== '') {
            $payload['fecha'] = $fecha;
        }
        if ($pesoPromedio !== null) {
            $payload['peso_promedio'] = $pesoPromedio;
        }

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    public static function qrPngBase64(string $qrJson, int $size = 140): string
    {
        if ($qrJson === '') {
            return '';
        }
        try {
            $png = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                ->size($size)
                ->margin(0)
                ->errorCorrection('M')
                ->generate($qrJson);

            return base64_encode((string) $png);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Code 39 (mismo tipo que ZPL ^B3N) como PNG base64 para DomPDF.
     */
    public static function code39PngBase64(string $text, int $barHeight = 48, int $module = 2): string
    {
        $text = preg_replace('/[^0-9A-Z\-\.\ \$\/\+%]/', '', strtoupper(trim($text))) ?? '';
        if ($text === '') {
            return '';
        }

        $patterns = [
            '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
            '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
            '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
            'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
            'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
            'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
            'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
            'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
            'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
            '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '$' => 'nwnwnwnnn',
            '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn', '*' => 'nwnnwnwnn',
        ];

        $encoded = '*'.$text.'*';
        $chars = [];
        for ($i = 0, $n = strlen($encoded); $i < $n; $i++) {
            $pat = $patterns[$encoded[$i]] ?? null;
            if ($pat !== null) {
                $chars[] = $pat;
            }
        }
        if ($chars === []) {
            return '';
        }

        $modules = 0;
        foreach ($chars as $idx => $pat) {
            for ($j = 0; $j < 9; $j++) {
                $modules += ($pat[$j] === 'w') ? 3 : 1;
            }
            if ($idx < count($chars) - 1) {
                $modules += 1;
            }
        }

        $width = max(1, $modules * $module);
        $labelH = 14;
        $height = $barHeight + $labelH + 4;
        $im = imagecreate($width + 8, $height);
        if ($im === false) {
            return '';
        }
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, $width + 7, $height - 1, $white);

        $x = 4;
        foreach ($chars as $idx => $pat) {
            $drawBar = true;
            for ($j = 0; $j < 9; $j++) {
                $w = (($pat[$j] === 'w') ? 3 : 1) * $module;
                if ($drawBar) {
                    imagefilledrectangle($im, $x, 2, $x + $w - 1, 2 + $barHeight - 1, $black);
                }
                $x += $w;
                $drawBar = ! $drawBar;
            }
            if ($idx < count($chars) - 1) {
                $x += $module;
            }
        }

        $font = 2;
        $tw = imagefontwidth($font) * strlen($text);
        $tx = (int) max(0, (($width + 8) - $tw) / 2);
        imagestring($im, $font, $tx, $barHeight + 4, $text, $black);

        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png !== '' ? base64_encode($png) : '';
    }

    private static function trimCerosIzq(string $codigo): string
    {
        $t = ltrim($codigo, '0');

        return $t === '' ? $codigo : $t;
    }

    private static function partirDescripcion(string $desc, int $max): array
    {
        if (mb_strlen($desc) <= $max) {
            return [$desc, ''];
        }
        $corte = $max;
        for ($i = $max; $i >= 0; $i--) {
            if (mb_substr($desc, $i, 1) === ' ') {
                $corte = $i;
                break;
            }
        }

        return [mb_substr($desc, 0, $corte), trim(mb_substr($desc, $corte))];
    }

    private static function zplSafe(string $s): string
    {
        return str_replace(['^', '~', '\\'], [' ', ' ', '/'], $s);
    }
}

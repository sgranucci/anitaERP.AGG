<?php

namespace App\Support\Stock;

/**
 * ZPL etiqueta Surmar (adaptación de eti_prod.fc).
 * Código de barras / QR usan el ID físico de stock_etiqueta.
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
     *   umd?:string,
     *   cantidad?:float|int,
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
        $neto = number_format((float) ($d['peso_neto'] ?? 0), 2, '.', '');
        $piezas = number_format((float) ($d['cant_pieza'] ?? 0), 2, '.', '');
        $umd = self::zplSafe(mb_substr((string) ($d['umd'] ?? 'KG'), 0, 3));
        $cant = (string) (int) ($d['cantidad'] ?? 0);
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
        $lines[] = '^FO5,590';
        $lines[] = '^ARN,60,60^FD'.$umd.': '.$cant.' - ID: '.$id.'^FS';

        if ($fecha !== '') {
            $lines[] = '^FO100,650';
            $lines[] = '^ARN,90,90^FDFecha : '.$fecha.'^FS';
        }
        if ($fvto !== '') {
            $lines[] = '^FO100,750';
            $lines[] = '^ARN,90,90^FDF.Vto.: '.$fvto.'^FS';
        }

        $lines[] = '^FO5,820';
        $lines[] = '^ARN,60,60^FDLote Nro.: '.$lote.'^FS';
        $lines[] = '^FO100,950^BY2,2.2,10^B3N,N,80,Y,N^FD'.$id.'^FS';

        $qr = json_encode([
            'v' => 2,
            'etiqueta_id' => $id,
            'codigo_articulo' => $codigo,
            'nombre' => $desc,
            'kilos' => (float) $neto,
            'peso_bruto' => (float) $bruto,
            'lote' => $lote,
            'vencimiento' => $fvto,
        ], JSON_UNESCAPED_UNICODE);

        $lines[] = '^FO575,0^BQN,2,3^FDMA,'.self::zplSafe((string) $qr).'^FS';
        $lines[] = '^XZ';

        return implode("\n", $lines)."\n";
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

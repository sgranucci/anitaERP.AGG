<?php

declare(strict_types=1);

namespace App\Support\Contable\IngresosBrutos;

/**
 * Archivo e-ARCIBA (AGIP), diseño vigente desde el período 01/2022.
 *
 * Documento técnico de importación de retenciones/percepciones: 23 campos, 226 caracteres.
 * Notas de crédito: diseño aparte, 119 caracteres (el C las escribía en credito.dat).
 *
 * Contraste con p-ingbruto.c (opción ARCIBA):
 * - El C ya emitía los campos 22 y 23 en blanco y el separador LF. Se mantiene.
 * - Situación IB: el C la pisaba con 4. En compras además dejaba el número de
 *   inscripción, y AGIP exige 00000000000 cuando la situación es 4. Acá van
 *   situación 4 y ceros: el armado no trae la situación real del padrón.
 * - Campo 21: AGIP pide que sea igual a la retención/percepción practicada.
 *   El C grababa base × alícuota, que puede diferir por redondeo.
 * - Código de norma 029: es el default del C cuando codarciba no tiene fila.
 *   AGIP admite alícuota 0 en los códigos 028 y 029.
 * - Percepción: AGIP solo acepta tipo de comprobante 01 (factura) o 09 (otro).
 *   Una nota de débito sale como 09. La nota de crédito va al otro archivo.
 * - Retención: tipo 03 (orden de pago), que es el OPP del C, y letra en blanco
 *   (si el tipo no es 01, 06 o 07, AGIP pide un blanco).
 */
final class IngresosBrutosFormatoAgipSupport
{
    /** Fallback de p-ingbruto.c cuando no hay código en codarciba. */
    public const CODIGO_NORMA = '029';

    public const EOL = "\n";

    public const LARGO_OPERACION = 226;

    public const LARGO_NOTA_CREDITO = 119;

    /**
     * @param  list<array<string, mixed>>  $registros
     * @return array{principal: string, notas_credito: string, cantidad_nc: int}
     */
    public static function generar(array $registros, bool $esRetencion): array
    {
        $principal = '';
        $notas = '';
        $cantidadNc = 0;

        foreach ($registros as $reg) {
            if (self::esNotaCredito($reg)) {
                $notas .= self::formatearNotaCredito($reg, $esRetencion);
                $cantidadNc++;
                continue;
            }
            $principal .= self::formatearOperacion($reg, $esRetencion);
        }

        return [
            'principal' => $principal,
            'notas_credito' => $notas,
            'cantidad_nc' => $cantidadNc,
        ];
    }

    public static function nombreArchivo(string $cuitAgente, string $periodoYm, bool $esRetencion, bool $notaCredito = false): string
    {
        $cuit = IngresosBrutosFormatoArbaSupport::normalizarCuit($cuitAgente);
        $que = $notaCredito
            ? 'notas-credito'
            : ($esRetencion ? 'retenciones' : 'percepciones');

        return sprintf('AGIP-%s-%s-%s.txt', $cuit, preg_replace('/\D/', '', $periodoYm) ?: date('Ym'), $que);
    }

    /**
     * @param  array<string, mixed>  $reg
     */
    public static function formatearOperacion(array $reg, bool $esRetencion): string
    {
        $tipoComp = $esRetencion
            ? '03'
            : self::tipoComprobantePercepcion((string) ($reg['tipo_documento'] ?? 'F'));
        $letra = $esRetencion ? ' ' : self::letra((string) ($reg['letra'] ?? ' '));
        $sucursal = $esRetencion ? 0 : (int) ($reg['sucursal'] ?? 0);
        $nro = $esRetencion
            ? (int) ($reg['nro_cert'] ?? $reg['nro_comp'] ?? 0)
            : (int) ($reg['nro_comp'] ?? 0);
        $fecha = self::fecha((string) ($reg['fecha_retencion'] ?? $reg['fecha_comp'] ?? ''));
        $base = abs((float) ($reg['base_calculo'] ?? 0));
        $practicada = abs((float) ($reg['importe'] ?? 0));
        $certificado = $esRetencion
            ? self::texto((string) (int) ($reg['nro_cert'] ?? 0), 16)
            : str_repeat(' ', 16);

        $linea = ($esRetencion ? '1' : '2')
            .self::CODIGO_NORMA
            .$fecha
            .$tipoComp
            .$letra
            .sprintf('%04d%04d%08d', 0, $sucursal, $nro)
            .$fecha
            .self::monto16($base)
            .$certificado
            .'3'
            .IngresosBrutosFormatoArbaSupport::normalizarCuit((string) ($reg['nro_documento'] ?? ''))
            .'4'
            .str_repeat('0', 11)
            .self::situacionIva($letra, $esRetencion)
            .self::texto((string) ($reg['razon_social'] ?? ''), 30)
            .self::monto16(0)
            .self::monto16(0)
            .self::monto16($base)
            .self::alicuota5((float) ($reg['alicuota'] ?? 0))
            .self::monto16($practicada)
            .self::monto16($practicada)
            .' '
            .str_repeat(' ', 10);

        return $linea.self::EOL;
    }

    /**
     * @param  array<string, mixed>  $reg
     */
    public static function formatearNotaCredito(array $reg, bool $esRetencion): string
    {
        $sucursal = (int) ($reg['sucursal'] ?? 0);
        $nro = (int) ($reg['nro_comp'] ?? $reg['nro_cert'] ?? 0);
        $fecha = self::fecha((string) ($reg['fecha_retencion'] ?? $reg['fecha_comp'] ?? ''));
        $base = abs((float) ($reg['base_calculo'] ?? 0));
        $practicada = abs((float) ($reg['importe'] ?? 0));
        $letra = $esRetencion ? ' ' : self::letra((string) ($reg['letra'] ?? ' '));
        $tipoOrigen = $esRetencion ? '03' : '01';

        $linea = ($esRetencion ? '1' : '2')
            .sprintf('%04d%08d', $sucursal, $nro)
            .$fecha
            .self::monto16($base)
            .str_repeat('0', 16)
            .$tipoOrigen
            .$letra
            .sprintf('%04d%04d%08d', 0, $sucursal, $nro)
            .IngresosBrutosFormatoArbaSupport::normalizarCuit((string) ($reg['nro_documento'] ?? ''))
            .self::CODIGO_NORMA
            .$fecha
            .self::monto16($practicada)
            .self::alicuota5((float) ($reg['alicuota'] ?? 0));

        return $linea.self::EOL;
    }

    /**
     * @param  array<string, mixed>  $reg
     */
    public static function esNotaCredito(array $reg): bool
    {
        if ((float) ($reg['importe'] ?? 0) < -0.001) {
            return true;
        }

        return strtoupper(substr((string) ($reg['tipo_documento'] ?? ''), 0, 1)) === 'C';
    }

    private static function tipoComprobantePercepcion(string $tipoDocumento): string
    {
        return strtoupper(substr($tipoDocumento, 0, 1)) === 'F' ? '01' : '09';
    }

    private static function letra(string $letra): string
    {
        $letra = strtoupper(substr(trim($letra), 0, 1));

        return $letra !== '' ? $letra : ' ';
    }

    /**
     * Sin condición de IVA en el armado: A/M se informan como inscripto (1).
     * El resto, monotributo (4), que es el default del C cuando no hay cliente.
     * En una orden de pago la letra va en blanco y la situación queda en 1.
     */
    private static function situacionIva(string $letra, bool $esRetencion): string
    {
        if ($esRetencion && $letra === ' ') {
            return '1';
        }

        return in_array($letra, ['A', 'M'], true) ? '1' : '4';
    }

    private static function fecha(string $iso): string
    {
        $formateada = IngresosBrutosFormatoArbaSupport::fechaArba($iso);

        return strlen($formateada) === 10 ? $formateada : str_repeat(' ', 10);
    }

    private static function monto16(float $valor): string
    {
        $texto = number_format(abs($valor), 2, ',', '');
        [$entero, $decimales] = explode(',', $texto);

        return str_pad($entero, 13, '0', STR_PAD_LEFT).','.$decimales;
    }

    private static function alicuota5(float $valor): string
    {
        $texto = number_format(abs($valor), 2, ',', '');
        [$entero, $decimales] = explode(',', $texto);

        return str_pad(substr($entero, -2), 2, '0', STR_PAD_LEFT).','.$decimales;
    }

    private static function texto(string $valor, int $largo): string
    {
        $plano = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
        if (! is_string($plano) || $plano === '') {
            $plano = $valor;
        }
        $plano = preg_replace('/[^\x20-\x7E]/', ' ', $plano) ?? '';
        $plano = substr($plano, 0, $largo);

        return str_pad($plano, $largo, ' ', STR_PAD_RIGHT);
    }
}

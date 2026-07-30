<?php

declare(strict_types=1);

namespace App\Support\Contable\Suss;

/**
 * Archivo plano SIRE F.2004 (retenciones SUSS, impuesto 353).
 * Especificación AFIP/ARCA: longitud fija, ISO-8859-1, CRLF o LF.
 *
 * Tipo comprobante default: 6 = Orden de pago (formato X(12) en campo de 16).
 */
final class SussFormatoF2004Support
{
    public const FORMULARIO = '2004';

    public const VERSION = '0100';

    public const IMPUESTO = 353;

    /** Orden de pago (tabla TIPO_COMPROBANTE SIRE). */
    public const TIPO_COMPROBANTE_ORDEN_PAGO = 6;

    public const TOLERANCIA = 0.05;

    /** @var list<array{nombre:string,long:int,tipo:string}> */
    private const CAMPOS = [
        ['nombre' => 'formulario', 'long' => 4, 'tipo' => 'integer'],
        ['nombre' => 'version', 'long' => 4, 'tipo' => 'integer'],
        ['nombre' => 'codigo_trazabilidad', 'long' => 10, 'tipo' => 'string'],
        ['nombre' => 'cuit_agente', 'long' => 11, 'tipo' => 'integer'],
        ['nombre' => 'impuesto', 'long' => 3, 'tipo' => 'integer'],
        ['nombre' => 'regimen', 'long' => 3, 'tipo' => 'integer'],
        ['nombre' => 'cuit_retenido', 'long' => 11, 'tipo' => 'integer'],
        ['nombre' => 'fecha_retencion', 'long' => 10, 'tipo' => 'date'],
        ['nombre' => 'tipo_comprobante', 'long' => 2, 'tipo' => 'integer'],
        ['nombre' => 'fecha_comprobante', 'long' => 10, 'tipo' => 'date'],
        ['nombre' => 'nro_comprobante', 'long' => 16, 'tipo' => 'string'],
        ['nombre' => 'importe_comprobante', 'long' => 14, 'tipo' => 'decimal'],
        ['nombre' => 'importe_retencion', 'long' => 14, 'tipo' => 'decimal'],
        ['nombre' => 'cert_original_nro', 'long' => 25, 'tipo' => 'integer'],
        ['nombre' => 'cert_original_fecha', 'long' => 10, 'tipo' => 'date'],
        ['nombre' => 'cert_original_importe', 'long' => 14, 'tipo' => 'decimal'],
        ['nombre' => 'otros_datos', 'long' => 30, 'tipo' => 'string'],
    ];

    public static function tolerancia(): float
    {
        return self::TOLERANCIA;
    }

    public static function cuadra(float $a, float $b): bool
    {
        return abs(round($a - $b, 2)) <= self::TOLERANCIA;
    }

    public static function normalizarCuit(string $cuit): string
    {
        $digitos = preg_replace('/\D+/', '', $cuit) ?? '';

        return str_pad(substr($digitos, 0, 11), 11, '0', STR_PAD_LEFT);
    }

    public static function fechaIsoDesdeAnita(int $ymd): string
    {
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }

    public static function fechaDdMmYyyy(string $iso): string
    {
        if ($iso === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $iso)) {
            return str_repeat(' ', 10);
        }

        return date('d/m/Y', strtotime(substr($iso, 0, 10)));
    }

    /**
     * @param  list<array<string, mixed>>  $registros
     */
    public static function generarArchivo(array $registros, string $cuitAgente, string $codigoTrazabilidad = ''): string
    {
        $cuitAgenteNorm = self::normalizarCuit($cuitAgente);
        $lineas = [];
        foreach ($registros as $reg) {
            $lineas[] = self::armarLinea($reg, $cuitAgenteNorm, $codigoTrazabilidad);
        }

        return implode("\r\n", $lineas).(count($lineas) > 0 ? "\r\n" : '');
    }

    /**
     * @param  array<string, mixed>  $reg
     */
    public static function armarLinea(array $reg, string $cuitAgente, string $codigoTrazabilidad = ''): string
    {
        $tipoComp = (int) ($reg['tipo_comprobante'] ?? self::TIPO_COMPROBANTE_ORDEN_PAGO);
        $esNc = $tipoComp === 3;

        $importeRet = abs((float) ($reg['importe'] ?? 0));
        $importeComp = abs((float) ($reg['importe_comprobante'] ?? $reg['base_calculo'] ?? 0));
        if ($importeComp < 0.01) {
            $importeComp = $importeRet;
        }
        if ($importeComp < $importeRet) {
            $importeComp = $importeRet;
        }

        $fechaRet = (string) ($reg['fecha_retencion'] ?? '');
        $fechaComp = (string) ($reg['fecha_comp'] ?? $fechaRet);
        if ($esNc) {
            $fechaComp = $fechaRet;
        }

        $nroComp = self::formatearNroComprobanteOp(
            (string) ($reg['nro_comprobante'] ?? $reg['nro_comp'] ?? ''),
        );

        $valores = [
            'formulario' => self::FORMULARIO,
            'version' => self::VERSION,
            'codigo_trazabilidad' => $codigoTrazabilidad !== ''
                ? $codigoTrazabilidad
                : (string) ($reg['codigo_trazabilidad'] ?? ''),
            'cuit_agente' => $cuitAgente,
            'impuesto' => (string) self::IMPUESTO,
            'regimen' => (string) ((int) ($reg['codigo_regimen'] ?? 0)),
            'cuit_retenido' => self::normalizarCuit((string) ($reg['nro_documento'] ?? '')),
            'fecha_retencion' => self::fechaDdMmYyyy($fechaRet),
            'tipo_comprobante' => (string) $tipoComp,
            'fecha_comprobante' => self::fechaDdMmYyyy($fechaComp),
            'nro_comprobante' => $nroComp,
            'importe_comprobante' => $importeComp,
            'importe_retencion' => $importeRet,
            'cert_original_nro' => $esNc ? (string) ($reg['cert_original_nro'] ?? '') : '',
            'cert_original_fecha' => $esNc
                ? self::fechaDdMmYyyy((string) ($reg['cert_original_fecha'] ?? ''))
                : '',
            'cert_original_importe' => $esNc ? (float) ($reg['cert_original_importe'] ?? 0) : null,
            'otros_datos' => (string) ($reg['otros_datos'] ?? ''),
        ];

        $out = '';
        foreach (self::CAMPOS as $campo) {
            $out .= self::formatearCampo(
                $valores[$campo['nombre']] ?? null,
                $campo['long'],
                $campo['tipo'],
                $esNc && str_starts_with($campo['nombre'], 'cert_original'),
            );
        }

        return $out;
    }

    public static function nombreArchivo(string $cuitAgente, string $periodoYm): string
    {
        $cuit = self::normalizarCuit($cuitAgente);
        $periodo = preg_replace('/\D/', '', $periodoYm) ?: date('Ym');

        return sprintf('F2004-%s-%s.txt', $cuit, $periodo);
    }

    private static function formatearNroComprobanteOp(string $nro): string
    {
        $nro = trim($nro);
        if ($nro === '' || $nro === '0') {
            return '';
        }
        // Orden de pago: hasta 12 caracteres alfanuméricos.
        return substr(preg_replace('/\s+/', '', $nro) ?? $nro, 0, 12);
    }

    private static function formatearCampo(mixed $valor, int $long, string $tipo, bool $obligatorio = false): string
    {
        if ($tipo === 'date') {
            $s = is_string($valor) ? $valor : '';
            if ($s === '' || strlen($s) !== 10) {
                return str_repeat(' ', $long);
            }

            return substr($s, 0, $long);
        }

        if ($tipo === 'decimal') {
            if ($valor === null || $valor === '') {
                return str_repeat(' ', $long);
            }
            $num = round((float) $valor, 2);
            // 14 chars: parte entera + coma + 2 decimales, alineado a derecha con ceros.
            $entero = (int) floor(abs($num));
            $dec = (int) round((abs($num) - $entero) * 100);
            if ($dec >= 100) {
                $entero++;
                $dec = 0;
            }
            $cuerpo = sprintf('%d,%02d', $entero, $dec);
            if (strlen($cuerpo) > $long) {
                $cuerpo = substr($cuerpo, -$long);
            }

            return str_pad($cuerpo, $long, '0', STR_PAD_LEFT);
        }

        if ($tipo === 'integer') {
            $digitos = preg_replace('/\D+/', '', (string) ($valor ?? '')) ?? '';
            if ($digitos === '' && ! $obligatorio) {
                return str_repeat(' ', $long);
            }
            if (strlen($digitos) > $long) {
                $digitos = substr($digitos, -$long);
            }

            return str_pad($digitos, $long, '0', STR_PAD_LEFT);
        }

        // string
        $s = substr((string) ($valor ?? ''), 0, $long);

        return str_pad($s, $long, ' ', STR_PAD_RIGHT);
    }
}

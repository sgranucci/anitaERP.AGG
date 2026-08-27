<?php

declare(strict_types=1);

namespace App\Support\Contable\LibroIvaDigital;

use App\Support\Ventas\MaquinaFslTipoSupport;

/**
 * Arma registros Libro IVA Digital / IVA Simple desde cabecera Anita FSL (100% exenta).
 */
final class LibroIvaDigitalVentasFslAnitaArmadoSupport
{
    public const ACTIVIDAD_APUESTAS_CODIGO = '920009';

    public static function claveNatural(int $sucursal, int $numero): string
    {
        return $sucursal.'|'.$numero;
    }

    public static function claveDesdeFilaAnita(array $fila, int $puntoVentaDefault = 0): string
    {
        return self::claveNatural(
            self::puntoVentaDesdeFila($fila, $puntoVentaDefault),
            self::enteroCampo($fila, 'ven_nro', 'nro', 'numero', 'numerocomprobante'),
        );
    }

    /**
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}|null
     */
    public static function armarRegistroLibro(array $fila, bool $porFechaJornada, int $puntoVentaDefault = 0): ?array
    {
        $monto = abs(self::importeCampo($fila, 'ven_monto', 'monto', 'importe'));
        $exento = abs(self::importeCampo($fila, 'ven_exento', 'exento'));
        if ($exento <= 0.0001) {
            $exento = $monto;
        }
        if ($monto <= 0.0001) {
            $monto = $exento;
        }
        if ($monto <= 0.0001) {
            return null;
        }

        $puntoVenta = self::puntoVentaDesdeFila($fila, $puntoVentaDefault);
        $numero = self::enteroCampo($fila, 'ven_nro', 'nro', 'numero', 'numerocomprobante');
        if ($puntoVenta <= 0 || $numero <= 0) {
            return null;
        }

        $fechaRaw = $porFechaJornada
            ? (string) (self::campo($fila, 'ven_fecha_vto', 'fecha_vto', 'fechajornada')
                ?: self::campo($fila, 'ven_fecha', 'fecha'))
            : (string) (self::campo($fila, 'ven_fecha', 'fecha')
                ?: self::campo($fila, 'ven_fecha_vto', 'fecha_vto', 'fechajornada'));
        $fecha = self::fechaYmdAnita($fechaRaw);
        if ($fecha === '') {
            return null;
        }

        $nombre = trim((string) (self::campo($fila, 'ven_nombre_cliente', 'nombre_cliente', 'nombre') ?? ''));
        if ($nombre === '') {
            $nombre = (string) config(
                'rendicion_maquina_anita.cierre_rendicion_contable.cliente_nombre',
                'Sala de máquinas',
            );
        }

        $tipoComprobante = LibroIvaDigitalMapeosSupport::RMV_TIPO_COMPROBANTE;
        $comprador = self::resolverComprador($monto, $nombre);

        $cabecera = [
            'fecha' => $fecha,
            'tipo_comprobante' => $tipoComprobante,
            'punto_venta' => $puntoVenta,
            'numero_comprobante' => $numero,
            'numero_hasta' => $numero,
            'codigo_documento' => $comprador['codigo_documento'],
            'numero_identificacion' => $comprador['numero_identificacion'],
            'nombre_comprador' => $comprador['nombre'],
            'importe_total' => $monto,
            'no_integra_neto' => 0.0,
            'percepcion_no_categorizados' => 0,
            'operaciones_exentas' => $exento,
            'percepciones_nacionales' => 0.0,
            'percepciones_iibb' => 0.0,
            'percepciones_municipales' => 0,
            'impuestos_internos' => 0.0,
            'codigo_moneda' => 'PES',
            'tipo_cambio' => 1.0,
            'cantidad_alicuotas' => 1,
            'codigo_operacion' => 'E',
            'otros_tributos' => 0,
            'fecha_vencimiento' => '00000000',
        ];

        $alicuotas = [[
            'tipo_comprobante' => $tipoComprobante,
            'punto_venta' => $puntoVenta,
            'numero_comprobante' => $numero,
            'neto_gravado' => 0.0,
            'alicuota_iva' => LibroIvaDigitalVentasAlicuotaSupport::codigoAlicuotaCero(),
            'impuesto_liquidado' => 0.0,
        ]];

        $registro = LibroIvaDigitalVentasAlicuotaSupport::asegurarRegistro([
            'cabecera' => $cabecera,
            'alicuotas' => $alicuotas,
        ]);
        $registro['iva_simple'] = self::metaIvaSimple();

        return $registro;
    }

    /**
     * @return array{actividad_codigo: string, actividad_nombre: string, tipo_sujeto: int, restitucion: bool}
     */
    public static function metaIvaSimple(): array
    {
        return [
            'actividad_codigo' => self::ACTIVIDAD_APUESTAS_CODIGO,
            'actividad_nombre' => 'Servicios de apuestas',
            'tipo_sujeto' => 3,
            'restitucion' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filaIvaSimpleExento(
        array $fila,
        bool $porFechaJornada = false,
        int $puntoVentaDefault = 0,
    ): ?array {
        if (self::armarRegistroLibro($fila, $porFechaJornada, $puntoVentaDefault) === null) {
            return null;
        }
        $exento = abs(self::importeCampo($fila, 'ven_exento', 'exento'));
        if ($exento <= 0.0001) {
            $exento = abs(self::importeCampo($fila, 'ven_monto', 'monto', 'importe'));
        }
        if ($exento <= 0.0001) {
            return null;
        }

        return [
            'actividad_codigo' => self::ACTIVIDAD_APUESTAS_CODIGO,
            'actividad_nombre' => 'Servicios de apuestas',
            'tipo_operacion' => '3',
            'tipo_sujeto' => null,
            'alicuota_codigo' => null,
            'tasa' => null,
            'neto' => 0.0,
            'iva' => 0.0,
            'iva_computable' => 0.0,
            'exento' => $exento,
            'restitucion' => false,
            'fuente' => 'anita_fsl',
        ];
    }

    /**
     * @return array{codigo_documento: string, numero_identificacion: string, nombre: string}
     */
    private static function resolverComprador(float $importeTotal, string $nombre): array
    {
        unset($importeTotal);
        // FSL no trae DNI del apostador. 96/0 lo rechaza ARCA; 99/0 es venta sin identificar.
        return [
            'codigo_documento' => '99',
            'numero_identificacion' => '0',
            'nombre' => $nombre !== '' ? $nombre : '-CONSUMIDOR FINAL-',
        ];
    }

    private static function fechaYmdAnita(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) === 8) {
            return $digits;
        }

        $ts = strtotime($raw);

        return $ts ? date('Ymd', $ts) : '';
    }

    public static function esFslTipo(?string $tipo): bool
    {
        return strtoupper(trim((string) $tipo)) === MaquinaFslTipoSupport::ABREVIATURA;
    }

    public static function puntoVentaDesdeFila(array $fila, int $puntoVentaDefault = 0): int
    {
        $pv = self::enteroCampo($fila, 'ven_sucursal', 'sucursal', 'sucursal', 'puntoventa');
        if ($pv <= 0 && $puntoVentaDefault > 0) {
            return $puntoVentaDefault;
        }

        return $pv;
    }

    private static function enteroCampo(array $fila, string ...$claves): int
    {
        $raw = self::campo($fila, ...$claves);
        if ($raw === null || $raw === '') {
            return 0;
        }

        return (int) preg_replace('/\D+/', '', (string) $raw);
    }

    private static function importeCampo(array $fila, string ...$claves): float
    {
        $raw = self::campo($fila, ...$claves);
        if ($raw === null || $raw === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $raw);
    }

    private static function campo(array $fila, string ...$claves): mixed
    {
        $porClave = [];
        foreach ($fila as $k => $v) {
            $porClave[strtolower(trim((string) $k))] = $v;
        }
        foreach ($claves as $clave) {
            $norm = strtolower(trim($clave));
            if (array_key_exists($norm, $porClave) && $porClave[$norm] !== null && $porClave[$norm] !== '') {
                return $porClave[$norm];
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Caja\Cuentacaja;
use App\Support\Compras\CbuSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use InvalidArgumentException;
use Throwable;

/**
 * Datos adicionales FCE exigidos por ARCA (MTXCA 331/335, WSFE opcionales).
 *
 * Anita El Bierzo (`a-comprob.c` + `factura_electronica.fc`): si `in_tipo_oper == 'A'`
 * lee tesmae `FACEL_CUENTA` ("00000032") → `tesm_nro_cbu` → opcional 21,
 * y manda opcional 27 = ADC. En AGG esa cuenta no existe: se usa la cuenta de caja
 * en pesos de la empresa (default Banco Macro Gerli).
 */
final class ArcaFceDatosAdicionalesSupport
{
    /** factura_electronica.fc FACEL_CUENTA */
    public const CUENTA_TESORERIA_ANITA = '00000032';

    /** MTXCA dato adicional. En WSFE el mismo dato es el opcional 2101. */
    public const TIPO_CBU_EMISOR = 21;

    public const TIPO_CBU_EMISOR_WSFE = 2101;

    /** Anulación S/N — obligatorio en NC/ND FCE (203/208/…). Igual en MTXCA y WSFE. */
    public const TIPO_ANULACION = 22;

    /** SCA o ADC. Igual en MTXCA y WSFE. */
    public const TIPO_OPCION_TRANSFERENCIA = 27;

    /**
     * FCE factura (no NC/ND). ARCA pide CBU emisor (21) y opción transferencia (27).
     *
     * @var list<int>
     */
    private const TIPOS_FACTURA_FCE = [201, 206, 211];

    public static function requiereCbuEmisor(int $cbteTipo): bool
    {
        return in_array($cbteTipo, self::TIPOS_FACTURA_FCE, true);
    }

    /**
     * MTXCA usa t=21 para el CBU. WSFE rechaza ese id (obs. 10053): el catálogo es 2101.
     */
    public static function idOpcionalWsfe(int $tipo): int
    {
        return $tipo === self::TIPO_CBU_EMISOR ? self::TIPO_CBU_EMISOR_WSFE : $tipo;
    }

    /**
     * FchVtoPago / fechaVencimientoPago de una FCE (yyyymmdd).
     * ARCA 10164: no puede ser anterior a la emisión ni al día de presentación.
     */
    public static function fechaVencimientoPago(array $datos, ?string $hoy = null): string
    {
        $vto = self::ymd($datos['fechavencimiento'] ?? '');
        $cbte = self::ymd($datos['fechacomprobante'] ?? '');
        $hoyYmd = self::ymd($hoy ?? date('Ymd'));
        $candidatos = array_values(array_filter(
            [$vto, $cbte, $hoyYmd],
            static fn (string $d): bool => strlen($d) === 8
        ));
        if ($candidatos === []) {
            return $hoyYmd !== '' ? $hoyYmd : date('Ymd');
        }

        return max($candidatos);
    }

    private static function ymd(mixed $valor): string
    {
        $d = preg_replace('/\D+/', '', (string) $valor) ?? '';

        return strlen($d) >= 8 ? substr($d, 0, 8) : '';
    }

    public static function requiereAnulacion(int $cbteTipo): bool
    {
        return ArcaFceNcMostradorSupport::esTipoNcNdFce($cbteTipo);
    }

    /**
     * @return list<array{t:int, c1:string}>
     */
    public static function paraComprobante(int $cbteTipo, int $empresaId = 0): array
    {
        if (! self::requiereCbuEmisor($cbteTipo)) {
            return [];
        }

        $cbu = self::cbuEmisor($empresaId);
        if ($cbu === '') {
            $codigo = $empresaId > 0
                ? trim((string) config('arca.caea.fce.cuenta_codigo_por_empresa.'.$empresaId, ''))
                : '';
            $donde = $codigo !== ''
                ? 'cuentacaja '.$codigo.' de la empresa '.$empresaId
                : 'tesmae cuenta '.self::CUENTA_TESORERIA_ANITA;

            throw new InvalidArgumentException(
                'FCE requiere CBU del emisor (ARCA dato adicional 21). '
                .'No hay un CBU válido en '.$donde.'. '
                .'Configure ARCA_FCE_CBU_EMPRESA_'.$empresaId.' / ARCA_FCE_CBU_EMISOR '
                .'o cargue el CBU en la cuenta de caja de esa empresa.'
            );
        }

        $opcion = trim((string) config('arca.caea.fce.opcion_transferencia', 'ADC'));

        return [
            ['t' => self::TIPO_CBU_EMISOR, 'c1' => $cbu],
            ['t' => self::TIPO_OPCION_TRANSFERENCIA, 'c1' => $opcion !== '' ? $opcion : 'ADC'],
        ];
    }

    /**
     * Completa 21/27 (factura FCE) si faltan. No pisa valores ya mandados.
     * El opcional 22 (anulación) lo arma el emisor mostrador; aquí no se inventa.
     *
     * @param  list<array<string, mixed>>  $lista
     * @return list<array<string, mixed>>
     */
    public static function asegurar(array $lista, int $cbteTipo, int $empresaId = 0): array
    {
        if (! self::requiereCbuEmisor($cbteTipo)) {
            return $lista;
        }

        $tiene = [];
        foreach ($lista as $row) {
            if (! is_array($row)) {
                continue;
            }
            $t = (int) ($row['t'] ?? $row['codigo'] ?? $row['Id'] ?? 0);
            if ($t > 0) {
                $tiene[$t] = true;
            }
        }
        if (! empty($tiene[self::TIPO_CBU_EMISOR]) && ! empty($tiene[self::TIPO_OPCION_TRANSFERENCIA])) {
            return $lista;
        }

        foreach (self::paraComprobante($cbteTipo, $empresaId) as $row) {
            if (empty($tiene[$row['t']])) {
                $lista[] = $row;
            }
        }

        return $lista;
    }

    /**
     * @return array{t:int, c1:string}
     */
    public static function opcionalAnulacion(string $anulacionSn): array
    {
        $sn = ArcaFceNcMostradorSupport::normalizarAnulacion($anulacionSn);
        if ($sn === null) {
            throw new InvalidArgumentException('Anulación FCE debe ser S o N (opcional 22).');
        }

        return ['t' => self::TIPO_ANULACION, 'c1' => $sn];
    }

    public static function cbuEmisor(int $empresaId = 0): string
    {
        $cuentaParam = ParametroSistemaSupport::fceCuentacaja();
        $empresaParam = (int) ($cuentaParam->empresa_id ?? 0);
        if ($cuentaParam !== null && ($empresaId <= 0 || $empresaParam <= 0 || $empresaParam === $empresaId)) {
            $n = CbuSupport::normalizar((string) ($cuentaParam->cbu ?? ''));
            if (CbuSupport::esValido($n)) {
                return $n;
            }
        }

        $desdeConfig = '';
        if ($empresaId > 0) {
            $desdeConfig = trim((string) config("arca.caea.fce.cbu_por_empresa.{$empresaId}", ''));
        }
        if ($desdeConfig === '') {
            $desdeConfig = trim((string) config('arca.caea.fce.cbu_emisor', ''));
        }

        $n = CbuSupport::normalizar($desdeConfig);
        if (CbuSupport::esValido($n)) {
            return $n;
        }

        if ($empresaId > 0) {
            $n = CbuSupport::normalizar(self::cbuDesdeCuentaEmpresa($empresaId));
            if (CbuSupport::esValido($n)) {
                return $n;
            }
        }

        $n = CbuSupport::normalizar(self::cbuDesdeCuentacaja());
        if (CbuSupport::esValido($n)) {
            return $n;
        }

        $n = CbuSupport::normalizar(self::cbuDesdeTesmaeAnita());

        return CbuSupport::esValido($n) ? $n : '';
    }

    private static function cbuDesdeCuentaEmpresa(int $empresaId): string
    {
        $codigo = trim((string) config('arca.caea.fce.cuenta_codigo_por_empresa.'.$empresaId, ''));
        if ($codigo === '') {
            return '';
        }

        $codigos = array_values(array_unique([
            $codigo,
            ltrim($codigo, '0') ?: '0',
        ]));
        $row = Cuentacaja::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('codigo', $codigos)
            ->whereNotNull('cbu')
            ->where('cbu', '!=', '')
            ->first(['cbu']);

        return trim((string) ($row->cbu ?? ''));
    }

    private static function cbuDesdeCuentacaja(): string
    {
        $codigos = [self::CUENTA_TESORERIA_ANITA, ltrim(self::CUENTA_TESORERIA_ANITA, '0') ?: '0'];
        $row = Cuentacaja::query()
            ->whereIn('codigo', $codigos)
            ->whereNotNull('cbu')
            ->where('cbu', '!=', '')
            ->first(['cbu']);

        return trim((string) ($row->cbu ?? ''));
    }

    private static function cbuDesdeTesmaeAnita(): string
    {
        $cuenta = self::CUENTA_TESORERIA_ANITA;

        try {
            $api = new ApiAnita();
            if (config('app.empresa') === 'AGG') {
                $rows = json_decode($api->apiCall([
                    'acc' => 'list',
                    'tabla' => 'tesmcbu',
                    'sistema' => 'che_ban',
                    'campos' => 'tesmc_cuenta, tesmc_nro_cbu',
                    'whereArmado' => " WHERE tesmc_cuenta = '".$cuenta."' ",
                ]));

                return trim((string) (($rows[0] ?? null)->tesmc_nro_cbu ?? ''));
            }

            $rows = json_decode($api->apiCall([
                'acc' => 'list',
                'tabla' => 'tesmae',
                'sistema' => 'che_ban',
                'campos' => 'tesm_cuenta, tesm_nro_cbu',
                'whereArmado' => " WHERE tesm_cuenta='".$cuenta."' ",
            ]));

            return trim((string) (($rows[0] ?? null)->tesm_nro_cbu ?? ''));
        } catch (Throwable) {
            return '';
        }
    }
}

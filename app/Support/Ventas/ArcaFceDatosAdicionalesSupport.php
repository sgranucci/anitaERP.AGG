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
 * Anita (`a-comprob.c` + `factura_electronica.fc`): si `in_tipo_oper == 'A'`
 * lee tesmae `FACEL_CUENTA` ("00000032") → `tesm_nro_cbu` → opcional 21,
 * y manda opcional 27 = ADC.
 */
final class ArcaFceDatosAdicionalesSupport
{
    /** factura_electronica.fc FACEL_CUENTA */
    public const CUENTA_TESORERIA_ANITA = '00000032';

    public const TIPO_CBU_EMISOR = 21;

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
     * @return list<array{t:int, c1:string}>
     */
    public static function paraComprobante(int $cbteTipo, int $empresaId = 0): array
    {
        if (! self::requiereCbuEmisor($cbteTipo)) {
            return [];
        }

        $cbu = self::cbuEmisor($empresaId);
        if ($cbu === '') {
            throw new InvalidArgumentException(
                'FCE requiere CBU del emisor (ARCA dato adicional 21). '
                .'Anita lo lee de tesmae cuenta '.self::CUENTA_TESORERIA_ANITA.' (tesm_nro_cbu). '
                .'Configure ARCA_FCE_CBU_EMPRESA_'.$empresaId.' / ARCA_FCE_CBU_EMISOR o cargue el CBU en esa cuenta.'
            );
        }

        $opcion = trim((string) config('arca.caea.fce.opcion_transferencia', 'ADC'));

        return [
            ['t' => self::TIPO_CBU_EMISOR, 'c1' => $cbu],
            ['t' => self::TIPO_OPCION_TRANSFERENCIA, 'c1' => $opcion !== '' ? $opcion : 'ADC'],
        ];
    }

    /**
     * Completa 21/27 si faltan. No pisa valores ya mandados en el payload.
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
            $t = (int) ($row['t'] ?? $row['codigo'] ?? 0);
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

    public static function cbuEmisor(int $empresaId = 0): string
    {
        $n = CbuSupport::normalizar((string) (ParametroSistemaSupport::fceCuentacaja()?->cbu ?? ''));
        if (CbuSupport::esValido($n)) {
            return $n;
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

        $n = CbuSupport::normalizar(self::cbuDesdeCuentacaja());
        if (CbuSupport::esValido($n)) {
            return $n;
        }

        $n = CbuSupport::normalizar(self::cbuDesdeTesmaeAnita());

        return CbuSupport::esValido($n) ? $n : '';
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

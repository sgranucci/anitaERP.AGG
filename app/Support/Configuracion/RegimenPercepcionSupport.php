<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use App\Models\Configuracion\RegimenPercepcion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo nacional de percepciones (PIVA RG 5329, PNC RG 2126).
 *
 * El motor solo se consulta desde facturación de administración (mostrador /
 * pedido / remito). Gastronomía, estacionamiento y POS mandan
 * omitir_percepciones y no llegan a estos parámetros.
 */
final class RegimenPercepcionSupport
{
    public const CODIGO_PIVA = 'PIVA';

    public const CODIGO_PNC = 'PNC';

    /** Tributo ARCA / FACEL: percepción IVA (RG 5329). */
    public const CODIGO_TRIBUTO_ARCA_PIVA = 1;

    /** Alícuota IVA reducida (50 % del art. 28 ley IVA) → PIVA 1,5 % si el régimen es 3 %. */
    public const ALICUOTA_IVA_REDUCIDA = 10.5;

    /** @var list<string> */
    public const CODIGOS_SISTEMA = [self::CODIGO_PIVA, self::CODIGO_PNC];

    /** @var array<string, ?RegimenPercepcion> */
    private static array $filas = [];

    private static bool $tablaLeida = false;

    private static bool $hayTabla = false;

    public static function olvidarCache(): void
    {
        self::$filas = [];
        self::$tablaLeida = false;
        self::$hayTabla = false;
    }

    public static function esCodigoSistema(string $codigo): bool
    {
        return in_array(strtoupper(trim($codigo)), self::CODIGOS_SISTEMA, true);
    }

    /**
     * Anita: tot_grav - minimo >= 0.01.
     */
    public static function superaMinimoBase(float $gravado, float $minimoBase): bool
    {
        return round($gravado, 2) - $minimoBase >= 0.01;
    }

    public static function superaMinimoImporte(float $importe, float $minimoImporte): bool
    {
        return round($importe, 2) > $minimoImporte + 0.00001;
    }

    /**
     * RG 5329 art. 11: 3 % sobre gravado 21 %; 1,50 % sobre gravado al 50 % de la alícuota general (10,5 %).
     * Anita: tot_grav × tasa + _tot_grav_otasa × (tasa / 2).
     */
    public static function tasaPivaSegunAlicuotaIva(float $tasaRegimen, float $alicuotaIva): float
    {
        if ($tasaRegimen <= 0.00001 || $alicuotaIva <= 0.00001) {
            return 0.0;
        }
        if (abs($alicuotaIva - self::ALICUOTA_IVA_REDUCIDA) < 0.05) {
            return round($tasaRegimen / 2, 4);
        }

        return $tasaRegimen;
    }

    /**
     * @param  list<array<string, mixed>>  $netos
     * @return array{base: float, importe: float, por_tasa: list<array{tasa: float, base: float, importe: float}>}
     */
    public static function liquidarPivaSobreNetos(array $netos, float $tasaRegimen): array
    {
        $porTasa = [];
        $base = 0.0;
        $importe = 0.0;
        foreach ($netos as $neto) {
            $alicuotaIva = (float) ($neto['tasa'] ?? 0);
            if ($alicuotaIva <= 0.00001) {
                continue;
            }
            $baseLinea = round((float) ($neto['importe'] ?? 0), 2);
            if (abs($baseLinea) < 0.00001) {
                continue;
            }
            $tasaPiva = self::tasaPivaSegunAlicuotaIva($tasaRegimen, $alicuotaIva);
            if ($tasaPiva <= 0.00001) {
                continue;
            }
            $imp = round($baseLinea * $tasaPiva / 100, 2);
            $clave = number_format($tasaPiva, 4, '.', '');
            if (! isset($porTasa[$clave])) {
                $porTasa[$clave] = ['tasa' => $tasaPiva, 'base' => 0.0, 'importe' => 0.0];
            }
            $porTasa[$clave]['base'] = round($porTasa[$clave]['base'] + $baseLinea, 2);
            $porTasa[$clave]['importe'] = round($porTasa[$clave]['importe'] + $imp, 2);
            $base += $baseLinea;
            $importe += $imp;
        }

        return [
            'base' => round($base, 2),
            'importe' => round($importe, 2),
            'por_tasa' => array_values($porTasa),
        ];
    }

    public static function vigenteEnFecha(?object $fila, ?string $fechaYmd): bool
    {
        if ($fila === null) {
            return false;
        }

        $fecha = self::normalizarFecha($fechaYmd);
        $desde = self::fechaCampo($fila->vigencia_desde ?? null);
        $hasta = self::fechaCampo($fila->vigencia_hasta ?? null);

        if ($desde !== null && $fecha < $desde) {
            return false;
        }
        if ($hasta !== null && $fecha > $hasta) {
            return false;
        }

        return true;
    }

    /**
     * @return array{habilitado: bool, tasa: float, minimo_base: float, minimo_importe: float, impuesto_id: int|null}
     */
    public static function parametrosPiva(?string $fechaYmd = null): array
    {
        $fila = self::porCodigo(self::CODIGO_PIVA);
        if ($fila !== null) {
            $habilitado = (bool) $fila->habilitado && self::vigenteEnFecha($fila, $fechaYmd);

            return [
                'habilitado' => $habilitado,
                'tasa' => $habilitado ? (float) $fila->tasa : 0.0,
                'minimo_base' => (float) $fila->minimo_base,
                'minimo_importe' => (float) $fila->minimo_importe,
                'impuesto_id' => null,
            ];
        }

        $habilitado = strtolower(trim((string) config('anita.agente_percepcion_iva', 'no'))) === 'si';
        $tasa = (float) config('anita.tasa_percepcion_iva', 0);

        return [
            'habilitado' => $habilitado,
            'tasa' => $habilitado ? $tasa : 0.0,
            'minimo_base' => (float) config('anita.minimo_base_percepcion_iva', 0),
            'minimo_importe' => (float) config('anita.minimo_importe_percepcion_iva', 0),
            'impuesto_id' => null,
        ];
    }

    public static function codigoCuentaFallback(string $codigoRegimen): string
    {
        $codigo = strtoupper(trim($codigoRegimen));
        if ($codigo === self::CODIGO_PNC) {
            return trim((string) config('facturacion.CUENTACONTABLE_PERCEPCION_NO_CATEGORIZADO', ''));
        }

        return trim((string) config('facturacion.CUENTACONTABLE_PERCEPCION_IVA', ''));
    }

    public static function codigoCuentaPiva(): string
    {
        return self::codigoCuentaFallback(self::CODIGO_PIVA);
    }

    public static function cuentaContableId(string $codigoRegimen, int $empresaId): ?int
    {
        if ($empresaId <= 0 || ! Schema::hasTable('regimen_percepcion_cuentacontable')) {
            return null;
        }
        $regimen = self::porCodigo($codigoRegimen);
        if ($regimen === null) {
            return null;
        }
        $id = DB::table('regimen_percepcion_cuentacontable')
            ->where('regimen_percepcion_id', $regimen->id)
            ->where('empresa_id', $empresaId)
            ->value('cuentacontable_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return list<string>
     */
    public static function codigosCuentaContableEmpresa(string $codigoRegimen, int $empresaId): array
    {
        $codigos = [];
        $cfg = self::codigoCuentaFallback($codigoRegimen);
        if ($cfg !== '') {
            $codigos[] = $cfg;
        }
        $regimen = self::porCodigo($codigoRegimen);
        if ($regimen !== null && $empresaId > 0 && Schema::hasTable('regimen_percepcion_cuentacontable')) {
            $filas = DB::table('regimen_percepcion_cuentacontable as rc')
                ->join('cuentacontable as c', 'c.id', '=', 'rc.cuentacontable_id')
                ->where('rc.regimen_percepcion_id', $regimen->id)
                ->where('rc.empresa_id', $empresaId)
                ->pluck('c.codigo');
            foreach ($filas as $codigo) {
                $codigo = trim((string) $codigo);
                if ($codigo !== '') {
                    $codigos[] = $codigo;
                }
            }
        }

        return array_values(array_unique($codigos));
    }

    /**
     * @return list<string>
     */
    public static function codigosCuentaContableEmpresaPiva(int $empresaId): array
    {
        return self::codigosCuentaContableEmpresa(self::CODIGO_PIVA, $empresaId);
    }

    public static function esConceptoPiva(string $concepto): bool
    {
        $c = mb_strtolower($concepto);

        return str_starts_with($c, 'percepcion iva') || str_starts_with($c, 'percepción iva');
    }

    public static function porCodigo(string $codigo): ?RegimenPercepcion
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }
        if (array_key_exists($codigo, self::$filas)) {
            return self::$filas[$codigo];
        }
        if (! self::hayTabla()) {
            return null;
        }

        $fila = RegimenPercepcion::query()->where('codigo', $codigo)->first();
        self::$filas[$codigo] = $fila;

        return $fila;
    }

    private static function hayTabla(): bool
    {
        if (! self::$tablaLeida) {
            self::$tablaLeida = true;
            self::$hayTabla = Schema::hasTable('regimen_percepcion');
        }

        return self::$hayTabla;
    }

    private static function normalizarFecha(?string $fechaYmd): string
    {
        $fecha = trim((string) $fechaYmd);
        if ($fecha === '') {
            return date('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha) === 1) {
            return substr($fecha, 0, 10);
        }

        return date('Y-m-d');
    }

    private static function fechaCampo(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        return substr($texto, 0, 10);
    }
}

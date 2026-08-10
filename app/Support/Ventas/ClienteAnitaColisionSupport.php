<?php

namespace App\Support\Ventas;

use App\Support\Database\SqlDialectSupport;
use App\ApiAnita;
use App\Models\Ventas\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Evita asignar un código de cliente ya usado en ERP o en climae (Anita ventas).
 */
final class ClienteAnitaColisionSupport
{
    public static function sistemaVentas(): string
    {
        return (string) config('cliente_anita.sistema', 'ventas');
    }

    public static function tablaClimae(): string
    {
        return (string) config('cliente_anita.tabla', 'climae');
    }

    /**
     * @return list<string>
     */
    public static function variantesCodigoNumerico(int $numero): array
    {
        if ($numero <= 0) {
            return [];
        }

        $norm = (string) $numero;
        $padded = str_pad($norm, 6, '0', STR_PAD_LEFT);

        return array_values(array_unique([$norm, $padded, ltrim($padded, '0') ?: '0']));
    }

    public static function codigoOcupadoParaNuevaAsignacion(int $numero): bool
    {
        if ($numero <= 0) {
            return false;
        }

        $variantes = self::variantesCodigoNumerico($numero);
        if ($variantes !== [] && DB::table('cliente')->whereIn('codigo', $variantes)->exists()) {
            return true;
        }

        return self::existeCodigoEnClimae($numero);
    }

    public static function existeCodigoEnClimae(int $numero): bool
    {
        if ($numero <= 0) {
            return false;
        }

        try {
            $codigoAnita = str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
            $api = new ApiAnita;
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => self::sistemaVentas(),
                'tabla' => self::tablaClimae(),
                'campos' => 'clim_cliente',
                'whereArmado' => " WHERE clim_cliente = '".addslashes($codigoAnita)."'",
                'limit' => 'FIRST 1',
            ]);

            return ApiAnita::primeraFilaLista($raw) !== null;
        } catch (\Throwable $e) {
            Log::warning('ClienteAnitaColision: no se pudo verificar climae', [
                'numero' => $numero,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public static function maxCodigoClienteErp(): int
    {
        $max = Cliente::query()
            ->whereNotNull('codigo')
            ->where('codigo', '!=', '')
            ->whereRaw("codigo REGEXP '^[0-9]+$'")
            ->max(DB::raw(SqlDialectSupport::castEntero('codigo')));

        return max(0, (int) $max);
    }

    public static function maxCodigoClimae(): int
    {
        try {
            $api = new ApiAnita;
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => self::sistemaVentas(),
                'tabla' => self::tablaClimae(),
                'campos' => 'max(clim_cliente) as max_codigo',
            ]);
            $fila = ApiAnita::primeraFilaLista($raw);
            $maxRaw = trim((string) ($fila->max_codigo ?? ''));

            return max(0, (int) filter_var($maxRaw, FILTER_SANITIZE_NUMBER_INT));
        } catch (\Throwable $e) {
            Log::warning('ClienteAnitaColision: no se pudo leer max clim_cliente', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public static function primerCodigoDisponible(int $desde): int
    {
        $numero = max(1, $desde);
        for ($intentos = 0; $intentos < 500; $intentos++) {
            if (! self::codigoOcupadoParaNuevaAsignacion($numero)) {
                return $numero;
            }
            $numero++;
        }

        throw new \RuntimeException(
            'No se encontró código de cliente libre desde '.$desde.' (numerador ERP/Anita).'
        );
    }
}

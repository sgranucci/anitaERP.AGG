<?php

namespace App\Support\Compras\AnitaSync;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Support\Compras\AnitaSync\Requisicion\AnitaSqlLiteral;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve usu_usuario (Anita bridge) desde logname ERP (campo usuario).
 */
final class AnitaUsuarioBridgeSupport
{
    /** @var array<string, int> */
    private static array $cachePorLogname = [];

    /** @var array<int, int> */
    private static array $cachePorErpId = [];

    public static function sistemaCompras(): string
    {
        return (string) config('requisicion.anita.sistema_compras', 'compras');
    }

    public static function usuUsuarioDesdeErpId(?int $erpUsuarioId, ?string $sistema = null): int
    {
        $erpId = (int) ($erpUsuarioId ?? 0);
        if ($erpId <= 0) {
            return 0;
        }

        if (array_key_exists($erpId, self::$cachePorErpId)) {
            return self::$cachePorErpId[$erpId];
        }

        $usuario = Usuario::query()->find($erpId);
        if ($usuario === null) {
            return self::$cachePorErpId[$erpId] = 0;
        }

        $logname = trim((string) ($usuario->usuario ?? ''));
        if ($logname === '') {
            Log::warning('AnitaUsuarioBridge: usuario ERP sin logname', ['erp_usuario_id' => $erpId]);

            return self::$cachePorErpId[$erpId] = 0;
        }

        $codigo = self::usuUsuarioPorLogname($logname, $sistema);
        self::$cachePorErpId[$erpId] = $codigo;

        return $codigo;
    }

    public static function usuUsuarioPorLogname(string $logname, ?string $sistema = null): int
    {
        $logname = trim($logname);
        if ($logname === '') {
            return 0;
        }

        $cacheKey = mb_strtolower($logname);
        if (array_key_exists($cacheKey, self::$cachePorLogname)) {
            return self::$cachePorLogname[$cacheKey];
        }

        $sistema = $sistema ?? self::sistemaCompras();
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => 'usuario',
            'campos' => 'usu_usuario, usu_logname',
            'whereArmado' => ' WHERE TRIM(usu_logname) = '.AnitaSqlLiteral::string($logname, 15),
            'limit' => 'FIRST 1',
        ]);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null && $err !== '') {
            Log::warning('AnitaUsuarioBridge: error consultando usuario Anita', [
                'logname' => $logname,
                'error' => $err,
            ]);

            return self::$cachePorLogname[$cacheKey] = 0;
        }

        $fila = ApiAnita::primeraFilaLista($raw);
        if ($fila === null) {
            Log::warning('AnitaUsuarioBridge: logname no encontrado en Anita', [
                'logname' => $logname,
            ]);

            return self::$cachePorLogname[$cacheKey] = 0;
        }

        $codigo = (int) ($fila->usu_usuario ?? 0);

        return self::$cachePorLogname[$cacheKey] = max(0, $codigo);
    }

    public static function limpiarCache(): void
    {
        self::$cachePorLogname = [];
        self::$cachePorErpId = [];
    }
}

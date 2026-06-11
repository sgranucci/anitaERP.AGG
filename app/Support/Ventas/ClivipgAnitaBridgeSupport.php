<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Ventas\ClienteVipGastronomia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Bridge Anita base_admin — tabla clivipg (clientes VIP gastronomía).
 */
final class ClivipgAnitaBridgeSupport
{
    public static function sistema(): string
    {
        return (string) config('gastronomia.cliente_vip_anita_sistema', 'base_admin');
    }

    public static function tabla(): string
    {
        return (string) config('gastronomia.cliente_vip_anita_tabla', 'clivipg');
    }

    public static function camposListado(): string
    {
        return (string) config(
            'gastronomia.cliente_vip_anita_campos_listado',
            'inumeroid,cnrodocumento,capellido,cnombre,iusualtaid,ifechaalta,choraalta,iusuumodid,ifechaumod,choraumod,clivig_nickname,clivig_localidad'
        );
    }

    /**
     * @return array{servidor?:string,path_sistema?:string,sistema:string,ifx_server?:string}
     */
    public static function parametrosBridge(int $empresaId): array
    {
        return GastronomiaTicketTarjetaAnitaBridgeSupport::parametrosBridge($empresaId);
    }

    /**
     * @return list<object>
     */
    public static function listar(int $empresaId, ?string $whereArmado = null): array
    {
        $api = new ApiAnita;
        $payload = array_merge(self::parametrosBridge($empresaId), [
            'acc' => 'list',
            'tabla' => self::tabla(),
            'campos' => self::camposListado(),
            'orderBy' => 'inumeroid',
        ]);

        if ($whereArmado !== null && trim($whereArmado) !== '') {
            $payload['whereArmado'] = $whereArmado;
        }

        $rows = json_decode($api->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    public static function maxNumeroid(int $empresaId): int
    {
        $api = new ApiAnita;
        $payload = array_merge(self::parametrosBridge($empresaId), [
            'acc' => 'list',
            'tabla' => self::tabla(),
            'campos' => 'max(inumeroid) as max_id',
        ]);

        $fila = ApiAnita::primeraFilaLista($api->apiCall($payload));

        return $fila ? (int) ($fila->max_id ?? $fila->MAX_ID ?? 0) : 0;
    }

    public static function existeEnAnita(int $empresaId, int $numeroid): bool
    {
        if ($numeroid <= 0) {
            return false;
        }

        $filas = self::listar($empresaId, ' WHERE inumeroid = '.(int) $numeroid);

        return $filas !== [];
    }

    public static function insertar(ClienteVipGastronomia $cliente): bool
    {
        $empresaId = (int) $cliente->empresa_id;
        $numeroid = (int) $cliente->numeroid;
        if ($numeroid <= 0) {
            return false;
        }

        if (self::existeEnAnita($empresaId, $numeroid)) {
            return true;
        }

        $api = new ApiAnita;
        $payload = array_merge(self::parametrosBridge($empresaId), [
            'acc' => 'insert',
            'tabla' => self::tabla(),
            'campos' => self::camposInsert(),
            'valores' => self::valoresSql($cliente),
        ]);

        $raw = (string) $api->apiCallEscritura($payload, 'clivipg insert '.$numeroid, 'cliente_vip_gastronomia.anita_bridge.fallo');
        if (stripos($raw, 'error') !== false) {
            Log::warning('ClivipgAnitaBridge: insert', ['numeroid' => $numeroid, 'empresa_id' => $empresaId, 'respuesta' => $raw]);

            return false;
        }

        return true;
    }

    public static function actualizar(ClienteVipGastronomia $cliente, int $numeroidAnterior): bool
    {
        $empresaId = (int) $cliente->empresa_id;
        $numeroid = (int) $cliente->numeroid;
        if ($numeroid <= 0) {
            return false;
        }

        if ($numeroidAnterior !== $numeroid && self::existeEnAnita($empresaId, $numeroid)) {
            throw new \RuntimeException("El numeroid {$numeroid} ya existe en Anita para la empresa {$empresaId}.");
        }

        if ($numeroidAnterior !== $numeroid) {
            self::eliminarPorClave($empresaId, $numeroidAnterior);

            return self::insertar($cliente);
        }

        $api = new ApiAnita;
        $payload = array_merge(self::parametrosBridge($empresaId), [
            'acc' => 'update',
            'tabla' => self::tabla(),
            'valores' => self::valoresUpdateSql($cliente),
            'whereArmado' => ' WHERE inumeroid = '.(int) $numeroid,
        ]);

        $raw = (string) $api->apiCallEscritura($payload, 'clivipg update '.$numeroid, 'cliente_vip_gastronomia.anita_bridge.fallo');
        if (stripos($raw, 'error') !== false) {
            Log::warning('ClivipgAnitaBridge: update', ['numeroid' => $numeroid, 'respuesta' => $raw]);

            return false;
        }

        return true;
    }

    public static function eliminar(ClienteVipGastronomia $cliente): bool
    {
        return self::eliminarPorClave((int) $cliente->empresa_id, (int) $cliente->numeroid);
    }

    public static function eliminarPorClave(int $empresaId, int $numeroid): bool
    {
        if ($numeroid <= 0) {
            return false;
        }

        $api = new ApiAnita;
        $payload = array_merge(self::parametrosBridge($empresaId), [
            'acc' => 'delete',
            'tabla' => self::tabla(),
            'whereArmado' => ' WHERE inumeroid = '.(int) $numeroid,
        ]);

        $raw = (string) $api->apiCallEscritura($payload, 'clivipg delete '.$numeroid, 'cliente_vip_gastronomia.anita_bridge.fallo');
        if (stripos($raw, 'error') !== false) {
            Log::warning('ClivipgAnitaBridge: delete', ['numeroid' => $numeroid, 'respuesta' => $raw]);

            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function datosAuditoriaAlta(): array
    {
        $usuarioId = (int) (Auth::id() ?? 0);
        $fecha = (int) date('Ymd');
        $hora = date('H:i');

        return [
            'usualta_id' => $usuarioId,
            'fecha_alta' => $fecha,
            'hora_alta' => $hora,
            'usumod_id' => $usuarioId,
            'fecha_mod' => $fecha,
            'hora_mod' => $hora,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function datosAuditoriaModificacion(): array
    {
        return [
            'usumod_id' => (int) (Auth::id() ?? 0),
            'fecha_mod' => (int) date('Ymd'),
            'hora_mod' => date('H:i'),
        ];
    }

    private static function camposInsert(): string
    {
        return '
            inumeroid,
            cnrodocumento,
            capellido,
            cnombre,
            iusualtaid,
            ifechaalta,
            choraalta,
            iusuumodid,
            ifechaumod,
            choraumod,
            clivig_nickname,
            clivig_localidad
        ';
    }

    private static function valoresSql(ClienteVipGastronomia $cliente): string
    {
        return '
            '.(int) $cliente->numeroid.',
            '.self::sqlStr($cliente->nrodocumento, 20).',
            '.self::sqlStr($cliente->apellido, 40).',
            '.self::sqlStr($cliente->nombre, 40).',
            '.(int) ($cliente->usualta_id ?? 0).',
            '.(int) ($cliente->fecha_alta ?? 0).',
            '.self::sqlStr($cliente->hora_alta, 5).',
            '.(int) ($cliente->usumod_id ?? 0).',
            '.(int) ($cliente->fecha_mod ?? 0).',
            '.self::sqlStr($cliente->hora_mod, 5).',
            '.self::sqlStr($cliente->nickname, 30).',
            '.self::sqlStr($cliente->localidad, 15).'
        ';
    }

    private static function valoresUpdateSql(ClienteVipGastronomia $cliente): string
    {
        return '
            cnrodocumento = '.self::sqlStr($cliente->nrodocumento, 20).',
            capellido = '.self::sqlStr($cliente->apellido, 40).',
            cnombre = '.self::sqlStr($cliente->nombre, 40).',
            iusuumodid = '.(int) ($cliente->usumod_id ?? 0).',
            ifechaumod = '.(int) ($cliente->fecha_mod ?? 0).',
            choraumod = '.self::sqlStr($cliente->hora_mod, 5).',
            clivig_nickname = '.self::sqlStr($cliente->nickname, 30).',
            clivig_localidad = '.self::sqlStr($cliente->localidad, 15).'
        ';
    }

    private static function sqlStr(?string $valor, int $maxLen): string
    {
        $texto = mb_substr(trim((string) ($valor ?? '')), 0, $maxLen);

        return "'".str_replace("'", "''", $texto)."'";
    }
}

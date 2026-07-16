<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Support\Ventas\GastronomiaTicketTarjetaAnitaBridgeSupport;

/**
 * Bridge Anita base_admin — tabla clivip (clientes VIP caja / tesorería).
 * Solo lectura: no hay insert/update/delete hacia Anita.
 */
final class ClivipAnitaBridgeSupport
{
    public static function sistema(): string
    {
        return (string) config('caja.cliente_vip_anita_sistema', 'base_admin');
    }

    public static function tabla(): string
    {
        return (string) config('caja.cliente_vip_anita_tabla', 'clivip');
    }

    public static function camposListado(): string
    {
        return (string) config(
            'caja.cliente_vip_anita_campos_listado',
            'inumeroid,cnrodocumento,capellido,cnombre,iusualtaid,ifechaalta,choraalta,iusuumodid,ifechaumod,choraumod,clivi_nickname,clivi_localidad'
        );
    }

    /**
     * @return array{servidor?:string,path_sistema?:string,sistema:string,ifx_server?:string}
     */
    public static function parametrosBridge(int $empresaId): array
    {
        $params = GastronomiaTicketTarjetaAnitaBridgeSupport::parametrosBridge($empresaId);
        $sistema = trim(self::sistema());
        if ($sistema !== '') {
            $params['sistema'] = $sistema;
        }

        return $params;
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
}

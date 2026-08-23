<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Ventas\Pedido;
use Illuminate\Support\Facades\Log;

/**
 * El circuito DESPACHO vive solo en ERP: no deja el pedido abierto en Anita.
 * Cierra pendmae (penm_estado = entregado) si existe; no crea filas nuevas.
 */
final class PedidoDespachoAnitaCierreSupport
{
    /** @var list<string> */
    private const ESTADOS_YA_CERRADOS = [
        PedidoEstadosInterforming::CAB_ENTREGADO,
        PedidoEstadosInterforming::CAB_FACTURADO,
        PedidoEstadosInterforming::CAB_ANULADO,
    ];

    /**
     * @return array{ok: bool, existia: bool, cerrado: bool, mensaje: string}
     */
    public static function cerrarSiExiste(Pedido $pedido): array
    {
        if (! ClienteDespachoSupport::circuitoHabilitado()) {
            return self::resultado(true, false, false, '');
        }

        $clave = self::claveDesdeCodigoErp((string) ($pedido->codigo ?? ''));
        if ($clave === null) {
            return self::resultado(true, false, false, '');
        }

        return self::cerrarPorClave($clave);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $clave
     * @return array{ok: bool, existia: bool, cerrado: bool, mensaje: string}
     */
    public static function cerrarPorClave(array $clave): array
    {
        $tipo = self::escSql($clave['tipo'] ?? PedidoReferenciaAnitaSupport::TIPO);
        $letra = self::escSql($clave['letra'] ?? 'X');
        $sucursal = (int) ($clave['sucursal'] ?? 0);
        $nro = (int) ($clave['nro'] ?? 0);
        if ($nro <= 0) {
            return self::resultado(true, false, false, '');
        }

        $where = " WHERE penm_tipo = '".$tipo."' AND penm_letra = '".$letra."'"
            .' AND penm_sucursal = '.$sucursal
            .' AND penm_nro = '.$nro;

        $api = new ApiAnita();
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'pendmae',
            'campos' => 'penm_tipo, penm_letra, penm_sucursal, penm_nro, penm_estado, penm_cliente',
            'whereArmado' => $where,
            'limit' => 'FIRST 1',
        ], 'despacho pendmae existe');

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            Log::warning('PedidoDespachoAnitaCierre: no se pudo leer pendmae', [
                'clave' => $clave,
                'error' => $err,
            ]);

            return self::resultado(false, false, false, 'No se pudo leer el pedido en Anita: '.$err);
        }

        $fila = ApiAnita::primeraFilaLista((string) $raw);
        if ($fila === null) {
            return self::resultado(true, false, false, '');
        }

        $estado = trim((string) ($fila->penm_estado ?? ''));
        if (in_array($estado, self::ESTADOS_YA_CERRADOS, true)) {
            return self::resultado(true, true, false, '');
        }

        $rawUpdate = $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => 'ventas',
            'tabla' => 'pendmae',
            'valores' => "penm_estado = '".PedidoEstadosInterforming::CAB_ENTREGADO."'",
            'whereArmado' => $where,
        ], 'despacho pendmae cerrar');

        $errUpdate = ApiAnita::extraerMensajeError($rawUpdate);
        if ($errUpdate !== null) {
            Log::warning('PedidoDespachoAnitaCierre: no se pudo cerrar pendmae', [
                'clave' => $clave,
                'error' => $errUpdate,
            ]);

            return self::resultado(false, true, false, 'No se pudo cerrar el pedido en Anita: '.$errUpdate);
        }

        return self::resultado(true, true, true, 'Pedido cerrado en Anita (entregado).');
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}|null
     */
    public static function claveDesdeCodigoErp(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '' || ! str_starts_with(strtoupper($codigo), PedidoReferenciaAnitaSupport::TIPO.'-')) {
            return null;
        }

        $ref = PedidoReferenciaAnitaSupport::desdeCodigoPedido($codigo);
        if ((int) $ref['numerofactura'] <= 0) {
            return null;
        }

        return [
            'tipo' => PedidoReferenciaAnitaSupport::TIPO,
            'letra' => $ref['letrafactura'],
            'sucursal' => (int) $ref['sucursalfactura'],
            'nro' => (int) $ref['numerofactura'],
        ];
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    public static function claveDesdeCabeceraAnita(object $cab): array
    {
        return [
            'tipo' => trim((string) ($cab->penm_tipo ?? PedidoReferenciaAnitaSupport::TIPO)) ?: PedidoReferenciaAnitaSupport::TIPO,
            'letra' => trim((string) ($cab->penm_letra ?? 'X')) ?: 'X',
            'sucursal' => (int) ($cab->penm_sucursal ?? 0),
            'nro' => (int) ($cab->penm_nro ?? 0),
        ];
    }

    /**
     * @return array{ok: bool, existia: bool, cerrado: bool, mensaje: string}
     */
    private static function resultado(bool $ok, bool $existia, bool $cerrado, string $mensaje): array
    {
        return [
            'ok' => $ok,
            'existia' => $existia,
            'cerrado' => $cerrado,
            'mensaje' => $mensaje,
        ];
    }

    private static function escSql(string $value): string
    {
        return str_replace("'", "''", trim($value));
    }
}

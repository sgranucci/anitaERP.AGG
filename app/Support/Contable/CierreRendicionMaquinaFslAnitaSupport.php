<?php

namespace App\Support\Contable;

use App\ApiAnita;
use App\Models\Caja\RendicionMaquina;
use App\Repositories\Ventas\VentaRepository;
use Carbon\Carbon;
use RuntimeException;

/**
 * FSL exenta en Anita (venta) + marca rendmaquina facturado.
 */
final class CierreRendicionMaquinaFslAnitaSupport
{
    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int, monto: float}
     */
    public static function emitirFslExenta(int $empresaId, string $fechaDia, float $monto): array
    {
        $tipo = (string) config('rendicion_maquina_anita.cierre_rendicion_contable.tipo_comprobante', 'FSL');
        $letra = (string) config('rendicion_maquina_anita.cierre_rendicion_contable.letra_comprobante', 'B');
        $sucursal = CierreRendicionMaquinaConfigSupport::puntoventaFsl($empresaId);
        $monto = round($monto, 2);

        if ($monto <= 0) {
            throw new RuntimeException('No hay recaudación online para emitir FSL exenta.');
        }

        $repo = app(VentaRepository::class);
        $numero = $repo->numeraAnita($tipo, $letra, (string) $sucursal, 'V');
        if (! is_int($numero) || $numero <= 0) {
            throw new RuntimeException('No se pudo numerar FSL exenta en Anita: '.(is_string($numero) ? $numero : 'error'));
        }

        self::insertarVentaAnita($tipo, $letra, $sucursal, $numero, $fechaDia, $monto);

        return [
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'nro' => $numero,
            'monto' => $monto,
        ];
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $fsl
     */
    public static function marcarRendicionesFacturadas(RendicionMaquina $rendicion, array $fsl, string $fechaDia): void
    {
        $estado = (string) config('rendicion_maquina_anita.cierre_rendicion_contable.estado_facturado_anita', 'F');
        $fechaFac = Carbon::parse($fechaDia)->format('Y-m-d');

        $updates = [
            'factura_tipo' => $fsl['tipo'],
            'factura_letra' => $fsl['letra'],
            'factura_sucursal' => (int) $fsl['sucursal'],
            'factura_nro' => (int) $fsl['nro'],
            'factura_fecha' => $fechaFac,
            'estado_facturacion' => $estado,
        ];

        $fillable = array_flip($rendicion->getFillable());
        $payload = array_intersect_key($updates, $fillable);
        if ($payload !== []) {
            $rendicion->update($payload);
        }

        self::actualizarRendmaquinaAnita($rendicion, $fsl, $fechaDia, $estado);
    }

    public static function revertirFacturacionAnita(RendicionMaquina $rendicion): void
    {
        $nroOper = (int) ($rendicion->nro_oper_anita ?? 0);
        if ($nroOper <= 0) {
            return;
        }

        $estadoPendiente = (string) config('rendicion_maquina_anita.estado_pendiente', ' ');
        $tipoOper = substr((string) config('rendicion_maquina_anita.tipo_oper', 'F'), 0, 1);
        $estadoSql = self::sqlTexto($estadoPendiente, 1);

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => (string) config('rendicion_maquina_anita.tabla_cabecera', 'rendmaquina'),
            'sistema' => (string) config('rendicion_maquina_anita.sistema', 'caja'),
            'valores' => implode(', ', [
                "rendm_estado = '{$estadoSql}'",
                "rendm_tipo_fac = ''",
                "rendm_letra_fac = ''",
                'rendm_sucursal_fac = 0',
                'rendm_nro_fac = 0',
                'rendm_fecha_fac = 0',
            ]),
            'whereArmado' => " WHERE rendm_nro_oper = {$nroOper} AND rendm_tipo_oper = '{$tipoOper}'",
        ], 'rendmaquina revierte facturada', 'cierre_maquina.anita');
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $fsl
     */
    private static function actualizarRendmaquinaAnita(
        RendicionMaquina $rendicion,
        array $fsl,
        string $fechaDia,
        string $estado,
    ): void {
        $nroOper = (int) ($rendicion->nro_oper_anita ?? 0);
        if ($nroOper <= 0) {
            return;
        }

        $tipoOper = substr((string) config('rendicion_maquina_anita.tipo_oper', 'F'), 0, 1);
        $fechaEntera = (int) Carbon::parse($fechaDia)->format('Ymd');
        $tipo = self::sqlTexto($fsl['tipo'], 3);
        $letra = self::sqlTexto($fsl['letra'], 1);
        $estadoSql = self::sqlTexto($estado, 1);

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => (string) config('rendicion_maquina_anita.tabla_cabecera', 'rendmaquina'),
            'sistema' => (string) config('rendicion_maquina_anita.sistema', 'caja'),
            'valores' => implode(', ', [
                "rendm_estado = '{$estadoSql}'",
                "rendm_tipo_fac = '{$tipo}'",
                "rendm_letra_fac = '{$letra}'",
                'rendm_sucursal_fac = '.(int) $fsl['sucursal'],
                'rendm_nro_fac = '.(int) $fsl['nro'],
                'rendm_fecha_fac = '.$fechaEntera,
            ]),
            'whereArmado' => " WHERE rendm_nro_oper = {$nroOper} AND rendm_tipo_oper = '{$tipoOper}'",
        ], 'rendmaquina marca facturada', 'cierre_maquina.anita');
    }

    private static function insertarVentaAnita(
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
        string $fechaDia,
        float $monto,
    ): void {
        $cliente = (string) config('rendicion_maquina_anita.cierre_rendicion_contable.cliente_codigo', '000000');
        $nombre = str_replace("'", "''", (string) config('rendicion_maquina_anita.cierre_rendicion_contable.cliente_nombre', 'Sala de máquinas'));
        $fecha = (int) Carbon::parse($fechaDia)->format('Ymd');
        $montoSql = number_format($monto, 4, '.', '');

        $api = new ApiAnita;
        $err = ApiAnita::extraerMensajeError($api->apiCallEscritura([
            'acc' => 'insert',
            'tabla' => 'venta',
            'sistema' => 'ventas',
            'path_sistema' => 'V',
            'campos' => 'ven_cliente,ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_fecha,ven_fecha_vto,ven_exento,ven_gravado,ven_monto,ven_t_ult_cobro,ven_t_cobrado,ven_cod_mon,ven_cotizacion,ven_cta_cte,ven_nombre_cliente,ven_usuario,ven_terminal',
            'valores' => "'{$cliente}', '{$tipo}', '{$letra}', {$sucursal}, {$numero}, {$fecha}, {$fecha}, {$montoSql}, 0, {$montoSql}, {$montoSql}, {$montoSql}, '1', 1, 'N', '{$nombre}', 'ERP', 'ERP'",
        ], 'venta FSL máquinas insert', 'cierre_maquina.anita'));

        if ($err !== null) {
            throw new RuntimeException('Error al grabar FSL exenta en Anita venta: '.$err);
        }
    }

    private static function sqlTexto(string $valor, int $max): string
    {
        return substr(str_replace("'", "''", $valor), 0, $max);
    }
}

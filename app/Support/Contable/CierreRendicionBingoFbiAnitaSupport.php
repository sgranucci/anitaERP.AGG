<?php

namespace App\Support\Contable;

use App\ApiAnita;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Repositories\Ventas\VentaRepository;
use Carbon\Carbon;
use RuntimeException;

/**
 * FBI exenta en Anita (venta) + marca rendbingo facturado.
 */
final class CierreRendicionBingoFbiAnitaSupport
{
    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int, monto: float}
     */
    public static function emitirFbiExenta(int $empresaId, string $fechaDia, float $monto): array
    {
        $tipo = (string) config('bingo.cierre_rendicion_contable.tipo_comprobante', 'FBI');
        $letra = (string) config('bingo.cierre_rendicion_contable.letra_comprobante', 'B');
        $sucursal = CierreRendicionBingoConfigSupport::puntoventaFbi($empresaId);
        $monto = round($monto, 2);

        if ($monto <= 0) {
            throw new RuntimeException('No hay recaudación para emitir FBI exenta.');
        }

        $repo = app(VentaRepository::class);
        $numero = $repo->numeraAnita($tipo, $letra, (string) $sucursal, 'V');
        if (! is_int($numero) || $numero <= 0) {
            throw new RuntimeException('No se pudo numerar FBI exenta en Anita: '.(is_string($numero) ? $numero : 'error'));
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
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $fbi
     */
    public static function marcarRendicionesFacturadas(RendicionBingoCaja $rendicion, array $fbi, string $fechaDia): void
    {
        $estado = (string) config('bingo.cierre_rendicion_contable.estado_facturado_anita', 'F');
        $fechaFac = Carbon::parse($fechaDia)->format('Y-m-d');

        $rendicion->update([
            'factura_tipo' => $fbi['tipo'],
            'factura_letra' => $fbi['letra'],
            'factura_sucursal' => (int) $fbi['sucursal'],
            'factura_nro' => (int) $fbi['nro'],
            'factura_fecha' => $fechaFac,
            'estado_facturacion' => $estado,
        ]);

        self::actualizarRendbingoAnita($rendicion, $fbi, $fechaDia, $estado);
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, nro: int}  $fbi
     */
    private static function actualizarRendbingoAnita(
        RendicionBingoCaja $rendicion,
        array $fbi,
        string $fechaDia,
        string $estado,
    ): void {
        $nroOper = (int) ($rendicion->nro_oper_anita ?? 0);
        if ($nroOper <= 0) {
            return;
        }

        $tipoOper = substr((string) config('rendicion_bingo_anita.tipo_oper', 'F'), 0, 1);
        $fechaEntera = (int) Carbon::parse($fechaDia)->format('Ymd');
        $tipo = self::sqlTexto($fbi['tipo'], 3);
        $letra = self::sqlTexto($fbi['letra'], 1);
        $estadoSql = self::sqlTexto($estado, 1);

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => (string) config('rendicion_bingo_anita.tabla_cabecera', 'rendbingo'),
            'sistema' => (string) config('rendicion_bingo_anita.sistema', 'caja'),
            'valores' => implode(', ', [
                "rendb_estado = '{$estadoSql}'",
                "rendb_tipo_fac = '{$tipo}'",
                "rendb_letra_fac = '{$letra}'",
                'rendb_sucursal_fac = '.(int) $fbi['sucursal'],
                'rendb_nro_fac = '.(int) $fbi['nro'],
                'rendb_fecha_fac = '.$fechaEntera,
            ]),
            'whereArmado' => " WHERE rendb_nro_oper = {$nroOper} AND rendb_tipo_oper = '{$tipoOper}'",
        ], 'rendbingo marca facturada', 'cierre_bingo.anita');
    }

    private static function insertarVentaAnita(
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
        string $fechaDia,
        float $monto,
    ): void {
        $cliente = (string) config('bingo.cierre_rendicion_contable.cliente_codigo', '000000');
        $nombre = str_replace("'", "''", (string) config('bingo.cierre_rendicion_contable.cliente_nombre', 'Sala de bingo'));
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
        ], 'venta FBI bingo insert', 'cierre_bingo.anita'));

        if ($err !== null) {
            throw new RuntimeException('Error al grabar FBI exenta en Anita venta: '.$err);
        }
    }

    private static function sqlTexto(string $valor, int $max): string
    {
        return substr(str_replace("'", "''", $valor), 0, $max);
    }
}

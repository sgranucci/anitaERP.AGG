<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\ApiAnita;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Contable\Asiento;
use App\Support\Stock\RecepcionProveedorAnitaWhereSupport;

/**
 * Lecturas de integridad Anita para una factura ERP (compra/concmov/promov/aplicped/ctamov).
 */
final class ComprobanteProveedorAnitaIntegridadSupport
{
    private const SISTEMA_COMPRAS = 'compras';

    private const SISTEMA_CONTAB = 'contab';

    /**
     * @return array{
     *     compra: bool,
     *     concmov: int,
     *     promov: int,
     *     aplicped: int,
     *     ctamov: int,
     *     problemas: list<string>
     * }
     */
    public static function diagnosticar(Comprobante_Proveedor $comprobante): array
    {
                $comprobante->loadMissing([
            'proveedores',
            'tipotransaccion_compras',
            'comprobante_proveedor_conceptos',
            'comprobante_proveedor_cuotas',
            'ordencompras',
            'asientos',
            'empresas',
        ]);

        $problemas = [];
        $nroInterno = (int) ($comprobante->anita_nro_interno ?? 0);
        if ($nroInterno <= 0) {
            return [
                'compra' => false,
                'concmov' => 0,
                'promov' => 0,
                'aplicped' => 0,
                'ctamov' => 0,
                'problemas' => ['Sin anita_nro_interno'],
            ];
        }

        $ctx = new ComprobanteProveedorAnitaContext($comprobante, $nroInterno);

        $compra = self::contar(self::SISTEMA_COMPRAS, 'compra', 'com_nro_interno', $ctx->claveWhereCompra()) > 0;
        if (! $compra) {
            $problemas[] = 'Falta compra en Anita';
        }

        $concmov = self::contar(
            self::SISTEMA_COMPRAS,
            'concmov',
            'concv_nro_interno',
            ' WHERE concv_nro_interno = '.$nroInterno
        );
        $esperadosConceptos = $comprobante->comprobante_proveedor_conceptos->count();
        if ($esperadosConceptos > 0 && $concmov < $esperadosConceptos) {
            $problemas[] = "concmov incompleto ({$concmov}/{$esperadosConceptos})";
        }

        $promov = self::contar(self::SISTEMA_COMPRAS, 'promov', 'prov_nro_interno', $ctx->claveWherePromov());
        $esperadasCuotas = $comprobante->comprobante_proveedor_cuotas->count();
        if ($esperadasCuotas > 0 && $promov < $esperadasCuotas) {
            $problemas[] = "promov incompleto ({$promov}/{$esperadasCuotas})";
        }

        $aplicped = 0;
        $requiereAplicped = (int) ($comprobante->ordencompra_id ?? 0) > 0
            && (int) ($comprobante->ordencompras?->numeroordencompra ?? 0) > 0;
        if ($requiereAplicped) {
            $tablaAplic = (string) config('recepcion_proveedor.anita.tablas.aplicacion_oc', 'aplicped');
            $claveFac = [
                'tipo' => $ctx->tipoComprobante(),
                'letra' => $ctx->letra(),
                'sucursal' => $ctx->sucursal(),
                'nro' => $ctx->numero(),
            ];
            $whereAplic = RecepcionProveedorAnitaWhereSupport::aplicpedCom($ctx->proveedorCodigo(), $claveFac);
            $aplicped = self::contar(self::SISTEMA_COMPRAS, $tablaAplic, 'aplp_nro_interno', $whereAplic);
            if ($aplicped <= 0) {
                $problemas[] = 'Falta aplicped (factura→PEP)';
            } else {
                $filas = self::listar(
                    self::SISTEMA_COMPRAS,
                    $tablaAplic,
                    'aplp_nro_interno',
                    $whereAplic
                );
                foreach ($filas as $fila) {
                    $a = (array) $fila;
                    if ((int) ($a['aplp_nro_interno'] ?? 0) !== $nroInterno) {
                        $problemas[] = 'aplicped con aplp_nro_interno distinto de com_nro_interno';
                        break;
                    }
                }
            }
        }

        $ctamov = 0;
        if ((int) ($comprobante->asiento_id ?? 0) > 0) {
            $asiento = $comprobante->asientos ?? Asiento::query()->find((int) $comprobante->asiento_id);
            $nroAsiento = (int) ($asiento?->numeroasiento ?? 0);
            $empresaCodigo = (int) $ctx->empresaCodigo();
            if ($nroAsiento > 0 && $empresaCodigo > 0) {
                $ctamov = self::contar(
                    self::SISTEMA_CONTAB,
                    'ctamov',
                    'ctav_nro_asiento',
                    ' WHERE ctav_empresa = '.$empresaCodigo.' AND ctav_nro_asiento = '.$nroAsiento
                );
                if ($ctamov <= 0) {
                    $problemas[] = 'Falta ctamov del asiento '.$nroAsiento;
                }
            } else {
                $problemas[] = 'Asiento ERP sin numeroasiento para verificar ctamov';
            }
        }

        return [
            'compra' => $compra,
            'concmov' => $concmov,
            'promov' => $promov,
            'aplicped' => $aplicped,
            'ctamov' => $ctamov,
            'problemas' => $problemas,
        ];
    }

    private static function contar(string $sistema, string $tabla, string $campo, string $whereArmado): int
    {
        return count(self::listar($sistema, $tabla, $campo, $whereArmado));
    }

    /**
     * @return list<object|array<string, mixed>>
     */
    private static function listar(string $sistema, string $tabla, string $campo, string $whereArmado): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campo,
            'whereArmado' => $whereArmado,
        ]);

        return ApiAnita::decodificarListaFilas($raw);
    }
}

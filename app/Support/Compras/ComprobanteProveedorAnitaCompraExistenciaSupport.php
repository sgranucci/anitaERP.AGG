<?php

namespace App\Support\Compras;

use App\ApiAnita;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Empresa;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Consulta Anita (tabla compra) para evitar cargar en ERP una factura
 * que ya existe cargada en Anita (nativo u otro origen).
 *
 * Clave natural: proveedor + tipo (abreviatura) + letra + sucursal + número
 * (+ empresa cuando se puede resolver).
 */
final class ComprobanteProveedorAnitaCompraExistenciaSupport
{
    /**
     * @throws RuntimeException
     */
    public static function assertNoDuplicadoEnAnita(
        int $empresaId,
        int $proveedorId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?int $excluirAnitaNroInterno = null,
    ): void {
        $fila = self::buscar(
            $empresaId,
            $proveedorId,
            $tipotransaccionCompraId,
            $letra,
            $sucursal,
            $numerocomprobante,
            $excluirAnitaNroInterno,
        );

        if ($fila === null) {
            return;
        }

        throw new RuntimeException(self::mensajeDuplicado($fila, $letra, $sucursal, $numerocomprobante));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buscar(
        int $empresaId,
        int $proveedorId,
        int $tipotransaccionCompraId,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
        ?int $excluirAnitaNroInterno = null,
    ): ?array {
        if ($proveedorId <= 0 || $tipotransaccionCompraId <= 0 || $numerocomprobante <= 0) {
            return null;
        }

        $proveedorCodigo = self::proveedorCodigoAnita($proveedorId);
        $tipoAbrev = self::tipoAbreviaturaAnita($tipotransaccionCompraId);
        $letraNorm = strtoupper(substr(trim($letra), 0, 1));
        if ($proveedorCodigo === '' || $tipoAbrev === '' || $letraNorm === '') {
            return null;
        }

        $empresaCodigo = self::empresaCodigoAnita($empresaId);

        $where = " WHERE com_proveedor = '".$proveedorCodigo."'"
            ." AND com_tipo = '".$tipoAbrev."'"
            ." AND com_letra = '".$letraNorm."'"
            .' AND com_sucursal = '.(int) $sucursal
            .' AND com_nro = '.(int) $numerocomprobante;

        if ($empresaCodigo !== null) {
            $where .= ' AND com_empresa = '.(int) $empresaCodigo;
        }

        if ($excluirAnitaNroInterno !== null && $excluirAnitaNroInterno > 0) {
            $where .= ' AND com_nro_interno <> '.(int) $excluirAnitaNroInterno;
        }

        // Descripción al final por convención del bridge (corrimiento por |).
        $campos = implode(', ', [
            'com_proveedor',
            'com_tipo',
            'com_letra',
            'com_sucursal',
            'com_nro',
            'com_nro_interno',
            'com_fecha',
            'com_empresa',
            'com_monto',
            'com_cuit_prov',
            'com_nombre_prov',
        ]);

        try {
            $api = new ApiAnita;
            $filas = ApiAnita::decodificarListaFilas($api->apiCall([
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'compra',
                'campos' => $campos,
                'whereArmado' => $where,
            ]));
        } catch (\Throwable $e) {
            Log::warning('comprobante_proveedor.anita_compra_existencia_error', [
                'proveedor' => $proveedorCodigo,
                'tipo' => $tipoAbrev,
                'letra' => $letraNorm,
                'sucursal' => $sucursal,
                'nro' => $numerocomprobante,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'No se pudo consultar Anita (tabla compra) para verificar si la factura ya existe. '
                .'Reintente; si el problema continúa, avise a sistemas. Detalle: '.$e->getMessage()
            );
        }

        if ($filas === []) {
            return null;
        }

        return (array) $filas[0];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function mensajeDuplicado(
        array $fila,
        string $letra,
        int $sucursal,
        int $numerocomprobante,
    ): string {
        $tipo = strtoupper(trim((string) ($fila['com_tipo'] ?? '')));
        $letraFila = strtoupper(substr(trim((string) ($fila['com_letra'] ?? $letra)), 0, 1));
        $suc = (int) ($fila['com_sucursal'] ?? $sucursal);
        $nro = (int) ($fila['com_nro'] ?? $numerocomprobante);
        $nroInterno = (int) ($fila['com_nro_interno'] ?? 0);
        $fecha = trim((string) ($fila['com_fecha'] ?? ''));
        $fechaFmt = self::formatearFechaAnita($fecha);
        $cuit = preg_replace('/\D/', '', (string) ($fila['com_cuit_prov'] ?? '')) ?? '';
        $cuitFmt = strlen($cuit) === 11
            ? ComprobanteProveedorUnicidadSupport::formatearCuit($cuit)
            : trim((string) ($fila['com_cuit_prov'] ?? ''));
        $nombre = trim((string) ($fila['com_nombre_prov'] ?? ''));

        return sprintf(
            'Factura ya existente en Anita: %s %s %s-%s (nro. interno %s%s%s). '
            .'Fue cargada en Anita y no se puede repetir desde el ERP.',
            $tipo !== '' ? $tipo : 'FAC',
            $letraFila,
            $suc,
            $nro,
            $nroInterno > 0 ? (string) $nroInterno : 's/d',
            $fechaFmt !== '' ? ', fecha '.$fechaFmt : '',
            $cuitFmt !== '' ? ', CUIT '.$cuitFmt.($nombre !== '' ? ' '.$nombre : '') : '',
        );
    }

    private static function proveedorCodigoAnita(int $proveedorId): string
    {
        $codigo = Proveedor::query()->whereKey($proveedorId)->value('codigo');
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return '';
        }

        return str_pad($codigo, 6, '0', STR_PAD_LEFT);
    }

    private static function tipoAbreviaturaAnita(int $tipotransaccionCompraId): string
    {
        $abrev = Tipotransaccion_Compra::query()->whereKey($tipotransaccionCompraId)->value('abreviatura');

        return substr(strtoupper(trim((string) $abrev)), 0, 3);
    }

    private static function empresaCodigoAnita(int $empresaId): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }

        $codigo = Empresa::query()->whereKey($empresaId)->value('codigo');
        if ($codigo === null || $codigo === '') {
            return $empresaId;
        }

        return (int) $codigo;
    }

    private static function formatearFechaAnita(string $ymd): string
    {
        $digits = preg_replace('/\D/', '', $ymd) ?? '';
        if (strlen($digits) !== 8) {
            return '';
        }

        return substr($digits, 6, 2).'/'.substr($digits, 4, 2).'/'.substr($digits, 0, 4);
    }
}

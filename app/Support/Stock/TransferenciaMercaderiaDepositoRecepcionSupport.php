<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Support\Facades\DB;

/**
 * Depósito de la última recepción COM confirmada del artículo (ERP; Anita si ANITA_IMPORT).
 *
 * Busca primero en la empresa indicada; si no hay COM, busca cross-empresa
 * (artículos TITO pueden tener COM en empresa 2/3 pero depósito 1004 en empresa 1).
 */
final class TransferenciaMercaderiaDepositoRecepcionSupport
{
    /**
     * @return array{deposito_id: ?int, origen: ?string}
     */
    public static function resolver(int $articuloId, int $empresaId): array
    {
        if ($articuloId <= 0 || $empresaId <= 0) {
            return ['deposito_id' => null, 'origen' => null];
        }

        $resultado = self::resolverDesdeEmpresa($articuloId, $empresaId);
        if ($resultado['deposito_id'] !== null) {
            return $resultado;
        }

        return self::resolverCrossEmpresa($articuloId, $empresaId);
    }

    /**
     * @return array{deposito_id: ?int, origen: ?string}
     */
    private static function resolverDesdeEmpresa(int $articuloId, int $empresaId): array
    {
        $fila = self::ultimaRecepcionFila($articuloId, $empresaId);
        if ($fila === null) {
            return ['deposito_id' => null, 'origen' => null];
        }

        return self::resolverDesdeFila($fila, $empresaId);
    }

    /**
     * @return array{deposito_id: ?int, origen: ?string}
     */
    private static function resolverCrossEmpresa(int $articuloId, int $empresaId): array
    {
        $fila = self::ultimaRecepcionFila($articuloId, null);
        if ($fila === null) {
            return ['deposito_id' => null, 'origen' => null];
        }

        return self::resolverDesdeFila($fila, $empresaId);
    }

    /**
     * @return array{deposito_id: ?int, origen: ?string}
     */
    private static function resolverDesdeFila(object $fila, int $empresaId): array
    {
        if (($fila->origen_carga ?? '') === 'ANITA_IMPORT') {
            $desdeAnita = self::depositoDesdeAnita($fila, $empresaId);
            if ($desdeAnita !== null) {
                return ['deposito_id' => $desdeAnita, 'origen' => 'anita'];
            }
        }

        $desdeErp = self::depositoDesdeFilaErp($fila);

        return [
            'deposito_id' => $desdeErp,
            'origen' => $desdeErp !== null ? 'erp' : null,
        ];
    }

    private static function ultimaRecepcionFila(int $articuloId, ?int $empresaId): ?object
    {
        $query = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
            ->join('articulo as a', 'a.id', '=', 'rpa.articulo_id')
            ->where('rpa.articulo_id', $articuloId)
            ->where('rp.estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION);

        if ($empresaId !== null) {
            $query->where('rp.empresa_id', $empresaId);
        }

        return $query
            ->orderByDesc('rp.fecha')
            ->orderByDesc('rp.id')
            ->orderByDesc('rpa.id')
            ->first([
                'rp.origen_carga',
                'rp.anita_tipo',
                'rp.anita_letra',
                'rp.anita_sucursal',
                'rp.anita_nro',
                'rp.numerorecepcion',
                'rpa.deposito_id as deposito_linea_id',
                'rp.deposito_id as deposito_cabecera_id',
                'a.sku',
            ]);
    }

    private static function depositoDesdeFilaErp(object $fila): ?int
    {
        $depositoId = (int) ($fila->deposito_linea_id ?? $fila->deposito_cabecera_id ?? 0);

        return $depositoId > 0 ? $depositoId : null;
    }

    private static function depositoDesdeAnita(object $fila, int $empresaId): ?int
    {
        $tipo = trim((string) ($fila->anita_tipo ?? 'COM'));
        $letra = trim((string) ($fila->anita_letra ?? 'X'));
        $sucursal = (int) ($fila->anita_sucursal ?? 0);
        $nro = (int) ($fila->anita_nro ?? $fila->numerorecepcion ?? 0);
        $sku = trim((string) ($fila->sku ?? ''));

        if ($sucursal <= 0 || $nro <= 0 || $sku === '') {
            return null;
        }

        try {
            $lineasAnita = RecepcionProveedorAnitaImportSupport::listarRecepmov($tipo, $letra, $sucursal, $nro);
        } catch (\Throwable) {
            return null;
        }

        return RecepcionProveedorDepositoAnitaSupport::resolverIdDepositoParaSku(
            $lineasAnita,
            $sku,
            $empresaId
        );
    }
}

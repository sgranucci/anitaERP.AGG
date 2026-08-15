<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resuelve el proveedor de cada línea para las columnas Emisor y CUIT.
 *
 * Orden de preferencia: proveedor del documento origen del asiento ERP
 * (comprobante, recepción, pago, orden de compra) → código de emisor de Anita.
 * Un código deducido de la descripción que no corresponde a ningún proveedor se
 * descarta: solía mostrar el número de comprobante COM en la columna Emisor.
 */
class MayorPlanoCuentaProveedorEnricher
{
    /** CUIT del proveedor en anitaERP. */
    private const COLUMNA_CUIT = 'nroinscripcion';

    /** Documentos del asiento ERP que identifican al proveedor. */
    private const TABLAS_ORIGEN = [
        'comprobante_proveedor_id' => 'comprobante_proveedor',
        'recepcionproveedor_id' => 'recepcion_proveedor',
        'pagoproveedor_id' => 'pagoproveedor',
        'ordencompra_id' => 'ordencompra',
    ];

    /** @var array<string, list<array{id: int, empresa_id: int, codigo: string, cuit: string}>> */
    private array $cachePorCodigo = [];

    /** @var array<int, array{id: int, empresa_id: int, codigo: string, cuit: string}> */
    private array $cachePorId = [];

    /** @var array<string, int> tabla|id => proveedor_id */
    private array $cacheProveedorDeDocumento = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas): array
    {
        $codigos = [];
        $documentos = [];

        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $codigo = self::normalizarCodigoEmisor((string) ($fila['emisor'] ?? ''));
            if ($codigo !== '') {
                $codigos[$codigo] = true;
            }

            foreach (self::TABLAS_ORIGEN as $campo => $tabla) {
                $id = (int) ($fila[$campo] ?? 0);
                if ($id > 0) {
                    $documentos[$tabla][$id] = $id;
                }
            }
        }

        $this->precargarPorCodigo(array_keys($codigos));
        $this->precargarProveedoresDeDocumentos($documentos);

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            $codigo = self::normalizarCodigoEmisor((string) ($fila['emisor'] ?? ''));
            $proveedor = $this->proveedorPorDocumentoOrigen($fila);
            if ($proveedor === null) {
                $proveedor = $codigo !== '' ? $this->proveedorPorCodigo($codigo, $empresaId) : null;
            }

            if ($proveedor !== null) {
                // Columna Emisor: sin ceros a la izquierda (Anita trae p. ej. 000482).
                $filas[$idx]['emisor'] = $proveedor['codigo'];
                $filas[$idx]['proveedor_id'] = $proveedor['id'];
                $filas[$idx]['cuit'] = $proveedor['cuit'];

                continue;
            }

            // Código inventado por el fallback de descripción: no es un proveedor.
            $filas[$idx]['emisor'] = ! empty($fila['emisor_deducido']) ? '' : $codigo;
            $filas[$idx]['proveedor_id'] = 0;
            $filas[$idx]['cuit'] = '';
        }

        return $filas;
    }

    public static function normalizarCodigoEmisor(string $emisor): string
    {
        $emisor = trim($emisor);
        if ($emisor === '') {
            return '';
        }

        $codigo = ltrim($emisor, '0');

        return $codigo !== '' ? $codigo : '';
    }

    /**
     * @return array{id: int, empresa_id: int, codigo: string, cuit: string}|null
     */
    private function proveedorPorCodigo(string $codigo, int $empresaId): ?array
    {
        $candidatos = $this->cachePorCodigo[$codigo] ?? [];
        if ($candidatos === []) {
            return null;
        }

        foreach ($candidatos as $candidato) {
            if ($empresaId > 0 && $candidato['empresa_id'] === $empresaId) {
                return $candidato;
            }
        }

        return $candidatos[0];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{id: int, empresa_id: int, codigo: string, cuit: string}|null
     */
    private function proveedorPorDocumentoOrigen(array $fila): ?array
    {
        foreach (self::TABLAS_ORIGEN as $campo => $tabla) {
            $id = (int) ($fila[$campo] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $proveedorId = (int) ($this->cacheProveedorDeDocumento[$tabla.'|'.$id] ?? 0);
            if ($proveedorId > 0 && isset($this->cachePorId[$proveedorId])) {
                return $this->cachePorId[$proveedorId];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $codigos
     */
    private function precargarPorCodigo(array $codigos): void
    {
        $faltantes = array_values(array_filter(
            $codigos,
            fn (string $codigo) => ! isset($this->cachePorCodigo[$codigo]),
        ));

        if ($faltantes === []) {
            return;
        }

        foreach ($faltantes as $codigo) {
            $this->cachePorCodigo[$codigo] = [];
        }

        foreach (DB::table('proveedor')->whereIn('codigo', $faltantes)->get($this->columnas()) as $row) {
            $this->guardarEnCache($row);
        }
    }

    /**
     * @param  array<string, array<int, int>>  $documentos
     */
    private function precargarProveedoresDeDocumentos(array $documentos): void
    {
        $proveedorIds = [];

        foreach ($documentos as $tabla => $ids) {
            $pendientes = array_values(array_filter(
                $ids,
                fn (int $id) => ! isset($this->cacheProveedorDeDocumento[$tabla.'|'.$id]),
            ));

            if ($pendientes === [] || ! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'proveedor_id')) {
                continue;
            }

            foreach (DB::table($tabla)->whereIn('id', $pendientes)->get(['id', 'proveedor_id']) as $row) {
                $proveedorId = (int) ($row->proveedor_id ?? 0);
                $this->cacheProveedorDeDocumento[$tabla.'|'.(int) $row->id] = $proveedorId;
                if ($proveedorId > 0) {
                    $proveedorIds[$proveedorId] = $proveedorId;
                }
            }
        }

        $faltantes = array_values(array_filter(
            $proveedorIds,
            fn (int $id) => ! isset($this->cachePorId[$id]),
        ));

        if ($faltantes === []) {
            return;
        }

        foreach (DB::table('proveedor')->whereIn('id', $faltantes)->get($this->columnas()) as $row) {
            $this->guardarEnCache($row);
        }
    }

    private function guardarEnCache(object $row): void
    {
        $datos = [
            'id' => (int) $row->id,
            'empresa_id' => (int) ($row->empresa_id ?? 0),
            'codigo' => self::normalizarCodigoEmisor((string) ($row->codigo ?? '')),
            'cuit' => trim((string) ($row->{self::COLUMNA_CUIT} ?? '')),
        ];

        $this->cachePorId[$datos['id']] = $datos;
        if ($datos['codigo'] !== '') {
            $this->cachePorCodigo[$datos['codigo']][] = $datos;
        }
    }

    /**
     * @return list<string>
     */
    private function columnas(): array
    {
        $columnas = ['id', 'codigo', 'empresa_id'];
        if (Schema::hasColumn('proveedor', self::COLUMNA_CUIT)) {
            $columnas[] = self::COLUMNA_CUIT;
        }

        return $columnas;
    }
}

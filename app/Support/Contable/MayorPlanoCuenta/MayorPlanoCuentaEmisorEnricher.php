<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Models\Caja\Cuentacaja;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resuelve el emisor de cada línea (columnas Emisor y CUIT) contra el maestro que
 * corresponde al subsistema de Anita: proveedor, cliente o cuenta de caja.
 *
 * Para proveedor manda el documento origen del asiento ERP (comprobante, recepción,
 * pago, orden de compra), luego la aplicación de cuenta corriente ligada al asiento
 * y recién después el código de Anita. Un código deducido de la descripción que no
 * corresponde a ningún proveedor se descarta: solía mostrar el número de comprobante
 * COM en la columna Emisor.
 */
class MayorPlanoCuentaEmisorEnricher
{
    private const COLUMNA_CUIT_PROVEEDOR = 'nroinscripcion';

    private const COLUMNA_CUIT_CLIENTE = 'numerodocumento';

    /** Documentos del asiento ERP que identifican al proveedor. */
    private const TABLAS_ORIGEN = [
        'comprobante_proveedor_id' => 'comprobante_proveedor',
        'recepcionproveedor_id' => 'recepcion_proveedor',
        'pagoproveedor_id' => 'pagoproveedor',
        'ordencompra_id' => 'ordencompra',
    ];

    /** @var array<string, list<array{id: int, empresa_id: int|null, codigo: string, nombre: string, cuit: string}>> entidad|codigo */
    private array $cachePorCodigo = [];

    /** @var array<int, array{id: int, empresa_id: int|null, codigo: string, nombre: string, cuit: string}> */
    private array $cacheProveedorPorId = [];

    /** @var array<string, int> tabla|id => proveedor_id */
    private array $cacheProveedorDeDocumento = [];

    /** @var array<int, int> asiento_id => proveedor_id */
    private array $cacheProveedorDeAplicacion = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas): array
    {
        $codigosPorEntidad = [];
        $documentos = [];
        $asientoIds = [];

        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $codigo = MayorPlanoCuentaEmisorSupport::normalizarCodigo((string) ($fila['emisor'] ?? ''));
            if ($codigo !== '') {
                $codigosPorEntidad[$this->entidadDeFila($fila)][$codigo] = true;
            }

            $tieneDocumento = false;
            foreach (self::TABLAS_ORIGEN as $campo => $tabla) {
                $id = (int) ($fila[$campo] ?? 0);
                if ($id > 0) {
                    $documentos[$tabla][$id] = $id;
                    $tieneDocumento = true;
                }
            }

            $asientoId = (int) ($fila['asiento_id'] ?? 0);
            if ($asientoId > 0 && $codigo === '' && ! $tieneDocumento) {
                $asientoIds[$asientoId] = $asientoId;
            }
        }

        foreach ($codigosPorEntidad as $entidad => $codigos) {
            $this->precargarPorCodigo((string) $entidad, array_keys($codigos));
        }
        $this->precargarProveedoresDeDocumentos($documentos);
        $this->precargarProveedoresDeAplicacionCc(array_values($asientoIds));

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $entidad = $this->entidadDeFila($fila);
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            $codigo = MayorPlanoCuentaEmisorSupport::normalizarCodigo((string) ($fila['emisor'] ?? ''));

            $registro = $entidad === MayorPlanoCuentaEmisorSupport::ENTIDAD_PROVEEDOR
                ? $this->proveedorPorDocumentoOrigen($fila)
                : null;
            if ($registro === null && $entidad === MayorPlanoCuentaEmisorSupport::ENTIDAD_PROVEEDOR) {
                $registro = $this->proveedorPorAplicacionCc($fila);
            }
            if ($registro === null && $codigo !== '') {
                $registro = $this->registroPorCodigo($entidad, $codigo, $empresaId);
            }

            $filas[$idx] = $this->aplicarEmisor($fila, $entidad, $codigo, $registro);
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array{id: int, empresa_id: int|null, codigo: string, nombre: string, cuit: string}|null  $registro
     * @return array<string, mixed>
     */
    private function aplicarEmisor(array $fila, string $entidad, string $codigo, ?array $registro): array
    {
        $fila['emisor_entidad'] = $entidad;
        $fila['proveedor_id'] = 0;
        $fila['cliente_id'] = 0;
        $fila['cuentacaja_id'] = 0;

        if ($registro === null) {
            // Código deducido de la descripción que no existe como proveedor: no es un emisor.
            $fila['emisor'] = ! empty($fila['emisor_deducido']) ? '' : $codigo;
            $fila['emisor_nombre'] = '';
            $fila['emisor_fmt'] = $fila['emisor'];
            $fila['cuit'] = '';

            return $fila;
        }

        $fila['emisor'] = $registro['codigo'];
        $fila['emisor_nombre'] = $registro['nombre'];
        $fila['emisor_fmt'] = $registro['nombre'] !== ''
            ? $registro['codigo'].' — '.$registro['nombre']
            : $registro['codigo'];
        $fila['cuit'] = $registro['cuit'];
        $fila[$this->campoIdEntidad($entidad)] = $registro['id'];

        return $fila;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function entidadDeFila(array $fila): string
    {
        $entidad = trim((string) ($fila['emisor_entidad'] ?? ''));

        return match ($entidad) {
            MayorPlanoCuentaEmisorSupport::ENTIDAD_CLIENTE => MayorPlanoCuentaEmisorSupport::ENTIDAD_CLIENTE,
            MayorPlanoCuentaEmisorSupport::ENTIDAD_CUENTACAJA => MayorPlanoCuentaEmisorSupport::ENTIDAD_CUENTACAJA,
            default => MayorPlanoCuentaEmisorSupport::ENTIDAD_PROVEEDOR,
        };
    }

    private function campoIdEntidad(string $entidad): string
    {
        return match ($entidad) {
            MayorPlanoCuentaEmisorSupport::ENTIDAD_CLIENTE => 'cliente_id',
            MayorPlanoCuentaEmisorSupport::ENTIDAD_CUENTACAJA => 'cuentacaja_id',
            default => 'proveedor_id',
        };
    }

    /**
     * @return array{id: int, empresa_id: int|null, codigo: string, nombre: string, cuit: string}|null
     */
    private function registroPorCodigo(string $entidad, string $codigo, int $empresaId): ?array
    {
        $candidatos = $this->cachePorCodigo[$entidad.'|'.$codigo] ?? [];
        if ($candidatos === []) {
            return null;
        }

        foreach ($candidatos as $candidato) {
            if ($empresaId > 0 && (int) $candidato['empresa_id'] === $empresaId) {
                return $candidato;
            }
        }

        // Cuenta de caja sin empresa = multiempresa (ver cuentacaja-empresa-multiempresa).
        foreach ($candidatos as $candidato) {
            if ($candidato['empresa_id'] === null) {
                return $candidato;
            }
        }

        if ($empresaId > 0 && $entidad === MayorPlanoCuentaEmisorSupport::ENTIDAD_CUENTACAJA) {
            return null;
        }

        return $candidatos[0];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{id: int, empresa_id: int|null, codigo: string, nombre: string, cuit: string}|null
     */
    private function proveedorPorDocumentoOrigen(array $fila): ?array
    {
        foreach (self::TABLAS_ORIGEN as $campo => $tabla) {
            $id = (int) ($fila[$campo] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $proveedorId = (int) ($this->cacheProveedorDeDocumento[$tabla.'|'.$id] ?? 0);
            if ($proveedorId > 0 && isset($this->cacheProveedorPorId[$proveedorId])) {
                return $this->cacheProveedorPorId[$proveedorId];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{id: int, empresa_id: int|null, codigo: string, nombre: string, cuit: string}|null
     */
    private function proveedorPorAplicacionCc(array $fila): ?array
    {
        $asientoId = (int) ($fila['asiento_id'] ?? 0);
        if ($asientoId <= 0) {
            return null;
        }

        $proveedorId = (int) ($this->cacheProveedorDeAplicacion[$asientoId] ?? 0);

        return $proveedorId > 0 ? ($this->cacheProveedorPorId[$proveedorId] ?? null) : null;
    }

    /**
     * @param  list<string>  $codigos
     */
    private function precargarPorCodigo(string $entidad, array $codigos): void
    {
        $faltantes = array_values(array_filter(
            $codigos,
            fn (string $codigo) => ! isset($this->cachePorCodigo[$entidad.'|'.$codigo]),
        ));

        if ($faltantes === []) {
            return;
        }

        foreach ($faltantes as $codigo) {
            $this->cachePorCodigo[$entidad.'|'.$codigo] = [];
        }

        foreach ($this->filasMaestro($entidad, $faltantes) as $registro) {
            $this->guardarEnCache($entidad, $registro);
        }
    }

    /**
     * @param  list<string>  $codigos
     * @return list<array{id: int, empresa_id: int|null, codigo: string, nombre: string, cuit: string}>
     */
    private function filasMaestro(string $entidad, array $codigos): array
    {
        if ($entidad === MayorPlanoCuentaEmisorSupport::ENTIDAD_CUENTACAJA) {
            return Cuentacaja::query()
                ->whereIn('codigo', $codigos)
                ->get(['id', 'codigo', 'nombre', 'empresa_id'])
                ->map(fn (Cuentacaja $cuenta) => [
                    'id' => (int) $cuenta->id,
                    'empresa_id' => $cuenta->empresa_id !== null ? (int) $cuenta->empresa_id : null,
                    'codigo' => MayorPlanoCuentaEmisorSupport::normalizarCodigo((string) $cuenta->codigo),
                    'nombre' => trim((string) $cuenta->nombre),
                    'cuit' => '',
                ])
                ->all();
        }

        $tabla = $entidad === MayorPlanoCuentaEmisorSupport::ENTIDAD_CLIENTE ? 'cliente' : 'proveedor';
        $columnaCuit = $this->columnaCuit($entidad);
        $registros = [];

        foreach (DB::table($tabla)->whereIn('codigo', $codigos)->get($this->columnas($entidad)) as $row) {
            $registros[] = [
                'id' => (int) $row->id,
                'empresa_id' => isset($row->empresa_id) ? (int) $row->empresa_id : null,
                // Columna Emisor: sin ceros a la izquierda (Anita trae p. ej. 000482).
                'codigo' => MayorPlanoCuentaEmisorSupport::normalizarCodigo((string) ($row->codigo ?? '')),
                'nombre' => trim((string) ($row->nombre ?? '')),
                'cuit' => $columnaCuit !== '' ? trim((string) ($row->{$columnaCuit} ?? '')) : '',
            ];
        }

        return $registros;
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

        $this->cargarProveedoresPorId(array_values($proveedorIds));
    }

    /**
     * Asientos de aplicación CC no tienen factura/pago en el asiento: el proveedor
     * está en la cuenta corriente ligada por asiento_id.
     *
     * @param  list<int>  $asientoIds
     */
    private function precargarProveedoresDeAplicacionCc(array $asientoIds): void
    {
        $pendientes = array_values(array_filter(
            $asientoIds,
            fn (int $id) => $id > 0 && ! isset($this->cacheProveedorDeAplicacion[$id]),
        ));

        if (
            $pendientes === []
            || ! Schema::hasTable('proveedor_cuentacorriente_aplicacion')
            || ! Schema::hasColumn('proveedor_cuentacorriente_aplicacion', 'asiento_id')
            || ! Schema::hasTable('proveedor_cuentacorriente')
            || ! Schema::hasColumn('proveedor_cuentacorriente', 'proveedor_id')
        ) {
            return;
        }

        foreach ($pendientes as $id) {
            $this->cacheProveedorDeAplicacion[$id] = 0;
        }

        $proveedorIds = [];
        $filas = DB::table('proveedor_cuentacorriente_aplicacion as apl')
            ->join('proveedor_cuentacorriente as cc', 'cc.id', '=', 'apl.proveedor_cuentacorriente_id')
            ->whereIn('apl.asiento_id', $pendientes)
            ->where('apl.asiento_id', '>', 0)
            ->get(['apl.asiento_id', 'cc.proveedor_id']);

        foreach ($filas as $row) {
            $asientoId = (int) $row->asiento_id;
            $proveedorId = (int) ($row->proveedor_id ?? 0);
            if ($proveedorId <= 0 || ($this->cacheProveedorDeAplicacion[$asientoId] ?? 0) > 0) {
                continue;
            }
            $this->cacheProveedorDeAplicacion[$asientoId] = $proveedorId;
            $proveedorIds[$proveedorId] = $proveedorId;
        }

        $this->cargarProveedoresPorId(array_values($proveedorIds));
    }

    /**
     * @param  list<int>  $proveedorIds
     */
    private function cargarProveedoresPorId(array $proveedorIds): void
    {
        $faltantes = array_values(array_filter(
            $proveedorIds,
            fn (int $id) => $id > 0 && ! isset($this->cacheProveedorPorId[$id]),
        ));

        if ($faltantes === [] || ! Schema::hasTable('proveedor')) {
            return;
        }

        $entidad = MayorPlanoCuentaEmisorSupport::ENTIDAD_PROVEEDOR;
        $columnaCuit = $this->columnaCuit($entidad);

        foreach (DB::table('proveedor')->whereIn('id', $faltantes)->get($this->columnas($entidad)) as $row) {
            $this->guardarEnCache($entidad, [
                'id' => (int) $row->id,
                'empresa_id' => isset($row->empresa_id) ? (int) $row->empresa_id : null,
                'codigo' => MayorPlanoCuentaEmisorSupport::normalizarCodigo((string) ($row->codigo ?? '')),
                'nombre' => trim((string) ($row->nombre ?? '')),
                'cuit' => $columnaCuit !== '' ? trim((string) ($row->{$columnaCuit} ?? '')) : '',
            ]);
        }
    }

    /**
     * @param  array{id: int, empresa_id: int|null, codigo: string, nombre: string, cuit: string}  $registro
     */
    private function guardarEnCache(string $entidad, array $registro): void
    {
        if ($entidad === MayorPlanoCuentaEmisorSupport::ENTIDAD_PROVEEDOR) {
            $this->cacheProveedorPorId[$registro['id']] = $registro;
        }
        if ($registro['codigo'] !== '') {
            $this->cachePorCodigo[$entidad.'|'.$registro['codigo']][] = $registro;
        }
    }

    private function columnaCuit(string $entidad): string
    {
        return match ($entidad) {
            MayorPlanoCuentaEmisorSupport::ENTIDAD_CLIENTE => self::COLUMNA_CUIT_CLIENTE,
            MayorPlanoCuentaEmisorSupport::ENTIDAD_CUENTACAJA => '',
            default => self::COLUMNA_CUIT_PROVEEDOR,
        };
    }

    /**
     * @return list<string>
     */
    private function columnas(string $entidad): array
    {
        $tabla = $entidad === MayorPlanoCuentaEmisorSupport::ENTIDAD_CLIENTE ? 'cliente' : 'proveedor';
        $columnas = ['id', 'codigo', 'nombre'];
        if (Schema::hasColumn($tabla, 'empresa_id')) {
            $columnas[] = 'empresa_id';
        }
        $cuit = $this->columnaCuit($entidad);
        if ($cuit !== '' && Schema::hasColumn($tabla, $cuit)) {
            $columnas[] = $cuit;
        }

        return $columnas;
    }
}

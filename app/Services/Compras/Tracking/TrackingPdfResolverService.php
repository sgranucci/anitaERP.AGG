<?php

namespace App\Services\Compras\Tracking;

use App\ApiAnita;
use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorArchivoPathSupport;
use App\Support\Compras\ComprobanteProveedorArchivoTipos;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use App\Support\Compras\Tracking\TrackingComprobanteFamilia;
use App\Support\Compras\Tracking\TrackingPdfReferencia;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve el PDF de un comprobante recorriendo en cascada las cuatro fuentes
 * que existen hoy, de la más barata a la más costosa:
 *
 *   1. adjunto propio del comprobante,
 *   2. PDF de la precarga IA,
 *   3. convención de nombre bajo Facturas_scan/comprobantes,
 *   4. escaneo histórico del Anita (base_admin.scanfactura).
 *
 * El paso 4 es el que recupera el acervo histórico: hasta ahora el ERP sólo
 * llegaba a esos PDF cuando el escaneo traía número de orden de compra
 * (`iotid`), y la enorme mayoría de las filas tiene `iotid = 0`. Acá se busca
 * por la clave real del comprobante (empresa, proveedor, tipo, letra, sucursal,
 * número), que es la que usa el Anita para indexar.
 *
 * Todas las consultas al puente se hacen por lote: una sola llamada HTTP para
 * la página completa de la grilla, nunca una por fila.
 */
class TrackingPdfResolverService
{
    /** Tope de números por consulta al puente, para no armar un IN gigante. */
    private const MAX_NUMEROS_POR_CONSULTA = 400;

    private const SCAN_DIR_DEFECTO = '/scan/compras/documentos';

    public function __construct(
        private readonly ComprobanteProveedorArchivoPathSupport $archivoPath = new ComprobanteProveedorArchivoPathSupport,
        private readonly PrecargaFacturaScanPathResolver $scanPath = new PrecargaFacturaScanPathResolver,
    ) {}

    /**
     * @param  iterable<Comprobante_Proveedor>  $comprobantes
     * @return array<int, TrackingPdfReferencia> indexado por comprobante_proveedor_id
     */
    public function resolverLote(iterable $comprobantes): array
    {
        // Tiene que ser una colección Eloquent y no la genérica: el precargado
        // de relaciones que viene abajo sólo existe en la primera.
        $comprobantes = $comprobantes instanceof EloquentCollection
            ? $comprobantes
            : new EloquentCollection(
                is_array($comprobantes) ? $comprobantes : iterator_to_array($comprobantes)
            );

        if ($comprobantes->isEmpty()) {
            return [];
        }

        $comprobantes->loadMissing([
            'proveedores',
            'tipotransaccion_compras',
            'empresas',
            'comprobante_proveedor_archivos',
            'precarga_comprobante_proveedores',
        ]);

        $resueltos = [];
        $pendientes = new Collection;

        foreach ($comprobantes as $comprobante) {
            $referencia = $this->resolverLocal($comprobante);
            if ($referencia !== null) {
                $resueltos[(int) $comprobante->id] = $referencia;

                continue;
            }
            $pendientes->push($comprobante);
        }

        foreach ($this->resolverEnAnita($pendientes) as $id => $referencia) {
            $resueltos[$id] = $referencia;
        }

        return $resueltos;
    }

    public function resolver(Comprobante_Proveedor $comprobante): ?TrackingPdfReferencia
    {
        return $this->resolverLote([$comprobante])[(int) $comprobante->id] ?? null;
    }

    /**
     * Ruta absoluta del PDF de un documento escaneado del Anita.
     */
    public function rutaDocumentoAnita(int $documentoId): ?string
    {
        if ($documentoId <= 0) {
            return null;
        }

        $nombre = sprintf('docu_%010d.pdf', $documentoId);
        foreach ($this->directoriosScanAnita() as $dir) {
            $ruta = $dir.'/'.$nombre;
            if (is_readable($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    /**
     * Fuentes 1 a 3: todo lo que se resuelve sin salir del ERP ni del montaje.
     */
    private function resolverLocal(Comprobante_Proveedor $comprobante): ?TrackingPdfReferencia
    {
        $archivo = $comprobante->comprobante_proveedor_archivos
            ->sortBy(fn ($a) => $a->tipo === ComprobanteProveedorArchivoTipos::ORIGEN_IA ? 0 : 1)
            ->first(fn ($a) => trim((string) $a->ruta_externa) !== '');

        if ($archivo !== null) {
            $ruta = $this->archivoPath->absolutePathDesdeStorageReference($archivo->ruta_externa);
            if ($ruta !== null) {
                return TrackingPdfReferencia::adjunto($ruta, (int) $archivo->id);
            }
        }

        $rutaPrecarga = $comprobante->precarga_comprobante_proveedores?->rutaalmacenamiento;
        if (trim((string) $rutaPrecarga) !== '') {
            $ruta = $this->scanPath->resolve($rutaPrecarga);
            if ($ruta !== null) {
                return TrackingPdfReferencia::precarga($ruta);
            }
        }

        $ruta = $this->archivoPath->absolutePathDesdeComprobante($comprobante);

        return $ruta !== null ? TrackingPdfReferencia::convencion($ruta) : null;
    }

    /**
     * Fuente 4: una sola consulta al puente para todo el lote pendiente.
     *
     * @param  Collection<int, Comprobante_Proveedor>  $comprobantes
     * @return array<int, TrackingPdfReferencia>
     */
    private function resolverEnAnita(Collection $comprobantes): array
    {
        if ($comprobantes->isEmpty()) {
            return [];
        }

        $porClave = [];
        $numeros = [];
        foreach ($comprobantes as $comprobante) {
            $clave = $this->claveScan($comprobante);
            if ($clave === null) {
                continue;
            }
            // Si dos comprobantes comparten clave el escaneo es el mismo documento.
            $porClave[$clave][] = (int) $comprobante->id;
            $numeros[] = (int) $comprobante->numerocomprobante;
        }

        if ($porClave === []) {
            return [];
        }

        $resueltos = [];
        $numeros = array_values(array_unique(array_filter($numeros, static fn (int $n) => $n > 0)));

        foreach (array_chunk($numeros, self::MAX_NUMEROS_POR_CONSULTA) as $chunk) {
            foreach ($this->consultarScanFactura($chunk) as $fila) {
                $documentoId = (int) ($fila['idocumentoid'] ?? 0);
                $clave = $this->claveScanDesdeFila($fila);
                if ($documentoId <= 0 || ! isset($porClave[$clave])) {
                    continue;
                }

                $ruta = $this->rutaDocumentoAnita($documentoId);
                if ($ruta === null) {
                    continue;
                }

                $fechaScan = $this->fechaScan($fila['ifecha'] ?? null);

                foreach ($porClave[$clave] as $comprobanteId) {
                    // La consulta viene ordenada por documento descendente:
                    // se queda el escaneo más reciente de la clave.
                    $resueltos[$comprobanteId] ??= TrackingPdfReferencia::anita($ruta, $documentoId, $fechaScan);
                }
            }
        }

        return $resueltos;
    }

    /**
     * `scanfactura.ifecha` viene como YYYYMMDD (o con separadores) en el puente.
     */
    private function fechaScan(mixed $ifecha): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $ifecha) ?? '';
        if (strlen($digitos) !== 8) {
            return null;
        }

        $fecha = substr($digitos, 0, 4).'-'.substr($digitos, 4, 2).'-'.substr($digitos, 6, 2);

        return strtotime($fecha) !== false ? $fecha : null;
    }

    private function claveScan(Comprobante_Proveedor $comprobante): ?string
    {
        $empresa = $this->empresaAnitaId($comprobante);
        $proveedor = $this->codigoProveedorAnita($comprobante);
        $numero = (int) $comprobante->numerocomprobante;

        if ($empresa <= 0 || $proveedor === '' || $numero <= 0) {
            return null;
        }

        $ctipo = TrackingComprobanteFamilia::ctipoScan(
            $comprobante->tipotransaccion_compras?->codigoafip,
            $comprobante->tipotransaccion_compras?->abreviatura,
        );

        if ($ctipo === '') {
            return null;
        }

        return $this->armarClave(
            $empresa,
            $proveedor,
            $ctipo,
            (string) $comprobante->letra,
            (int) $comprobante->sucursal,
            $numero,
        );
    }

    /** @param array<string, mixed> $fila */
    private function claveScanDesdeFila(array $fila): string
    {
        return $this->armarClave(
            (int) ($fila['iempresaid'] ?? 0),
            (string) ($fila['cproveedor'] ?? ''),
            (string) ($fila['ctipo'] ?? ''),
            (string) ($fila['cletra'] ?? ''),
            (int) ($fila['isucursal'] ?? 0),
            (int) ($fila['inumero'] ?? 0),
        );
    }

    private function armarClave(
        int $empresa,
        string $proveedor,
        string $ctipo,
        string $letra,
        int $sucursal,
        int $numero,
    ): string {
        return implode('|', [
            $empresa,
            str_pad(trim($proveedor), 6, '0', STR_PAD_LEFT),
            str_pad(trim($ctipo), 2, '0', STR_PAD_LEFT),
            strtoupper(trim($letra)),
            $sucursal,
            $numero,
        ]);
    }

    private function codigoProveedorAnita(Comprobante_Proveedor $comprobante): string
    {
        $codigo = (int) ($comprobante->proveedores?->codigo ?? 0);

        return $codigo > 0 ? str_pad((string) $codigo, 6, '0', STR_PAD_LEFT) : '';
    }

    /**
     * El Anita numera las empresas del 1 al 9; `empresa.codigo` guarda ese número.
     */
    private function empresaAnitaId(Comprobante_Proveedor $comprobante): int
    {
        $codigo = (int) ($comprobante->empresas->codigo ?? 0);
        if ($codigo > 0 && $codigo < 10) {
            return $codigo;
        }

        return (int) ($comprobante->empresa_id ?? 0);
    }

    /**
     * @param  list<int>  $numeros
     * @return list<array<string, mixed>>
     */
    private function consultarScanFactura(array $numeros): array
    {
        if ($numeros === []) {
            return [];
        }

        try {
            $raw = (new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => 'base_admin',
                'tabla' => 'scanfactura',
                'campos' => 'iempresaid, cproveedor, ctipo, cletra, isucursal, inumero, idocumentoid, ifecha',
                'whereArmado' => ' WHERE inumero IN ('.implode(',', $numeros).') AND idocumentoid > 0',
                'orderBy' => 'idocumentoid DESC',
            ]);
        } catch (\Throwable $e) {
            Log::warning('tracking_facturas.scanfactura', ['error' => $e->getMessage()]);

            return [];
        }

        $filas = [];
        foreach (ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw)) as $fila) {
            $filas[] = (array) $fila;
        }

        return $filas;
    }

    /**
     * @return list<string>
     */
    private function directoriosScanAnita(): array
    {
        $configurado = rtrim(
            (string) config('comprobante_proveedor_pdf_ia.corpus.scan_legacy_dir', self::SCAN_DIR_DEFECTO),
            '/'
        );

        return array_values(array_unique(array_filter([
            $configurado !== '' ? $configurado : null,
            self::SCAN_DIR_DEFECTO,
        ])));
    }
}

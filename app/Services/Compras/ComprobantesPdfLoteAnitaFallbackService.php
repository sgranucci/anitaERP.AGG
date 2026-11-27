<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Empresa;
use App\Services\Compras\Tracking\TrackingPdfResolverService;
use App\Support\Compras\AnitaCompraClienteSupport;
use App\Support\Compras\ComprobantesPdfLoteFila;
use App\Support\Compras\Tracking\TrackingComprobanteFamilia;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Respaldo del reporte «Comprobantes en PDF»: las facturas que Anita tiene y el ERP no se leen de la
 * tabla compra (solo lectura) y el PDF se busca en el escaneo histórico (base_admin.scanfactura).
 * Una factura ya presente en el ERP (mismo nro. interno de Anita, o mismo proveedor + tipo + letra +
 * sucursal + número) no se repite, tenga o no cargado el nº de cliente.
 */
class ComprobantesPdfLoteAnitaFallbackService
{
    private const CACHE_SEGUNDOS = 120;

    /** @var array<string, array{codigoafip: string, abreviatura: string}>|null */
    private ?array $tiposPorAbreviatura = null;

    public function __construct(
        private readonly TrackingPdfResolverService $pdfResolver,
    ) {}

    /**
     * Facturas de Anita para los proveedores indicados (ids ERP) dentro de los filtros del reporte.
     *
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $proveedorIds
     * @return Collection<int, ComprobantesPdfLoteFila>
     */
    public function filasFacturas(array $filtros, array $proveedorIds): Collection
    {
        $filas = collect();
        if ($proveedorIds === []) {
            return $filas;
        }

        $nroCliente = trim((string) ($filtros['nro_cliente'] ?? ''));
        if ($nroCliente !== '' && ! AnitaCompraClienteSupport::disponible()) {
            // Sin com_cliente no se puede filtrar: mejor nada que listar todo.
            return $filas;
        }

        $proveedores = [];
        foreach (Proveedor::query()->whereIn('id', $proveedorIds)->get(['id', 'codigo', 'nombre']) as $prov) {
            $codigo = trim((string) $prov->codigo);
            if ($codigo === '') {
                continue;
            }
            $proveedores[str_pad($codigo, 6, '0', STR_PAD_LEFT)] = $prov;
        }
        if ($proveedores === []) {
            return $filas;
        }

        $compras = $this->leerCompras($filtros, array_keys($proveedores));
        $enErp = $this->clavesYaEnErp($filtros, $proveedores, $compras);

        foreach ($compras as $compra) {
            $prov = $proveedores[str_pad(trim((string) ($compra['com_proveedor'] ?? '')), 6, '0', STR_PAD_LEFT)] ?? null;
            if ($prov !== null && $this->estaEnErp($compra, (int) $prov->id, $enErp)) {
                continue;
            }
            $cliente = AnitaCompraClienteSupport::extraerNroCliente($compra['com_cliente'] ?? '');
            if ($nroCliente !== '' && mb_stripos($cliente, $nroCliente) === false) {
                continue;
            }
            $prov = $proveedores[str_pad(trim((string) ($compra['com_proveedor'] ?? '')), 6, '0', STR_PAD_LEFT)] ?? null;
            if ($prov === null) {
                continue;
            }
            $filas->push($this->fila($compra, $prov, $cliente));
        }

        return $filas;
    }

    /**
     * Facturas del ERP (misma empresa y proveedores, sin filtrar por nº de cliente) contra las que se deduplica.
     *
     * @param  array<string, mixed>  $filtros
     * @param  array<string, Proveedor>  $proveedores
     * @param  list<array<string, mixed>>  $compras
     * @return array{internos: array<string, true>, numeros: array<string, true>}
     */
    private function clavesYaEnErp(array $filtros, array $proveedores, array $compras): array
    {
        $out = ['internos' => [], 'numeros' => []];
        if ($compras === []) {
            return $out;
        }
        $ids = array_values(array_map(static fn (Proveedor $p) => (int) $p->id, $proveedores));
        $empresaId = (int) $filtros['empresa_id'];

        $internos = [];
        foreach ($compras as $c) {
            $n = (int) ($c['com_nro_interno'] ?? 0);
            if ($n > 0) {
                $internos[$n] = $n;
            }
        }
        foreach (array_chunk(array_values($internos), 1000) as $chunk) {
            $erp = Comprobante_Proveedor::query()
                ->where('empresa_id', $empresaId)
                ->whereIn('anita_nro_interno', $chunk)
                ->pluck('anita_nro_interno');
            foreach ($erp as $n) {
                $out['internos'][(string) (int) $n] = true;
            }
        }

        $filas = Comprobante_Proveedor::query()
            ->with('tipotransaccion_compras:id,abreviatura')
            ->where('empresa_id', $empresaId)
            ->whereIn('proveedor_id', $ids)
            ->whereDate('fechacomprobante', '>=', (string) $filtros['fecha_desde'])
            ->whereDate('fechacomprobante', '<=', (string) $filtros['fecha_hasta'])
            ->get(['id', 'proveedor_id', 'tipotransaccion_compra_id', 'letra', 'sucursal', 'numerocomprobante']);
        foreach ($filas as $f) {
            $tipo = strtoupper(substr(trim((string) ($f->tipotransaccion_compras->abreviatura ?? '')), 0, 3));
            $out['numeros'][$this->claveNumero((int) $f->proveedor_id, $tipo, (string) $f->letra, (int) $f->sucursal, (int) $f->numerocomprobante)] = true;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $compra
     * @param  array{internos: array<string, true>, numeros: array<string, true>}  $enErp
     */
    private function estaEnErp(array $compra, int $proveedorId, array $enErp): bool
    {
        if (isset($enErp['internos'][(string) (int) ($compra['com_nro_interno'] ?? 0)])) {
            return true;
        }

        return isset($enErp['numeros'][$this->claveNumero(
            $proveedorId,
            strtoupper(substr(trim((string) ($compra['com_tipo'] ?? '')), 0, 3)),
            (string) ($compra['com_letra'] ?? ''),
            (int) ($compra['com_sucursal'] ?? 0),
            (int) ($compra['com_nro'] ?? 0),
        )]);
    }

    private function claveNumero(int $proveedorId, string $tipo, string $letra, int $sucursal, int $numero): string
    {
        return $proveedorId.'|'.$tipo.'|'.strtoupper(substr(trim($letra), 0, 1)).'|'.$sucursal.'|'.$numero;
    }

    /**
     * Completa tienePdf / pdfUrl / ruta de las filas de Anita buscando el escaneo (una consulta por tanda).
     *
     * @param  Collection<int, ComprobantesPdfLoteFila>  $filas
     */
    public function completarPdf(Collection $filas): void
    {
        $claves = [];
        foreach ($filas as $indice => $fila) {
            if (! $fila->esAnita()) {
                continue;
            }
            $a = $fila->anita;
            $tipo = $this->tipoPorAbreviatura((string) ($a['tipo'] ?? ''));
            $claves[$indice] = [
                'empresa' => (int) ($a['empresa'] ?? 0),
                'proveedor' => (string) ($a['proveedor'] ?? ''),
                'ctipo' => TrackingComprobanteFamilia::ctipoScan($tipo['codigoafip'], $tipo['abreviatura']),
                'letra' => (string) ($a['letra'] ?? ''),
                'sucursal' => (int) ($a['sucursal'] ?? 0),
                'numero' => (int) ($a['numero'] ?? 0),
            ];
        }
        if ($claves === []) {
            return;
        }

        $resueltos = $this->pdfResolver->resolverClavesScan($claves);
        foreach ($claves as $indice => $_) {
            $fila = $filas[$indice];
            $ref = $resueltos[$indice] ?? null;
            $fila->tienePdf = $ref !== null;
            $fila->pdfEtiqueta = $ref !== null ? 'Escaneo Anita' : 'Sin PDF';
            if ($ref !== null) {
                $fila->anita['ruta'] = $ref['ruta'];
                $fila->anita['documento_id'] = $ref['documento_id'];
                $fila->pdfUrl = route('comprobantes_pdf_lote_anita_pdf', [
                    'documento' => $ref['documento_id'],
                    'inline' => 1,
                ]);
            }
        }
    }

    /**
     * Empresa ERP a la que pertenece un documento escaneado (para validar permisos antes de servirlo).
     */
    public function empresaIdErpDeDocumento(int $documentoId): ?int
    {
        if ($documentoId <= 0) {
            return null;
        }

        try {
            $filas = ApiAnita::decodificarListaFilas((new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => 'base_admin',
                'tabla' => 'scanfactura',
                'campos' => 'iempresaid, idocumentoid',
                'whereArmado' => ' WHERE idocumentoid = '.$documentoId,
            ]));
        } catch (Throwable) {
            return null;
        }

        $codigo = (int) (((array) ($filas[0] ?? []))['iempresaid'] ?? 0);
        if ($codigo <= 0) {
            return null;
        }

        $id = Empresa::query()->where('codigo', (string) $codigo)->value('id');
        if ($id) {
            return (int) $id;
        }

        return Empresa::query()->whereKey($codigo)->exists() ? $codigo : null;
    }

    public function rutaPdf(int $documentoId): ?string
    {
        return $this->pdfResolver->rutaDocumentoAnita($documentoId);
    }

    /**
     * @param  list<string>  $codigosAnita  proveedor con 6 dígitos
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function leerCompras(array $filtros, array $codigosAnita): array
    {
        $desde = (int) str_replace('-', '', (string) $filtros['fecha_desde']);
        $hasta = (int) str_replace('-', '', (string) $filtros['fecha_hasta']);
        $empresaCodigo = $this->empresaCodigoAnita((int) $filtros['empresa_id']);
        $filtraEmpresa = $empresaCodigo !== null && ! EntornoEmpresaSupport::esCrown();

        $lista = implode(',', array_map(
            static fn (string $c) => "'".str_replace("'", '', $c)."'",
            $codigosAnita
        ));
        $where = ' WHERE com_proveedor IN ('.$lista.')'
            .' AND com_fecha >= '.$desde
            .' AND com_fecha <= '.$hasta;
        if ($filtraEmpresa) {
            $where .= ' AND com_empresa = '.(int) $empresaCodigo;
        }

        // Textos al final (corrimiento por | en el bridge): com_cliente y nombre del proveedor.
        $campos = implode(', ', array_filter([
            'com_empresa',
            'com_proveedor',
            'com_tipo',
            'com_letra',
            'com_sucursal',
            'com_nro',
            'com_fecha',
            'com_nro_interno',
            'com_monto',
            AnitaCompraClienteSupport::disponible() ? 'com_cliente' : null,
        ]));

        $cacheKey = 'comprobantes_pdf_lote.anita.'.md5($campos.$where);

        return Cache::remember($cacheKey, self::CACHE_SEGUNDOS, function () use ($campos, $where): array {
            $raw = (new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'compra',
                'campos' => $campos,
                'whereArmado' => $where,
                'orderBy' => 'com_fecha, com_nro_interno',
            ]);

            $out = [];
            foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
                $out[] = (array) $fila;
            }

            return $out;
        });
    }

    /**
     * @param  array<string, mixed>  $compra
     */
    private function fila(array $compra, Proveedor $prov, string $cliente): ComprobantesPdfLoteFila
    {
        $ymd = preg_replace('/\D/', '', (string) ($compra['com_fecha'] ?? '')) ?? '';
        [$fecha, $iso] = ['', ''];
        if (strlen($ymd) === 8) {
            try {
                $c = Carbon::createFromFormat('Ymd', $ymd);
                $fecha = $c->format('d/m/Y');
                $iso = $c->format('Y-m-d');
            } catch (Throwable) {
                $fecha = $ymd;
                $iso = $ymd;
            }
        }

        $empresa = (int) ($compra['com_empresa'] ?? 0);
        $nroInterno = (int) ($compra['com_nro_interno'] ?? 0);
        $letra = strtoupper(substr(trim((string) ($compra['com_letra'] ?? '')), 0, 1));
        $sucursal = (int) ($compra['com_sucursal'] ?? 0);
        $numero = (int) ($compra['com_nro'] ?? 0);
        $tipo = strtoupper(substr(trim((string) ($compra['com_tipo'] ?? '')), 0, 3));
        $codigo = (string) $prov->codigo;

        return new ComprobantesPdfLoteFila(
            clave: 'anita:'.$empresa.'-'.$nroInterno,
            tipo: 'anita',
            id: $nroInterno,
            fecha: $fecha,
            fechaIso: $iso,
            proveedorCodigo: $codigo,
            proveedor: trim($codigo.' '.(string) $prov->nombre),
            tipoEtiqueta: $tipo !== '' ? $tipo : 'FAC',
            numero: trim($letra.' '.str_pad((string) $sucursal, 4, '0', STR_PAD_LEFT).'-'.str_pad((string) $numero, 8, '0', STR_PAD_LEFT)),
            total: (float) ($compra['com_monto'] ?? 0),
            tienePdf: false,
            pdfEtiqueta: 'Sin PDF',
            editarUrl: null,
            pdfUrl: null,
            servicioCliente: $cliente,
            origen: ComprobantesPdfLoteFila::ORIGEN_ANITA,
            anita: [
                'empresa' => $empresa,
                'proveedor' => str_pad(trim((string) ($compra['com_proveedor'] ?? '')), 6, '0', STR_PAD_LEFT),
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => $sucursal,
                'numero' => $numero,
                'nro_interno' => $nroInterno,
            ],
        );
    }

    private function empresaCodigoAnita(int $empresaId): ?int
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

    /**
     * @return array{codigoafip: string, abreviatura: string}
     */
    private function tipoPorAbreviatura(string $abreviatura): array
    {
        if ($this->tiposPorAbreviatura === null) {
            $this->tiposPorAbreviatura = [];
            foreach (Tipotransaccion_Compra::query()->get(['abreviatura', 'codigoafip']) as $tipo) {
                $abrev = strtoupper(substr(trim((string) $tipo->abreviatura), 0, 3));
                if ($abrev !== '' && ! isset($this->tiposPorAbreviatura[$abrev])) {
                    $this->tiposPorAbreviatura[$abrev] = [
                        'codigoafip' => (string) $tipo->codigoafip,
                        'abreviatura' => (string) $tipo->abreviatura,
                    ];
                }
            }
        }

        $abrev = strtoupper(substr(trim($abreviatura), 0, 3));

        return $this->tiposPorAbreviatura[$abrev] ?? ['codigoafip' => '', 'abreviatura' => $abreviatura];
    }
}

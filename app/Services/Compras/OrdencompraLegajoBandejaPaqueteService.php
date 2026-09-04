<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Compras\ComprobanteProveedorRetornoLegajoSupport;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Compras\OrdencompraLegajoAnitaScanFacturaSupport;
use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Factura + COM del legajo (la OC) y asignación persistida para que CxP cargue.
 */
class OrdencompraLegajoBandejaPaqueteService
{
    public function __construct(
        private PrecargaFacturaScanPathResolver $scanPathResolver,
        private OrdencompraLegajoFacturaPdfService $facturaPdfService,
    ) {
    }

    public function encontrarOcVisible(int $id): Ordencompra
    {
        $query = Ordencompra::query()->whereKey($id);
        app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($query, 'ordencompra.empresa_id');
        OrdencompraSectorVisibilidadSupport::aplicarFiltro($query);
        $oc = $query->with('empresas:id,codigo,nombre')->first();
        if (! $oc) {
            abort(404, 'Orden de compra no encontrada.');
        }

        return $oc;
    }

    /**
     * @return array<string, mixed>
     */
    public function paquete(Ordencompra $oc): array
    {
        $oc->loadMissing('empresas:id,codigo,nombre');
        $this->materializarPdfsScanAnita($oc);
        $facturas = $this->facturasDelLegajo($oc);
        $facturas = array_merge($facturas, $this->scansAnitaSinPrecarga($oc, $facturas));
        $coms = $this->comsDelLegajo($oc);
        $precargaIds = [];
        foreach ($facturas as $f) {
            if (($f['origen'] ?? 'precarga') === 'precarga') {
                $precargaIds[] = (int) $f['id'];
            }
        }
        $asignadas = $this->asignacionesPorPrecarga($precargaIds);
        $comprobantes = $this->comprobantesDelLegajo($oc, $precargaIds);
        $pagos = $this->pagosDeComprobantes(array_map(static fn (array $c) => (int) $c['id'], $comprobantes));
        $primeraPrecargaId = $precargaIds[0] ?? 0;
        $tieneComprobante = $comprobantes !== [];

        return [
            'ordencompra_id' => (int) $oc->id,
            'numero' => (string) $oc->numeroordencompra,
            'facturas' => $facturas,
            'coms' => $coms,
            'asignadas' => $asignadas,
            'comprobantes' => $comprobantes,
            'pagos' => $pagos,
            'url_cargar_cxp' => (! $tieneComprobante
                && OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar((int) ($oc->sector_legajocompra_id ?? 0)))
                ? route('crear_comprobante_proveedor', array_filter([
                    'origen' => ComprobanteProveedorRetornoLegajoSupport::ORIGEN_BANDEJA,
                    'ordencompra_id' => (int) $oc->id,
                    'precarga_id' => $primeraPrecargaId > 0 ? $primeraPrecargaId : null,
                ]))
                : null,
            'url_oc' => can('editar-ordencompra', false)
                ? route('editar_ordencompra', ['id' => (int) $oc->id])
                : route('solo_consulta_ordencompra', ['id' => (int) $oc->id]),
        ];
    }

    /**
     * @param  list<int>  $recepcionIds
     */
    public function asignar(Ordencompra $oc, int|string $facturaRef, array $recepcionIds): void
    {
        $precargaId = (int) $this->resolverPrecargaParaAsignacion($oc, $facturaRef)->id;
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $recepcionIds),
            static fn (int $id) => $id > 0
        )));

        if ($ids !== []) {
            $validas = Recepcion_Proveedor::query()
                ->where('ordencompra_id', $oc->id)
                ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
                ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
            $faltan = array_values(array_diff($ids, $validas));
            if ($faltan !== []) {
                throw ValidationException::withMessages([
                    'recepcion_ids' => 'Hay COM que no pertenecen a esta OC o no están confirmadas.',
                ]);
            }
        }

        DB::transaction(function () use ($precargaId, $ids) {
            Precarga_Comprobante_Proveedor_Recepcion::query()
                ->where('precarga_comprobante_proveedor_id', $precargaId)
                ->delete();
            foreach ($ids as $orden => $recepcionId) {
                Precarga_Comprobante_Proveedor_Recepcion::query()->create([
                    'precarga_comprobante_proveedor_id' => $precargaId,
                    'recepcion_proveedor_id' => $recepcionId,
                    'orden' => $orden + 1,
                ]);
            }
        });
    }

    public function assertPrecargaDelLegajo(Ordencompra $oc, int $precargaId): Precarga_Comprobante_Proveedor
    {
        if ($precargaId <= 0) {
            abort(404, 'La factura no pertenece a este legajo.');
        }
        $precarga = Precarga_Comprobante_Proveedor::query()->find($precargaId);
        if (! $precarga || ! $this->precargaPerteneceAlLegajo($oc, $precarga)) {
            abort(404, 'La factura no pertenece a este legajo.');
        }

        return $precarga;
    }

    public function assertComDelLegajo(Ordencompra $oc, int $recepcionId): Recepcion_Proveedor
    {
        $com = Recepcion_Proveedor::query()
            ->whereKey($recepcionId)
            ->where('ordencompra_id', $oc->id)
            ->first();
        if (! $com) {
            abort(404, 'La COM no pertenece a este legajo.');
        }

        return $com;
    }

    public function rutaFacturaPdf(Precarga_Comprobante_Proveedor $precarga): ?string
    {
        $ruta = trim((string) $precarga->rutaalmacenamiento);
        if ($ruta === '') {
            return null;
        }

        return $this->scanPathResolver->resolve($ruta);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function facturasDelLegajo(Ordencompra $oc): array
    {
        $numero = trim((string) $oc->numeroordencompra);
        $empresaId = (int) $oc->empresa_id;
        if ($numero === '' || $empresaId <= 0) {
            return [];
        }

        $rows = Precarga_Comprobante_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('numeroordencompra', $numero)
            ->whereNotNull('rutaalmacenamiento')
            ->where('rutaalmacenamiento', '!=', '')
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->orderByDesc('id')
            ->get([
                'id', 'letra', 'sucursal', 'numerocomprobante', 'fechafactura',
                'total', 'rutaalmacenamiento', 'estado', 'origen_entrada',
            ]);

        $out = [];
        foreach ($rows as $pre) {
            $id = (int) $pre->id;
            $out[] = [
                'id' => $id,
                'origen' => 'precarga',
                'origen_label' => PrecargaComprobanteOrigenEntrada::etiqueta($pre->origen_entrada ?? null),
                'etiqueta' => $this->etiquetaFactura($pre),
                'fecha' => $pre->fechafactura ? $pre->fechafactura->format('d/m/Y') : '',
                'total' => $pre->total !== null ? (float) $pre->total : null,
                'estado' => (string) ($pre->estado ?? ''),
                'url_pdf' => route('ordencompra_legajo_bandeja_factura_pdf', [
                    'id' => (int) $oc->id,
                    'precarga' => $id,
                    'inline' => 1,
                ]),
                'url_cargar_cxp' => route('crear_comprobante_proveedor', ['precarga_id' => $id]),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function comsDelLegajo(Ordencompra $oc): array
    {
        $rows = Recepcion_Proveedor::query()
            ->where('ordencompra_id', $oc->id)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['id', 'numerorecepcion', 'fecha', 'estado', 'anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro']);

        $out = [];
        foreach ($rows as $rec) {
            $id = (int) $rec->id;
            $out[] = [
                'id' => $id,
                'documento' => $this->documentoCom($rec),
                'fecha' => $rec->fecha ? $rec->fecha->format('d/m/Y') : '',
                'estado' => (string) $rec->estado,
                'confirmada' => $rec->estado === Recepcion_Proveedor::ESTADO_CONFIRMADA,
                'url_pdf' => route('ordencompra_legajo_bandeja_com_pdf', [
                    'id' => (int) $oc->id,
                    'recepcion' => $id,
                    'inline' => 1,
                ]),
                'url_editar' => route('editar_recepcion_proveedor', ['id' => $id]),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $precargaIds
     * @return array<int, list<int>>
     */
    public function asignacionesPorPrecarga(array $precargaIds): array
    {
        if ($precargaIds === [] || ! Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            return [];
        }

        $out = [];
        $rows = Precarga_Comprobante_Proveedor_Recepcion::query()
            ->whereIn('precarga_comprobante_proveedor_id', $precargaIds)
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['precarga_comprobante_proveedor_id', 'recepcion_proveedor_id']);
        foreach ($rows as $row) {
            $preId = (int) $row->precarga_comprobante_proveedor_id;
            $out[$preId] ??= [];
            $out[$preId][] = (int) $row->recepcion_proveedor_id;
        }

        return $out;
    }

    /**
     * @param  list<int>  $precargaIds
     * @return list<array<string, mixed>>
     */
    private function comprobantesDelLegajo(Ordencompra $oc, array $precargaIds): array
    {
        $query = Comprobante_Proveedor::query()
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->where(function ($q) use ($oc, $precargaIds) {
                $q->where('ordencompra_id', $oc->id);
                if ($precargaIds !== []) {
                    $q->orWhereIn('precarga_comprobante_proveedor_id', $precargaIds);
                }
            })
            ->orderByDesc('id');

        $out = [];
        foreach ($query->get(['id', 'letra', 'sucursal', 'numerocomprobante', 'total', 'estado']) as $cp) {
            $out[] = [
                'id' => (int) $cp->id,
                'etiqueta' => trim(sprintf(
                    '%s %04d-%08d',
                    $cp->letra ?: 'FC',
                    (int) $cp->sucursal,
                    (int) $cp->numerocomprobante
                )),
                'total' => $cp->total !== null ? (float) $cp->total : null,
                'estado' => (string) ($cp->estado ?? ''),
                'url' => route('editar_comprobante_proveedor', ['id' => (int) $cp->id]),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $comprobanteIds
     * @return list<array<string, mixed>>
     */
    private function pagosDeComprobantes(array $comprobanteIds): array
    {
        if ($comprobanteIds === []) {
            return [];
        }
        $rows = Proveedor_Cuentacorriente::query()
            ->with(['pagoproveedores:id,tipocomprobante,letra,sucursal,numerotransaccion'])
            ->whereIn('comprobante_proveedor_id', $comprobanteIds)
            ->where('pagoproveedor_id', '>', 0)
            ->orderByDesc('id')
            ->get();
        $out = [];
        $vistos = [];
        foreach ($rows as $row) {
            $pagoId = (int) $row->pagoproveedor_id;
            if ($pagoId <= 0 || isset($vistos[$pagoId])) {
                continue;
            }
            $vistos[$pagoId] = true;
            $pago = $row->pagoproveedores;
            $out[] = [
                'id' => $pagoId,
                'etiqueta' => $pago ? $pago->etiquetaComprobante() : ('OP #'.$pagoId),
                'url' => route('editar_pagoproveedor', ['id' => $pagoId]),
            ];
        }

        return $out;
    }

    public function resolverPrecargaParaAsignacion(Ordencompra $oc, int|string $facturaRef): Precarga_Comprobante_Proveedor
    {
        $ref = trim((string) $facturaRef);
        if (preg_match('/^anita-(\d+)$/i', $ref, $m)) {
            return $this->precargaDesdeFacturaAnita($oc, (int) $m[1]);
        }

        return $this->assertPrecargaDelLegajo($oc, (int) $ref);
    }

    private function precargaDesdeFacturaAnita(Ordencompra $oc, int $documentoId): Precarga_Comprobante_Proveedor
    {
        $fila = OrdencompraLegajoAnitaScanFacturaSupport::filaDeOc($oc, $documentoId);
        if ($fila === null) {
            abort(404, 'La factura no pertenece a este legajo.');
        }

        $existente = $this->precargaDelLegajoCompatibleConScan($oc, $fila);
        if ($existente) {
            return $this->asegurarPdfScanEnPrecarga($oc, $existente, $documentoId, $fila);
        }

        $empresaId = (int) $oc->empresa_id;
        $proveedorId = (int) $oc->proveedor_id;
        $tipoId = OrdencompraEnvioCuentasAPagarGateSupport::tipotransaccionCompraIdParaOrdencompra($oc);
        if ($empresaId <= 0 || $proveedorId <= 0 || $tipoId <= 0) {
            throw ValidationException::withMessages([
                'precarga_id' => 'No se puede crear la precarga del legajo para asignar la COM (faltan empresa, proveedor o tipo de factura).',
            ]);
        }

        $letra = strtoupper(trim((string) ($fila['cletra'] ?? ''))) ?: 'A';
        $sucursal = (int) ($fila['isucursal'] ?? 0);
        $numero = (int) ($fila['inumero'] ?? 0);
        $fecha = $this->fechaYmdDesdeScanAnita((string) ($fila['ifecha'] ?? ''));

        if ($numero > 0) {
            $dup = ComprobanteProveedorUnicidadSupport::findDuplicadoPrecarga(
                $empresaId,
                $tipoId,
                $letra,
                $sucursal,
                $numero,
                ComprobanteProveedorUnicidadSupport::resolverCuitDigitos($proveedorId, null),
            );
            if ($dup && $this->precargaPerteneceAlLegajo($oc, $dup)) {
                return $this->asegurarPdfScanEnPrecarga($oc, $dup, $documentoId, $fila);
            }
            if ($dup) {
                throw ValidationException::withMessages([
                    'precarga_id' => 'Ya existe una precarga con esa factura; no se puede asignar la COM desde este scan Anita.',
                ]);
            }
        }

        $monedaId = $this->monedaIdParaPrecarga($oc);
        $moneda = Moneda::query()->whereKey($monedaId)->first();

        $precarga = Precarga_Comprobante_Proveedor::query()->create([
            'empresa_id' => $empresaId,
            'proveedor_id' => $proveedorId,
            'tipotransaccion_compra_id' => $tipoId,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numerocomprobante' => $numero,
            'fechafactura' => $fecha,
            'numeroordencompra' => (string) $oc->numeroordencompra,
            'subtotal' => 0,
            'total' => 0,
            'estado' => 'PENDIENTE',
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::SCAN_ANITA,
            'pararevisar' => 1,
            'moneda' => strtoupper(trim((string) ($moneda->abreviatura ?: $moneda->nombre ?: 'PESOS'))),
            'moneda_id' => $monedaId,
            'cotizacion' => 1,
        ]);

        return $this->asegurarPdfScanEnPrecarga($oc, $precarga, $documentoId, $fila);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function precargaDelLegajoCompatibleConScan(Ordencompra $oc, array $fila): ?Precarga_Comprobante_Proveedor
    {
        $letra = strtoupper(trim((string) ($fila['cletra'] ?? '')));
        $sucursal = (int) ($fila['isucursal'] ?? 0);
        $numero = (int) ($fila['inumero'] ?? 0);

        $candidatas = Precarga_Comprobante_Proveedor::query()
            ->where('empresa_id', (int) $oc->empresa_id)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->orderByDesc('id')
            ->get();

        $delLegajo = $candidatas->filter(
            fn (Precarga_Comprobante_Proveedor $p) => $this->precargaPerteneceAlLegajo($oc, $p)
        );
        if ($delLegajo->isEmpty()) {
            return null;
        }

        if ($numero > 0) {
            $porNumero = $delLegajo->first(function (Precarga_Comprobante_Proveedor $p) use ($letra, $sucursal, $numero) {
                $mismaLetra = $letra === '' || strtoupper(trim((string) $p->letra)) === $letra;

                return $mismaLetra
                    && (int) $p->sucursal === $sucursal
                    && (int) $p->numerocomprobante === $numero;
            });
            if ($porNumero) {
                return $porNumero;
            }
        }

        return OrdencompraEnvioCuentasAPagarGateSupport::precargaDelLegajo($oc)
            ?? $delLegajo->first();
    }

    private function fechaYmdDesdeScanAnita(string $ymd): string
    {
        $ymd = preg_replace('/\D+/', '', $ymd) ?? '';
        if (strlen($ymd) === 8) {
            return substr($ymd, 0, 4).'-'.substr($ymd, 4, 2).'-'.substr($ymd, 6, 2);
        }

        return now()->format('Y-m-d');
    }

    /**
     * @param  list<array<string, mixed>>  $precargas
     * @return list<array<string, mixed>>
     */
    private function scansAnitaSinPrecarga(Ordencompra $oc, array $precargas): array
    {
        $claves = [];
        foreach ($precargas as $pre) {
            $claves[$this->claveFacturaEtiqueta((string) ($pre['etiqueta'] ?? ''))] = true;
        }
        $out = [];
        foreach (OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOc($oc) as $scan) {
            $clave = $this->claveFacturaEtiqueta((string) ($scan['etiqueta'] ?? ''));
            if ($clave !== '' && isset($claves[$clave])) {
                continue;
            }
            $out[] = $scan;
        }

        return $out;
    }

    private function claveFacturaEtiqueta(string $etiqueta): string
    {
        $etiqueta = strtoupper(trim($etiqueta));
        if (preg_match('/([A-Z])\s+(\d{1,5})-(\d{1,8})/', $etiqueta, $m)) {
            return $m[1].'|'.((int) $m[2]).'|'.((int) $m[3]);
        }

        return $etiqueta;
    }

    private function materializarPdfsScanAnita(Ordencompra $oc): void
    {
        foreach (OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOc($oc) as $scan) {
            $docId = (int) ($scan['documento_id'] ?? 0);
            if ($docId <= 0) {
                continue;
            }
            try {
                $this->precargaDesdeFacturaAnita($oc, $docId);
            } catch (\Throwable $e) {
                Log::warning('bandeja.scan_pdf_precarga', [
                    'oc' => (int) $oc->id,
                    'documento' => $docId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function asegurarPdfScanEnPrecarga(
        Ordencompra $oc,
        Precarga_Comprobante_Proveedor $precarga,
        int $documentoId,
        array $fila,
    ): Precarga_Comprobante_Proveedor {
        $precarga = $this->marcarOrigenScanAnitaSiNoEsIa($precarga);
        $ruta = trim((string) ($precarga->rutaalmacenamiento ?? ''));
        if ($ruta !== '' && $this->scanPathResolver->resolve($ruta)) {
            return $precarga;
        }

        $origen = OrdencompraLegajoAnitaScanFacturaSupport::rutaPdf($documentoId);
        $proveedor = Proveedor::query()->find((int) $oc->proveedor_id);
        if ($origen === null || ! $proveedor) {
            throw ValidationException::withMessages([
                'precarga_id' => 'No se encontró el PDF del scan Anita para grabarlo en la carpeta de precargas.',
            ]);
        }

        $tipoId = (int) ($precarga->tipotransaccion_compra_id
            ?: OrdencompraEnvioCuentasAPagarGateSupport::tipotransaccionCompraIdParaOrdencompra($oc));
        $tipoAbrev = (string) (Tipotransaccion_Compra::query()->whereKey($tipoId)->value('abreviatura') ?? 'FAC');
        $fecha = $this->fechaYmdDesdeScanAnita((string) ($fila['ifecha'] ?? ''));
        if ($precarga->fechafactura) {
            $fecha = $precarga->fechafactura->format('Y-m-d');
        }

        try {
            $storage = $this->facturaPdfService->copiarPdfLocalAAlmacenPrecarga(
                $origen,
                $proveedor,
                $fecha,
                $tipoAbrev,
                (string) ($precarga->letra ?: ($fila['cletra'] ?? 'A')),
                (int) ($precarga->sucursal ?? $fila['isucursal'] ?? 0),
                (int) ($precarga->numerocomprobante ?? $fila['inumero'] ?? 0),
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'precarga_id' => $e->getMessage(),
            ]);
        }

        $precarga->rutaalmacenamiento = $storage;
        $precarga->save();

        return $precarga;
    }

    private function monedaIdParaPrecarga(Ordencompra $oc): int
    {
        $oc->loadMissing('ordencompra_articulos');
        $candidatos = [];
        $linea = $oc->ordencompra_articulos->first();
        if ($linea) {
            $candidatos[] = (int) ($linea->moneda_id ?? 0);
        }
        $candidatos[] = (int) ($oc->contrato_moneda_id ?? 0);

        foreach ($candidatos as $id) {
            if ($id > 0 && Moneda::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        $fallback = (int) (Moneda::query()->orderBy('id')->value('id') ?? 0);
        if ($fallback <= 0) {
            throw ValidationException::withMessages([
                'precarga_id' => 'No hay una moneda válida para crear la precarga del scan Anita.',
            ]);
        }

        return $fallback;
    }

    private function marcarOrigenScanAnitaSiNoEsIa(Precarga_Comprobante_Proveedor $precarga): Precarga_Comprobante_Proveedor
    {
        if (PrecargaComprobanteOrigenEntrada::esLecturaIa((string) ($precarga->origen_entrada ?? ''))) {
            return $precarga;
        }
        if ((string) ($precarga->origen_entrada ?? '') === PrecargaComprobanteOrigenEntrada::SCAN_ANITA) {
            return $precarga;
        }
        $precarga->origen_entrada = PrecargaComprobanteOrigenEntrada::SCAN_ANITA;
        $precarga->save();

        return $precarga;
    }

    public function precargaPerteneceAlLegajo(Ordencompra $oc, Precarga_Comprobante_Proveedor $precarga): bool
    {
        if ((int) $precarga->empresa_id !== (int) $oc->empresa_id) {
            return false;
        }

        $a = trim((string) $precarga->numeroordencompra);
        $b = trim((string) $oc->numeroordencompra);
        if ($a === $b) {
            return true;
        }
        $na = preg_replace('/\D+/', '', $a) ?? '';
        $nb = preg_replace('/\D+/', '', $b) ?? '';

        return $na !== '' && $na === $nb;
    }

    private function etiquetaFactura(Precarga_Comprobante_Proveedor $pre): string
    {
        $pre->loadMissing('tipotransaccion_compras:id,abreviatura');
        $abrev = strtoupper(trim((string) ($pre->tipotransaccion_compras->abreviatura ?? '')));
        $letra = trim((string) ($pre->letra ?? ''));
        $suc = (int) ($pre->sucursal ?? 0);
        $nro = (int) ($pre->numerocomprobante ?? 0);
        $numero = ($letra !== '' || $nro > 0)
            ? trim(sprintf('%s %04d-%08d', $letra !== '' ? $letra : 'FC', $suc, $nro))
            : 'Factura #'.$pre->id;
        $base = $abrev !== '' ? $abrev.' '.$numero : $numero;
        if ((string) ($pre->origen_entrada ?? '') === PrecargaComprobanteOrigenEntrada::SCAN_ANITA) {
            return $base.' ('.PrecargaComprobanteOrigenEntrada::etiqueta(PrecargaComprobanteOrigenEntrada::SCAN_ANITA).')';
        }

        return $base;
    }

    private function documentoCom(Recepcion_Proveedor $rec): string
    {
        $nro = $rec->numerorecepcion ?: $rec->id;
        if ($rec->anita_tipo && $rec->anita_sucursal && $rec->anita_nro) {
            return sprintf(
                'COM %s %s %d-%d',
                $rec->anita_tipo,
                $rec->anita_letra ?? '',
                $rec->anita_sucursal,
                $rec->anita_nro
            );
        }

        return 'COM #'.$nro;
    }
}

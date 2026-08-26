<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Compras\OrdencompraLegajoAnitaScanFacturaSupport;
use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Factura + COM del legajo (la OC) y asignación persistida para que CxP cargue.
 */
class OrdencompraLegajoBandejaPaqueteService
{
    public function __construct(
        private PrecargaFacturaScanPathResolver $scanPathResolver,
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
        $facturas = $this->facturasDelLegajo($oc);
        $facturas = array_merge($facturas, OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOc($oc));
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
            'url_cargar_cxp' => ($primeraPrecargaId > 0 && ! $tieneComprobante)
                ? route('crear_comprobante_proveedor', ['precarga_id' => $primeraPrecargaId])
                : null,
            'url_oc' => can('editar-ordencompra', false)
                ? route('editar_ordencompra', ['id' => (int) $oc->id])
                : route('solo_consulta_ordencompra', ['id' => (int) $oc->id]),
        ];
    }

    /**
     * @param  list<int>  $recepcionIds
     */
    public function asignar(Ordencompra $oc, int $precargaId, array $recepcionIds): void
    {
        $this->assertPrecargaDelLegajo($oc, $precargaId);
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
                'total', 'rutaalmacenamiento', 'estado',
            ]);

        $out = [];
        foreach ($rows as $pre) {
            $id = (int) $pre->id;
            $out[] = [
                'id' => $id,
                'origen' => 'precarga',
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

    public function precargaPerteneceAlLegajo(Ordencompra $oc, Precarga_Comprobante_Proveedor $precarga): bool
    {
        return (int) $precarga->empresa_id === (int) $oc->empresa_id
            && trim((string) $precarga->numeroordencompra) === trim((string) $oc->numeroordencompra);
    }

    private function etiquetaFactura(Precarga_Comprobante_Proveedor $pre): string
    {
        $letra = trim((string) ($pre->letra ?? ''));
        $suc = (int) ($pre->sucursal ?? 0);
        $nro = (int) ($pre->numerocomprobante ?? 0);
        if ($letra !== '' || $nro > 0) {
            return trim(sprintf('%s %04d-%08d', $letra !== '' ? $letra : 'FC', $suc, $nro));
        }

        return 'Factura #'.$pre->id;
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

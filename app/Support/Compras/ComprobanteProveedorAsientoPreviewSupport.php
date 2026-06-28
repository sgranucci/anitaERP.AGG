<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Arma un comprobante en memoria desde el formulario para vista previa de asiento.
 */
final class ComprobanteProveedorAsientoPreviewSupport
{
    public function construirDesdeRequest(Request $request, ?Comprobante_Proveedor $base = null): Comprobante_Proveedor
    {
        $comprobante = $base ? $base->replicate() : new Comprobante_Proveedor;

        if ($base) {
            $comprobante->id = $base->id;
            $comprobante->exists = true;
            $comprobante->asiento_id = $base->asiento_id;
            $comprobante->estado = $base->estado;
        }

        $comprobante->fill([
            'empresa_id' => (int) $request->input('empresa_id', $base->empresa_id ?? 0),
            'proveedor_id' => (int) $request->input('proveedor_id', $base->proveedor_id ?? 0),
            'tipotransaccion_compra_id' => (int) $request->input('tipotransaccion_compra_id', $base->tipotransaccion_compra_id ?? 0),
            'ordencompra_id' => (int) $request->input('ordencompra_id', $base->ordencompra_id ?? 0) ?: null,
            'letra' => (string) $request->input('letra', $base->letra ?? ''),
            'sucursal' => (int) $request->input('sucursal', $base->sucursal ?? 0),
            'numerocomprobante' => (int) $request->input('numerocomprobante', $base->numerocomprobante ?? 0),
            'fechacomprobante' => $request->input('fechacomprobante', $base->fechacomprobante ?? now()->format('Y-m-d')),
            'fechaiva' => $request->input('fechaiva', $base->fechaiva ?? now()->format('Y-m-d')),
            'moneda_id' => (int) $request->input('moneda_id', $base->moneda_id ?? 1),
            'cotizacion' => (float) $request->input('cotizacion', $base->cotizacion ?? 1),
            'subtotal' => (float) $request->input('subtotal', $base->subtotal ?? 0),
            'total' => (float) $request->input('total', $base->total ?? 0),
            'modo_carga' => (string) $request->input('modo_carga', $base->modo_carga ?? ComprobanteProveedorModoCarga::SIN_RECEPCION),
        ]);

        $proveedorId = (int) $comprobante->proveedor_id;
        $comprobante->setRelation(
            'proveedores',
            $proveedorId > 0 ? Proveedor::query()->find($proveedorId) : null
        );

        $tipoId = (int) $comprobante->tipotransaccion_compra_id;
        $comprobante->setRelation(
            'tipotransaccion_compras',
            $tipoId > 0 ? Tipotransaccion_Compra::query()->find($tipoId) : null
        );

        $ocId = (int) ($comprobante->ordencompra_id ?? 0);
        $comprobante->setRelation(
            'ordencompras',
            $ocId > 0 ? Ordencompra::query()->find($ocId) : null
        );

        $comprobante->setRelation(
            'comprobante_proveedor_conceptos',
            $this->construirConceptosDesdeRequest($request)
        );

        $comprobante->setRelation(
            'comprobante_proveedor_recepciones',
            $this->construirRecepcionesDesdeRequest($request)
        );

        return $comprobante;
    }

    /**
     * @return list<array{tipo: string, mensaje: string, concepto_ivacompra_id?: int, nombre?: string}>
     */
    public function avisosFaltantes(Comprobante_Proveedor $comprobante): array
    {
        $avisos = [];
        $modoAsignaRecepcion = $comprobante->modo_carga === ComprobanteProveedorModoCarga::ASIGNA_RECEPCION;

        $cuentaProveedor = (int) ($comprobante->proveedores?->cuentacontable_id
            ?? $comprobante->proveedores?->cuentacontablecompra_id
            ?? 0);
        if ((int) ($comprobante->proveedor_id ?? 0) <= 0) {
            $avisos[] = [
                'tipo' => 'proveedor_sin_seleccionar',
                'mensaje' => 'Seleccione un proveedor con cuenta contable de proveedores.',
            ];
        } elseif ($cuentaProveedor <= 0) {
            $avisos[] = [
                'tipo' => 'proveedor_sin_cuenta',
                'mensaje' => 'El proveedor no tiene cuenta contable de proveedores (haber del asiento).',
            ];
        }

        if ($modoAsignaRecepcion) {
            $provisionId = CuentaAutomaticaResolver::resolverId(
                (int) $comprobante->empresa_id,
                CuentaAutomaticaClaves::RECEPCION_PROVISION_FACTURAS
            );
            if (! $provisionId) {
                $avisos[] = [
                    'tipo' => 'provision_sin_config',
                    'mensaje' => 'Falta configurar la cuenta de provisión de facturas a recibir para la empresa.',
                ];
            }

            if ($comprobante->comprobante_proveedor_recepciones->isEmpty()) {
                $avisos[] = [
                    'tipo' => 'sin_recepciones_com',
                    'mensaje' => 'Modo factura contra recepción: seleccione al menos una recepción COM.',
                ];
            }
        }

        foreach ($comprobante->comprobante_proveedor_conceptos as $linea) {
            $concepto = $linea->concepto_ivacompras;
            if (! $concepto) {
                continue;
            }

            $monto = round(abs((float) $linea->monto), 2);
            if ($monto <= 0) {
                continue;
            }

            $tipoConcepto = (string) ($concepto->tipoconcepto ?? '');

            if ($modoAsignaRecepcion && ComprobanteProveedorConceptoIvaTipos::esNeto($tipoConcepto)) {
                continue;
            }

            $cuentaId = (int) ($concepto->cuentacontabledebe_id ?? 0);
            if ($cuentaId <= 0) {
                $avisos[] = [
                    'tipo' => 'concepto_sin_cuenta_debe',
                    'concepto_ivacompra_id' => (int) $concepto->id,
                    'nombre' => (string) $concepto->nombre,
                    'mensaje' => 'Falta cuenta contable DEBE en concepto IVA «'.$concepto->nombre.'».',
                ];
            }
        }

        return $avisos;
    }

    /**
     * @param  Collection<int, Concepto_Ivacompra>  $conceptosQuery
     * @return array<int, array{cuenta_debe_id: int, tipoconcepto: string, nombre: string}>
     */
    public function metaConceptosParaCliente(Collection $conceptosQuery): array
    {
        $meta = [];
        foreach ($conceptosQuery as $concepto) {
            $meta[(int) $concepto->id] = [
                'cuenta_debe_id' => (int) ($concepto->cuentacontabledebe_id ?? 0),
                'tipoconcepto' => (string) ($concepto->tipoconcepto ?? ''),
                'nombre' => (string) ($concepto->nombre ?? ''),
            ];
        }

        return $meta;
    }

    /** @return Collection<int, Comprobante_Proveedor_Concepto> */
    private function construirConceptosDesdeRequest(Request $request): Collection
    {
        $ids = $request->input('concepto_ivacompra_ids', []);
        $montos = $request->input('montos', []);
        $conceptos = collect();

        if (! is_array($ids)) {
            return $conceptos;
        }

        $conceptosPorId = Concepto_Ivacompra::query()
            ->whereIn('id', array_filter(array_map('intval', $ids)))
            ->get()
            ->keyBy('id');

        foreach ($ids as $i => $conceptoId) {
            $conceptoId = (int) $conceptoId;
            if ($conceptoId <= 0) {
                continue;
            }

            $linea = new Comprobante_Proveedor_Concepto([
                'concepto_ivacompra_id' => $conceptoId,
                'monto' => (float) ($montos[$i] ?? 0),
                'orden' => $i + 1,
            ]);
            $linea->setRelation('concepto_ivacompras', $conceptosPorId->get($conceptoId));
            $conceptos->push($linea);
        }

        return $conceptos;
    }

    /** @return Collection<int, Comprobante_Proveedor_Recepcion> */
    private function construirRecepcionesDesdeRequest(Request $request): Collection
    {
        $ids = $request->input('recepcion_proveedor_ids', []);
        if (! is_array($ids)) {
            return collect();
        }

        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return collect();
        }

        $recepciones = Recepcion_Proveedor::query()->whereIn('id', $ids)->get()->keyBy('id');
        $vinculos = collect();

        foreach ($ids as $i => $recepcionId) {
            $recepcion = $recepciones->get($recepcionId);
            if (! $recepcion) {
                continue;
            }

            $vinculo = new Comprobante_Proveedor_Recepcion([
                'recepcion_proveedor_id' => $recepcionId,
                'orden' => $i + 1,
            ]);
            $vinculo->setRelation('recepcion_proveedores', $recepcion);
            $vinculos->push($vinculo);
        }

        return $vinculos;
    }
}

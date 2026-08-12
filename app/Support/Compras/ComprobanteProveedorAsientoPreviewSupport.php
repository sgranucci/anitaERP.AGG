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
use App\Support\Compras\ComprobanteProveedorConceptosIvaCoherenciaSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use App\Support\Contable\MontoEsArSupport;
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
            'cotizacion' => MontoEsArSupport::parse($request->input('cotizacion', $base->cotizacion ?? 1)),
            'subtotal' => MontoEsArSupport::parse($request->input('subtotal', $base->subtotal ?? 0)),
            'total' => MontoEsArSupport::parse($request->input('total', $base->total ?? 0)),
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
            $ocId > 0
                ? Ordencompra::query()->with('ordencompra_articulos')->find($ocId)
                : null
        );

        $comprobante->setRelation(
            'comprobante_proveedor_conceptos',
            $this->construirConceptosDesdeRequest($request)
        );

        $this->sincronizarTotalesDesdeConceptos($comprobante);

        $comprobante->setRelation(
            'comprobante_proveedor_recepciones',
            $this->construirRecepcionesDesdeRequest($request)
        );

        return $comprobante;
    }

    /**
     * En preview on-the-fly el campo #total a veces queda desfasado del monto de conceptos
     * (ej. al editar Perc. IIBB). El Haber del asiento usa total: alinear con la suma de líneas.
     */
    private function sincronizarTotalesDesdeConceptos(Comprobante_Proveedor $comprobante): void
    {
        $conceptos = $comprobante->comprobante_proveedor_conceptos;
        if ($conceptos === null || $conceptos->isEmpty()) {
            return;
        }

        $total = 0.0;
        $subtotal = 0.0;
        foreach ($conceptos as $linea) {
            $monto = abs((float) ($linea->monto ?? 0));
            if ($monto < 0.0001) {
                continue;
            }
            $total += $monto;
            $tipo = (string) ($linea->concepto_ivacompras?->tipoconcepto ?? '');
            if (ComprobanteProveedorConceptoIvaTipos::esNeto($tipo)) {
                $subtotal += $monto;
            }
        }

        $total = round($total, 2);
        if ($total <= 0) {
            return;
        }

        $comprobante->total = $total;
        if ($subtotal > 0) {
            $comprobante->subtotal = round($subtotal, 2);
        }
    }

    /**
     * @return list<array{tipo: string, mensaje: string, concepto_ivacompra_id?: int, nombre?: string}>
     */
    public function avisosFaltantes(Comprobante_Proveedor $comprobante): array
    {
        $avisos = [];
        $modoAsignaRecepcion = $comprobante->modo_carga === ComprobanteProveedorModoCarga::ASIGNA_RECEPCION;
        $usaProvisionCom = $modoAsignaRecepcion
            && ComprobanteProveedorComContabilidadSupport::generaAsientoCom((int) ($comprobante->empresa_id ?? 0));

        // MN/ME: OC si hay; sin OC → moneda del comprobante.
        $resMoneda = ProveedorCuentaContableMonedaSupport::resolverMonedaParaCuentaProveedor($comprobante);
        $monedaCuentaId = (int) $resMoneda['moneda_id'];
        $origenMoneda = ProveedorCuentaContableMonedaSupport::etiquetaOrigenMoneda($resMoneda['origen']);
        $cuentaProveedor = ProveedorCuentaContableMonedaSupport::cuentaProveedorId(
            $comprobante->proveedores,
            $monedaCuentaId
        );
        if ((int) ($comprobante->proveedor_id ?? 0) <= 0) {
            $avisos[] = [
                'tipo' => 'proveedor_sin_seleccionar',
                'mensaje' => 'Seleccione un proveedor con cuenta contable de proveedores.',
            ];
        } elseif (ProveedorCuentaContableMonedaSupport::esMonedaExtranjera($monedaCuentaId)
            && (int) ($comprobante->proveedores?->cuentacontableme_id ?? 0) <= 0) {
            $avisos[] = [
                'tipo' => 'proveedor_sin_cuenta_me',
                'mensaje' => 'El proveedor no tiene cuenta contable de proveedores moneda extranjera (m/e) según '.$origenMoneda.'.',
            ];
        } elseif ($cuentaProveedor <= 0) {
            $avisos[] = [
                'tipo' => 'proveedor_sin_cuenta',
                'mensaje' => 'El proveedor no tiene cuenta contable de '
                    .ProveedorCuentaContableMonedaSupport::etiquetaCuentaEsperada($monedaCuentaId)
                    .' según '.$origenMoneda.' (haber del asiento).',
            ];
        }

        if ($modoAsignaRecepcion) {
            if ($usaProvisionCom) {
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
            }

            if ($comprobante->comprobante_proveedor_recepciones->isEmpty()) {
                $avisos[] = [
                    'tipo' => 'sin_recepciones_com',
                    'mensaje' => 'Modo factura contra recepción: seleccione al menos una recepción COM.',
                ];
            }
        }

        $facturaAnticipada = ComprobanteProveedorFacturaAnticipadaSupport::aplica($comprobante);
        if ($facturaAnticipada) {
            $tieneCapex = ComprobanteProveedorFacturaAnticipadaSupport::ocTieneCapex($comprobante->ordencompras);
            $claveAnticipo = ComprobanteProveedorFacturaAnticipadaSupport::claveCuentaAnticipo($tieneCapex);
            $cuentaAnticipoId = CuentaAutomaticaResolver::resolverId(
                (int) $comprobante->empresa_id,
                $claveAnticipo
            );
            if (! $cuentaAnticipoId) {
                $avisos[] = [
                    'tipo' => 'anticipo_sin_config',
                    'mensaje' => $tieneCapex
                        ? 'OC anticipada con Capex: falta configurar la cuenta de anticipo a proveedores bienes de uso para la empresa.'
                        : 'OC anticipada sin Capex: falta configurar la cuenta de anticipo a proveedores (factura anticipada) para la empresa.',
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

            if ($usaProvisionCom && ComprobanteProveedorConceptoIvaTipos::esNeto($tipoConcepto)) {
                continue;
            }

            // Neto de factura anticipada: usa cuenta automática de anticipo, no la del concepto.
            if ($facturaAnticipada && ComprobanteProveedorConceptoIvaTipos::esNeto($tipoConcepto)) {
                continue;
            }

            $empresaId = (int) ($comprobante->empresa_id ?? 0);
            $cuentaId = (int) ($concepto->cuentacontableDebeIdParaEmpresa($empresaId));
            if ($cuentaId <= 0) {
                $avisos[] = [
                    'tipo' => 'concepto_sin_cuenta_debe',
                    'concepto_ivacompra_id' => (int) $concepto->id,
                    'nombre' => (string) $concepto->nombre,
                    'mensaje' => 'Falta cuenta contable DEBE en concepto IVA «'.$concepto->nombre.'»'
                        .($empresaId > 0 ? ' para la empresa del comprobante.' : '.'),
                ];
            }
        }

        return $avisos;
    }

    /**
     * @param  Collection<int, Concepto_Ivacompra>  $conceptosQuery
     * @return array<int, array{cuenta_debe_id: int, tipoconcepto: string, nombre: string, impuesto_tasa: float, cuentas_por_empresa: array<int, int>}>
     */
    public function metaConceptosParaCliente(Collection $conceptosQuery, ?int $empresaId = null): array
    {
        $meta = [];
        foreach ($conceptosQuery as $concepto) {
            if (! $concepto->relationLoaded('concepto_ivacompra_empresas')) {
                $concepto->load('concepto_ivacompra_empresas');
            }
            $mapa = $concepto->mapaCuentaDebePorEmpresa();
            $primeraClave = array_key_first($mapa);
            $cuentaDefault = $empresaId !== null
                ? $concepto->cuentacontableDebeIdParaEmpresa($empresaId)
                : (int) ($concepto->cuentacontabledebe_id ?? ($primeraClave !== null ? ($mapa[$primeraClave] ?? 0) : 0));

            $meta[(int) $concepto->id] = [
                'cuenta_debe_id' => $cuentaDefault,
                'tipoconcepto' => (string) ($concepto->tipoconcepto ?? ''),
                'nombre' => (string) ($concepto->nombre ?? ''),
                'impuesto_tasa' => round((float) ($concepto->impuestos->valor ?? 0), 3),
                'cuentas_por_empresa' => $mapa,
            ];
        }

        return $meta;
    }

    /** @return Collection<int, Comprobante_Proveedor_Concepto> */
    private function construirConceptosDesdeRequest(Request $request): Collection
    {
        $lineas = ComprobanteProveedorConceptosIvaCoherenciaSupport::lineasDesdeArrays(
            $request->input('concepto_ivacompra_ids', []),
            $request->input('montos', []),
        );

        $lineas = ComprobanteProveedorConceptosIvaCoherenciaSupport::normalizarYValidar($lineas);

        $conceptos = collect();
        if ($lineas === []) {
            return $conceptos;
        }

        $conceptosPorId = Concepto_Ivacompra::query()
            ->whereIn('id', array_column($lineas, 'concepto_ivacompra_id'))
            ->get()
            ->keyBy('id');

        foreach ($lineas as $i => $linea) {
            $conceptoId = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            if ($conceptoId <= 0) {
                continue;
            }

            $modelo = new Comprobante_Proveedor_Concepto([
                'concepto_ivacompra_id' => $conceptoId,
                'monto' => (float) ($linea['monto'] ?? 0),
                'orden' => $i + 1,
            ]);
            $modelo->setRelation('concepto_ivacompras', $conceptosPorId->get($conceptoId));
            $conceptos->push($modelo);
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

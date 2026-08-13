<?php

namespace App\Services\Compras;

use App\Models\Ai\AiDecision;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Repositories\Compras\Precarga_Comprobante_ProveedorRepositoryInterface;
use App\Repositories\Compras\Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillRegistry;
use App\Services\Compras\Ai\ExtraerFacturaProveedorSkill;
use App\Support\Compras\ComprobanteProveedorConceptosIvaCoherenciaSupport;
use App\Support\Compras\ComprobanteProveedorCotizacionSupport;
use App\Support\Compras\ComprobanteProveedorTipoAutorizacion;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Support\Compras\PrecargaProveedor\ComprobanteProveedorPdfIaConceptoMatcherSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorImporteParserSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorIibbPadronCruceSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorIvaTasaInversaSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorNumeroComprobanteSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorConceptosListaSupport;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorNumeroOcSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorResolucionSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorTipoComprobanteSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorWscdcConstatacionSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorApocConsultaSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ComprobanteProveedorPdfIaService
{
    public function __construct(
        private ComprobanteProveedorPdfIaClient $iaClient,
        private PrecargaProveedorResolucionSupport $resolucionSupport,
        private PrecargaProveedorConceptosListaSupport $conceptosListaSupport,
        private ComprobanteProveedorPdfIaConceptoMatcherSupport $conceptoMatcher,
        private FacturaProveedorIvaTasaInversaSupport $ivaTasaInversa,
        private FacturaProveedorIibbPadronCruceSupport $iibbPadronCruce,
        private FacturaProveedorImporteParserSupport $importeParser,
        private ComprobanteService $comprobanteService,
        private Concepto_IvacompraRepositoryInterface $conceptoIvacompraRepository,
        private Precarga_Comprobante_ProveedorRepositoryInterface $precargaRepository,
        private Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface $precargaConceptoRepository,
        private PrecargaComprobanteAnitaSyncService $precargaAnitaSync,
        private PrecargaProveedorNumeroOcSupport $numeroOcSupport,
        private ComprobanteProveedorFacturaScanStorageService $facturaScanStorage,
        private PrecargaProveedorWscdcConstatacionSupport $wscdcConstatacionSupport,
        private PrecargaProveedorApocConsultaSupport $apocConsultaSupport,
        private AiSkillRegistry $skillRegistry,
        private AiPolicy $aiPolicy,
        private AiDecisionLogger $aiDecisionLogger,
        private CotizacionQueryInterface $cotizacionQuery,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(
        UploadedFile $pdf,
        ?string $numeroOcManual = null,
        bool $ejecucionSistema = false,
    ): array
    {
        $this->assertHabilitado();

        $extraido = $this->extraerConGobernanza($pdf, $ejecucionSistema);
        $extraido = $this->aplicarOcManualEnExtraccion($extraido, $numeroOcManual);

        $numeroOc = $this->resolverNumeroOc($extraido);
        if ($numeroOc === null) {
            return [
                'ok' => false,
                'oc_requerida' => true,
                'message' => 'No se detectó orden de compra en el PDF. Ingrese el número de OC (6 dígitos) para continuar.',
                'extraccion' => $extraido,
                'extraccion_meta' => $extraido['_meta'] ?? [],
                'decision_id' => $this->decisionIdDeExtraccion($extraido),
            ];
        }

        try {
            $resuelto = $this->resolverDesdeExtraccion($extraido, $numeroOc);
        } catch (RuntimeException $e) {
            return [
                'ok' => false,
                'oc_requerida' => $this->debeSolicitarOcManual($e->getMessage()),
                'message' => $e->getMessage(),
                'extraccion' => $extraido,
                'extraccion_meta' => $extraido['_meta'] ?? [],
                'numero_oc_intentado' => $numeroOc,
                'decision_id' => $this->decisionIdDeExtraccion($extraido),
            ];
        }

        return $this->empaquetarPreviewOk($extraido, $resuelto);
    }

    /**
     * Re-resuelve con OC manual sin re-leer el PDF (payload del preview anterior).
     *
     * @param  array<string, mixed>  $extraccion
     * @return array<string, mixed>
     */
    public function resolverConOcManual(array $extraccion, string $numeroOcManual): array
    {
        $this->assertHabilitado();

        $extraido = $this->aplicarOcManualEnExtraccion($extraccion, $numeroOcManual);
        $numeroOc = $this->resolverNumeroOc($extraido);
        if ($numeroOc === null) {
            throw new RuntimeException('Ingrese una orden de compra válida de 6 dígitos.');
        }

        $resuelto = $this->resolverDesdeExtraccion($extraido, $numeroOc);

        return $this->empaquetarPreviewOk($extraido, $resuelto);
    }

    /**
     * @param  array<string, mixed>  $payloadConfirmacion  Datos devueltos por preview (o editados en UI)
     * @return array{precarga_id: int, message: string}
     */
    public function confirmar(
        array $payloadConfirmacion,
        ?UploadedFile $pdf = null,
        string $origenEntrada = PrecargaComprobanteOrigenEntrada::PDF_IA,
        ?int $proveedorIdEsperado = null,
        ?bool $forzarParaRevisar = null,
    ): array
    {
        $this->assertHabilitado();

        $resuelto = $payloadConfirmacion['resuelto'] ?? null;
        if (! is_array($resuelto)) {
            throw new RuntimeException('Payload de confirmación inválido.');
        }

        $numeroOc = $this->numeroOcSupport->normalizar($resuelto['numero_oc'] ?? '');
        if ($numeroOc === '') {
            throw new RuntimeException('Sin orden de compra no se puede procesar la precarga.');
        }

        $empresaId = (int) ($resuelto['empresa_id'] ?? 0);
        $proveedorId = (int) ($resuelto['proveedor_id'] ?? 0);
        $tipoAbreviatura = (string) ($resuelto['tipo_abreviatura'] ?? '');
        $tipotransaccionId = (int) ($resuelto['tipotransaccion_compra_id'] ?? 0);

        if ($proveedorIdEsperado !== null && $proveedorId !== $proveedorIdEsperado) {
            throw new RuntimeException(
                'El proveedor detectado en la factura no coincide con el proveedor autenticado/seleccionado.'
            );
        }

        if ($empresaId <= 0 || $proveedorId <= 0 || $tipotransaccionId <= 0) {
            throw new RuntimeException('Faltan datos resueltos de empresa, proveedor o tipo de comprobante.');
        }

        $conceptosAsignados = $resuelto['conceptos_asignados'] ?? [];
        if ($conceptosAsignados === []) {
            throw new RuntimeException('No hay conceptos asignados para grabar.');
        }

        $lineasConcepto = [];
        foreach ($conceptosAsignados as $linea) {
            $codigoAnita = $linea['id_concepto'] ?? null;
            try {
                $this->precargaAnitaSync->resolverCodigoConceptoAnita(null, $codigoAnita);
            } catch (RuntimeException $e) {
                throw new RuntimeException($e->getMessage());
            }

            $concepto = $this->conceptoIvacompraRepository->findPorCodigo($codigoAnita);
            if (! $concepto) {
                $normalizado = ltrim((string) $codigoAnita, '0');
                if ($normalizado !== '') {
                    $concepto = $this->conceptoIvacompraRepository->findPorCodigo($normalizado);
                }
            }
            if (! $concepto) {
                throw new RuntimeException('Concepto IVA compra código «'.$codigoAnita.'» inexistente en ERP.');
            }

            $lineasConcepto[] = [
                'concepto_ivacompra_id' => (int) $concepto->id,
                'codigo_concepto_anita' => $codigoAnita,
                'monto' => (float) ($linea['importe'] ?? 0),
            ];
        }

        $lineasConcepto = ComprobanteProveedorConceptosIvaCoherenciaSupport::enriquecerCodigosAnita(
            ComprobanteProveedorConceptosIvaCoherenciaSupport::normalizarYValidar($lineasConcepto)
        );

        [$letra, $sucursal, $numeroFactura] = $this->resolverLetraSucursalNumero($resuelto);

        $tipoAutorizacion = ComprobanteProveedorTipoAutorizacion::normalizar(
            $resuelto['tipo_autorizacion'] ?? null
        ) ?? (filled($resuelto['numerocae'] ?? null)
            ? ComprobanteProveedorTipoAutorizacion::CAE
            : null);

        ComprobanteProveedorUnicidadSupport::assertUnicoPrecarga(
            $empresaId,
            $tipotransaccionId,
            $letra,
            $sucursal,
            $numeroFactura,
            $proveedorId,
            null,
            $resuelto['numerocae'] ?? null,
            $tipoAutorizacion,
        );

        $rutaAlmacenamiento = $resuelto['ruta_almacenamiento'] ?? null;
        if ($pdf !== null) {
            $rutaAlmacenamiento = $this->facturaScanStorage->guardarPdfPrecarga($pdf, $resuelto);
        } elseif (! filled($rutaAlmacenamiento)) {
            throw new RuntimeException('Debe adjuntar el PDF de la factura para grabar en Facturas_scan.');
        }

        $data = [
            'empresa_id' => $empresaId,
            'codigoempresa' => (string) ($resuelto['codigo_empresa'] ?? ''),
            'proveedor_id' => $proveedorId,
            'codigoproveedor' => (string) ($resuelto['codigo_proveedor'] ?? ''),
            'tipotransaccion_compra_id' => $tipotransaccionId,
            'tipo' => $tipoAbreviatura,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numerocomprobante' => $numeroFactura,
            'fechafactura' => $this->resolverFechaFacturaParaGrabar($resuelto),
            'fecharecepcionemail' => $resuelto['fecha_recepcion_email'] ?? now()->format('Y-m-d'),
            'fechavencimientocaicae' => $resuelto['fecha_vto_cai_cae'] ?? null,
            'fechavencimiento' => $resuelto['fecha_vencimiento'] ?? null,
            'numerocae' => $resuelto['numerocae'] ?? null,
            'tipo_autorizacion' => $tipoAutorizacion,
            'numeroordencompra' => $numeroOc,
            'rutaalmacenamiento' => $rutaAlmacenamiento,
            'pararevisar' => $forzarParaRevisar === true
                || $this->wscdcConstatacionSupport->tieneDiscrepancias($resuelto)
                || $this->apocConsultaSupport->tieneProblemasApoc($resuelto)
                || empty($resuelto['fecha_factura']) ? 1 : 0,
            'subtotal' => (float) ($resuelto['subtotal'] ?? 0),
            'total' => (float) ($resuelto['total'] ?? 0),
            'moneda' => (string) ($resuelto['moneda'] ?? 'PESOS'),
            'moneda_id' => (int) ($resuelto['moneda_id'] ?? 1),
            'cotizacion' => $this->resolverCotizacionParaGrabar($resuelto),
            'estado' => 'PENDIENTE',
            'origen_entrada' => $origenEntrada,
        ];

        DB::beginTransaction();
        try {
            $precarga = $this->precargaRepository->create($data);

            foreach ($lineasConcepto as $linea) {
                $this->precargaConceptoRepository->create([
                    'precarga_comprobante_proveedor_id' => $precarga->id,
                    'concepto_ivacompra_id' => $linea['concepto_ivacompra_id'],
                    'codigo_concepto_anita' => $linea['codigo_concepto_anita'],
                    'monto' => $linea['monto'],
                ]);
            }

            $this->persistirArticulosPrecarga(
                (int) $precarga->id,
                $resuelto['articulos'] ?? [],
                $proveedorId,
            );

            // Asegurar cabecera coherente con conceptos grabados.
            $sumaConceptos = round(array_sum(array_map(
                static fn (array $l): float => (float) ($l['monto'] ?? 0),
                $lineasConcepto
            )), 2);
            if ($sumaConceptos > 0) {
                $totalCab = round((float) ($data['total'] ?? 0), 2);
                $subCab = round((float) ($data['subtotal'] ?? 0), 2);
                $updateCab = [];
                if ($totalCab <= 1 || abs($sumaConceptos - $totalCab) > max(1.0, $totalCab * 0.5)) {
                    $updateCab['total'] = $sumaConceptos;
                }
                if ($subCab <= 1 || ($updateCab !== [] && $subCab < 10)) {
                    $netoLineas = 0.0;
                    foreach ($lineasConcepto as $linea) {
                        // Heurística: el mayor monto suele ser el neto en facturas A.
                        $netoLineas = max($netoLineas, (float) ($linea['monto'] ?? 0));
                    }
                    // Mejor: si hay 3 líneas típicas, el neto es total - iva - iibb ≈ primer concepto de neto.
                    // Usar subtotal del resuelto si es coherente; si no, total - impuestos estimados.
                    $subResuelto = round((float) ($resuelto['subtotal'] ?? 0), 2);
                    if ($subResuelto > 1 && $subResuelto <= $sumaConceptos + 0.05) {
                        $updateCab['subtotal'] = $subResuelto;
                    } elseif ($sumaConceptos > 0) {
                        $updateCab['subtotal'] = min($netoLineas, $sumaConceptos);
                    }
                }
                if ($updateCab !== []) {
                    Precarga_Comprobante_Proveedor::query()
                        ->whereKey((int) $precarga->id)
                        ->update($updateCab);
                    $precarga->refresh();
                }
            }

            DB::commit();

            $this->resolverDecisionConfirmada($payloadConfirmacion, (int) $precarga->id, $resuelto);

            $mensaje = 'Precarga registrada desde PDF+IA.';
            if ($this->wscdcConstatacionSupport->tieneDiscrepancias($resuelto)
                || $this->apocConsultaSupport->tieneProblemasApoc($resuelto)) {
                $mensaje .= ' Marcada con errores (para revisar) por discrepancias con ARCA.';
            }

            return [
                'precarga_id' => (int) $precarga->id,
                'message' => $mensaje,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $extraido
     * @return array<string, mixed>
     */
    private function resolverDesdeExtraccion(array $extraido, string $numeroOc): array
    {
        $numeroOc = $this->numeroOcSupport->normalizar($numeroOc);

        $extraido = $this->alinearCuitsConOc($extraido, $numeroOc);
        $cuitProveedor = trim((string) ($extraido['cuit_proveedor'] ?? ''));
        if ($cuitProveedor === '') {
            $cuitProveedor = $this->resolucionSupport->resolverCuitProveedorDesdeOc($numeroOc);
            $extraido['cuit_proveedor'] = $cuitProveedor;
            $extraido['cuit_proveedor_origen'] = 'oc';
        }

        $cuitDestinatario = trim((string) ($extraido['cuit_destinatario'] ?? ''));
        $advertencias = [];
        if (! empty($extraido['_advertencia_cuits_swap'])) {
            $advertencias[] = (string) $extraido['_advertencia_cuits_swap'];
            unset($extraido['_advertencia_cuits_swap']);
        }
        if ($cuitDestinatario === '') {
            $empresa = $this->resolucionSupport->resolverEmpresaPorOc($numeroOc);
        } else {
            try {
                $empresa = $this->resolucionSupport->resolverEmpresaPorCuit($cuitDestinatario);
                if (! empty($empresa['cuit_corregido'])) {
                    $advertencias[] = 'CUIT destinatario corregido por OCR: «'
                        .($empresa['cuit_leido'] ?? $cuitDestinatario)
                        .'» → «'.$empresa['cuit_corregido'].'» ('.$empresa['nombre'].').';
                    $extraido['cuit_destinatario'] = $empresa['cuit_corregido'];
                    $extraido['cuit_destinatario_origen'] = 'ocr_corregido';
                    $cuitDestinatario = $empresa['cuit_corregido'];
                }
            } catch (RuntimeException $e) {
                // CUIT ilegible/errado: si hay OC, la empresa sale de ahí (misma política que sin CUIT).
                try {
                    $empresa = $this->resolucionSupport->resolverEmpresaPorOc($numeroOc);
                    $advertencias[] = 'CUIT destinatario «'.$cuitDestinatario.'» no matcheó empresa; '
                        .'se usó la empresa de la OC '.$numeroOc.' ('.$empresa['nombre'].').';
                    $extraido['cuit_destinatario_origen'] = 'oc_fallback';
                } catch (RuntimeException) {
                    throw $e;
                }
            }
        }

        try {
            $empresaOc = $this->resolucionSupport->resolverEmpresaPorOc($numeroOc);
            if ((int) $empresaOc['empresa_id'] !== (int) $empresa['empresa_id']) {
                $advertencias[] = 'El CUIT destinatario matcheó '.$empresa['nombre']
                    .', pero la OC '.$numeroOc.' es de '.$empresaOc['nombre']
                    .'. Se usa la empresa de la OC.';
                $empresa = $empresaOc;
                $extraido['cuit_destinatario_origen'] = 'oc_prevalece';
            }
        } catch (RuntimeException) {
            // Sin OC local: se mantiene la empresa del CUIT.
        }

        $proveedor = $this->resolucionSupport->resolverProveedorPorOc($cuitProveedor, $numeroOc);

        // FC / ND / NC (o REC/REM) desde OCR/LLM; el tipo fino (FIA, CGA…) lo arma listaConcepto + CC.
        $tipoSolicitado = PrecargaProveedorTipoComprobanteSupport::desdeExtraccion($extraido);
        $listaConceptos = $this->conceptosListaSupport->resolver($cuitProveedor, $numeroOc, $tipoSolicitado);

        $comprobante = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($listaConceptos['tipocomprobante']);
        if (! $comprobante) {
            throw new RuntimeException('Tipo de comprobante «'.$listaConceptos['tipocomprobante'].'» inválido.');
        }

        $lineasIa = $extraido['lineas'] ?? [];
        if (! is_array($lineasIa) || $lineasIa === []) {
            throw new RuntimeException('La IA no devolvió líneas de conceptos.');
        }

        // 1) Alícuota IVA por división inversa contra tasas de `impuesto`.
        $lineasIa = $this->ivaTasaInversa->enriquecerLineas($lineasIa);

        // 2) Percepción IIBB: cruce ARBA → CABA con CUIT de la empresa destinataria.
        $netoParaIibb = $this->netoDesdeLineasOSubtotal($lineasIa, $extraido);
        $cruceIibb = $this->iibbPadronCruce->enriquecerLineas(
            $lineasIa,
            (int) $empresa['empresa_id'],
            $this->normalizarFechaYmd($extraido['fecha_factura'] ?? null),
            $netoParaIibb,
        );
        $lineasIa = $cruceIibb['lineas'];
        foreach ($cruceIibb['advertencias'] as $avisoIibb) {
            $advertencias[] = $avisoIibb;
        }

        // 3) Match contra conceptos de la OC usando nombre_ia (alias).
        $conceptosAsignados = $this->conceptoMatcher->matchear($listaConceptos['conceptos'], $lineasIa);
        $conceptosAsignados = $this->aplicarCoherenciaIvaAConceptosAsignados($conceptosAsignados);

        $totalAsignado = round(array_sum(array_column($conceptosAsignados, 'importe')), 2);
        $totalFactura = $this->parsearImporte($extraido['total'] ?? null) ?? 0.0;
        $subtotalFactura = $this->parsearImporte($extraido['subtotal'] ?? null) ?? 0.0;

        // Si la cabecera vino mal casteada (ej. (float)"1.183.650,16" → 1.18), reconstruir desde conceptos.
        if ($totalAsignado > 0 && ($totalFactura <= 0 || $totalAsignado > $totalFactura * 10 || abs($totalAsignado - $totalFactura) > max(1.0, $totalFactura * 0.5))) {
            $totalFactura = $totalAsignado;
            $advertencias[] = 'Total de cabecera reconstruido desde la suma de conceptos ('.$totalAsignado.').';
        }
        if ($subtotalFactura <= 0 || ($totalFactura > 0 && $subtotalFactura > $totalFactura + 0.05 && $totalAsignado > 0)) {
            $netoConceptos = 0.0;
            foreach ($conceptosAsignados as $linea) {
                $tipo = strtolower((string) ($linea['tipo'] ?? ''));
                $imp = abs((float) ($linea['importe'] ?? 0));
                if (str_contains($tipo, 'neto') || str_contains($tipo, 'gravado') || str_contains($tipo, 'exento') || str_contains($tipo, 'subtotal')) {
                    $netoConceptos += $imp;
                }
            }
            if ($netoConceptos > 0) {
                $subtotalFactura = round($netoConceptos, 2);
            } elseif ($totalFactura > 0 && $subtotalFactura <= 1) {
                $subtotalFactura = $totalFactura;
            }
        }

        if ($totalFactura > 0 && abs($totalAsignado - $totalFactura) > 0.05 && $totalAsignado > 0) {
            $advertencias[] = 'La suma de conceptos ('.$totalAsignado.') difiere del total del comprobante ('.$totalFactura.').';
        }

        [$letraRes, $sucursalRes, $numeroFacturaRes] = $this->resolverLetraSucursalNumero($extraido);
        if ($letraRes === '' || $letraRes === '0') {
            $letraRes = (string) ($listaConceptos['letra'] ?? 'A');
        }

        $resuelto = [
            'numero_oc' => $numeroOc,
            'empresa_id' => $empresa['empresa_id'],
            'codigo_empresa' => $empresa['codigo'],
            'empresa_nombre' => $empresa['nombre'],
            'proveedor_id' => $proveedor['proveedor_id'],
            'codigo_proveedor' => $proveedor['codigo'],
            'proveedor_nombre' => $proveedor['nombre'],
            'centro_costo_codigo' => $listaConceptos['centro_costo_codigo'],
            'tipo_solicitado' => $tipoSolicitado,
            'tipo_solicitado_etiqueta' => PrecargaProveedorTipoComprobanteSupport::etiqueta($tipoSolicitado),
            'tipo_abreviatura' => $listaConceptos['tipocomprobante'],
            'tipotransaccion_compra_id' => (int) $comprobante->id,
            'letra' => $letraRes,
            'sucursal' => $sucursalRes,
            'numero_factura' => $numeroFacturaRes,
            'fecha_factura' => $this->normalizarFechaYmd($extraido['fecha_factura'] ?? null),
            'fecha_recepcion_email' => now()->format('Y-m-d'),
            'numerocae' => $this->normalizarTexto($extraido['numerocae'] ?? null),
            'fecha_vto_cai_cae' => $this->normalizarFechaYmd($extraido['fecha_vto_cai_cae'] ?? null),
            'fecha_vencimiento' => $this->normalizarFechaYmd($extraido['fecha_vencimiento'] ?? null),
            'subtotal' => $subtotalFactura,
            'total' => $totalFactura,
            'moneda' => strtoupper((string) ($extraido['moneda'] ?? 'PESOS')),
            'moneda_id' => $this->resolverMonedaId($extraido['moneda'] ?? 'PESOS'),
            'cotizacion' => $this->parsearImporte($extraido['cotizacion'] ?? null) ?? 1.0,
            'conceptos_candidatos' => $listaConceptos['conceptos'],
            'conceptos_asignados' => $conceptosAsignados,
            'articulos' => $this->normalizarArticulosExtraidos(
                $extraido['articulos'] ?? [],
                (int) ($proveedor['proveedor_id'] ?? 0)
            ),
            'advertencias' => $advertencias,
            'pararevisar' => $totalFactura > 0 && abs($totalAsignado - $totalFactura) > 0.05,
        ];

        $resuelto['cotizacion'] = $this->resolverCotizacionParaGrabar($resuelto);
        if (empty($resuelto['fecha_factura'])) {
            $advertencias[] = 'No se detectó fecha de factura en el PDF; se usará la fecha de recepción al confirmar.';
            $resuelto['advertencias'] = $advertencias;
            $resuelto['pararevisar'] = true;
        }

        $resuelto = $this->wscdcConstatacionSupport->constatarYEnriquecer($extraido, $resuelto);

        return $this->apocConsultaSupport->consultarYEnriquecer($resuelto);
    }

    private function assertHabilitado(): void
    {
        if (! config('comprobante_proveedor_pdf_ia.habilitado')) {
            throw new RuntimeException('Carga PDF+IA deshabilitada (COMPROBANTE_PROVEEDOR_PDF_IA_HABILITADO=false).');
        }
    }

    /**
     * Extrae el PDF vía skill gobernada cuando la plataforma IA está activa
     * (deja traza en ai_decision); si no, usa el cliente directo de siempre.
     *
     * @return array<string, mixed>
     */
    private function extraerConGobernanza(UploadedFile $pdf, bool $ejecucionSistema = false): array
    {
        $skill = ExtraerFacturaProveedorSkill::NOMBRE;

        $habilitada = $ejecucionSistema
            ? $this->aiPolicy->skillHabilitada($skill)
            : $this->aiPolicy->puedeEjecutar($skill);
        if (! $this->skillRegistry->tiene($skill) || ! $habilitada) {
            return $this->iaClient->extraer($pdf);
        }

        $contexto = new AiSkillContext(
            entradas: ['pdf' => $pdf],
            entidadTipo: ExtraerFacturaProveedorSkill::ENTIDAD,
        );
        $resultado = $ejecucionSistema
            ? $this->skillRegistry->ejecutarSistema($skill, $contexto)
            : $this->skillRegistry->ejecutar($skill, $contexto);

        if (! $resultado->ok) {
            throw new RuntimeException($resultado->error ?? 'No se pudo extraer el PDF con IA.');
        }

        $extraccion = $resultado->datos;
        $extraccion['_meta']['ai_score'] = $resultado->score;
        $extraccion['_meta']['ai_auto_aplicable'] = (bool) $resultado->autoAplicable;
        if ($resultado->decisionId !== null) {
            $extraccion['_meta']['ai_decision_id'] = $resultado->decisionId;
        }

        return $extraccion;
    }

    /**
     * @param  array<string, mixed>  $extraccion
     */
    private function decisionIdDeExtraccion(array $extraccion): ?int
    {
        $id = $extraccion['_meta']['ai_decision_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Cierra el ciclo de gobernanza: la sugerencia de IA terminó en una precarga grabada.
     *
     * @param  array<string, mixed>  $payloadConfirmacion
     * @param  array<string, mixed>  $resuelto
     */
    private function resolverDecisionConfirmada(array $payloadConfirmacion, int $precargaId, array $resuelto = []): void
    {
        $decisionId = $payloadConfirmacion['decision_id'] ?? null;
        if (! is_numeric($decisionId)) {
            $extraccion = $payloadConfirmacion['extraccion'] ?? [];
            $decisionId = is_array($extraccion) ? $this->decisionIdDeExtraccion($extraccion) : null;
        }

        if (! is_numeric($decisionId)) {
            return;
        }

        $extraccion = is_array($payloadConfirmacion['extraccion'] ?? null)
            ? $payloadConfirmacion['extraccion']
            : [];
        $origenOc = (string) ($extraccion['numero_oc_origen'] ?? '');
        $autoAplicable = $this->esAutoAplicablePayload($payloadConfirmacion);
        if ($autoAplicable && $origenOc !== 'manual' && empty($resuelto['pararevisar'])) {
            $accion = AiDecision::ACCION_AUTO_APLICADA;
        } elseif ($origenOc === 'manual' || ! empty($resuelto['pararevisar'])) {
            $accion = AiDecision::ACCION_EDITADA;
        } else {
            $accion = AiDecision::ACCION_CONFIRMADA;
        }

        $this->aiDecisionLogger->resolver((int) $decisionId, $accion, null, [
            'entidad_id' => $precargaId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extraido
     * @param  array<string, mixed>  $resuelto
     * @return array<string, mixed>
     */
    private function empaquetarPreviewOk(array $extraido, array $resuelto): array
    {
        $meta = is_array($extraido['_meta'] ?? null) ? $extraido['_meta'] : [];
        $auto = ! empty($meta['ai_auto_aplicable']);

        return [
            'ok' => true,
            'extraccion' => $extraido,
            'extraccion_meta' => $meta,
            'resuelto' => $resuelto,
            'conceptos' => $resuelto['conceptos_asignados'],
            'articulos' => $resuelto['articulos'] ?? [],
            'advertencias' => $resuelto['advertencias'] ?? [],
            'decision_id' => $this->decisionIdDeExtraccion($extraido),
            'ai_score' => isset($meta['ai_score']) ? (float) $meta['ai_score'] : null,
            'ai_auto_aplicable' => $auto,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function esAutoAplicablePayload(array $payload): bool
    {
        if (array_key_exists('ai_auto_aplicable', $payload)) {
            return (bool) $payload['ai_auto_aplicable'];
        }
        $meta = $payload['extraccion_meta'] ?? ($payload['extraccion']['_meta'] ?? []);
        if (is_array($meta) && array_key_exists('ai_auto_aplicable', $meta)) {
            return (bool) $meta['ai_auto_aplicable'];
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $extraido
     */
    private function aplicarOcManualEnExtraccion(array $extraido, ?string $numeroOcManual): array
    {
        if ($numeroOcManual !== null && trim($numeroOcManual) !== '') {
            $extraido['numero_oc'] = $this->numeroOcSupport->normalizar($numeroOcManual);
            $extraido['numero_oc_origen'] = 'manual';
        }

        return $extraido;
    }

    /**
     * Si OCR/LLM invirtió emisor ↔ receptor, el CUIT de la OC suele quedar en destinatario.
     *
     * @param  array<string, mixed>  $extraido
     * @return array<string, mixed>
     */
    private function alinearCuitsConOc(array $extraido, string $numeroOc): array
    {
        try {
            $cuitOc = $this->resolucionSupport->resolverCuitProveedorDesdeOc($numeroOc);
        } catch (RuntimeException) {
            return $extraido;
        }

        $cuitOcDig = preg_replace('/\D/', '', $cuitOc) ?? '';
        $provDig = preg_replace('/\D/', '', (string) ($extraido['cuit_proveedor'] ?? '')) ?? '';
        $destDig = preg_replace('/\D/', '', (string) ($extraido['cuit_destinatario'] ?? '')) ?? '';

        if ($cuitOcDig === '' || $provDig === $cuitOcDig) {
            return $extraido;
        }

        if ($destDig === $cuitOcDig) {
            $leidoComoProv = (string) ($extraido['cuit_proveedor'] ?? '');
            $extraido['cuit_destinatario'] = $leidoComoProv !== '' ? $leidoComoProv : null;
            $extraido['cuit_proveedor'] = $cuitOc;
            $extraido['cuit_proveedor_origen'] = 'oc_swap_emisor_receptor';
            $extraido['cuit_destinatario_origen'] = 'swap_desde_proveedor_erroneo';
            $extraido['_advertencia_cuits_swap'] = 'CUIT emisor/receptor invertidos en la lectura; '
                .'se realineó con el proveedor de la OC '.$numeroOc.' ('.$cuitOc.').';

            return $extraido;
        }

        return $extraido;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  array<string, mixed>  $extraido
     */
    private function netoDesdeLineasOSubtotal(array $lineas, array $extraido): ?float
    {
        $suma = 0.0;
        $hay = false;
        foreach ($lineas as $linea) {
            $tipo = strtolower((string) ($linea['tipo'] ?? ''));
            if (str_contains($tipo, 'neto') || str_contains($tipo, 'subtotal') || str_contains($tipo, 'gravado')) {
                $suma += abs((float) ($linea['importe'] ?? 0));
                $hay = true;
            }
        }
        if ($hay && $suma > 0) {
            return round($suma, 2);
        }

        $subtotal = (float) ($extraido['subtotal'] ?? 0);
        if ($subtotal > 0) {
            return round($subtotal, 2);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $extraido
     */
    private function resolverNumeroOc(array $extraido): ?string
    {
        $raw = trim((string) ($extraido['numero_oc'] ?? ''));
        if ($raw === '') {
            return null;
        }

        try {
            return $this->numeroOcSupport->normalizar($raw);
        } catch (RuntimeException) {
            return null;
        }
    }

    private function normalizarTexto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /** Parsea importes AR/EN sin el bug de (float)"1.183.650,16" → 1.18. */
    private function parsearImporte(mixed $valor): ?float
    {
        return $this->importeParser->parsear($valor);
    }

    /** Normaliza a Y-m-d o null (acepta Y-m-d, d/m/Y, d-m-Y, d.m.Y). */
    private function normalizarFechaYmd(mixed $valor): ?string
    {
        $texto = $this->normalizarTexto($valor);
        if ($texto === null) {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $texto : null;
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $texto, $m)) {
            $y = (int) $m[3];
            if ($y < 100) {
                $y += 2000;
            }
            $mes = (int) $m[2];
            $dia = (int) $m[1];
            if (! checkdate($mes, $dia, $y)) {
                return null;
            }

            return sprintf('%04d-%02d-%02d', $y, $mes, $dia);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resuelto
     */
    private function resolverFechaFacturaParaGrabar(array $resuelto): string
    {
        $fecha = $this->normalizarFechaYmd($resuelto['fecha_factura'] ?? null);
        if ($fecha !== null) {
            return $fecha;
        }

        $recepcion = $this->normalizarFechaYmd($resuelto['fecha_recepcion_email'] ?? null);

        return $recepcion ?? now()->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $resuelto
     */
    private function resolverCotizacionParaGrabar(array $resuelto): float
    {
        $monedaId = (int) ($resuelto['moneda_id'] ?? 1);
        $cot = (float) ($resuelto['cotizacion'] ?? 0);
        if (! ComprobanteProveedorCotizacionSupport::esMonedaExtranjera($monedaId)) {
            return 1.0;
        }
        if ($cot > 1.0) {
            return $cot;
        }

        $fecha = $this->resolverFechaFacturaParaGrabar($resuelto);

        return ComprobanteProveedorCotizacionSupport::cotizacionVentaDelDia(
            $this->cotizacionQuery,
            $fecha,
            $monedaId
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{0: string, 1: int|null, 2: int|null}
     */
    private function resolverLetraSucursalNumero(array $datos): array
    {
        $compacto = FacturaProveedorNumeroComprobanteSupport::parsearValorCompacto($datos['numero_factura'] ?? null)
            ?? FacturaProveedorNumeroComprobanteSupport::parsearValorCompacto(
                trim((string) ($datos['sucursal'] ?? ''))
                .(string) ($datos['letra'] ?? '')
                .trim((string) ($datos['numero_factura'] ?? ''))
            );

        if ($compacto !== null && ($compacto['numero'] ?? 0) > 0) {
            return [
                (string) $compacto['letra'],
                (int) $compacto['sucursal'],
                (int) $compacto['numero'],
            ];
        }

        return [
            (string) ($datos['letra'] ?? 'A'),
            $this->normalizarEntero($datos['sucursal'] ?? null),
            $this->normalizarEntero($datos['numero_factura'] ?? null),
        ];
    }

    private function normalizarEntero(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        // Si viene compacto (0070A00369548), no aplastar letra+dígitos en un entero gigante.
        $compacto = FacturaProveedorNumeroComprobanteSupport::parsearValorCompacto($valor);
        if ($compacto !== null) {
            return (int) $compacto['numero'];
        }
        $limpio = ltrim(preg_replace('/\D/', '', (string) $valor) ?? '', '0');

        return $limpio === '' ? 0 : (int) $limpio;
    }

    private function resolverMonedaId(mixed $moneda): int
    {
        $valor = strtoupper(trim((string) $moneda));
        // "$" / vacío / ARS → pesos.
        if ($valor === '' || $valor === '$' || $valor === 'ARS' || $valor === 'PESO' || $valor === 'PESOS') {
            return 1;
        }

        return match ($valor) {
            'DOLARES', 'DOLAR', 'USD', 'US$', 'U$S', 'DOL' => 2,
            'EUROS', 'EURO', 'EUR' => 3,
            default => 1,
        };
    }

    /**
     * Abre gravados por alícuota y valida IVA↔neto sobre los conceptos matcheados por IA.
     *
     * @param  list<array{id_concepto: int|string, importe: float, descripcion_ia?: string, concepto_nombre?: string}>  $conceptosAsignados
     * @return list<array{id_concepto: int|string, importe: float, descripcion_ia: string, concepto_nombre: string}>
     */
    private function aplicarCoherenciaIvaAConceptosAsignados(array $conceptosAsignados): array
    {
        $lineas = [];
        foreach ($conceptosAsignados as $linea) {
            $codigoAnita = $linea['id_concepto'] ?? null;
            $concepto = $this->conceptoIvacompraRepository->findPorCodigo($codigoAnita);
            if (! $concepto) {
                $normalizado = ltrim((string) $codigoAnita, '0');
                if ($normalizado !== '') {
                    $concepto = $this->conceptoIvacompraRepository->findPorCodigo($normalizado);
                }
            }
            if (! $concepto) {
                throw new RuntimeException('Concepto IVA compra código «'.$codigoAnita.'» inexistente en ERP.');
            }
            $lineas[] = [
                'concepto_ivacompra_id' => (int) $concepto->id,
                'codigo_concepto_anita' => $concepto->codigo,
                'monto' => (float) ($linea['importe'] ?? 0),
            ];
        }

        $lineas = ComprobanteProveedorConceptosIvaCoherenciaSupport::enriquecerCodigosAnita(
            ComprobanteProveedorConceptosIvaCoherenciaSupport::normalizarYValidar($lineas)
        );

        $resultado = [];
        foreach ($lineas as $linea) {
            $concepto = $this->conceptoIvacompraRepository->find((int) $linea['concepto_ivacompra_id']);
            if (! $concepto) {
                continue;
            }
            $resultado[] = [
                'id_concepto' => $concepto->codigo,
                'importe' => round((float) $linea['monto'], 2),
                'descripcion_ia' => (string) ($concepto->nombre_ia ?: $concepto->nombre),
                'concepto_nombre' => (string) $concepto->nombre,
            ];
        }

        return $resultado;
    }

    /**
     * @param  mixed  $raw
     * @return list<array{sku: string, codigo_proveedor: string, descripcion: string, cantidad: float, precio_unitario: float, articulo_id: int|null}>
     */
    private function normalizarArticulosExtraidos(mixed $raw, int $proveedorId): array
    {
        $coleccion = \App\Support\Compras\ComprobanteProveedorLineasFacturaSupport::desdePayloadIa([
            'articulos' => is_array($raw) ? $raw : [],
        ]);

        $out = [];
        foreach ($coleccion as $linea) {
            $sku = (string) ($linea['sku'] ?? '');
            $codProv = (string) ($linea['codigo_proveedor'] ?? '');
            $articuloId = (int) ($linea['articulo_id'] ?? 0) ?: null;

            if ($articuloId === null && $sku !== '') {
                $articuloId = \App\Support\Stock\ArticuloSkuMatchSupport::resolverCanonico($sku)?->id;
            }
            if ($articuloId === null && $codProv !== '' && $proveedorId > 0) {
                $match = \App\Support\Compras\ArticuloProveedorMatchSupport::resolver($proveedorId, $codProv, null, null);
                $articuloId = (int) ($match['articulo_id'] ?? 0) ?: null;
                if ($articuloId && $sku === '') {
                    $sku = (string) ($match['articulo_sku'] ?? '');
                }
            }

            $out[] = [
                'sku' => $sku,
                'codigo_proveedor' => $codProv,
                'descripcion' => (string) ($linea['descripcion'] ?? ''),
                'cantidad' => (float) ($linea['cantidad'] ?? 0),
                'precio_unitario' => (float) ($linea['precio_unitario'] ?? 0),
                'articulo_id' => $articuloId,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $articulos
     */
    private function persistirArticulosPrecarga(int $precargaId, array $articulos, int $proveedorId): void
    {
        $normalizados = $this->normalizarArticulosExtraidos($articulos, $proveedorId);
        foreach ($normalizados as $i => $linea) {
            \App\Models\Compras\Precarga_Comprobante_Proveedor_Articulo::query()->create([
                'precarga_comprobante_proveedor_id' => $precargaId,
                'orden' => $i + 1,
                'articulo_id' => $linea['articulo_id'] ?: null,
                'sku' => $linea['sku'] !== '' ? $linea['sku'] : null,
                'codigo_proveedor' => $linea['codigo_proveedor'] !== '' ? $linea['codigo_proveedor'] : null,
                'descripcion' => $linea['descripcion'] !== '' ? $linea['descripcion'] : null,
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['precio_unitario'],
            ]);
        }
    }

    private function debeSolicitarOcManual(string $mensaje): bool
    {
        $msg = mb_strtolower($mensaje);

        if (str_contains($msg, 'oc inexistente') || str_contains($msg, 'no corresponde con el cuit')) {
            return false;
        }

        return str_contains($msg, 'orden de compra')
            || str_contains($msg, 'no se detectó')
            || str_contains($msg, 'ingrese el número de oc')
            || str_contains($msg, 'falta número de orden');
    }
}

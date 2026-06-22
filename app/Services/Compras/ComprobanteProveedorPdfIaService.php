<?php

namespace App\Services\Compras;

use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Repositories\Compras\Precarga_Comprobante_ProveedorRepositoryInterface;
use App\Repositories\Compras\Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface;
use App\Support\Compras\PrecargaProveedor\ComprobanteProveedorPdfIaConceptoMatcherSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorConceptosListaSupport;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorNumeroOcSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorResolucionSupport;
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
        private ComprobanteService $comprobanteService,
        private Concepto_IvacompraRepositoryInterface $conceptoIvacompraRepository,
        private Precarga_Comprobante_ProveedorRepositoryInterface $precargaRepository,
        private Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface $precargaConceptoRepository,
        private PrecargaComprobanteAnitaSyncService $precargaAnitaSync,
        private PrecargaProveedorNumeroOcSupport $numeroOcSupport,
        private ComprobanteProveedorFacturaScanStorageService $facturaScanStorage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(UploadedFile $pdf, ?string $numeroOcManual = null): array
    {
        $this->assertHabilitado();

        $extraido = $this->iaClient->extraer($pdf);
        $extraido = $this->aplicarOcManualEnExtraccion($extraido, $numeroOcManual);

        $numeroOc = $this->resolverNumeroOc($extraido);
        if ($numeroOc === null) {
            return [
                'ok' => false,
                'oc_requerida' => true,
                'message' => 'No se detectó orden de compra en el PDF. Ingrese el número de OC (6 dígitos) para continuar.',
                'extraccion' => $extraido,
                'extraccion_meta' => $extraido['_meta'] ?? [],
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
            ];
        }

        return [
            'ok' => true,
            'extraccion' => $extraido,
            'extraccion_meta' => $extraido['_meta'] ?? [],
            'resuelto' => $resuelto,
            'conceptos' => $resuelto['conceptos_asignados'],
            'advertencias' => $resuelto['advertencias'] ?? [],
        ];
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

        return [
            'ok' => true,
            'extraccion' => $extraido,
            'extraccion_meta' => $extraido['_meta'] ?? [],
            'resuelto' => $resuelto,
            'conceptos' => $resuelto['conceptos_asignados'],
            'advertencias' => $resuelto['advertencias'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payloadConfirmacion  Datos devueltos por preview (o editados en UI)
     * @return array{precarga_id: int, message: string}
     */
    public function confirmar(array $payloadConfirmacion, ?UploadedFile $pdf = null): array
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

        $letra = (string) ($resuelto['letra'] ?? 'A');
        $sucursal = $this->normalizarEntero($resuelto['sucursal'] ?? null);
        $numeroFactura = $this->normalizarEntero($resuelto['numero_factura'] ?? null);

        $duplicado = $this->precargaRepository->findDuplicadoPrecarga(
            $empresaId,
            $proveedorId,
            $tipotransaccionId,
            $letra,
            $sucursal,
            $numeroFactura,
        );
        if ($duplicado !== null) {
            throw new RuntimeException(
                $this->precargaRepository->mensajeFacturaDuplicada($duplicado, $tipoAbreviatura)
            );
        }

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
            'fechafactura' => $resuelto['fecha_factura'] ?? null,
            'fecharecepcionemail' => $resuelto['fecha_recepcion_email'] ?? now()->format('Y-m-d'),
            'fechavencimientocaicae' => $resuelto['fecha_vto_cai_cae'] ?? null,
            'numerocae' => $resuelto['numerocae'] ?? null,
            'numeroordencompra' => $numeroOc,
            'rutaalmacenamiento' => $rutaAlmacenamiento,
            'pararevisar' => 0,
            'subtotal' => (float) ($resuelto['subtotal'] ?? 0),
            'total' => (float) ($resuelto['total'] ?? 0),
            'moneda' => (string) ($resuelto['moneda'] ?? 'PESOS'),
            'moneda_id' => (int) ($resuelto['moneda_id'] ?? 1),
            'cotizacion' => (float) ($resuelto['cotizacion'] ?? 1),
            'estado' => 'PENDIENTE',
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::PDF_IA,
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

            DB::commit();

            return [
                'precarga_id' => (int) $precarga->id,
                'message' => 'Precarga registrada desde PDF+IA.',
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

        $cuitProveedor = trim((string) ($extraido['cuit_proveedor'] ?? ''));
        if ($cuitProveedor === '') {
            $cuitProveedor = $this->resolucionSupport->resolverCuitProveedorDesdeOc($numeroOc);
            $extraido['cuit_proveedor'] = $cuitProveedor;
            $extraido['cuit_proveedor_origen'] = 'oc';
        }

        $cuitDestinatario = trim((string) ($extraido['cuit_destinatario'] ?? ''));
        if ($cuitDestinatario === '') {
            $empresa = $this->resolucionSupport->resolverEmpresaPorOc($numeroOc);
        } else {
            $empresa = $this->resolucionSupport->resolverEmpresaPorCuit($cuitDestinatario);
        }

        $proveedor = $this->resolucionSupport->resolverProveedorPorOc($cuitProveedor, $numeroOc);

        $tipoSolicitado = 'FC';
        $listaConceptos = $this->conceptosListaSupport->resolver($cuitProveedor, $numeroOc, $tipoSolicitado);

        $comprobante = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($listaConceptos['tipocomprobante']);
        if (! $comprobante) {
            throw new RuntimeException('Tipo de comprobante «'.$listaConceptos['tipocomprobante'].'» inválido.');
        }

        $lineasIa = $extraido['lineas'] ?? [];
        if (! is_array($lineasIa) || $lineasIa === []) {
            throw new RuntimeException('La IA no devolvió líneas de conceptos.');
        }

        $conceptosAsignados = $this->conceptoMatcher->matchear($listaConceptos['conceptos'], $lineasIa);

        $totalAsignado = round(array_sum(array_column($conceptosAsignados, 'importe')), 2);
        $totalFactura = round((float) ($extraido['total'] ?? 0), 2);
        $advertencias = [];
        if ($totalFactura > 0 && abs($totalAsignado - $totalFactura) > 0.05) {
            $advertencias[] = 'La suma de conceptos ('.$totalAsignado.') difiere del total del comprobante ('.$totalFactura.').';
        }

        return [
            'numero_oc' => $numeroOc,
            'empresa_id' => $empresa['empresa_id'],
            'codigo_empresa' => $empresa['codigo'],
            'empresa_nombre' => $empresa['nombre'],
            'proveedor_id' => $proveedor['proveedor_id'],
            'codigo_proveedor' => $proveedor['codigo'],
            'proveedor_nombre' => $proveedor['nombre'],
            'centro_costo_codigo' => $listaConceptos['centro_costo_codigo'],
            'tipo_abreviatura' => $listaConceptos['tipocomprobante'],
            'tipotransaccion_compra_id' => (int) $comprobante->id,
            'letra' => (string) ($extraido['letra'] ?? $listaConceptos['letra']),
            'sucursal' => $this->normalizarEntero($extraido['sucursal'] ?? null),
            'numero_factura' => $this->normalizarEntero($extraido['numero_factura'] ?? null),
            'fecha_factura' => $this->normalizarTexto($extraido['fecha_factura'] ?? null),
            'fecha_recepcion_email' => now()->format('Y-m-d'),
            'numerocae' => $this->normalizarTexto($extraido['numerocae'] ?? null),
            'fecha_vto_cai_cae' => $this->normalizarTexto($extraido['fecha_vto_cai_cae'] ?? null),
            'subtotal' => (float) ($extraido['subtotal'] ?? 0),
            'total' => $totalFactura,
            'moneda' => strtoupper((string) ($extraido['moneda'] ?? 'PESOS')),
            'moneda_id' => $this->resolverMonedaId($extraido['moneda'] ?? 'PESOS'),
            'cotizacion' => (float) ($extraido['cotizacion'] ?? 1),
            'conceptos_candidatos' => $listaConceptos['conceptos'],
            'conceptos_asignados' => $conceptosAsignados,
            'advertencias' => $advertencias,
        ];
    }

    private function assertHabilitado(): void
    {
        if (! config('comprobante_proveedor_pdf_ia.habilitado')) {
            throw new RuntimeException('Carga PDF+IA deshabilitada (COMPROBANTE_PROVEEDOR_PDF_IA_HABILITADO=false).');
        }
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

    private function normalizarEntero(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $limpio = ltrim(preg_replace('/\D/', '', (string) $valor) ?? '', '0');

        return $limpio === '' ? 0 : (int) $limpio;
    }

    private function resolverMonedaId(mixed $moneda): int
    {
        return match (strtoupper((string) $moneda)) {
            'DOLARES', 'USD' => 2,
            'EUROS', 'EUR' => 3,
            default => 1,
        };
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

<?php

namespace App\Services\Compras;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Contable\Asiento;
use App\Repositories\Compras\PagoproveedorRepositoryInterface;
use App\Support\Compras\PagoproveedorAplicacionLadoSupport;
use App\Support\Compras\PagoproveedorFirmaAgenteRetencionSupport;
use App\Support\Compras\PagoproveedorRetencionCertificadoLineasSupport;
use App\Support\Compras\Retencion\RetencionesPagoBasesDesdeConceptosSupport;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Sueldos\NumeroALetrasEs;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF profesional de OPP/OPA a proveedores: hoja principal + una página por retención.
 * Combina el layout de OP de Ingresos/Egresos (solicitudes) con el contenido de Anita (lista_op / lista_ret*).
 */
class PagoproveedorComprobantePdfService
{
    public function __construct(
        private PagoproveedorRepositoryInterface $pagoproveedorRepository,
        private RetencionesPagoBasesDesdeConceptosSupport $basesRetencionSupport,
    ) {
    }

    public function generarRespuesta(int $id): BinaryFileResponse
    {
        $pago = $this->cargarPago($id);
        $pdf = $this->armarPdf($pago);

        $dir = storage_path('pdf/pagoproveedor');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (! is_writable($dir)) {
            @chmod($dir, 0775);
        }
        if (! is_writable($dir)) {
            throw new \RuntimeException(
                'No se puede escribir en '.$dir.'. Revisar permisos (grupo www-data, 775).'
            );
        }

        $path = $dir.'/op_'.$pago->id.'.pdf';
        $pdf->save($path);

        $nombre = sprintf(
            'orden_pago_%s_%04d-%s.pdf',
            strtolower((string) $pago->tipocomprobante),
            (int) $pago->sucursal,
            $pago->numerotransaccion
        );

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
        ]);
    }

    public function streamRetencion(int $pagoId, int $retencionId): StreamedResponse
    {
        $pago = $this->cargarPago($pagoId);
        $retencion = $pago->pagoproveedor_retenciones->firstWhere('id', $retencionId);
        if ($retencion === null) {
            abort(404);
        }

        $datos = $this->datosVista($pago);
        $datos['retencionesPaginas'] = collect([$retencion]);
        $datos['soloRetencion'] = true;

        $pdf = Pdf::loadView('compras.pagoproveedor.comprobante', $datos)->setPaper('a4');

        return $pdf->stream('retencion_'.$retencion->id.'.pdf');
    }

    public function armarPdf(Pagoproveedor $pago)
    {
        return Pdf::loadView('compras.pagoproveedor.comprobante', $this->datosVista($pago))
            ->setPaper('a4');
    }

    public function cargarPago(int $id): Pagoproveedor
    {
        $pago = $this->pagoproveedorRepository->find($id);
        $pago->loadMissing([
            'empresas.localidad',
            'empresas.provincia',
            'proveedores.condicionivas',
            'proveedores.localidades',
            'proveedores.provincias',
            'usuarios',
            'monedas',
            'tipotransaccion_cajas',
            'pagoproveedor_comprobantes.monedas',
            'pagoproveedor_comprobantes.proveedor_cuentacorrientes.comprobante_proveedores.tipotransaccion_compras',
            'pagoproveedor_comprobantes.proveedor_cuentacorrientes.pagoproveedores',
            'pagoproveedor_comprobantes.proveedor_cuentacorrientes.comprobante_proveedores.monedas',
            'pagoproveedor_retenciones.provincias',
            'pagoproveedor_retenciones.monedas',
            'pagoproveedor_retenciones.retencionganancias',
            'pagoproveedor_retenciones.retencionivas',
            'pagoproveedor_retenciones.retencionsusss',
            'cheques.bancos',
            'cheques.monedas',
            'caja_movimientos.caja_movimiento_cuentacajas.cuentacajas',
            'caja_movimientos.caja_movimiento_cuentacajas.monedas',
            'asientos.asiento_movimientos.cuentacontables',
            'asientos.asiento_movimientos.centrocostos',
        ]);

        return $pago;
    }

    /**
     * @return array<string, mixed>
     */
    public function datosVista(Pagoproveedor $pago): array
    {
        $empresa = $pago->empresas;
        $proveedor = $pago->proveedores;
        $logo = EmpresaLogoArchivo::dataUriDesdeNombre($empresa->nombre ?? null);

        $aplicaciones = $this->armarAplicaciones($pago);
        $lineasRetencionPorId = $this->armarLineasCertificadoRetencion($pago, $aplicaciones);
        $mediosCaja = $this->armarMediosCaja($pago);
        $cheques = ($pago->cheques ?? collect())->map(function ($cheque) {
            $fecha = $cheque->fechapago ?: $cheque->fechaemision;
            $caracter = (string) ($cheque->caracter ?? '');
            $caracterNombre = '';
            foreach (\App\Models\Caja\Cheque::$enumCaracter as $item) {
                if (($item['valor'] ?? '') === $caracter) {
                    $caracterNombre = (string) ($item['nombre'] ?? '');
                    break;
                }
            }

            return (object) [
                'fecha' => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : '',
                'numerocheque' => (string) ($cheque->numerocheque ?? ''),
                'banco' => (string) (optional($cheque->bancos)->nombre ?? ''),
                'caracter' => $caracterNombre !== '' ? $caracterNombre : $caracter,
                'monto' => (float) ($cheque->monto ?? 0),
                'moneda' => (string) (optional($cheque->monedas)->abreviatura ?? ''),
                'anombrede' => (string) ($cheque->anombrede ?? ''),
            ];
        })->values();
        $retenciones = ($pago->pagoproveedor_retenciones ?? collect())
            ->filter(fn ($r) => (float) $r->importe > 0)
            ->values();
        $asientoLineas = $this->armarAsiento($pago);

        $totalOp = (float) $pago->monto;
        $totalRetenciones = (float) $retenciones->sum('importe');
        $totalCheques = (float) $cheques->sum('monto');
        $totalCaja = (float) $mediosCaja->sum('monto_abs');
        $totalMedios = $totalCaja + $totalCheques;
        $totalAplicacionesMonto = (float) $aplicaciones->sum('monto');
        $totalAplicacionesAplicado = (float) $aplicaciones->sum('monto_aplicado');
        $monedasApl = $aplicaciones->pluck('moneda')->unique()->filter()->values();
        $monedaAplicaciones = $monedasApl->count() === 1 ? (string) $monedasApl->first() : '';
        $totalAsientoDebe = (float) $asientoLineas->sum(fn ($l) => (float) ($l['debe'] ?? 0));
        $totalAsientoHaber = (float) $asientoLineas->sum(fn ($l) => (float) ($l['haber'] ?? 0));

        $direccionEmpresa = trim((string) ($empresa->domicilio ?? ''));
        $localidadEmpresa = trim((string) (optional($empresa->localidad)->nombre ?? ''));
        if ($direccionEmpresa !== '' && $localidadEmpresa !== '' && stripos($direccionEmpresa, $localidadEmpresa) === false) {
            $direccionEmpresa .= ' — '.$localidadEmpresa;
        }

        $usuarioLogin = optional($pago->usuarios)->usuario
            ?: optional($pago->usuarios)->nombre
            ?: '';

        $nroOp = $pago->etiquetaComprobante();

        $lugarFecha = 'Bs.As. '.optional($pago->fecha)->format('d/m/Y');

        return [
            'pago' => $pago,
            'empresa' => $empresa,
            'proveedor' => $proveedor,
            'logo' => $logo,
            'nroOp' => $nroOp,
            'lugarFecha' => $lugarFecha,
            'direccionEmpresa' => $direccionEmpresa,
            'usuarioLogin' => $usuarioLogin,
            'aplicaciones' => $aplicaciones,
            'lineasRetencionPorId' => $lineasRetencionPorId,
            'mediosCaja' => $mediosCaja,
            'cheques' => $cheques,
            'retenciones' => $retenciones,
            'retencionesPaginas' => $retenciones,
            'asientoLineas' => $asientoLineas,
            'totalOp' => $totalOp,
            'totalRetenciones' => $totalRetenciones,
            'totalCheques' => $totalCheques,
            'totalCaja' => $totalCaja,
            'totalMedios' => $totalMedios,
            'totalAplicacionesMonto' => $totalAplicacionesMonto,
            'totalAplicacionesAplicado' => $totalAplicacionesAplicado,
            'monedaAplicaciones' => $monedaAplicaciones,
            'totalAsientoDebe' => $totalAsientoDebe,
            'totalAsientoHaber' => $totalAsientoHaber,
            'importeLetras' => mb_strtoupper(NumeroALetrasEs::monto($totalOp), 'UTF-8'),
            'monedaAbr' => (string) (optional($pago->monedas)->abreviatura ?? ''),
            'cotizacion' => (float) ($pago->cotizacion ?? 0),
            'generadoEn' => now()->format('d/m/Y H:i'),
            'soloRetencion' => false,
            'fechaDdjjGanancias' => $this->fechaPresentacionGanancias($pago->fecha),
            'periodoSuss' => $this->periodoQuincenaSuss($pago->fecha),
            'leyendasLegales' => $this->leyendasLegales(),
            'firmaAgenteRetencion' => PagoproveedorFirmaAgenteRetencionSupport::dataUri(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function armarAplicaciones(Pagoproveedor $pago): Collection
    {
        return ($pago->pagoproveedor_comprobantes ?? collect())->map(function ($apl) {
            $pcc = $apl->proveedor_cuentacorrientes;
            $cp = optional($pcc)->comprobante_proveedores;
            $tipo = optional(optional($cp)->tipotransaccion_compras)->abreviatura
                ?: (string) (optional($cp)->tipocomprobante ?? '');
            if ($tipo === '' && $pcc && PagoproveedorAplicacionLadoSupport::esOpa($pcc)) {
                $tipo = (string) (optional($pcc->pagoproveedores)->tipocomprobante ?: 'OPA');
            }
            if ($tipo === '') {
                $tipo = 'CC';
            }
            $letra = (string) (optional($cp)->letra ?? '');
            $suc = (int) (optional($cp)->sucursal ?? 0);
            $nro = (string) (optional($cp)->numerocomprobante ?? optional($pcc)->id ?? '');
            $nroFmt = $cp
                ? ($letra !== ''
                    ? sprintf('%s %s%04d-%08d', $tipo, $letra, $suc, (int) $nro)
                    : sprintf('%s %s', $tipo, $nro))
                : (string) (optional($pcc?->pagoproveedores)->etiquetaComprobante() ?: ('CC#'.(int) (optional($pcc)->id ?? 0)));

            $signo = $pcc ? PagoproveedorAplicacionLadoSupport::signo($pcc) : 1;
            $montoDoc = abs((float) (optional($cp)->total ?? optional($pcc)->total ?? $apl->montoaplicado));
            $fecha = optional($cp)->fechacomprobante ?? optional($pcc)->fecha;

            return [
                'cc_id' => (int) ($apl->proveedor_cuentacorriente_id ?? optional($pcc)->id ?? 0),
                'fecha' => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : '',
                'tipo' => $tipo,
                'numero' => $nroFmt,
                'nro_int' => (string) (optional($pcc)->id ?? ''),
                'monto' => $signo * $montoDoc,
                'moneda' => (string) (optional($apl->monedas)->abreviatura
                    ?? optional(optional($cp)->monedas)->abreviatura
                    ?? ''),
                'cotizacion' => (float) ($apl->cotizacion ?? optional($pcc)->cotizacion ?? 1),
                'monto_aplicado' => $signo * abs((float) $apl->montoaplicado),
                'neto_gravado' => $signo * abs((float) (optional($cp)->subtotal ?? $apl->montoaplicado)),
                'signo' => $signo,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $aplicaciones
     * @return array<int, list<array<string, mixed>>>
     */
    private function armarLineasCertificadoRetencion(Pagoproveedor $pago, Collection $aplicaciones): array
    {
        $payload = [];
        foreach ($pago->pagoproveedor_comprobantes ?? [] as $apl) {
            $ccId = (int) ($apl->proveedor_cuentacorriente_id ?? 0);
            $monto = abs((float) ($apl->montoaplicado ?? 0));
            if ($ccId <= 0 || $monto <= 0) {
                continue;
            }
            $payload[] = [
                'proveedor_cuentacorriente_id' => $ccId,
                'montoaplicado' => $monto,
                'cotizacion_aplicada' => (float) ($apl->cotizacion_aplicada ?? $apl->cotizacion ?? 0),
                'moneda_id' => (int) ($apl->moneda_id ?? 0) ?: null,
            ];
        }

        $bases = $payload === []
            ? null
            : $this->basesRetencionSupport->desdeAplicaciones(
                $payload,
                (int) ($pago->moneda_id ?: 1),
                (float) ($pago->cotizacion ?: 1)
            );
        $detalle = $bases?->detalle ?? [];
        $filasApl = $aplicaciones->all();
        $out = [];

        foreach ($pago->pagoproveedor_retenciones ?? [] as $ret) {
            $out[(int) $ret->id] = PagoproveedorRetencionCertificadoLineasSupport::lineas(
                $filasApl,
                $detalle,
                (string) $ret->tiporetencion,
                (float) $ret->base_calculo,
                (float) $ret->importe,
                (float) $ret->alicuota,
            );
        }

        return $out;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function armarMediosCaja(Pagoproveedor $pago): Collection
    {
        $lineas = collect();
        foreach ($pago->caja_movimientos ?? [] as $mov) {
            foreach ($mov->caja_movimiento_cuentacajas ?? [] as $linea) {
                $monto = (float) $linea->monto;
                $lineas->push([
                    'cuenta' => trim(
                        (string) (optional($linea->cuentacajas)->codigo ?? '')
                        .' '
                        .(string) (optional($linea->cuentacajas)->nombre ?? '')
                    ),
                    'monto' => $monto,
                    'monto_abs' => abs($monto),
                    'moneda' => (string) (optional($linea->monedas)->abreviatura ?? ''),
                    'cotizacion' => (float) ($linea->cotizacion ?? 1),
                ]);
            }
        }

        return $lineas->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function armarAsiento(Pagoproveedor $pago): Collection
    {
        $asiento = $pago->asientos;
        if ((! $asiento || ! $asiento->relationLoaded('asiento_movimientos') || $asiento->asiento_movimientos->isEmpty())
            && (int) ($pago->asiento_id ?? 0) > 0) {
            $asiento = Asiento::query()
                ->with(['asiento_movimientos.cuentacontables', 'asiento_movimientos.centrocostos'])
                ->find((int) $pago->asiento_id);
        }
        if (! $asiento || ! $asiento->asiento_movimientos || $asiento->asiento_movimientos->isEmpty()) {
            return collect();
        }

        return $asiento->asiento_movimientos->map(function ($am) {
            $monto = (float) ($am->monto ?? 0);

            return [
                'cuenta' => trim(
                    (string) (optional($am->cuentacontables)->codigo ?? $am->cuentacontable_id)
                    .' '
                    .(string) (optional($am->cuentacontables)->nombre ?? '')
                ),
                'debe' => $monto > 0 ? $monto : null,
                'haber' => $monto < 0 ? abs($monto) : null,
                'centrocosto' => trim(
                    (string) (optional($am->centrocostos)->codigo ?? '')
                    .' '
                    .(string) (optional($am->centrocostos)->nombre ?? '')
                ),
                'obs' => (string) ($am->observacion ?? ''),
            ];
        })->values();
    }

    /**
     * Período de la DDJJ Ganancias (mes siguiente al pago). Sin día: el vencimiento
     * exacto no es fijo en el certificado.
     */
    private function fechaPresentacionGanancias($fecha): string
    {
        if (! $fecha) {
            return '—';
        }
        $d = Carbon::parse($fecha)->startOfMonth()->addMonth();
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return ($meses[(int) $d->month] ?? $d->month).' '.$d->year;
    }

    private function periodoQuincenaSuss($fecha): string
    {
        if (! $fecha) {
            return '—';
        }
        $d = Carbon::parse($fecha);
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $quincena = ((int) $d->day <= 15) ? '1ra. Quincena' : '2da. Quincena';

        return $quincena.' de '.($meses[(int) $d->month] ?? $d->month).' de '.$d->year;
    }

    /**
     * @return array<string, string>
     */
    private function leyendasLegales(): array
    {
        return [
            Pagoproveedor_Retencion::TIPO_GANANCIAS =>
                'Este comprobante es válido para computar los importes correspondientes a retenciones sufridas en el IMPUESTO A LAS GANANCIAS (RG 830 art. 33).',
            Pagoproveedor_Retencion::TIPO_IVA =>
                'Este comprobante es válido para computar los importes correspondientes a retenciones sufridas en el IMPUESTO AL VALOR AGREGADO.',
            Pagoproveedor_Retencion::TIPO_IIBB =>
                'Este comprobante es válido para computar los importes correspondientes a retenciones sufridas en el IMPUESTO SOBRE LOS INGRESOS BRUTOS. Disp. Normativa "B" 001/02 Dirección Provincial de Rentas Art. 202.',
            Pagoproveedor_Retencion::TIPO_SUSS =>
                'Este comprobante es válido para computar los importes correspondientes a retenciones sufridas en el RÉGIMEN DE SEGURIDAD SOCIAL (SUSS).',
        ];
    }
}

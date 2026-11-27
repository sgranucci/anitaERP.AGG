<?php

namespace App\Services\Compras;

use App\Models\Compras\AplicacionCcProveedorComprobante;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Configuracion\Moneda;
use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF imprimible del comprobante APL (aplicación de cuenta corriente de proveedor).
 *
 * Por línea: tipo/número/descripción de la factura, importe del comprobante, lo aplicado en
 * este comprobante y el saldo que queda (parcial o total). Bloque aparte con el crédito
 * (OPA, NC…) y su saldo. El saldo "luego de aplicar" se calcula con las aplicaciones de esa
 * cuenta corriente hasta el id de esta línea, así no cambia si después se aplica más.
 * En comprobantes anulados las aplicaciones ya no existen: el saldo sale como no disponible.
 */
class AplicacionCcProveedorComprobantePdfService
{
    private const TOLERANCIA = 0.005;

    public function cargar(int $id): AplicacionCcProveedorComprobante
    {
        return AplicacionCcProveedorComprobante::query()
            ->with(['empresas', 'proveedores', 'monedas', 'usuarios', 'anulado_usuarios', 'lineas'])
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function datosVista(AplicacionCcProveedorComprobante $comp): array
    {
        $empresa = $comp->empresas;
        $proveedor = $comp->proveedores;
        $logo = EmpresaLogoArchivo::dataUriDesdeNombre($empresa->nombre ?? null);

        $monedas = Moneda::query()
            ->whereIn('id', $comp->lineas->flatMap(fn ($l) => [$l->moneda_deuda_id, $l->moneda_credito_id])->filter()->unique()->all())
            ->pluck('abreviatura', 'id');

        $ccIds = $comp->lineas->flatMap(fn ($l) => [$l->deuda_cc_id, $l->credito_cc_id])->filter()->unique()->all();
        $ccs = Proveedor_Cuentacorriente::query()
            ->with(['comprobante_proveedores.tipotransaccion_compras', 'comprobante_proveedor_cuotas', 'pagoproveedores'])
            ->whereIn('id', $ccIds)
            ->get()
            ->keyBy('id');

        $lineas = $comp->lineas->map(function ($l) use ($monedas, $ccs) {
            $cc = $ccs->get((int) $l->deuda_cc_id);
            $cp = $cc?->comprobante_proveedores;
            $tt = $cp?->tipotransaccion_compras;

            $tipo = strtoupper(trim((string) ($tt->abreviatura ?? '')));
            $letra = trim((string) ($cp->letra ?? ''));
            $numero = $cp
                ? sprintf('%04d-%08d', (int) $cp->sucursal, (int) $cp->numerocomprobante).ProveedorCuentacorrienteGrillaSupport::sufijoCuota($cc)
                : (string) ($l->deuda_etiqueta ?? '');
            $fecha = $cp?->fechacomprobante ?? $cc?->fecha;

            $importe = $cc ? abs((float) $cc->total) : null;
            $saldo = null;
            if ($cc && (int) $l->aplicacion_deuda_id > 0) {
                $aplicadoHasta = (float) Proveedor_Cuentacorriente_Aplicacion::query()
                    ->where('proveedor_cuentacorriente_id', (int) $cc->id)
                    ->where('id', '<=', (int) $l->aplicacion_deuda_id)
                    ->sum('total');
                $saldo = ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $cc->total, $aplicadoHasta);
            }

            return (object) [
                'fecha' => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : '',
                'tipo' => trim($tipo.' '.$letra) !== '' ? trim($tipo.' '.$letra) : 'CC',
                'tipo_nombre' => (string) ($tt->nombre ?? ''),
                'numero' => $numero,
                'descripcion' => $this->descripcion($cc, $cp),
                'credito' => (string) ($l->credito_etiqueta ?? ''),
                'moneda' => (string) ($monedas[$l->moneda_deuda_id] ?? ''),
                'importe' => $importe,
                'aplicado' => (float) $l->monto_deuda,
                'saldo' => $saldo,
                'parcial' => $saldo !== null && $saldo > self::TOLERANCIA,
                'valor_local' => (float) $l->valor_local_deuda,
                'diferencia_cambio' => (float) $l->diferencia_cambio,
            ];
        })->values();

        $creditos = $this->bloqueCreditos($comp, $ccs, $monedas);

        $direccion = trim((string) ($empresa->domicilio ?? ''));
        $localidad = trim((string) (optional($empresa->localidad)->nombre ?? ''));
        if ($direccion !== '' && $localidad !== '' && stripos($direccion, $localidad) === false) {
            $direccion .= ' — '.$localidad;
        }

        $monedaLocal = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);

        return [
            'comp' => $comp,
            'empresa' => $empresa,
            'proveedor' => $proveedor,
            'logo' => $logo,
            'direccionEmpresa' => $direccion,
            'lineas' => $lineas,
            'creditos' => $creditos,
            'variosCreditos' => $creditos->count() > 1,
            'totalPesos' => (float) $comp->total,
            'totalMoneda' => $comp->total_moneda !== null && (int) $comp->moneda_id !== $monedaLocal
                ? (float) $comp->total_moneda
                : null,
            'monedaTotal' => $comp->total_moneda !== null ? (string) ($comp->monedas->abreviatura ?? '') : '',
            'diferenciaCambio' => (float) $comp->diferencia_cambio,
            'anulado' => $comp->estaAnulado(),
            'usuarioLogin' => (string) (optional($comp->usuarios)->usuario ?: optional($comp->usuarios)->nombre ?: ''),
            'generadoEn' => now()->format('d/m/Y H:i'),
        ];
    }

    public function armarPdf(AplicacionCcProveedorComprobante $comp)
    {
        return Pdf::loadView('compras.aplicacion_cc_comprobante.comprobante', $this->datosVista($comp))
            ->setPaper('a4');
    }

    public function generarRespuesta(int $id): Response
    {
        $comp = $this->cargar($id);
        $nombre = 'aplicacion_cc_'.str_replace(' ', '_', strtolower($comp->numeroFormateado())).'.pdf';

        return $this->armarPdf($comp)->stream($nombre);
    }

    /**
     * Un renglón por crédito usado (OPA, NC…): importe, aplicado acá, aplicado antes y saldos.
     *
     * @param  \Illuminate\Support\Collection<int, Proveedor_Cuentacorriente>  $ccs
     * @param  \Illuminate\Support\Collection<int, string>  $monedas
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function bloqueCreditos(AplicacionCcProveedorComprobante $comp, $ccs, $monedas)
    {
        return $comp->lineas->groupBy('credito_cc_id')->map(function ($grupo, $creditoCcId) use ($ccs, $monedas) {
            $cc = $ccs->get((int) $creditoCcId);
            $primera = $grupo->first();
            $idsAplicacion = $grupo->pluck('aplicacion_credito_id')->filter()->map(fn ($v) => (int) $v);
            $aplicadoAca = (float) $grupo->sum(fn ($l) => abs((float) $l->monto_credito));

            $previo = null;
            $saldoLuego = null;
            $saldoActual = null;
            $importe = null;
            if ($cc) {
                $importe = abs((float) $cc->total);
                $saldoActual = ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
                    (float) $cc->total,
                    (float) Proveedor_Cuentacorriente_Aplicacion::query()->where('proveedor_cuentacorriente_id', (int) $cc->id)->sum('total')
                );
                if ($idsAplicacion->count() === $grupo->count() && $idsAplicacion->isNotEmpty()) {
                    $previo = abs((float) Proveedor_Cuentacorriente_Aplicacion::query()
                        ->where('proveedor_cuentacorriente_id', (int) $cc->id)
                        ->where('id', '<', $idsAplicacion->min())
                        ->sum('total'));
                    $saldoLuego = ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
                        (float) $cc->total,
                        (float) Proveedor_Cuentacorriente_Aplicacion::query()
                            ->where('proveedor_cuentacorriente_id', (int) $cc->id)
                            ->where('id', '<=', $idsAplicacion->max())
                            ->sum('total')
                    );
                }
            }

            $fecha = $cc?->pagoproveedores?->fecha ?? $cc?->fecha;

            return (object) [
                'etiqueta' => (string) ($primera->credito_etiqueta ?? ''),
                'fecha' => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : '',
                'moneda' => (string) ($monedas[$primera->moneda_credito_id] ?? ''),
                'importe' => $importe,
                'aplicado_aca' => $aplicadoAca,
                'previo' => $previo,
                'saldo_luego' => $saldoLuego,
                'saldo_actual' => $saldoActual,
            ];
        })->values();
    }

    /**
     * Leyenda cargada en la factura; si no hay, el detalle de la cuota o de la cuenta corriente.
     */
    private function descripcion(?Proveedor_Cuentacorriente $cc, $cp): string
    {
        $leyenda = trim((string) ($cp->leyenda ?? ''));
        if ($leyenda !== '') {
            return $leyenda;
        }
        $cuota = trim((string) (optional($cc?->comprobante_proveedor_cuotas)->detalle ?? ''));
        if ($cuota !== '') {
            return $cuota;
        }
        $leyendaCc = trim((string) ($cc->leyenda ?? ''));
        if ($leyendaCc === '' || str_starts_with($leyendaCc, ProveedorCuentacorrienteGrillaSupport::PREFIJO_LEYENDA_AJUSTE)) {
            return '';
        }

        return $leyendaCc;
    }
}

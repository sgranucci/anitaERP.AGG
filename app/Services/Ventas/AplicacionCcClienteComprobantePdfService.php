<?php

namespace App\Services\Ventas;

use App\Models\Configuracion\Moneda;
use App\Models\Ventas\AplicacionCcClienteComprobante;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\ClienteCuentacorrienteAplicacionFilaSupport;
use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF imprimible del comprobante APL (aplicación de cuenta corriente de cliente).
 *
 * Por línea: tipo/número/descripción de la factura o ND, importe del comprobante, lo aplicado en
 * este comprobante y el saldo que queda (parcial o total). Bloque aparte con el crédito
 * (COA, NC…) y su saldo. El saldo "luego de aplicar" se calcula con las aplicaciones de esa
 * cuenta corriente hasta el id de esta línea, así no cambia si después se aplica más.
 * En comprobantes anulados las aplicaciones ya no existen: el saldo sale como no disponible.
 */
class AplicacionCcClienteComprobantePdfService
{
    private const TOLERANCIA = 0.005;

    public function cargar(int $id): AplicacionCcClienteComprobante
    {
        return AplicacionCcClienteComprobante::query()
            ->with(['empresas', 'clientes', 'monedas', 'usuarios', 'anulado_usuarios', 'lineas'])
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function datosVista(AplicacionCcClienteComprobante $comp): array
    {
        $empresa = $comp->empresas;
        $cliente = $comp->clientes;
        $logo = EmpresaLogoArchivo::dataUriDesdeNombre($empresa?->nombre ?? null);

        $monedas = Moneda::query()
            ->whereIn('id', $comp->lineas->flatMap(fn ($l) => [$l->moneda_deuda_id, $l->moneda_credito_id])->filter()->unique()->all())
            ->pluck('abreviatura', 'id');

        $ccIds = $comp->lineas->flatMap(fn ($l) => [$l->deuda_cc_id, $l->credito_cc_id])->filter()->unique()->all();
        $ccs = Cliente_Cuentacorriente::query()
            ->with(['ventas.tipotransacciones', 'cobranzas'])
            ->whereIn('id', $ccIds)
            ->get()
            ->keyBy('id');

        $lineas = $comp->lineas->map(function ($l) use ($monedas, $ccs) {
            $cc = $ccs->get((int) $l->deuda_cc_id);
            $venta = $cc?->ventas;
            $tt = $venta?->tipotransacciones;

            $tipo = $cc ? ClienteCuentacorrienteAplicacionFilaSupport::abreviatura($cc) : '';
            $numero = $venta && trim((string) ($venta->codigo ?? '')) !== ''
                ? (string) $venta->codigo
                : (string) ($l->deuda_etiqueta ?? '');
            $fecha = $venta?->fecha ?? $cc?->fecha;

            $importe = $cc ? abs((float) $cc->total) : null;
            $saldo = null;
            if ($cc && (int) $l->aplicacion_deuda_id > 0) {
                $aplicadoHasta = (float) Cliente_Cuentacorriente_Aplicacion::query()
                    ->where('cliente_cuentacorriente_id', (int) $cc->id)
                    ->where('id', '<=', (int) $l->aplicacion_deuda_id)
                    ->sum('total');
                $saldo = ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $cc->total, $aplicadoHasta);
            }

            return (object) [
                'fecha' => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : '',
                'tipo' => $tipo !== '' ? $tipo : 'CC',
                'tipo_nombre' => (string) ($tt->nombre ?? ''),
                'numero' => $numero,
                'descripcion' => $this->descripcion($cc),
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

        $direccion = trim((string) ($empresa?->domicilio ?? ''));
        $localidad = trim((string) ($empresa?->localidad?->nombre ?? ''));
        if ($direccion !== '' && $localidad !== '' && stripos($direccion, $localidad) === false) {
            $direccion .= ' — '.$localidad;
        }

        $monedaLocal = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);

        return [
            'comp' => $comp,
            'empresa' => $empresa,
            'cliente' => $cliente,
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

    public function armarPdf(AplicacionCcClienteComprobante $comp)
    {
        return Pdf::loadView('ventas.aplicacion_cc_comprobante.comprobante', $this->datosVista($comp))
            ->setPaper('a4');
    }

    public function generarRespuesta(int $id): Response
    {
        $comp = $this->cargar($id);
        $nombre = 'aplicacion_cc_cliente_'.str_replace(' ', '_', strtolower($comp->numeroFormateado())).'.pdf';

        return $this->armarPdf($comp)->stream($nombre);
    }

    /**
     * Un renglón por crédito usado (COA, NC…): importe, aplicado acá, aplicado antes y saldos.
     *
     * @param  \Illuminate\Support\Collection<int, Cliente_Cuentacorriente>  $ccs
     * @param  \Illuminate\Support\Collection<int, string>  $monedas
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function bloqueCreditos(AplicacionCcClienteComprobante $comp, $ccs, $monedas)
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
                $saldoActual = ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
                    (float) $cc->total,
                    (float) Cliente_Cuentacorriente_Aplicacion::query()->where('cliente_cuentacorriente_id', (int) $cc->id)->sum('total')
                );
                if ($idsAplicacion->count() === $grupo->count() && $idsAplicacion->isNotEmpty()) {
                    $previo = abs((float) Cliente_Cuentacorriente_Aplicacion::query()
                        ->where('cliente_cuentacorriente_id', (int) $cc->id)
                        ->where('id', '<', $idsAplicacion->min())
                        ->sum('total'));
                    $saldoLuego = ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto(
                        (float) $cc->total,
                        (float) Cliente_Cuentacorriente_Aplicacion::query()
                            ->where('cliente_cuentacorriente_id', (int) $cc->id)
                            ->where('id', '<=', $idsAplicacion->max())
                            ->sum('total')
                    );
                }
            }

            $fecha = $cc?->cobranzas?->fecha ?? $cc?->fecha;

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
     * Leyenda cargada en la factura o ND; si no hay, la leyenda de la cuenta corriente (sin ajustes).
     */
    private function descripcion(?Cliente_Cuentacorriente $cc): string
    {
        $leyenda = trim((string) ($cc?->ventas?->leyenda ?? ''));
        if ($leyenda !== '') {
            return $leyenda;
        }

        $leyendaCc = trim((string) ($cc->leyenda ?? ''));
        if ($leyendaCc === '' || str_starts_with($leyendaCc, ClienteCuentacorrienteGrillaSupport::PREFIJO_LEYENDA_AJUSTE)) {
            return '';
        }

        return $leyendaCc;
    }
}

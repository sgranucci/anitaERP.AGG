<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Entrega_Prenda_Sueldos;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de la API de TuLegajo.com (V2) para subir el comprobante de entrega de
 * indumentaria al legajo digital del empleado.
 *
 * Flujo: POST /lotes/cargar-documentos con el PDF en Base64. TuLegajo asigna el
 * documento al empleado leyendo el CUIL impreso en el PDF y gestiona la firma
 * digital del empleado (por PIN); el ERP no firma nada.
 */
class TuLegajoClient
{
    public function habilitado(): bool
    {
        return (bool) config('tulegajo.enabled') && $this->apiKey(null) !== '';
    }

    /**
     * Sube el comprobante PDF de una entrega a TuLegajo.
     *
     * @return array{ok:bool, mensaje:string, http?:int}
     */
    public function subirComprobanteEntrega(Entrega_Prenda_Sueldos $entrega): array
    {
        $entrega->loadMissing(['empleado.empresa:id,nombre', 'articulos.prenda:id,codigo,descripcion', 'articulos.color:id,nombre', 'articulos.talle:id,nombre', 'usuario:id,nombre', 'deposito:id,nombre']);

        $empleado = $entrega->empleado;
        if ($empleado === null) {
            return $this->fallo($entrega, 'La entrega no tiene empleado asociado.');
        }

        $cuil = preg_replace('/\D+/', '', (string) ($empleado->cuil ?? ''));
        if ($cuil === '' || strlen($cuil) < 11) {
            return $this->fallo($entrega, 'El empleado no tiene CUIL válido; TuLegajo lo necesita para asignar el documento.');
        }

        $empresaId = (int) ($empleado->empresa_id ?? 0);
        if (! $this->configuradoParaEmpresa($empresaId)) {
            return $this->fallo($entrega, 'TuLegajo no está configurado (falta API KEY o está deshabilitado).');
        }

        $pdf = $this->generarPdf($entrega);
        if ($pdf === null) {
            return $this->fallo($entrega, 'No se pudo generar el comprobante PDF.');
        }

        $fecha = $entrega->fecha instanceof Carbon ? $entrega->fecha : Carbon::parse((string) $entrega->fecha);
        $periodo = $fecha->format('m-Y');
        $nombreDoc = 'Entrega de indumentaria #'.$entrega->id.' - '.trim((string) $empleado->nombre);

        $payload = [
            'archivo' => base64_encode($pdf),
            'nombre' => mb_substr($nombreDoc, 0, 250),
            'nombreLote' => (string) config('tulegajo.lote_nombre', 'Entregas de indumentaria'),
            'periodoLote' => $periodo,
        ];
        $tipoDoc = trim((string) config('tulegajo.tipo_documento_id', ''));
        if ($tipoDoc !== '') {
            $payload['tipoDocumentoId'] = $tipoDoc;
        }

        try {
            $resp = Http::withHeaders($this->headers($empresaId))
                ->timeout((int) config('tulegajo.timeout', 60))
                ->acceptJson()
                ->post($this->url('/lotes/cargar-documentos'), $payload);
        } catch (\Throwable $e) {
            Log::warning('tulegajo.error_http', ['entrega' => $entrega->id, 'msg' => $e->getMessage()]);

            return $this->fallo($entrega, 'Error de conexión con TuLegajo: '.$e->getMessage());
        }

        $json = $resp->json();
        $status = is_array($json) ? ($json['status'] ?? null) : null;

        if ($resp->successful() && $status !== 'error') {
            $entrega->forceFill([
                'tulegajo_estado' => 'ENVIADO',
                'tulegajo_enviado_at' => now(),
                'tulegajo_mensaje' => 'Cargado en lote "'.$payload['nombreLote'].'" ('.$periodo.').',
            ])->save();

            return ['ok' => true, 'mensaje' => 'Comprobante enviado a TuLegajo (lote '.$payload['nombreLote'].' '.$periodo.').', 'http' => $resp->status()];
        }

        $msg = 'HTTP '.$resp->status();
        if (is_array($json) && isset($json['error']['message'])) {
            $msg .= ' - '.$json['error']['message'];
        } elseif (is_string($resp->body()) && $resp->body() !== '') {
            $msg .= ' - '.mb_substr($resp->body(), 0, 180);
        }
        Log::warning('tulegajo.error_api', ['entrega' => $entrega->id, 'http' => $resp->status(), 'body' => mb_substr((string) $resp->body(), 0, 500)]);

        return $this->fallo($entrega, 'TuLegajo rechazó la carga: '.$msg, $resp->status());
    }

    private function configuradoParaEmpresa(int $empresaId): bool
    {
        return (bool) config('tulegajo.enabled') && $this->apiKey($empresaId) !== '';
    }

    private function apiKey(?int $empresaId): string
    {
        $porEmpresa = (array) config('tulegajo.api_keys', []);
        if ($empresaId && isset($porEmpresa[(string) $empresaId]) && trim((string) $porEmpresa[(string) $empresaId]) !== '') {
            return trim((string) $porEmpresa[(string) $empresaId]);
        }

        return trim((string) config('tulegajo.api_key', ''));
    }

    /**
     * @return array<string,string>
     */
    private function headers(?int $empresaId): array
    {
        return [
            'x-api-key' => $this->apiKey($empresaId),
            'User-Agent' => (string) config('tulegajo.user_agent', 'api-consumer'),
        ];
    }

    private function url(string $ruta): string
    {
        return rtrim((string) config('tulegajo.url', 'https://api.tulegajo.com/V2'), '/').'/'.ltrim($ruta, '/');
    }

    private function generarPdf(Entrega_Prenda_Sueldos $entrega): ?string
    {
        try {
            $html = \View::make('sueldos.entrega_prenda.comprobante', ['entrega' => $entrega])->render();
            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('a4');
            $pdf->loadHTML($html);

            return $pdf->output();
        } catch (\Throwable $e) {
            Log::warning('tulegajo.error_pdf', ['entrega' => $entrega->id, 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{ok:bool, mensaje:string, http?:int}
     */
    private function fallo(Entrega_Prenda_Sueldos $entrega, string $mensaje, ?int $http = null): array
    {
        $entrega->forceFill([
            'tulegajo_estado' => 'ERROR',
            'tulegajo_mensaje' => mb_substr($mensaje, 0, 255),
        ])->save();

        return ['ok' => false, 'mensaje' => $mensaje] + ($http !== null ? ['http' => $http] : []);
    }
}

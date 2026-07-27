<?php

namespace App\Services\Solicitudpago;

use App\Models\Solicitudpago\Solicitudpago;
use App\Models\Solicitudpago\Solicitudpago_Estado;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Comprobante PDF de solicitud de pago (equivalente Anita lista_solicitud / Emite).
 */
class SolicitudpagoComprobantePdfService
{
    /**
     * @return array{pdf: \Barryvdh\DomPDF\PDF, nombre: string}
     */
    public function generar(int $solicitudpagoId): array
    {
        $data = Solicitudpago::query()
            ->with([
                'empresas',
                'proveedores',
                'conceptos',
                'formapagosol',
                'monedas',
                'sectores',
                'centrocostos',
                'madre',
                'cuentas.empresas',
                'cuentas.cuentacontables',
                'cuentas.centrocostos',
                'cuotas.hijas',
                'archivos.usuarios',
                'estados.usuarios',
            ])
            ->findOrFail($solicitudpagoId);

        $emitio = $this->usuarioEmisor($data);
        $tratamientoLabel = $this->etiquetaTratamiento($data->tratamiento ?? '');
        $estadoLabel = SolicitudpagoEstados::label($data->estado ?? '');
        $logo = EmpresaLogoArchivo::dataUriDesdeNombre(optional($data->empresas)->nombre);
        $logoEmpresaDataUri = $logo['uri'] ?? null;

        $html = view('solicitudpago.solicitudpago.comprobante', [
            'data' => $data,
            'emitio' => $emitio,
            'tratamientoLabel' => $tratamientoLabel,
            'estadoLabel' => $estadoLabel,
            'logoEmpresaDataUri' => $logoEmpresaDataUri,
            'generadoEn' => now()->format('d/m/Y H:i'),
        ])->render();

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $nombre = 'Solicitud_pago_'.preg_replace('/[^\w\-]+/', '_', (string) $data->codigo).'.pdf';

        return [
            'pdf' => $pdf,
            'nombre' => $nombre,
        ];
    }

    /**
     * @return array{id: ?int, nombre: string}|null
     */
    private function usuarioEmisor(Solicitudpago $sp): ?array
    {
        $estadoAlta = $sp->estados
            ->filter(fn (Solicitudpago_Estado $e) => strtoupper((string) $e->estado_actual) === SolicitudpagoEstados::EMITIDA)
            ->sortBy('id')
            ->first();

        if ($estadoAlta && $estadoAlta->usuarios) {
            return [
                'id' => (int) $estadoAlta->usuario_id,
                'nombre' => (string) $estadoAlta->usuarios->nombre,
            ];
        }

        return null;
    }

    private function etiquetaTratamiento(?string $tratamiento): string
    {
        foreach (SolicitudpagoTratamientos::opciones() as $opt) {
            if (($opt['valor'] ?? '') === $tratamiento) {
                return (string) $opt['nombre'];
            }
        }

        return $tratamiento !== null && $tratamiento !== '' ? $tratamiento : '—';
    }
}

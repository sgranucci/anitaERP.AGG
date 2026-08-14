<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Models\Compras\Ordencompra;
use App\Support\Compras\OrdencompraContratoRutaFacturaSupport;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Illuminate\Support\Facades\Auth;

/**
 * Revisión de una OC configurada como contrato con factura directa, sin recepción COM.
 */
class ComprasOrdencompraContratoSinComAvisoHandler implements ModuloAvisoHandlerInterface
{
    public function contextoFiltro(int $entityId): array
    {
        $oc = $this->cargar($entityId);

        return [
            'empresa_id' => (int) ($oc->empresa_id ?? 0) ?: null,
            'centrocosto_id' => (int) ($oc->centrocosto_id ?? 0) ?: null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $oc = $this->cargar($entityId);
        $usuario = Auth::user();
        $numero = trim((string) ($oc->numeroordencompra ?? ''));
        $cuenta = $oc->contrato_cuentacontables;
        $cuentaTexto = 'No corresponde: la imputación se obtiene de los artículos de la OC';

        if ($cuenta) {
            $codigo = trim((string) ($cuenta->codigo ?? ''));
            $nombre = trim((string) ($cuenta->nombre ?? $cuenta->descripcion ?? ''));
            $cuentaTexto = trim($codigo.' — '.$nombre, " \t\n\r\0\x0B—");
        }

        return [
            'id' => (string) $oc->id,
            'numero' => $numero !== '' ? $numero : '#'.$oc->id,
            'empresa' => (string) (optional($oc->empresas)->nombre ?? '—'),
            'proveedor' => (string) (optional($oc->proveedores)->nombre ?? '—'),
            'centrocosto' => (string) (optional($oc->centrocostos)->nombre ?? '—'),
            'detalle' => (string) ($oc->detalle ?? '—'),
            'tratamiento' => (string) ($oc->tratamiento ?? '—'),
            'imputacion' => OrdencompraContratoRutaFacturaSupport::etiquetaImputacion(
                OrdencompraContratoRutaFacturaSupport::normalizarImputacion(
                    $oc->contrato_imputacion_contable
                )
            ),
            'cuenta_contable' => $cuentaTexto !== '' ? $cuentaTexto : '—',
            'responsable' => (string) (optional($oc->contrato_responsables)->nombre
                ?? optional($oc->contrato_responsables)->usuario
                ?? '—'),
            'vigencia_desde' => $oc->contrato_vigencia_desde?->format('d/m/Y') ?? '—',
            'vigencia_hasta' => $oc->contrato_vigencia_hasta?->format('d/m/Y') ?? '—',
            'usuario_cambio' => (string) (optional($usuario)->nombre
                ?? optional($usuario)->usuario
                ?? 'Proceso automático'),
            'fecha_cambio' => now()->format('d/m/Y H:i'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return ModoConsultaUrlSupport::route('editar_ordencompra', ['id' => $entityId]);
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    private function cargar(int $entityId): Ordencompra
    {
        return Ordencompra::query()
            ->with([
                'empresas:id,nombre',
                'proveedores:id,nombre',
                'centrocostos:id,nombre',
                'contrato_responsables:id,nombre,usuario',
                'contrato_cuentacontables',
            ])
            ->findOrFail($entityId);
    }
}

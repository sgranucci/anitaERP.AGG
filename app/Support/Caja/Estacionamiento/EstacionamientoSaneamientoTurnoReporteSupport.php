<?php

namespace App\Support\Caja\Estacionamiento;

use App\Models\Configuracion\Empresa;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Support\Facades\Auth;

/**
 * Datos para el informe PDF de diagnóstico de saneamiento de turnos.
 */
final class EstacionamientoSaneamientoTurnoReporteSupport
{
    /**
     * @param  array<string, mixed>  $diagnostico  Respuesta de EstacionamientoTurnoSaneamientoService::diagnostico()
     * @return array<string, mixed>
     */
    public function datosInformePdf(array $diagnostico, ?string $empresaNombre = null): array
    {
        $empresaId = (int) ($diagnostico['empresa_id'] ?? 0);
        if ($empresaNombre === null || $empresaNombre === '') {
            $empresaNombre = Empresa::query()->where('id', $empresaId)->value('nombre') ?? '';
        }

        $terminales = $diagnostico['terminales'] ?? [];
        $totalHuerfanas = 0;
        $totalCuentasPendientes = 0;
        foreach ($terminales as $t) {
            $totalHuerfanas += (int) ($t['cantidad_huerfanas'] ?? 0);
            $totalCuentasPendientes += (int) ($t['cuentas_pendientes'] ?? 0);
        }

        $usuario = Auth::user();

        return [
            'titulo' => 'Informe de saneamiento — turnos estacionamiento',
            'subtitulo' => 'Diagnóstico de facturas huérfanas y cuentas pendientes',
            'logo' => EmpresaLogoArchivo::dataUriDesdeNombre($empresaNombre),
            'empresa_nombre' => $empresaNombre,
            'fecha_emision' => now()->format('d/m/Y H:i'),
            'usuario_emision' => $usuario?->nombre ?? $usuario?->usuario ?? '',
            'jornada' => $diagnostico['jornada'] ?? [],
            'requiere_habilitacion_turno' => ! empty($diagnostico['requiere_habilitacion_turno']),
            'terminales' => $terminales,
            'resumen' => [
                'terminales' => count($terminales),
                'facturas_huerfanas' => $totalHuerfanas,
                'cuentas_pendientes' => $totalCuentasPendientes,
            ],
        ];
    }
}

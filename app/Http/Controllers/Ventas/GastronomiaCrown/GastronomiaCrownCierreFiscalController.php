<?php

namespace App\Http\Controllers\Ventas\GastronomiaCrown;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownCierreFiscal;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Caja\TesoreriaCrownCierreFiscalDesdeVentaService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Pantalla Crown equivalente a Anita p-cierrefact: cierra facturación (Z) desde venta ERP.
 */
class GastronomiaCrownCierreFiscalController extends Controller
{
    public function index(Request $request): View
    {
        $this->assertCrown();
        can('listar-cierre-fiscal-gastronomia-crown');

        $fecha = trim((string) $request->input('fecha', now()->format('Y-m-d')));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = now()->format('Y-m-d');
        }
        $sucursalOpt = trim((string) $request->input('sucursal', ''));
        $sucursal = $sucursalOpt !== '' ? (int) $sucursalOpt : null;
        $consultar = (string) $request->input('consultar', '') === '1';

        $jornada = TesoreriaCrownJornada::query()
            ->whereDate('fecha', $fecha)
            ->orderBy('id')
            ->first();

        $existentes = TesoreriaCrownCierreFiscal::query()
            ->whereDate('fecha_jor', $fecha)
            ->where('nro_z', '>', 0)
            ->when($sucursal !== null, fn ($q) => $q->where('sucursal', $sucursal))
            ->orderBy('sucursal')
            ->get();

        $preview = null;
        $errorPreview = null;
        if ($consultar) {
            try {
                $preview = TesoreriaCrownCierreFiscalDesdeVentaService::procesarFecha(
                    $fecha,
                    $jornada,
                    $sucursal,
                    false,
                    true,
                    false,
                );
            } catch (Throwable $e) {
                $errorPreview = $e->getMessage();
            }
        }

        return view('ventas.gastronomia_crown.cierre_fiscal.index', [
            'fecha' => $fecha,
            'sucursal' => $sucursalOpt,
            'jornada' => $jornada,
            'existentes' => $existentes,
            'preview' => $preview,
            'error_preview' => $errorPreview,
            'consultar' => $consultar,
            'puede_procesar' => can('procesar-cierre-fiscal-gastronomia-crown', false),
        ]);
    }

    public function procesar(Request $request)
    {
        $this->assertCrown();
        can('procesar-cierre-fiscal-gastronomia-crown');

        $fecha = trim((string) $request->input('fecha', ''));
        $sucursalOpt = trim((string) $request->input('sucursal', ''));
        $sucursal = $sucursalOpt !== '' ? (int) $sucursalOpt : null;
        $forzar = (bool) $request->boolean('forzar_tomados');

        $volver = [
            'fecha' => $fecha,
            'sucursal' => $sucursalOpt,
            'consultar' => 1,
        ];

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return redirect()
                ->route('gastronomia_crown_cierre_fiscal', $volver)
                ->with('errores', ['Indique una fecha de jornada válida (Y-m-d).']);
        }

        $jornada = TesoreriaCrownJornada::query()
            ->whereDate('fecha', $fecha)
            ->orderBy('id')
            ->first();

        try {
            $resultado = TesoreriaCrownCierreFiscalDesdeVentaService::procesarFecha(
                $fecha,
                $jornada,
                $sucursal,
                $forzar,
                false,
                false,
            );
        } catch (Throwable $e) {
            return redirect()
                ->route('gastronomia_crown_cierre_fiscal', $volver)
                ->with('errores', [$e->getMessage()]);
        }

        $msg = sprintf(
            'Cierre fiscal Z: %d sucursales | creados %d | actualizados %d | omitidos %d.',
            $resultado['sucursales'],
            $resultado['creados'],
            $resultado['actualizados'],
            $resultado['omitidos_tomados']
        );

        return redirect()
            ->route('gastronomia_crown_cierre_fiscal', $volver)
            ->with('mensaje', $msg);
    }

    private function assertCrown(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
    }
}

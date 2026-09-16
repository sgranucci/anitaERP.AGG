<?php

declare(strict_types=1);

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\MacroArchivoPagoService;
use App\Support\Caja\Macro\MacroArchivoPagoFiltros;
use App\Support\Caja\Macro\MacroArchivoPagoFormatoSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\View\View;

/**
 * Exportación pagos Banco Macro por archivo (diskette), ERP + Anita.
 * Equivalente a p-enviamacro.c. Canal webservice queda abierto en config/macro.php.
 */
class MacroArchivoPagoController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'macro_archivo_pago';

    private const PERMISO = 'generar-archivo-pago-macro';

    public function __construct(
        private readonly MacroArchivoPagoService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        can(self::PERMISO);

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = MacroArchivoPagoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferencias($request, $filtros, $empresaQuery);
        $cuentaOrigen = $this->hidratarCuentaOrigen($request, $filtros);
        $filtros['cuentacaja_id'] = $cuentaOrigen ? (int) $cuentaOrigen->id : (int) ($filtros['cuentacaja_id'] ?? 0);
        $filtros['cuenta_anita'] = $cuentaOrigen
            ? MacroArchivoPagoFiltros::padCuentaAnita((string) $cuentaOrigen->codigo)
            : (string) ($filtros['cuenta_anita'] ?? '');

        $empAnita = (int) ($filtros['empresa_id'] ?? 0) > 0
            ? SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $filtros['empresa_id'])
            : 0;
        $sucBanco = (int) ($filtros['sucursal_banco'] ?? 0);
        if ($sucBanco <= 0) {
            $sucBanco = (int) config('macro.sucursal_default', 651);
            $filtros['sucursal_banco'] = $sucBanco;
        }

        // Cuenta débito: siempre desde CBU de la cuenta (como Anita) o mapa por empresa.
        $cuentaDebito = '';
        if ($cuentaOrigen !== null) {
            $cuentaDebito = MacroArchivoPagoFormatoSupport::cuentaDebitoDesdeCbu(
                (string) $cuentaOrigen->cbu,
                $sucBanco
            );
        }
        if ($cuentaDebito === '' && $empAnita > 0) {
            $cuentaDebito = MacroArchivoPagoFormatoSupport::cuentaDebitoEmpresa($empAnita);
        }
        $filtros['cuenta_debito'] = $cuentaDebito;

        if (($filtros['usuario_retencion'] ?? '') === '' && $empAnita > 0) {
            $filtros['usuario_retencion'] = MacroArchivoPagoFormatoSupport::usuarioRetencionEmpresa($empAnita);
        }

        $consultado = $request->boolean('consultar')
            && MacroArchivoPagoFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        if ($consultado) {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '180');
            $this->persistirPreferencias($filtros);
            $resultado = $this->service->generar($filtros);
        }

        $filtrosQuery = MacroArchivoPagoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }

        $usuariosRetencion = (array) config('macro.usuarios_retencion', []);

        return view('caja.macro.archivo_pago', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'cuenta_origen' => $cuentaOrigen,
            'canal' => (string) config('macro.canal', 'archivo'),
            'usuarios_retencion' => $usuariosRetencion,
        ]);
    }

    public function descargar(Request $request): \Illuminate\Http\Response|RedirectResponse
    {
        can(self::PERMISO);

        $filtros = MacroArchivoPagoFiltros::resolverDesdeRequest($request);
        if (! MacroArchivoPagoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('macro_archivo_pago')
                ->with('mensaje_error', 'Indique empresa y fechas para generar el archivo.');
        }

        $cuenta = $this->service->resolverCuentaOrigen(
            (int) $filtros['empresa_id'],
            (int) ($filtros['cuentacaja_id'] ?? 0),
            (string) ($filtros['cuenta_anita'] ?? '')
        );
        if ($cuenta === null) {
            return redirect()->route('macro_archivo_pago', MacroArchivoPagoFiltros::paraQueryString($filtros))
                ->with('mensaje_error', 'Seleccione una cuenta de caja Macro.');
        }
        $filtros['cuentacaja_id'] = (int) $cuenta->id;
        $filtros['cuenta_anita'] = MacroArchivoPagoFiltros::padCuentaAnita((string) $cuenta->codigo);

        $empAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $filtros['empresa_id']);
        $sucBanco = (int) ($filtros['sucursal_banco'] ?? 0);
        if ($sucBanco <= 0) {
            $sucBanco = (int) config('macro.sucursal_default', 651);
            $filtros['sucursal_banco'] = $sucBanco;
        }
        $cuentaDebito = MacroArchivoPagoFormatoSupport::cuentaDebitoDesdeCbu((string) $cuenta->cbu, $sucBanco);
        if ($cuentaDebito === '') {
            $cuentaDebito = MacroArchivoPagoFormatoSupport::cuentaDebitoEmpresa($empAnita);
        }
        $filtros['cuenta_debito'] = $cuentaDebito;
        if (($filtros['usuario_retencion'] ?? '') === '') {
            $filtros['usuario_retencion'] = MacroArchivoPagoFormatoSupport::usuarioRetencionEmpresa($empAnita);
        }

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '180');

        $resultado = $this->service->generar($filtros);
        if (empty($resultado['ok']) || ($resultado['cantidad'] ?? 0) <= 0) {
            return redirect()
                ->route('macro_archivo_pago', array_merge(
                    MacroArchivoPagoFiltros::paraQueryString($filtros),
                    ['consultar' => 1]
                ))
                ->with('mensaje_error', $resultado['mensaje'] ?? 'Sin pagos Macro para exportar.');
        }

        $this->persistirPreferencias($filtros);

        $export = $resultado['export'] ?? [];
        $nombre = (string) ($export['nombre'] ?? 'macro_pagos.zip');
        $mime = (string) ($export['mime'] ?? 'application/zip');
        $contenido = (string) ($export['contenido'] ?? '');

        return ResponseFacade::make($contenido, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarPreferencias(Request $request, array $filtros, $empresaQuery): array
    {
        if (! $request->has('empresa_id') && (int) ($filtros['empresa_id'] ?? 0) <= 0) {
            $empresaPref = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
            $permitidas = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($empresaPref !== null && in_array($empresaPref, $permitidas, true)) {
                $filtros['empresa_id'] = $empresaPref;
            } elseif (count($permitidas) === 1) {
                $filtros['empresa_id'] = $permitidas[0];
            }
        }

        foreach (['cuentacaja_id', 'cuenta_anita', 'sucursal_banco', 'usuario_retencion', 'tipo_op', 'tipo_aplicacion'] as $clave) {
            if ($request->filled($clave)) {
                continue;
            }
            $pref = ReportePreferenciasUsuario::leerString(self::PREFERENCIAS_CLAVE, $clave);
            if ($pref === '') {
                continue;
            }
            if (in_array($clave, ['cuentacaja_id', 'sucursal_banco'], true) && ctype_digit($pref)) {
                $filtros[$clave] = (int) $pref;
            } else {
                $filtros[$clave] = $pref;
            }
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function hidratarCuentaOrigen(Request $request, array $filtros): ?Cuentacaja
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $cuentaId = (int) ($filtros['cuentacaja_id'] ?? 0);
        $hint = (string) ($filtros['cuenta_anita'] ?? '');

        if ($request->filled('cuentacaja_id') || $cuentaId > 0) {
            $porId = $this->service->buscarCuentaOrigen($empresaId, $cuentaId);
            if ($porId !== null) {
                return $porId;
            }
        }

        return $this->service->resolverCuentaOrigen($empresaId, 0, $hint);
    }

    /** @param  array<string, mixed>  $filtros */
    private function persistirPreferencias(array $filtros): void
    {
        ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
        ]);
        foreach ([
            'cuentacaja_id' => (string) max(0, (int) ($filtros['cuentacaja_id'] ?? 0)),
            'cuenta_anita' => (string) ($filtros['cuenta_anita'] ?? ''),
            'cuenta_debito' => (string) ($filtros['cuenta_debito'] ?? ''),
            'sucursal_banco' => (string) max(0, (int) ($filtros['sucursal_banco'] ?? 0)),
            'usuario_retencion' => (string) ($filtros['usuario_retencion'] ?? ''),
            'tipo_op' => (string) ($filtros['tipo_op'] ?? '0'),
            'tipo_aplicacion' => (string) ($filtros['tipo_aplicacion'] ?? ''),
        ] as $k => $v) {
            ReportePreferenciasUsuario::persistirString(self::PREFERENCIAS_CLAVE, $k, $v);
        }
    }
}

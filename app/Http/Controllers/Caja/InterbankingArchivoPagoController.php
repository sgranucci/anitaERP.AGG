<?php

declare(strict_types=1);

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\InterbankingArchivoPagoService;
use App\Support\Caja\InterbankingArchivoPagoFiltros;
use App\Support\Compras\CbuSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\View\View;

/**
 * Archivo ASCII de transferencias Interbanking (ERP + Anita), equivalente a p-pagoxbanco.
 */
class InterbankingArchivoPagoController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'interbanking_archivo_pago';

    private const PERMISO = 'generar-archivo-pago-interbanking';

    public function __construct(
        private readonly InterbankingArchivoPagoService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        can(self::PERMISO);

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = InterbankingArchivoPagoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferencias($request, $filtros, $empresaQuery);
        $cuentaOrigen = $this->hidratarCuentaOrigen($request, $filtros);
        $filtros['cuentacaja_id'] = $cuentaOrigen ? (int) $cuentaOrigen->id : (int) ($filtros['cuentacaja_id'] ?? 0);
        $filtros['cbu_origen'] = $cuentaOrigen
            ? CbuSupport::normalizar((string) $cuentaOrigen->cbu)
            : '';

        $consultado = $request->boolean('consultar')
            && InterbankingArchivoPagoFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        if ($consultado) {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '180');
            $this->persistirPreferencias($filtros);
            $resultado = $this->service->generar($filtros);
        }

        $filtrosQuery = InterbankingArchivoPagoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }

        return view('caja.interbanking.archivo_pago', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'cuenta_origen' => $cuentaOrigen,
        ]);
    }

    public function descargar(Request $request): \Illuminate\Http\Response|RedirectResponse
    {
        can(self::PERMISO);

        $filtros = InterbankingArchivoPagoFiltros::resolverDesdeRequest($request);
        if (! InterbankingArchivoPagoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('interbanking_archivo_pago')
                ->with('mensaje_error', 'Indique empresa y fechas para generar el archivo.');
        }

        $cbu = $this->service->cbuOrigenDesdeFiltros($filtros);
        if (! CbuSupport::esValido($cbu)) {
            return redirect()->route('interbanking_archivo_pago', InterbankingArchivoPagoFiltros::paraQueryString($filtros))
                ->with('mensaje_error', 'Seleccione una cuenta de caja con CBU válido.');
        }
        $filtros['cbu_origen'] = $cbu;

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '180');

        $resultado = $this->service->generar($filtros);
        if (empty($resultado['ok']) || ($resultado['cantidad'] ?? 0) <= 0) {
            return redirect()
                ->route('interbanking_archivo_pago', array_merge(
                    InterbankingArchivoPagoFiltros::paraQueryString($filtros),
                    ['consultar' => 1]
                ))
                ->with('mensaje_error', $resultado['mensaje'] ?? 'Sin transferencias para exportar.');
        }

        $this->persistirPreferencias(array_merge($filtros, [
            'secuencia' => (int) $filtros['secuencia'] + 1,
        ]));

        $nombre = sprintf('pagobanco_%s_%08d.txt', date('Ymd_His'), (int) $filtros['secuencia']);

        return ResponseFacade::make($resultado['archivo'], 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
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

        if (! $request->filled('cuentacaja_id')) {
            $idPref = ReportePreferenciasUsuario::leerString(self::PREFERENCIAS_CLAVE, 'cuentacaja_id');
            if ($idPref !== '' && ctype_digit($idPref)) {
                $filtros['cuentacaja_id'] = (int) $idPref;
            }
        }

        if (! $request->filled('cbu_origen')) {
            $cbuPref = ReportePreferenciasUsuario::leerString(self::PREFERENCIAS_CLAVE, 'cbu_origen');
            if ($cbuPref !== '') {
                $filtros['cbu_origen'] = $cbuPref;
            }
        }

        if (! $request->filled('secuencia')) {
            $secPref = ReportePreferenciasUsuario::leerString(self::PREFERENCIAS_CLAVE, 'secuencia');
            if ($secPref !== '' && ctype_digit($secPref)) {
                $filtros['secuencia'] = max(1, (int) $secPref);
            }
        }

        if (! $request->filled('tipo_aplicacion')) {
            $filtros['tipo_aplicacion'] = ReportePreferenciasUsuario::leerString(
                self::PREFERENCIAS_CLAVE,
                'tipo_aplicacion',
                (string) ($filtros['tipo_aplicacion'] ?? '')
            );
        }

        if (! $request->filled('tipo_op')) {
            $tipoPref = ReportePreferenciasUsuario::leerString(self::PREFERENCIAS_CLAVE, 'tipo_op');
            if ($tipoPref !== '') {
                $filtros['tipo_op'] = $tipoPref;
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
        $cbuHint = (string) ($filtros['cbu_origen'] ?? '');

        if ($request->filled('cuentacaja_id') || $cuentaId > 0) {
            $porId = $this->service->buscarCuentaOrigen($empresaId, $cuentaId);
            if ($porId !== null) {
                return $porId;
            }
        }

        return $this->service->resolverCuentaOrigen($empresaId, 0, $cbuHint);
    }

    /** @param  array<string, mixed>  $filtros */
    private function persistirPreferencias(array $filtros): void
    {
        ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
        ]);
        ReportePreferenciasUsuario::persistirString(
            self::PREFERENCIAS_CLAVE,
            'cuentacaja_id',
            (string) max(0, (int) ($filtros['cuentacaja_id'] ?? 0))
        );
        ReportePreferenciasUsuario::persistirString(
            self::PREFERENCIAS_CLAVE,
            'cbu_origen',
            (string) ($filtros['cbu_origen'] ?? '')
        );
        ReportePreferenciasUsuario::persistirString(
            self::PREFERENCIAS_CLAVE,
            'secuencia',
            (string) max(1, (int) ($filtros['secuencia'] ?? 1))
        );
        ReportePreferenciasUsuario::persistirString(
            self::PREFERENCIAS_CLAVE,
            'tipo_aplicacion',
            (string) ($filtros['tipo_aplicacion'] ?? '')
        );
        ReportePreferenciasUsuario::persistirString(
            self::PREFERENCIAS_CLAVE,
            'tipo_op',
            (string) ($filtros['tipo_op'] ?? 'OPP')
        );
    }
}

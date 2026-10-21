<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\EstructuraPersonalExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Sueldos\EstructuraPersonalMotor;
use App\Support\Sueldos\EstructuraPersonalPresentacion;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class EstructuraPersonalController extends Controller
{
    public function __construct(
        private readonly EstructuraPersonalMotor $motor,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-estructura-personal-sueldos');

        $corte = $this->corteDesdeRequest($request);
        $empresaId = $this->empresaDesdeRequest($request);
        $reporte = $this->motor->reporte($corte, $empresaId);
        $reporte['url_empleado'] = can('editar-empleado-sueldos', false)
            ? route('editar_empleado_sueldos', ['id' => '__ID__', 'origen' => 'modal_consulta', 'vista' => 'consulta'])
            : null;

        return view('sueldos.estructura_personal.index', [
            'reporte' => $reporte,
            'meses' => $this->mesesDelAnio($corte),
            'anios' => $this->anios($corte),
        ]);
    }

    public function excel(Request $request)
    {
        can('listar-estructura-personal-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $corte = $this->corteDesdeRequest($request);
        $empresaId = $this->empresaDesdeRequest($request);
        $reporte = $this->motor->reporte($corte, $empresaId);

        return (new EstructuraPersonalExport($reporte))
            ->download('estructura-personal-'.$corte.'.xlsx');
    }

    private function corteDesdeRequest(Request $request): string
    {
        $hoy = CarbonImmutable::now()->startOfDay();
        $texto = trim((string) $request->input('corte', ''));
        try {
            $fecha = $texto !== '' ? CarbonImmutable::parse($texto)->startOfDay() : $hoy;
        } catch (\Throwable) {
            $fecha = $hoy;
        }
        if ($fecha->greaterThan($hoy) || $fecha->year < 2000) {
            $fecha = $hoy;
        }

        return $fecha->toDateString();
    }

    private function empresaDesdeRequest(Request $request): int
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        return $empresaId > 0 ? $empresaId : 0;
    }

    /**
     * @return list<array{label: string, fecha: ?string, activo: bool, futuro: bool}>
     */
    private function mesesDelAnio(string $corte): array
    {
        $fecha = CarbonImmutable::parse($corte)->startOfDay();
        $hoy = CarbonImmutable::now()->startOfDay();
        $meses = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $fin = CarbonImmutable::create($fecha->year, $mes, 1)->endOfMonth()->startOfDay();
            $futuro = $fin->greaterThan($hoy) && $fin->format('Y-m') !== $hoy->format('Y-m');
            $destino = null;
            if (! $futuro) {
                $destino = $fin->format('Y-m') === $hoy->format('Y-m') ? $hoy : $fin;
            }
            $meses[] = [
                'label' => EstructuraPersonalPresentacion::mesCorto($mes),
                'fecha' => $destino?->toDateString(),
                'activo' => $mes === $fecha->month,
                'futuro' => $futuro,
            ];
        }

        return $meses;
    }

    /**
     * @return list<int>
     */
    private function anios(string $corte): array
    {
        $anio = (int) substr($corte, 0, 4);
        $actual = (int) date('Y');

        return array_values(array_unique([$actual, $actual - 1, $actual - 2, $anio]));
    }
}

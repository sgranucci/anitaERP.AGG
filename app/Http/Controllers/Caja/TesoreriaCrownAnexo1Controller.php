<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownAnexo1Calculo;
use App\Support\Caja\TesoreriaCrownAnexo1NewCalculo;
use App\Support\Caja\TesoreriaCrownAnexo2Calculo;
use App\Support\Caja\TesoreriaCrownAnexo7Calculo;
use App\Support\Caja\TesoreriaCrownAnexo8Calculo;
use App\Support\Caja\TesoreriaCrownAnexoRangoSupport;
use App\Support\Caja\TesoreriaCrownJornadaConsultaSupport;
use App\Support\Caja\TesoreriaCrownResumenCajeroCalculo;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TesoreriaCrownAnexo1Controller extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-anexo');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $salaId = $sala ? (int) $sala->id : 0;

        $paneles = ['anexo', 'anexo2', 'rescajero', 'anexo3', 'anexo7', 'anexo8'];
        $panel = (string) $request->query('panel', 'anexo');
        if (! in_array($panel, $paneles, true)) {
            $panel = 'anexo';
        }

        $modoRango = TesoreriaCrownAnexoRangoSupport::panelAdmiteRango($panel);
        $fechasRango = TesoreriaCrownAnexoRangoSupport::resolverFechas($request);
        $fechaDesde = $fechasRango['fecha_desde'];
        $fechaHasta = $fechasRango['fecha_hasta'];
        $fechaPedida = trim((string) $request->input('fecha', $fechaHasta ?? $fechaDesde ?? ''));
        $consultar = $request->boolean('consultar')
            || $request->filled('fecha_desde')
            || $request->filled('fecha_hasta')
            || $request->filled('fecha')
            || $request->filled('jornada_id');

        /** @var Collection<int, TesoreriaCrownJornada> $jornadasRango */
        $jornadasRango = collect();
        $jornada = null;

        if ($salaId > 0) {
            if ($modoRango) {
                if ($fechaDesde !== null && $fechaHasta !== null && $consultar) {
                    $jornadasRango = TesoreriaCrownAnexoRangoSupport::jornadasEnRango($salaId, $fechaDesde, $fechaHasta);
                    $jornada = $jornadasRango->last();
                } elseif ($fechaDesde === null && $fechaHasta === null) {
                    // Solo precarga fechas en el form; no calcula hasta Consultar.
                    $sugerida = TesoreriaCrownJornada::query()
                        ->where('sala_id', $salaId)
                        ->orderByDesc('fecha')
                        ->first();
                    if ($sugerida) {
                        $ymd = $sugerida->fecha->format('Y-m-d');
                        $fechaDesde = $ymd;
                        $fechaHasta = $ymd;
                        $fechaPedida = $ymd;
                    }
                }
            } else {
                // Al venir del tab I/VIII con rango, usar "hasta" como jornada del resto de anexos.
                if (! $request->filled('fecha') && ! $request->filled('jornada_id') && $fechaHasta !== null) {
                    $request->merge(['fecha' => $fechaHasta]);
                    $fechaPedida = $fechaHasta;
                }
                $jornada = TesoreriaCrownJornadaConsultaSupport::pedida($request, $salaId);
                if ($jornada) {
                    $jornadasRango = collect([$jornada]);
                }
            }
        }

        $anexo = null;
        $anexoError = null;
        $anexoVariante = $request->input('formato_anexo') === 'nuevo' ? 'nuevo' : 'clasico';
        $anexo2 = $rescajero = $anexo3 = $anexo7 = $anexo8 = null;
        $anexo2Error = $rescajeroError = $anexo3Error = $anexo7Error = $anexo8Error = null;
        $clientesFormulario = 0;
        /** @var list<array{jornada: TesoreriaCrownJornada, anexo: array<string, mixed>|null, error: string|null}> $anexosRango */
        $anexosRango = [];
        /** @var list<array{jornada: TesoreriaCrownJornada, anexo8: array<string, mixed>|null, error: string|null}> $anexos8Rango */
        $anexos8Rango = [];

        if ($jornada && ! $modoRango) {
            if ($panel === 'anexo2') {
                try {
                    $grupoAnexo2 = TesoreriaCrownAnexo2Calculo::resolverGrupoPermitido($request->query('grupo_anexo2'));
                    if ($grupoAnexo2 === null) {
                        $anexo2Error = 'No tenés permiso para ningún grupo del anexo II (mesas, máquinas, gastronomía o todo). Pedile a Sistemas que lo tilde en el rol.';
                    } else {
                        $anexo2 = TesoreriaCrownAnexo2Calculo::deJornada($jornada, $grupoAnexo2);
                    }
                } catch (\RuntimeException $e) {
                    $anexo2Error = $e->getMessage();
                }
            } elseif ($panel === 'rescajero') {
                try {
                    $rescajero = TesoreriaCrownResumenCajeroCalculo::deJornada($jornada);
                } catch (\RuntimeException $e) {
                    $rescajeroError = $e->getMessage();
                }
            } elseif ($panel === 'anexo3') {
                try {
                    $anexo3 = TesoreriaCrownResumenCajeroCalculo::deJornada($jornada);
                } catch (\RuntimeException $e) {
                    $anexo3Error = $e->getMessage();
                }
            } elseif ($panel === 'anexo7') {
                try {
                    $anexo7 = TesoreriaCrownAnexo7Calculo::deJornada($jornada);
                } catch (\RuntimeException $e) {
                    $anexo7Error = $e->getMessage();
                }
            }
            $clientesFormulario = Schema::hasColumn('tesoreria_crown_jornada', 'clientes')
                ? TesoreriaCrownAnexo1Calculo::clientesParaFormulario($jornada)
                : 0;
        }

        if ($modoRango && $jornadasRango->isNotEmpty() && $consultar) {
            if ($panel === 'anexo') {
                foreach ($jornadasRango as $jornadaDia) {
                    $armado = null;
                    $error = null;
                    try {
                        $armado = $anexoVariante === 'nuevo'
                            ? TesoreriaCrownAnexo1NewCalculo::deJornada($jornadaDia)
                            : TesoreriaCrownAnexo1Calculo::deJornada($jornadaDia);
                    } catch (\RuntimeException $e) {
                        $error = $e->getMessage();
                    }
                    $anexosRango[] = [
                        'jornada' => $jornadaDia,
                        'anexo' => $armado,
                        'error' => $error,
                    ];
                }
                $primero = $anexosRango[0] ?? null;
                $anexo = $primero['anexo'] ?? null;
                $anexoError = $primero['error'] ?? null;
                if (count($anexosRango) === 1) {
                    $anexo = $anexosRango[0]['anexo'];
                    $anexoError = $anexosRango[0]['error'];
                }
            } elseif ($panel === 'anexo8') {
                foreach ($jornadasRango as $jornadaDia) {
                    $armado = null;
                    $error = null;
                    try {
                        $armado = TesoreriaCrownAnexo8Calculo::deJornada($jornadaDia);
                    } catch (\RuntimeException $e) {
                        $error = $e->getMessage();
                    }
                    $anexos8Rango[] = [
                        'jornada' => $jornadaDia,
                        'anexo8' => $armado,
                        'error' => $error,
                    ];
                }
                if (count($anexos8Rango) === 1) {
                    $anexo8 = $anexos8Rango[0]['anexo8'];
                    $anexo8Error = $anexos8Rango[0]['error'];
                }
            }
            if ($jornada && Schema::hasColumn('tesoreria_crown_jornada', 'clientes')) {
                $clientesFormulario = TesoreriaCrownAnexo1Calculo::clientesParaFormulario($jornada);
            }
        }

        return view('caja.tesoreria_crown.anexo1.index', [
            'salas' => $salas,
            'sala' => $sala,
            'jornada' => $jornada,
            'fechaPedida' => $fechaPedida,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'modoRango' => $modoRango,
            'jornadasRango' => $jornadasRango,
            'anexosRango' => $anexosRango,
            'anexos8Rango' => $anexos8Rango,
            'consultar' => $consultar,
            'panel' => $panel,
            'anexo' => $anexo,
            'anexoError' => $anexoError,
            'anexoVariante' => $anexoVariante,
            'anexo2' => $anexo2,
            'anexo2Error' => $anexo2Error,
            'rescajero' => $rescajero,
            'rescajeroError' => $rescajeroError,
            'anexo3' => $anexo3,
            'anexo3Error' => $anexo3Error,
            'anexo7' => $anexo7,
            'anexo7Error' => $anexo7Error,
            'anexo8' => $anexo8,
            'anexo8Error' => $anexo8Error,
            'clientesFormulario' => $clientesFormulario,
        ]);
    }
}

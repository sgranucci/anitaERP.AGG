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
use App\Support\Caja\TesoreriaCrownJornadaConsultaSupport;
use App\Support\Caja\TesoreriaCrownResumenCajeroCalculo;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TesoreriaCrownAnexo1Controller extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-anexo');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $fechaPedida = trim((string) $request->input('fecha', ''));
        $jornada = TesoreriaCrownJornadaConsultaSupport::pedida($request, $sala ? (int) $sala->id : 0);

        $paneles = ['anexo', 'anexo2', 'rescajero', 'anexo3', 'anexo7', 'anexo8'];
        $panel = (string) $request->query('panel', 'anexo');
        if (! in_array($panel, $paneles, true)) {
            $panel = 'anexo';
        }

        $anexo = null;
        $anexoError = null;
        $anexoVariante = $request->input('formato_anexo') === 'nuevo' ? 'nuevo' : 'clasico';
        $anexo2 = $rescajero = $anexo3 = $anexo7 = $anexo8 = null;
        $anexo2Error = $rescajeroError = $anexo3Error = $anexo7Error = $anexo8Error = null;
        $clientesFormulario = 0;

        if ($jornada) {
            if ($panel === 'anexo') {
                try {
                    $anexo = $anexoVariante === 'nuevo'
                        ? TesoreriaCrownAnexo1NewCalculo::deJornada($jornada)
                        : TesoreriaCrownAnexo1Calculo::deJornada($jornada);
                } catch (\RuntimeException $e) {
                    $anexoError = $e->getMessage();
                }
            } elseif ($panel === 'anexo2') {
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
            } elseif ($panel === 'anexo8') {
                try {
                    $anexo8 = TesoreriaCrownAnexo8Calculo::deJornada($jornada);
                } catch (\RuntimeException $e) {
                    $anexo8Error = $e->getMessage();
                }
            }
            $clientesFormulario = Schema::hasColumn('tesoreria_crown_jornada', 'clientes')
                ? TesoreriaCrownAnexo1Calculo::clientesParaFormulario($jornada)
                : 0;
        }

        return view('caja.tesoreria_crown.anexo1.index', [
            'salas' => $salas,
            'sala' => $sala,
            'jornada' => $jornada,
            'fechaPedida' => $fechaPedida,
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

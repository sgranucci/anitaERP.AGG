<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownJornadaConsultaSupport;
use App\Support\Caja\TesoreriaCrownPoderPublicoCalculo;
use App\Support\Caja\TesoreriaCrownPoderPublicoGenerador;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Illuminate\Http\Request;
use Throwable;

class TesoreriaCrownPoderPublicoController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-poder-publico');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $fechaPedida = trim((string) $request->input('fecha', ''));
        $jornada = TesoreriaCrownJornadaConsultaSupport::pedida($request, $sala ? (int) $sala->id : 0);

        $poderPublico = null;
        $poderError = null;
        if ($jornada) {
            try {
                $poderPublico = $this->poderDelDia($request, $jornada);
            } catch (\RuntimeException $e) {
                $poderError = $e->getMessage();
            }
        }

        return view('caja.tesoreria_crown.poder_publico.index', [
            'salas' => $salas,
            'sala' => $sala,
            'jornada' => $jornada,
            'fechaPedida' => $fechaPedida,
            'poderPublico' => $poderPublico,
            'poderError' => $poderError,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function poderDelDia(Request $request, TesoreriaCrownJornada $jornada): array
    {
        $fecha = $jornada->fecha->format('Y-m-d');
        $armado = TesoreriaCrownPoderPublicoCalculo::delDia($fecha, (int) $jornada->sala_id);
        try {
            $persistido = TesoreriaCrownPoderPublicoGenerador::obtenerOGenerar(
                (int) $jornada->sala_id,
                $fecha,
                $request->query('recalcular') === '1'
            );
            $armado['diario'] = (int) round((float) $persistido['ficha_dia']);
            $armado['mes'] = (int) round((float) $persistido['ficha_mes']);
            $armado['acumulado'] = (int) round((float) $persistido['ficha_anio']);
            $armado['slot_dia'] = (float) $persistido['slot_dia'];
            $armado['slot_mes'] = (float) $persistido['slot_mes'];
            $armado['slot_acumulado'] = (float) $persistido['slot_anio'];
            $armado['origen_erp'] = (string) $persistido['origen'];
            foreach ($persistido['avisos'] as $aviso) {
                if (! in_array($aviso, $armado['avisos'], true)) {
                    $armado['avisos'][] = $aviso;
                }
            }
        } catch (Throwable $e) {
            $armado['avisos'][] = 'No se pudo persistir el poder público en el ERP: '.$e->getMessage();
        }

        return $armado;
    }
}

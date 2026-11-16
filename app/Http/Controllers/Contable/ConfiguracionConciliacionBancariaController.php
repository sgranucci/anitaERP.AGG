<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaCodificacionMapasSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaDocumentoConfigSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaPccMapaSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaReglasSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaResidualReglaSupport;
use Illuminate\Http\Request;

class ConfiguracionConciliacionBancariaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function editar()
    {
        can('editar-configuracion-conciliacion-bancaria');

        return view('contable.configuracion_conciliacion_bancaria.editar', [
            'reglas' => ConciliacionBancariaReglasSupport::paraFormulario(),
            'pccFilas' => ConciliacionBancariaPccMapaSupport::paraFormulario(),
            'conceptoFilas' => ConciliacionBancariaCodificacionMapasSupport::conceptosParaFormulario(),
            'codOpFilas' => ConciliacionBancariaCodificacionMapasSupport::codOpsParaFormulario(),
            'patronGastoFilas' => ConciliacionBancariaCodificacionMapasSupport::patronesGastoParaFormulario(),
            'tipoCompFilas' => ConciliacionBancariaDocumentoConfigSupport::tipoCompParaFormulario(),
            'docGastoFilas' => ConciliacionBancariaDocumentoConfigSupport::docGastoParaFormulario(),
            'residualFilas' => ConciliacionBancariaResidualReglaSupport::paraFormulario(),
            'codigosGasto' => $this->codigosGastoOpciones(),
        ]);
    }

    public function actualizar(Request $request)
    {
        can('actualizar-configuracion-conciliacion-bancaria');

        ConciliacionBancariaReglasSupport::guardar((array) $request->input('reglas', []));
        ConciliacionBancariaPccMapaSupport::guardar((array) $request->input('pcc', []));
        ConciliacionBancariaCodificacionMapasSupport::guardarConceptos((array) $request->input('concepto', []));
        ConciliacionBancariaCodificacionMapasSupport::guardarCodOps((array) $request->input('cod_op', []));
        ConciliacionBancariaCodificacionMapasSupport::guardarPatronesGasto((array) $request->input('patron_gasto', []));
        ConciliacionBancariaDocumentoConfigSupport::guardarTipoComp((array) $request->input('tipo_comp', []));
        ConciliacionBancariaDocumentoConfigSupport::guardarDocGasto((array) $request->input('doc_gasto', []));
        ConciliacionBancariaResidualReglaSupport::guardar((array) $request->input('residual', []));

        return redirect()
            ->route('configuracion_conciliacion_bancaria')
            ->with('mensaje', 'Configuración de conciliación bancaria actualizada.');
    }

    /**
     * @return list<array{valor: string, etiqueta: string}>
     */
    private function codigosGastoOpciones(): array
    {
        $ops = [['valor' => 'AC', 'etiqueta' => 'AC — acumulado diario']];
        $gastos = ConciliacionBancariaCodificacionMapasSupport::codificacionGastos();
        if ($gastos === []) {
            $gastos = (array) config('conciliacion_bancaria.codificacion_gastos', []);
        }
        foreach ($gastos as $codigo => $def) {
            $ops[] = [
                'valor' => (string) $codigo,
                'etiqueta' => $codigo.' — '.((string) ($def['descripcion'] ?? '')),
            ];
        }

        return $ops;
    }
}

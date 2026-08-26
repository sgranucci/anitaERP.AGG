<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Contrato_Validacion_Abono;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Compras\ContratoValidacionAbonoService;
use App\Support\Compras\ContratoPeriodoServicioSupport;
use App\Support\Compras\ContratoValidacionAbonoCumplimientoSupport;
use App\Support\Compras\ContratoValidacionAbonoPermisoSupport;
use App\Support\Compras\ContratoValidacionAbonoPoliticaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContratoValidacionAbonoController extends Controller
{
    public function __construct(
        private readonly ContratoValidacionAbonoService $service,
    ) {
    }

    public function editarRecepcion(int $id)
    {
        $recepcion = Recepcion_Proveedor::query()
            ->with(['ordencompras.proveedores', 'proveedores', 'recepcion_proveedor_articulos.articulos'])
            ->findOrFail($id);
        $this->assertPuedeVerRecepcion($recepcion);

        $validacion = $this->service->asegurarParaRecepcion($recepcion);
        if (! $validacion) {
            return redirect()
                ->route('editar_recepcion_proveedor', ['id' => $id])
                ->with('mensaje', 'Esta recepción no exige validación de abono.');
        }

        return $this->mostrarFormulario($validacion, 'recepcion');
    }

    public function guardarRecepcion(Request $request, int $id)
    {
        $recepcion = Recepcion_Proveedor::query()->with('ordencompras')->findOrFail($id);
        $this->assertPuedeVerRecepcion($recepcion);

        $validacion = $this->service->asegurarParaRecepcion($recepcion);
        if (! $validacion) {
            return redirect()->route('editar_recepcion_proveedor', ['id' => $id]);
        }

        return $this->persistir($request, $validacion, route('editar_recepcion_proveedor', [
            'id' => $id,
            'solapa' => 'validacion',
        ]));
    }

    public function editarComprobante(int $id)
    {
        $comprobante = Comprobante_Proveedor::query()
            ->with(['ordencompras.proveedores', 'proveedores'])
            ->findOrFail($id);
        $this->assertPuedeVerComprobante();

        $validacion = $this->service->asegurarParaComprobante($comprobante);
        if (! $validacion) {
            return redirect()
                ->route('editar_comprobante_proveedor', ['id' => $id])
                ->with('mensaje', 'Esta factura no exige validación de abono.');
        }

        return $this->mostrarFormulario($validacion, 'factura');
    }

    public function guardarComprobante(Request $request, int $id)
    {
        $comprobante = Comprobante_Proveedor::query()->with('ordencompras')->findOrFail($id);
        $this->assertPuedeVerComprobante();

        $validacion = $this->service->asegurarParaComprobante($comprobante);
        if (! $validacion) {
            return redirect()->route('editar_comprobante_proveedor', ['id' => $id]);
        }

        return $this->persistir($request, $validacion, route('editar_comprobante_proveedor', ['id' => $id]));
    }

    private function mostrarFormulario(Contrato_Validacion_Abono $validacion, string $origen)
    {
        $validacion->load([
            'plantillas.preguntas',
            'respuestas',
            'usuarios',
            'ordencompras.proveedores',
            'recepcion_proveedores.recepcion_proveedor_articulos.articulos',
            'comprobante_proveedores',
        ]);
        $oc = $validacion->ordencompras;
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc($oc);
        $puedeCompletar = ContratoValidacionAbonoPermisoSupport::desdeSesion((int) $politica['responsable_id'])
            && ! $validacion->estaCompleta();
        $respuestas = [];
        foreach ($validacion->respuestas as $respuesta) {
            $respuestas[(int) $respuesta->pregunta_id] = [
                'valor' => $respuesta->valor,
                'comentario' => $respuesta->comentario,
            ];
        }

        $itemTxt = '—';
        if ($origen === 'recepcion' && $validacion->recepcion_proveedores) {
            $linea = $validacion->recepcion_proveedores->recepcion_proveedor_articulos->first();
            $itemTxt = trim((string) (optional($linea?->articulos)->descripcion ?? $linea->detalle ?? ''));
        }
        if ($itemTxt === '—' || $itemTxt === '') {
            $itemTxt = (string) ($oc->detalle ?? '—');
        }

        $periodoEtiqueta = '—';
        if ($validacion->periodo_desde && $validacion->periodo_hasta) {
            $periodoEtiqueta = ContratoPeriodoServicioSupport::etiqueta((string) $validacion->periodo_modalidad)
                .': '.$validacion->periodo_desde->format('d/m/Y').' a '.$validacion->periodo_hasta->format('d/m/Y');
        }

        $cumplimiento = ContratoValidacionAbonoCumplimientoSupport::evaluar(
            $politica,
            [
                'estado' => (string) $validacion->estado,
                'ingresos_informados' => (int) $validacion->ingresos_informados,
            ]
        );

        $volverUrl = $origen === 'recepcion'
            ? route('editar_recepcion_proveedor', [
                'id' => $validacion->recepcion_proveedor_id,
                'solapa' => 'validacion',
            ])
            : route('editar_comprobante_proveedor', ['id' => $validacion->comprobante_proveedor_id]);

        $guardarUrl = $origen === 'recepcion'
            ? route('guardar_validacion_abono_recepcion', ['id' => $validacion->recepcion_proveedor_id])
            : route('guardar_validacion_abono_comprobante', ['id' => $validacion->comprobante_proveedor_id]);

        return view('compras.contrato_validacion_abono.form', [
            'validacion' => $validacion,
            'oc' => $oc,
            'politica' => $politica,
            'puedeCompletar' => $puedeCompletar,
            'respuestas' => $respuestas,
            'itemTxt' => $itemTxt !== '' ? $itemTxt : '—',
            'periodoEtiqueta' => $periodoEtiqueta,
            'cumplimiento' => $cumplimiento,
            'volverUrl' => $volverUrl,
            'guardarUrl' => $guardarUrl,
            'origen' => $origen,
        ]);
    }

    private function persistir(Request $request, Contrato_Validacion_Abono $validacion, string $volverUrl)
    {
        $validacion->loadMissing('ordencompras');
        $oc = $validacion->ordencompras;
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc($oc);
        if (! ContratoValidacionAbonoPermisoSupport::desdeSesion((int) $politica['responsable_id'])) {
            abort(403, 'No tiene permiso para completar la validación de abono.');
        }

        try {
            $this->service->confirmar(
                $validacion,
                (array) $request->input('respuestas', []),
                (int) (Auth::id() ?? 0)
            );
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect($volverUrl)->with('mensaje', 'Validación de abono confirmada.');
    }

    private function assertPuedeVerRecepcion(Recepcion_Proveedor $recepcion): void
    {
        if (can('listar-recepcion-proveedor', false)
            || can('editar-recepcion-proveedor', false)
            || can('completar-validacion-abono', false)
            || can('override-validacion-abono', false)
        ) {
            return;
        }
        $responsableId = (int) (optional($recepcion->ordencompras)->contrato_responsable_id ?? 0);
        if ($responsableId > 0 && (int) Auth::id() === $responsableId) {
            return;
        }
        can('editar-recepcion-proveedor');
    }

    private function assertPuedeVerComprobante(): void
    {
        if (can('listar-comprobante-proveedor', false)
            || can('editar-comprobante-proveedor', false)
            || can('completar-validacion-abono', false)
            || can('override-validacion-abono', false)
        ) {
            return;
        }
        can('editar-comprobante-proveedor');
    }
}

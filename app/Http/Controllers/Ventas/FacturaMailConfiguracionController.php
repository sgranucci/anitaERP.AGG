<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Factura_Mail_Configuracion;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\FacturaMailEnvioService;
use App\Support\Ventas\FacturaMailConfiguracionSupport;
use Illuminate\Http\Request;

class FacturaMailConfiguracionController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private FacturaMailEnvioService $facturaMailEnvioService,
    ) {
    }

    public function index(Request $request)
    {
        can('editar-factura-mail-configuracion');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = (int) ($request->input('empresa_id') ?: old('empresa_id') ?: ($empresa_query->first()->id ?? 0));
        $config = FacturaMailConfiguracionSupport::paraEmpresa($empresa_id);

        return view('ventas.factura_mail_configuracion.editar', compact(
            'empresa_query',
            'empresa_id',
            'config'
        ));
    }

    public function actualizar(Request $request)
    {
        can('actualizar-factura-mail-configuracion');

        $request->validate([
            'empresa_id' => 'required|integer|exists:empresa,id',
            'asunto' => 'nullable|string|max:200',
            'cuerpo' => 'nullable|string|max:4000',
            'bcc' => 'nullable|string|max:500',
        ]);

        $empresaId = (int) $request->input('empresa_id');

        Factura_Mail_Configuracion::query()->updateOrCreate(
            ['empresa_id' => $empresaId],
            [
                'habilitado' => $request->boolean('habilitado'),
                'envio_automatico' => $request->boolean('envio_automatico'),
                'incluir_remito' => $request->boolean('incluir_remito'),
                'incluir_envio' => $request->boolean('incluir_envio'),
                'exigir_flag_cliente' => $request->boolean('exigir_flag_cliente'),
                'asunto' => (string) ($request->input('asunto') ?: 'Comprobante {codigo} - {empresa}'),
                'cuerpo' => (string) ($request->input('cuerpo') ?: ''),
                'bcc' => (string) ($request->input('bcc') ?: ''),
            ]
        );

        return redirect()
            ->route('factura_mail_configuracion', ['empresa_id' => $empresaId])
            ->with('mensaje', 'Configuración de mail de facturas guardada.');
    }

    public function enviar(Request $request, int $id)
    {
        can('enviar-factura-mail');

        $request->validate([
            'email' => 'nullable|string|max:500',
            'mensaje' => 'nullable|string|max:2000',
        ]);

        $ret = $this->facturaMailEnvioService->enviar(
            $id,
            $request->input('email'),
            $request->input('mensaje')
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($ret, ($ret['ok'] ?? false) ? 200 : 422);
        }

        return redirect()->back()->with(
            ($ret['ok'] ?? false) ? 'mensaje' : 'mensaje-error',
            $ret['mensaje'] ?? 'Error'
        );
    }
}

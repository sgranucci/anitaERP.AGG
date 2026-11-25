<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\Sueldos_Recibo_Firma;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Sueldos\ReciboFirmaEmpleadorSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ReciboFirma_SueldosController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('editar-recibo-firma-sueldos');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $ambito = (string) ($request->input('ambito') ?: old('ambito') ?: 'empresa');
        if (! in_array($ambito, ['empresa', 'global'], true)) {
            $ambito = 'empresa';
        }

        $empresa_id = (int) ($request->input('empresa_id') ?: old('empresa_id') ?: ($empresa_query->first()->id ?? 0));
        $empresaIdResuelta = $ambito === 'global' ? null : $empresa_id;

        $datos = ReciboFirmaEmpleadorSupport::paraEmpresa($empresaIdResuelta);
        // data-uri: no depende de APP_URL / symlink / auth del /storage
        $preview_src = ReciboFirmaEmpleadorSupport::dataUri($empresaIdResuelta);
        $tiene_subida = $datos['imagen'] !== '';
        $origen = $datos['origen'];

        $listado = $this->armarListado($empresa_query);

        return view('sueldos.recibo_firma.editar', compact(
            'empresa_query',
            'empresa_id',
            'ambito',
            'datos',
            'preview_src',
            'tiene_subida',
            'origen',
            'listado'
        ));
    }

    public function actualizar(Request $request)
    {
        can('actualizar-recibo-firma-sueldos');

        $request->validate([
            'ambito' => 'required|in:empresa,global',
            'empresa_id' => 'nullable|integer|exists:empresa,id',
            'nombre' => 'nullable|string|max:120',
            'cargo' => 'nullable|string|max:120',
            'imagen' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
            'quitar_imagen' => 'nullable|in:1',
        ]);

        $ambito = (string) $request->input('ambito');
        $empresaId = $ambito === 'global' ? null : (int) $request->input('empresa_id');
        if ($ambito === 'empresa' && $empresaId <= 0) {
            return back()->withInput()->with('mensaje-error', 'Seleccioná una empresa.');
        }

        ReciboFirmaEmpleadorSupport::guardar(
            $empresaId,
            (string) $request->input('nombre', ''),
            (string) $request->input('cargo', ''),
            $request->file('imagen'),
            $request->boolean('quitar_imagen')
        );

        $redir = ['ambito' => $ambito];
        if ($empresaId) {
            $redir['empresa_id'] = $empresaId;
        }

        return redirect()
            ->route('recibo_firma_sueldos', $redir)
            ->with('mensaje', 'Firma del recibo guardada.');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresas
     * @return list<array{ambito: string, empresa_id: ?int, empresa_nombre: string, nombre: string, cargo: string, imagen: string, preview_src: ?string, origen: string, tiene_propia: bool}>
     */
    private function armarListado($empresas): array
    {
        $filasPropias = collect();
        if (Schema::hasTable('sueldos_recibo_firma')) {
            $filasPropias = Sueldos_Recibo_Firma::query()
                ->get(['empresa_id', 'nombre', 'cargo', 'imagen'])
                ->keyBy(static fn ($f) => $f->empresa_id === null ? 'g' : (string) $f->empresa_id);
        }

        $out = [];

        $global = ReciboFirmaEmpleadorSupport::paraEmpresa(null);
        $out[] = [
            'ambito' => 'global',
            'empresa_id' => null,
            'empresa_nombre' => 'Global (fallback)',
            'nombre' => $global['nombre'],
            'cargo' => $global['cargo'],
            'imagen' => $global['imagen'],
            'preview_src' => $global['imagen_uri'],
            'origen' => $global['origen'],
            'tiene_propia' => $filasPropias->has('g'),
        ];

        foreach ($empresas as $empresa) {
            $id = (int) $empresa->id;
            $datos = ReciboFirmaEmpleadorSupport::paraEmpresa($id);
            $out[] = [
                'ambito' => 'empresa',
                'empresa_id' => $id,
                'empresa_nombre' => (string) $empresa->nombre,
                'nombre' => $datos['nombre'],
                'cargo' => $datos['cargo'],
                'imagen' => $datos['imagen'],
                'preview_src' => $datos['imagen_uri'],
                'origen' => $datos['origen'],
                'tiene_propia' => $filasPropias->has((string) $id),
            ];
        }

        return $out;
    }
}

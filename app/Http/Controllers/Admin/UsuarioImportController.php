<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Rol;
use App\Models\Compras\SectorLegajocompra;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\OficinacompraRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Services\Admin\UsuarioImportPreviewService;
use App\Services\Admin\UsuarioImportService;
use App\Support\Admin\UsuarioImportIdentidadSupport;
use Illuminate\Http\Request;
use Throwable;

class UsuarioImportController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private CentrocostoRepositoryInterface $centrocostoRepository,
        private OficinacompraRepositoryInterface $oficinacompraRepository,
        private UsuarioImportPreviewService $previewService,
        private UsuarioImportService $importService,
    ) {
    }

    public function crear()
    {
        can('importar-usuarios');

        $rols = Rol::orderBy('id')->pluck('nombre', 'id')->toArray();
        $empresa_query = $this->empresaRepository->all()->pluck('nombre', 'id')->toArray();
        $centrocosto_query = $this->centrocostoRepository->all()->toArray();
        $oficinacompra_query = $this->oficinacompraRepository->all()->pluck('nombre', 'id')->toArray();
        $sector_legajocompra_query = SectorLegajocompra::orderBy('nombre')->get();
        $dominio_email_default = UsuarioImportIdentidadSupport::dominioEmailDefault();
        $generar_login_default = (bool) config('usuario_import.generar_login_si_falta', true);
        $generar_email_default = (bool) config('usuario_import.generar_email_si_falta', true);

        return view('admin.usuario.crearimportacion', compact(
            'rols',
            'empresa_query',
            'centrocosto_query',
            'oficinacompra_query',
            'sector_legajocompra_query',
            'dominio_email_default',
            'generar_login_default',
            'generar_email_default'
        ));
    }

    public function preview(Request $request)
    {
        can('importar-usuarios');

        $request->validate([
            'file' => 'required|file|mimetypes:application/vnd.ms-office,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,text/plain',
            'col_usuario' => 'nullable|string|max:80',
            'col_nombre' => 'nullable|string|max:80',
            'col_email' => 'nullable|string|max:80',
            'dominio_email' => 'nullable|string|max:100',
            'fila_encabezado' => 'nullable|integer|min:1|max:50',
            'hoja_indice' => 'nullable|integer|min:1|max:100',
        ]);

        $preview = $this->previewService->previsualizar(
            $request->file('file'),
            $request->input('col_usuario'),
            $request->input('col_nombre'),
            $request->input('col_email'),
            $request->filled('fila_encabezado') ? (int) $request->input('fila_encabezado') : null,
            $request->filled('hoja_indice') ? (int) $request->input('hoja_indice') : null,
            $request->input('dominio_email'),
            $request->boolean('generar_login_si_falta'),
            $request->boolean('generar_email_si_falta')
        );

        return response()->json($preview);
    }

    public function importar(Request $request)
    {
        can('importar-usuarios');

        foreach (['vendedor_id', 'sector_legajocompra_id', 'oficinacompra_id', 'fila_encabezado'] as $campoOpcional) {
            if (! $request->filled($campoOpcional)) {
                $request->merge([$campoOpcional => null]);
            }
        }

        $request->validate([
            'file' => 'required|file|mimetypes:application/vnd.ms-office,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,text/plain',
            'password' => 'required|string|min:5|max:100',
            're_password' => 'required|same:password',
            'centrocosto_id' => 'required|integer|exists:centrocosto,id',
            'rol_id' => 'required|array|min:1',
            'rol_id.*' => 'integer|exists:rol,id',
            'empresa_ids' => 'required|array|min:1',
            'empresa_ids.*' => 'integer|exists:empresa,id',
            'vendedor_id' => 'nullable|integer|exists:vendedor,id',
            'sector_legajocompra_id' => 'nullable|integer|exists:sector_legajocompra,id',
            'oficinacompra_id' => 'nullable|integer|exists:oficinacompra,id',
            'dominio_email' => 'nullable|string|max:100',
            'col_usuario' => 'nullable|string|max:80',
            'col_nombre' => 'nullable|string|max:80',
            'col_email' => 'nullable|string|max:80',
            'fila_encabezado' => 'nullable|integer|min:1|max:50',
            'hoja_indice' => 'nullable|integer|min:1|max:100',
        ], [
            're_password.same' => 'Las contraseñas no coinciden.',
            'rol_id.required' => 'Debe asignar al menos un rol.',
            'empresa_ids.required' => 'Debe asignar al menos una empresa.',
        ]);

        try {
            $resumen = $this->importService->importar(
                $request->file('file'),
                (array) $request->input('rol_id', []),
                (array) $request->input('empresa_ids', []),
                (int) $request->input('centrocosto_id'),
                (string) $request->input('password'),
                $request->filled('vendedor_id') ? (int) $request->input('vendedor_id') : null,
                $request->filled('sector_legajocompra_id') ? (int) $request->input('sector_legajocompra_id') : null,
                $request->filled('oficinacompra_id') ? (int) $request->input('oficinacompra_id') : null,
                $request->input('col_usuario'),
                $request->input('col_nombre'),
                $request->input('col_email'),
                $request->filled('fila_encabezado') ? (int) $request->input('fila_encabezado') : null,
                $request->filled('hoja_indice') ? (int) $request->input('hoja_indice') : null,
                $request->input('dominio_email'),
                $request->boolean('generar_login_si_falta'),
                $request->boolean('generar_email_si_falta')
            );
        } catch (Throwable $e) {
            return redirect()
                ->route('crear_importacion_usuario')
                ->withInput($request->except(['file', 'password', 're_password']))
                ->with('mensaje-error', $e->getMessage());
        }

        return redirect()
            ->route('crear_importacion_usuario')
            ->with('usuario_import_resultado', $resumen)
            ->with('mensaje', 'Importación finalizada: '.(int) ($resumen['usuarios_creados'] ?? 0).' usuario(s) creado(s).');
    }
}

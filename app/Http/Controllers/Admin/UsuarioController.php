<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionUsuario;
use App\Models\Admin\Rol;
use App\Models\Compras\SectorLegajocompra;
use App\Models\Seguridad\Usuario;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\OficinacompraRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Ventas\VendedorRepositoryInterface;
use App\Support\Http\EliminacionRegistroSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UsuarioController extends Controller
{
    private $empresaRepository;

    private $centrocostoRepository;

    private $vendedorRepository;

    private $usuarioRepository;

    private $oficinacompraRepository;

    public function __construct(EmpresaRepositoryInterface $empresarepository,
        CentrocostoRepositoryInterface $centrocostorepository,
        VendedorRepositoryInterface $vendedorrepository,
        UsuarioRepositoryInterface $usuariorepository,
        OficinacompraRepositoryInterface $oficinacomprarepository)
    {
        $this->empresaRepository = $empresarepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->vendedorRepository = $vendedorrepository;
        $this->usuarioRepository = $usuariorepository;
        $this->oficinacompraRepository = $oficinacomprarepository;
    }

    public function index()
    {
        $datas = Usuario::with(['roles:id,nombre', 'usuario_empresas', 'centrocostos', 'sectorLegajocompra'])
            ->orderBy('id')
            ->get();

        return view('admin.usuario.index', compact('datas'));
    }

    public function crear()
    {
        $rols = Rol::orderBy('id')->pluck('nombre', 'id')->toArray();
        $empresa_query = $this->empresaRepository->all()->pluck('nombre', 'id')->toArray();
        $centrocosto_query = $this->centrocostoRepository->all()->toArray();
        $vendedor_query = $this->vendedorRepository->all()->pluck('nombre', 'id')->toArray();
        $oficinacompra_query = $this->oficinacompraRepository->all()->pluck('nombre', 'id')->toArray();
        $sector_legajocompra_query = SectorLegajocompra::orderBy('nombre')->get();

        return view('admin.usuario.crear', compact('rols', 'empresa_query', 'centrocosto_query', 'vendedor_query', 'oficinacompra_query', 'sector_legajocompra_query'));
    }

    public function guardar(ValidacionUsuario $request)
    {
        if ($foto = Usuario::setFoto($request)) {
            $request->request->add(['foto' => $foto]);
        }

        $request->merge([
            'sector_legajocompra_id' => $request->filled('sector_legajocompra_id')
                ? (int) $request->input('sector_legajocompra_id')
                : null,
        ]);

        $usuario = Usuario::create($request->all());
        $usuario->auditSync('roles', $request->rol_id);

        // Actualiza las empresas
        $usuario->auditSync('usuario_empresas', $request->empresa_ids);

        $this->sincronizarDepositosAutorizados($usuario, $request);

        return redirect('admin/usuario')->with('mensaje', 'Usuario creado con éxito');
    }

    public function editar($id)
    {
        $rols = Rol::orderBy('id')->pluck('nombre', 'id')->toArray();
        $data = Usuario::with(['roles', 'usuario_empresas', 'depositosAutorizados.empresas'])->findOrFail($id);
        $empresa_query = $this->empresaRepository->all()->pluck('nombre', 'id')->toArray();
        $centrocosto_query = $this->centrocostoRepository->all()->toArray();
        $vendedor_query = $this->vendedorRepository->all()->pluck('nombre', 'id')->toArray();
        $oficinacompra_query = $this->oficinacompraRepository->all()->pluck('nombre', 'id')->toArray();
        $sector_legajocompra_query = SectorLegajocompra::orderBy('nombre')->get();

        return view('admin.usuario.editar', compact('data', 'rols', 'empresa_query', 'centrocosto_query', 'vendedor_query', 'oficinacompra_query', 'sector_legajocompra_query'));
    }

    public function actualizar(ValidacionUsuario $request, $id)
    {
        $usuario = Usuario::findOrFail($id);
        if ($foto = Usuario::setFoto($request, $usuario->foto)) {
            $request->request->add(['foto' => $foto]);
        }

        $data = array_filter($request->all());

        if (! isset($data['vendedor_id'])) {
            $data['vendedor_id'] = null;
        }

        if (! isset($data['oficinacompra_id'])) {
            $data['oficinacompra_id'] = null;
        }

        $data['sector_legajocompra_id'] = $request->filled('sector_legajocompra_id')
            ? (int) $request->input('sector_legajocompra_id')
            : null;

        $usuario->update($data);
        $usuario->auditSync('roles', $request->rol_id);

        // Actualiza las empresas
        $usuario->auditSync('usuario_empresas', $request->empresa_ids);

        $this->sincronizarDepositosAutorizados($usuario, $request);

        return redirect('admin/usuario')->with('mensaje', 'Usuario actualizado con exito');
    }

    public function eliminar(Request $request, $id)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        try {
            $usuario = Usuario::findOrFail($id);

            if ((int) $id === (int) session('usuario_id')) {
                return EliminacionRegistroSupport::respuestaJsonError('No puede eliminar su propio usuario mientras tiene la sesión activa.');
            }

            $usuario->auditDetach('roles');
            $usuario->auditDetach('usuario_empresas');
            $usuario->auditDetach('depositosAutorizados');

            $foto = $usuario->foto;
            $usuario->delete();

            if ($foto) {
                Storage::disk('public')->delete("imagenes/fotos_usuarios/$foto");
            }

            return EliminacionRegistroSupport::respuestaJsonOk();
        } catch (QueryException $e) {
            return EliminacionRegistroSupport::respuestaJsonError(
                EliminacionRegistroSupport::mensajeDesdeQueryException($e, 'el usuario')
            );
        } catch (\Throwable $e) {
            return EliminacionRegistroSupport::respuestaJsonError(
                EliminacionRegistroSupport::mensajeDesdeExcepcion($e, 'el usuario')
            );
        }
    }

    public function crearUsuarioRemoto(Request $request)
    {
        $separado = explode(' ', $request->nombre);

        $primerLetra = substr($separado[0], 0, 1);

        if (count($separado) > 1) {
            $apellido = $separado[count($separado) - 1];
            $login = strtolower($primerLetra.$apellido);
        } else {
            $login = strtolower($separado[0]);
        }

        // Verifica que no exista
        $data = Usuario::where('usuario', $login)->first();
        if (! $data) {
            $password = config('ticket.passwordNuevoUsuario');

            // Busca el rol
            $rolId = 1;
            foreach (config('ticket.rolTecnico') as $areadestino) {
                if ($areadestino['areadestino_id'] == $request->areadestino_id) {
                    $rolId = $areadestino['rol_id'];
                }
            }
            $dataUsuario = ['usuario' => $login,
                'password' => $password,
                'nombre' => $request->nombre,
                'email' => $login.config('ticket.dominioEmail'),
            ];

            $usuario = Usuario::create($dataUsuario);
            $usuario->auditSync('roles', $rolId);
        }
    }

    public function leerUsuario()
    {
        return Usuario::with('roles:id,nombre')->orderBy('id')->get();
    }

    public function consultaUsuario(Request $request)
    {
        return $this->usuarioRepository->consultaUsuario($request->consulta, $request->empresa_id, $request->centrocosto_id);
    }

    public function leeUnUsuario($usuario_id)
    {
        return $this->usuarioRepository->find($usuario_id);
    }

    /**
     * Resuelve id y nombre a partir del código de usuario o del id (árbol de aprobación y similares).
     */
    public function resolverUsuario(Request $request)
    {
        $valor = trim((string) $request->query('valor', ''));
        $empresa_id = $request->query('empresa_id');

        if ($valor === '') {
            return response()->json(null);
        }

        $usuario = $this->usuarioRepository->findPorIdOCodigo($valor, $empresa_id ? (int) $empresa_id : null);

        if (! $usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'Usuario no encontrado']);
        }

        $empresa_ok = true;
        if ($empresa_id) {
            $empresa_ok = $usuario->usuario_empresas->contains('id', (int) $empresa_id);
        }

        return response()->json([
            'ok' => true,
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'usuario' => $usuario->usuario,
            'empresa_ok' => $empresa_ok,
        ]);
    }

    private function sincronizarDepositosAutorizados(Usuario $usuario, Request $request): void
    {
        $depositoIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $request->input('deposito_ids', [])
        ))));

        if ($depositoIds === []) {
            $usuario->auditSync('depositosAutorizados', []);

            return;
        }

        $empresaIds = $request->input('empresa_ids', []);
        $validIds = UsuarioDepositoAutorizado::idsValidosParaEmpresas($depositoIds, $empresaIds);

        $usuario->auditSync('depositosAutorizados', $validIds);
    }
}

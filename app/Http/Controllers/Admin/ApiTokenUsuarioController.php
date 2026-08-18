<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Seguridad\Usuario;
use App\Repositories\Admin\UsuarioRepository;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSeguridadSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Emisión/revocación de tokens técnicos Sanctum (sin login técnico público).
 */
class ApiTokenUsuarioController extends Controller
{
    public function __construct(private UsuarioRepository $usuarioRepository) {}

    public function index(Request $request)
    {
        can('administrar-api-tokens');
        $usuarioId = (int) ($request->input('usuario_id') ?: Auth::id());
        $usuario = $this->usuarioRepository->findOperativo($usuarioId)
            ?? Usuario::query()->findOrFail($usuarioId);

        return view('admin.api_token.index', [
            'usuario' => $usuario,
            'tokens' => $usuario->tokens()->orderByDesc('id')->get(),
            'abilities' => ReporteSueldosDefinibleSeguridadSupport::abilitiesCatalogo(),
            'usuarios' => $this->usuarioRepository->listadoOperativoParaSelector(
                null,
                null,
                ['id', 'nombre', 'email', 'usuario'],
                soloConEmail: false,
                with: []
            ),
        ]);
    }

    public function store(Request $request)
    {
        can('administrar-api-tokens');
        $data = $request->validate([
            'usuario_id' => 'required|integer|exists:usuario,id',
            'name' => 'required|string|max:80',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string|max:40',
        ]);
        $usuario = Usuario::query()->findOrFail((int) $data['usuario_id']);
        $abilities = array_values(array_intersect(
            (array) ($data['abilities'] ?? []),
            ReporteSueldosDefinibleSeguridadSupport::abilitiesCatalogo()
        ));
        if ($abilities === []) {
            $abilities = [
                ReporteSueldosDefinibleSeguridadSupport::ABILITY_REPORTS_READ,
                ReporteSueldosDefinibleSeguridadSupport::ABILITY_DATASETS_READ,
            ];
        }
        $plain = $usuario->createToken((string) $data['name'], $abilities)->plainTextToken;

        return redirect()
            ->route('api_token_usuario', ['usuario_id' => $usuario->id])
            ->with('mensaje', 'Token creado. Copialo ahora (no se vuelve a mostrar): '.$plain);
    }

    public function destroy($tokenId)
    {
        can('administrar-api-tokens');
        PersonalAccessToken::query()->whereKey((int) $tokenId)->delete();

        return redirect()->route('api_token_usuario')->with('mensaje', 'Token revocado.');
    }
}

<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionContabilidadCuentaAutomatica;
use App\Models\Contable\Cuentacontable;
use App\Models\Contable\Contabilidad_CuentaAutomatica;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\ContabilidadCuentaAutomaticaSeedService;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;

class ContabilidadCuentaAutomaticaController extends Controller
{
    private const RUTA_INDEX = 'contable/cuentas-automaticas';

    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly ContabilidadCuentaAutomaticaSeedService $cuentaAutomaticaSeedService,
    ) {}

    public function index()
    {
        can('editar-cuentas-automaticas-contables');

        $empresa_query = $this->empresaRepository->empresasActivasOperativas();
        $empresa_id = (int) (request('empresa_id') ?: old('empresa_id') ?: ($empresa_query->first()->id ?? 0));

        if ($empresa_id > 0 && ! $this->empresaOperativaPermitida($empresa_id, $empresa_query)) {
            $empresa_id = (int) ($empresa_query->first()->id ?? 0);
        }

        $filas = $empresa_id > 0 ? $this->armarFilasParaVista($empresa_id) : [];

        return view('contable.cuenta_automatica.editar', compact('empresa_query', 'empresa_id', 'filas'));
    }

    public function actualizar(ValidacionContabilidadCuentaAutomatica $request)
    {
        can('actualizar-cuentas-automaticas-contables');

        $empresaId = (int) $request->validated('empresa_id');

        if (! $this->empresaRepository->empresaTieneUsuariosAsignados($empresaId)) {
            return redirect(self::RUTA_INDEX)
                ->with('errores', ['La empresa no está activa (sin usuarios asignados). Asigne la empresa a un usuario primero.']);
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return redirect(self::RUTA_INDEX)
                ->with('errores', ['No tiene permiso para configurar cuentas automáticas de esa empresa.']);
        }

        $cuentas = $request->validated('cuentas') ?? [];

        foreach (CuentaAutomaticaClaves::todasLasClaves() as $clave) {
            $cuentaId = isset($cuentas[$clave]) ? (int) $cuentas[$clave] : null;
            if ($cuentaId !== null && $cuentaId <= 0) {
                $cuentaId = null;
            }

            if ($cuentaId !== null) {
                $existe = Cuentacontable::query()
                    ->where('id', $cuentaId)
                    ->where('empresa_id', $empresaId)
                    ->exists();
                if (! $existe) {
                    return redirect(self::RUTA_INDEX.'?empresa_id='.$empresaId)
                        ->with('errores', ['La cuenta '.$clave.' no existe o no pertenece a la empresa.']);
                }
            }

            Contabilidad_CuentaAutomatica::query()->updateOrCreate(
                ['empresa_id' => $empresaId, 'clave' => $clave],
                ['cuentacontable_id' => $cuentaId],
            );
        }

        return redirect(self::RUTA_INDEX.'?empresa_id='.$empresaId)
            ->with('mensaje', 'Cuentas automáticas guardadas.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function armarFilasParaVista(int $empresaId): array
    {
        $centralRows = Contabilidad_CuentaAutomatica::query()
            ->where('empresa_id', $empresaId)
            ->get()
            ->keyBy('clave');

        $filas = [];
        foreach (CuentaAutomaticaClaves::catalogo() as $clave => $meta) {
            $row = $centralRows->get($clave);
            $displayCentralId = self::intOrNull($row?->cuentacontable_id);
            $efectivoId = CuentaAutomaticaResolver::resolverId($empresaId, $clave);
            $overrideModulo = CuentaAutomaticaResolver::tieneOverrideModulo($empresaId, $clave);

            $cuentaCentral = $displayCentralId
                ? Cuentacontable::query()->where('id', $displayCentralId)->where('empresa_id', $empresaId)->first()
                : null;

            $filas[] = [
                'clave' => $clave,
                'grupo' => $meta['grupo'],
                'descripcion' => $meta['descripcion'],
                'cuentacontable_id' => $displayCentralId,
                'codigo' => $cuentaCentral?->codigo ?? '',
                'nombre' => $cuentaCentral?->nombre ?? '',
                'efectivo_id' => $efectivoId,
                'override_modulo' => $overrideModulo,
            ];
        }

        return $filas;
    }

    private static function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }

        $id = (int) $v;

        return $id > 0 ? $id : null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresaQuery
     */
    private function empresaOperativaPermitida(int $empresaId, $empresaQuery): bool
    {
        return $empresaQuery->contains('id', $empresaId);
    }
}

<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionContabilidadCuentaAutomatica;
use App\Models\Contable\Cuentacontable;
use App\Models\Contable\Contabilidad_CuentaAutomatica;
use App\Models\Contable\Contabilidad_CuentaAutomaticaDetalle;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\ContabilidadCuentaAutomaticaSeedService;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use Illuminate\Support\Facades\DB;

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
        $cuentasMultiples = $request->validated('cuentas_multiples') ?? [];

        try {
            DB::transaction(function () use ($empresaId, $cuentas, $cuentasMultiples) {
                foreach (CuentaAutomaticaClaves::todasLasClaves() as $clave) {
                    if (CuentaAutomaticaClaves::esMultiple($clave)) {
                        $this->sincronizarCuentasMultiples(
                            $empresaId,
                            $clave,
                            $cuentasMultiples[$clave] ?? []
                        );

                        continue;
                    }

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
                            throw new \RuntimeException(
                                'La cuenta '.$clave.' no existe o no pertenece a la empresa.'
                            );
                        }
                    }

                    Contabilidad_CuentaAutomatica::query()->updateOrCreate(
                        ['empresa_id' => $empresaId, 'clave' => $clave],
                        ['cuentacontable_id' => $cuentaId],
                    );
                }
            });
        } catch (\Throwable $e) {
            return redirect(self::RUTA_INDEX.'?empresa_id='.$empresaId)
                ->with('errores', [$e->getMessage()]);
        }

        return redirect(self::RUTA_INDEX.'?empresa_id='.$empresaId)
            ->with('mensaje', 'Cuentas automáticas guardadas.');
    }

    /**
     * @param  list<int|string|null>  $cuentaIds
     */
    private function sincronizarCuentasMultiples(int $empresaId, string $clave, array $cuentaIds): void
    {
        $ids = [];
        foreach ($cuentaIds as $raw) {
            $id = (int) $raw;
            if ($id <= 0) {
                continue;
            }
            $ids[$id] = $id;
        }
        $ids = array_values($ids);

        foreach ($ids as $cuentaId) {
            $existe = Cuentacontable::query()
                ->where('id', $cuentaId)
                ->where('empresa_id', $empresaId)
                ->exists();
            if (! $existe) {
                throw new \RuntimeException(
                    'Una cuenta de '.$clave.' no existe o no pertenece a la empresa.'
                );
            }
        }

        Contabilidad_CuentaAutomatica::query()->updateOrCreate(
            ['empresa_id' => $empresaId, 'clave' => $clave],
            ['cuentacontable_id' => $ids[0] ?? null],
        );

        Contabilidad_CuentaAutomaticaDetalle::query()
            ->where('empresa_id', $empresaId)
            ->where('clave', $clave)
            ->delete();

        foreach ($ids as $cuentaId) {
            Contabilidad_CuentaAutomaticaDetalle::query()->create([
                'empresa_id' => $empresaId,
                'clave' => $clave,
                'cuentacontable_id' => $cuentaId,
            ]);
        }
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

        $detallesPorClave = Contabilidad_CuentaAutomaticaDetalle::query()
            ->with('cuentacontables:id,codigo,nombre,empresa_id')
            ->where('empresa_id', $empresaId)
            ->orderBy('id')
            ->get()
            ->groupBy('clave');

        $filas = [];
        foreach (CuentaAutomaticaClaves::catalogo() as $clave => $meta) {
            $multiple = ! empty($meta['multiple']);
            $row = $centralRows->get($clave);
            $overrideModulo = CuentaAutomaticaResolver::tieneOverrideModulo($empresaId, $clave);

            if ($multiple) {
                $cuentas = [];
                foreach ($detallesPorClave->get($clave, collect()) as $detalle) {
                    $cuenta = $detalle->cuentacontables;
                    if ($cuenta === null || (int) ($cuenta->empresa_id ?? 0) !== $empresaId) {
                        continue;
                    }
                    $cuentas[] = [
                        'cuentacontable_id' => (int) $cuenta->id,
                        'codigo' => (string) ($cuenta->codigo ?? ''),
                        'nombre' => (string) ($cuenta->nombre ?? ''),
                    ];
                }

                // Fallback a fila simple si aún no hay detalle.
                if ($cuentas === []) {
                    $legacyId = self::intOrNull($row?->cuentacontable_id);
                    if ($legacyId !== null) {
                        $cuentaLegacy = Cuentacontable::query()
                            ->where('id', $legacyId)
                            ->where('empresa_id', $empresaId)
                            ->first();
                        if ($cuentaLegacy !== null) {
                            $cuentas[] = [
                                'cuentacontable_id' => (int) $cuentaLegacy->id,
                                'codigo' => (string) ($cuentaLegacy->codigo ?? ''),
                                'nombre' => (string) ($cuentaLegacy->nombre ?? ''),
                            ];
                        }
                    }
                }

                $filas[] = [
                    'clave' => $clave,
                    'grupo' => $meta['grupo'],
                    'descripcion' => $meta['descripcion'],
                    'multiple' => true,
                    'cuentas' => $cuentas,
                    'cuentacontable_id' => $cuentas[0]['cuentacontable_id'] ?? null,
                    'codigo' => $cuentas[0]['codigo'] ?? '',
                    'nombre' => $cuentas[0]['nombre'] ?? '',
                    'efectivo_id' => $cuentas[0]['cuentacontable_id'] ?? null,
                    'override_modulo' => $overrideModulo,
                ];

                continue;
            }

            $displayCentralId = self::intOrNull($row?->cuentacontable_id);
            $efectivoId = CuentaAutomaticaResolver::resolverId($empresaId, $clave);
            $cuentaCentral = $displayCentralId
                ? Cuentacontable::query()->where('id', $displayCentralId)->where('empresa_id', $empresaId)->first()
                : null;

            $filas[] = [
                'clave' => $clave,
                'grupo' => $meta['grupo'],
                'descripcion' => $meta['descripcion'],
                'multiple' => false,
                'cuentas' => [],
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

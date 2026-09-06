<?php

namespace App\Services\Compras;

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Models\Contable\Centrocosto;
use App\Support\Compras\OrdencompraEstados;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aprobadores del circuito de suscripciones.
 *
 * El árbol SU es de nivel único: cada centro de costo tiene un gerente (usuario
 * AnitaERP) que autoriza todas las suscripciones del área. Este servicio traduce
 * ese ABM simple a filas de `arbolaprobacion_nivel`.
 */
class SuscripcionAprobadorService
{
    private const MONTO_MAXIMO = '999999999999999999.9999';

    public function __construct(
        private \App\Services\Configuracion\ArbolaprobacionService $arbolaprobacionService,
    ) {}

    public function nombreTipoArbol(): string
    {
        return $this->arbolaprobacionService->nombreTipoArbolSuscripciones();
    }

    public function arbolDeEmpresa(int $empresaId, bool $crearSiFalta = false): ?Arbolaprobacion
    {
        $arbol = Arbolaprobacion::query()
            ->where('tipoarbol', $this->nombreTipoArbol())
            ->where('empresa_id', $empresaId)
            ->orderBy('id')
            ->first();

        if ($arbol || ! $crearSiFalta) {
            return $arbol;
        }

        $nombreEmpresa = (string) (DB::table('empresa')->where('id', $empresaId)->value('nombre') ?? '');
        $estado = (string) (Arbolaprobacion::query()->whereNotNull('estado')->value('estado') ?: 'Activo');

        return Arbolaprobacion::query()->create([
            'nombre' => trim('Suscripciones — '.$nombreEmpresa),
            'tipoarbol' => $this->nombreTipoArbol(),
            'empresa_id' => $empresaId,
            'recordatorio' => 'N',
            'diasinrespuesta' => 0,
            'diavencimientorecordatorio' => 0,
            'estado' => $estado,
        ]);
    }

    /**
     * Listado del ABM, opcionalmente filtrado por empresa.
     *
     * @return Collection<int, array{
     *     id: int, empresa_id: int, empresa: string, centrocosto_id: int, codigo: string,
     *     nombre: string, usuario_id: int, usuario_codigo: string, usuario_nombre: string,
     *     suscripciones: int
     * }>
     */
    public function listar(?int $empresaId = null): Collection
    {
        $q = Arbolaprobacion_Nivel::query()
            ->with(['centrocosto_ids', 'usuarios', 'arbolaprobaciones.empresas'])
            ->whereHas('arbolaprobaciones', function ($q) use ($empresaId) {
                $q->where('tipoarbol', $this->nombreTipoArbol());
                if ($empresaId && $empresaId > 0) {
                    $q->where('empresa_id', $empresaId);
                }
            })
            ->orderBy('id');

        $niveles = $q->get();

        $conteosPorEmpresa = [];
        foreach ($niveles->pluck('arbolaprobaciones.empresa_id')->unique()->filter() as $empId) {
            $conteosPorEmpresa[(int) $empId] = $this->conteoSuscripcionesPorCc((int) $empId);
        }

        return $niveles->map(function (Arbolaprobacion_Nivel $nivel) use ($conteosPorEmpresa): array {
            $arbol = $nivel->arbolaprobaciones;
            $empresaId = (int) ($arbol->empresa_id ?? 0);
            $cc = $nivel->centrocosto_ids;
            $usuario = $nivel->usuarios;
            $conteo = $conteosPorEmpresa[$empresaId] ?? collect();

            return [
                'id' => (int) $nivel->id,
                'empresa_id' => $empresaId,
                'empresa' => (string) (optional($arbol->empresas)->nombre ?? ''),
                'centrocosto_id' => (int) $nivel->centrocosto_id,
                'codigo' => (string) ($cc->codigo ?? ''),
                'nombre' => (string) ($cc->nombre ?? ''),
                'usuario_id' => (int) $nivel->usuario_id,
                'usuario_codigo' => (string) ($usuario->usuario ?? $usuario->id ?? ''),
                'usuario_nombre' => (string) ($usuario->nombre ?? ''),
                'suscripciones' => (int) ($conteo[$nivel->centrocosto_id] ?? 0),
            ];
        })->sortBy(fn (array $f) => $f['empresa'].'|'.$f['codigo'].'|'.$f['nombre'])->values();
    }

    public function findNivel(int $id): Arbolaprobacion_Nivel
    {
        $nivel = Arbolaprobacion_Nivel::query()
            ->with(['centrocosto_ids', 'usuarios', 'arbolaprobaciones.empresas'])
            ->findOrFail($id);

        if (! $nivel->arbolaprobaciones
            || $nivel->arbolaprobaciones->tipoarbol !== $this->nombreTipoArbol()) {
            abort(404);
        }

        return $nivel;
    }

    /**
     * @return array{centrocosto_id: int, codigo: string, nombre: string, usuario_id: int, usuario_codigo: string, usuario_nombre: string, nivel_id: int, suscripciones: int}
     *
     * @deprecated Preferí listar(); se mantiene por el alta de suscripciones.
     */
    public function grillaDeEmpresa(int $empresaId): Collection
    {
        return $this->listar($empresaId)->map(fn (array $f) => [
            'centrocosto_id' => $f['centrocosto_id'],
            'codigo' => $f['codigo'],
            'nombre' => $f['nombre'],
            'usuario_id' => $f['usuario_id'],
            'usuario_codigo' => $f['usuario_codigo'],
            'usuario_nombre' => $f['usuario_nombre'],
            'nivel_id' => $f['id'],
            'suscripciones' => $f['suscripciones'],
        ]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function crear(int $empresaId, int $centrocostoId, int $usuarioId): Arbolaprobacion_Nivel
    {
        $this->assertCcYUsuario($centrocostoId, $usuarioId);

        $arbol = $this->arbolDeEmpresa($empresaId, crearSiFalta: true);
        if (! $arbol) {
            throw new \InvalidArgumentException('No se pudo resolver el árbol de suscripciones de la empresa.');
        }

        $ya = Arbolaprobacion_Nivel::query()
            ->where('arbolaprobacion_id', $arbol->id)
            ->where('centrocosto_id', $centrocostoId)
            ->exists();

        if ($ya) {
            throw new \InvalidArgumentException(
                'Ese centro de costo ya tiene gerente en el circuito de suscripciones de esta empresa.'
            );
        }

        return Arbolaprobacion_Nivel::query()->create($this->payloadNivel(
            (int) $arbol->id,
            $centrocostoId,
            $usuarioId,
            $this->monedaBase($empresaId)
        ));
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function actualizar(int $nivelId, int $centrocostoId, int $usuarioId): Arbolaprobacion_Nivel
    {
        $this->assertCcYUsuario($centrocostoId, $usuarioId);

        $nivel = $this->findNivel($nivelId);
        $arbol = $nivel->arbolaprobaciones;
        $empresaId = (int) $arbol->empresa_id;

        $dupe = Arbolaprobacion_Nivel::query()
            ->where('arbolaprobacion_id', $arbol->id)
            ->where('centrocosto_id', $centrocostoId)
            ->where('id', '!=', $nivel->id)
            ->exists();

        if ($dupe) {
            throw new \InvalidArgumentException(
                'Ese centro de costo ya tiene gerente en el circuito de suscripciones de esta empresa.'
            );
        }

        $nivel->update($this->payloadNivel(
            (int) $arbol->id,
            $centrocostoId,
            $usuarioId,
            $this->monedaBase($empresaId)
        ));

        return $nivel->fresh(['centrocosto_ids', 'usuarios', 'arbolaprobaciones.empresas']);
    }

    public function eliminar(int $nivelId): void
    {
        $this->findNivel($nivelId)->delete();
    }

    /**
     * @return array{arbol: ?Arbolaprobacion, configurados: int, sin_gerente: list<array{codigo: string, nombre: string, suscripciones: int}>}
     */
    public function diagnostico(int $empresaId): array
    {
        $arbol = $this->arbolDeEmpresa($empresaId);
        $configurados = $arbol
            ? Arbolaprobacion_Nivel::query()->where('arbolaprobacion_id', $arbol->id)->count()
            : 0;

        $conGerente = $arbol
            ? Arbolaprobacion_Nivel::query()
                ->where('arbolaprobacion_id', $arbol->id)
                ->pluck('centrocosto_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $conteo = $this->conteoSuscripcionesPorCc($empresaId);
        $sinGerenteIds = $conteo->keys()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => in_array($id, $conGerente, true))
            ->values()
            ->all();

        $sinGerente = [];
        if ($sinGerenteIds !== []) {
            $ccs = Centrocosto::query()
                ->whereIn('id', $sinGerenteIds)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']);

            foreach ($ccs as $cc) {
                $sinGerente[] = [
                    'codigo' => (string) $cc->codigo,
                    'nombre' => (string) $cc->nombre,
                    'suscripciones' => (int) ($conteo[$cc->id] ?? 0),
                ];
            }
        }

        return [
            'arbol' => $arbol,
            'configurados' => $configurados,
            'sin_gerente' => $sinGerente,
        ];
    }

    public function gerenteDe(int $empresaId, int $centrocostoId): ?int
    {
        $arbol = $this->arbolDeEmpresa($empresaId);
        if (! $arbol) {
            return null;
        }

        $id = (int) (Arbolaprobacion_Nivel::query()
            ->where('arbolaprobacion_id', $arbol->id)
            ->where('centrocosto_id', $centrocostoId)
            ->value('usuario_id') ?? 0);

        return $id > 0 ? $id : null;
    }

    /** @return Collection<int|string, int> */
    private function conteoSuscripcionesPorCc(int $empresaId): Collection
    {
        return DB::table('ordencompra')
            ->where('es_suscripcion', true)
            ->where('empresa_id', $empresaId)
            ->whereNotNull('centrocosto_id')
            ->selectRaw('centrocosto_id, COUNT(*) AS total')
            ->groupBy('centrocosto_id')
            ->pluck('total', 'centrocosto_id');
    }

    private function assertCcYUsuario(int $centrocostoId, int $usuarioId): void
    {
        if ($centrocostoId <= 0) {
            throw new \InvalidArgumentException('Indicá el centro de costo.');
        }
        if ($usuarioId <= 0) {
            throw new \InvalidArgumentException('Indicá el gerente (usuario AnitaERP).');
        }
        if (! Centrocosto::query()->where('id', $centrocostoId)->exists()) {
            throw new \InvalidArgumentException('El centro de costo no existe.');
        }
        if (! DB::table('usuario')->where('id', $usuarioId)->exists()) {
            throw new \InvalidArgumentException('El usuario no existe.');
        }
    }

    /** @return array<string, mixed> */
    private function payloadNivel(int $arbolId, int $centrocostoId, int $usuarioId, int $monedaId): array
    {
        return [
            'arbolaprobacion_id' => $arbolId,
            'centrocosto_id' => $centrocostoId,
            'nivel' => 1,
            'usuario_id' => $usuarioId,
            'desdemonto' => 0,
            'hastamonto' => self::MONTO_MAXIMO,
            'moneda_id' => $monedaId,
            'documento_estado_al_aprobar' => OrdencompraEstados::APROBADA,
            'doble_aprobacion' => 'N',
            'rama' => null,
        ];
    }

    private function monedaBase(int $empresaId): int
    {
        $desdeOtroArbol = (int) (DB::table('arbolaprobacion_nivel')
            ->join('arbolaprobacion', 'arbolaprobacion.id', '=', 'arbolaprobacion_nivel.arbolaprobacion_id')
            ->where('arbolaprobacion.empresa_id', $empresaId)
            ->value('arbolaprobacion_nivel.moneda_id') ?? 0);

        if ($desdeOtroArbol > 0) {
            return $desdeOtroArbol;
        }

        return (int) (DB::table('moneda')->orderBy('id')->value('id') ?? 1);
    }
}

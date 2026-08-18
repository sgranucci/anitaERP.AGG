<?php

namespace App\Repositories\Solicitudpago;

use App\Models\Solicitudpago\Concepto_Solicitudpago;
use App\Models\Solicitudpago\Solicitudpago;
use App\Models\Solicitudpago\Solicitudpago_Archivo;
use App\Models\Solicitudpago\Solicitudpago_Cuenta;
use App\Models\Solicitudpago\Solicitudpago_Cuota;
use App\Models\Solicitudpago\Solicitudpago_Estado;
use App\Services\Solicitudpago\SolicitudpagoAnitaEscrituraService;
use App\Services\Solicitudpago\SolicitudpagoAnitaSyncService;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Solicitudpago\ConceptoSolicitudpagoFormaPago;
use App\Support\Solicitudpago\SolicitudpagoArchivoStorageSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoListadoFiltros;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use App\Support\Solicitudpago\SolicitudpagoVisibilidadSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SolicitudpagoRepository implements SolicitudpagoRepositoryInterface
{
    protected $model;

    public function __construct(
        Solicitudpago $model,
        private SolicitudpagoAnitaSyncService $syncService,
        private SolicitudpagoAnitaEscrituraService $escrituraService,
    ) {
        $this->model = $model;
    }

    public function all()
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        return $this->leeSolicitudpago(SolicitudpagoListadoFiltros::filtrosVacios(), false);
    }

    public function sincronizarConAnita(): array
    {
        return $this->syncService->sincronizar();
    }

    public function leeSolicitudpago(array|string|null $filtros, bool $paginar = true)
    {
        if (is_string($filtros)) {
            $f = SolicitudpagoListadoFiltros::filtrosVacios();
            $f['valor'] = $filtros;
            $f['busqueda_rapida'] = true;
            $filtros = $f;
        } elseif ($filtros === null) {
            $filtros = SolicitudpagoListadoFiltros::filtrosVacios();
        }

        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        $q = $this->model->newQuery()
            ->with([
                'empresas',
                'proveedores',
                'conceptos',
                'sectores',
                'centrocostos',
                'formapagosol',
                'monedas',
                'madre',
                'cajaMovimientosPago.tipotransaccioncajas:id,abreviatura,nombre',
            ])
            ->withCount([
                'cuotas as cuotas_pendientes_count' => fn ($qq) => $qq->whereNull('solicitudpago_hija_id'),
                'cuotas as cuotas_total_count',
                'hijas as hijas_count',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('codigo');

        SolicitudpagoVisibilidadSupport::aplicarFiltroListado(
            $q,
            'solicitudpago.empresa_id',
            'solicitudpago.centrocosto_id',
            SolicitudpagoVisibilidadSupport::forzarAlcanceCentrocostoDesdeFiltros(is_array($filtros) ? $filtros : [])
        );
        SolicitudpagoListadoFiltros::aplicar($q, $filtros);

        return $paginar ? $q->paginate(10) : $q->get();
    }

    public function create(array $data)
    {
        return $this->guardarCompleto($data, null);
    }

    public function update(array $data, $id)
    {
        return $this->guardarCompleto($data, (int) $id);
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            return false;
        }

        $codigo = (int) $registro->codigo;
        $ok = (bool) $this->model->destroy($id);
        if ($ok) {
            $this->escrituraService->eliminar($codigo);
        }

        return $ok;
    }

    public function find($id)
    {
        return $this->findOrFail($id);
    }

    public function findOrFail($id)
    {
        $registro = $this->model->newQuery()
            ->with([
                'empresas', 'proveedores', 'conceptos', 'formapagosol', 'monedas', 'sectores', 'centrocostos', 'madre',
                'hijas:id,codigo,estado,monto,fecha,solicitudpago_madre_id',
                'cuentas.empresas', 'cuentas.cuentacontables', 'cuentas.centrocostos',
                'cuotas.hijas',
                'estados.usuarios',
                'archivos.usuarios',
                'cajaMovimientosPago.tipotransaccioncajas:id,abreviatura,nombre',
            ])
            ->find($id);

        if ($registro === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $registro;
    }

    public function findPorCodigo(int $codigo)
    {
        return $this->model->newQuery()->where('codigo', $codigo)->first();
    }

    public function guardarCompleto(array $data, ?int $id = null)
    {
        return DB::transaction(function () use ($data, $id) {
            $existente = $id !== null ? $this->model->findOrFail($id) : null;
            $estadoAnterior = $existente?->estado;
            $payload = $this->normalizarCabecera($data, $existente);

            if ($existente) {
                $existente->update($payload);
                $sp = $existente->fresh();
            } else {
                $sp = $this->model->create($payload);
            }

            $this->guardarCuentas($sp, $data);
            $this->guardarCuotas($sp, $data);
            $this->guardarArchivos($sp, $data);

            if ($existente === null || ($estadoAnterior !== null && $estadoAnterior !== $sp->estado)) {
                $this->registrarEstadoLocal(
                    $sp,
                    $existente === null ? null : $estadoAnterior,
                    $sp->estado,
                    $existente === null ? 'Alta' : 'Actualiza estado'
                );
            }

            $sp = $sp->fresh([
                'empresas', 'proveedores', 'conceptos', 'formapagosol', 'monedas', 'sectores', 'madre',
                'cuentas.empresas', 'cuentas.cuentacontables', 'cuentas.centrocostos',
                'cuotas.hijas', 'archivos.usuarios',
            ]);

            if ($existente) {
                $this->escrituraService->actualizar($sp, $estadoAnterior);
            } else {
                $this->escrituraService->insertar($sp);
                if (
                    config('solicitudpago.arbol_al_crear', true)
                    && $sp->estado === SolicitudpagoEstados::EMITIDA
                ) {
                    try {
                        app(\App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService::class)
                            ->dispararAlGuardar((int) $sp->id);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('solicitudpago.arbol_al_crear', [
                            'id' => $sp->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return $sp;
        });
    }

    public function cambiarEstado(int $id, string $nuevoEstado, string $leyenda = '')
    {
        return DB::transaction(function () use ($id, $nuevoEstado, $leyenda) {
            $sp = $this->model->findOrFail($id);
            $anterior = $sp->estado;
            if ($anterior === $nuevoEstado) {
                return $sp;
            }
            $sp->update(['estado' => $nuevoEstado]);
            $this->registrarEstadoLocal($sp, $anterior, $nuevoEstado, $leyenda !== '' ? $leyenda : 'Cambio estado');
            $sp = $sp->fresh([
                'empresas', 'proveedores', 'conceptos', 'formapagosol', 'monedas', 'sectores', 'madre',
                'cuentas.empresas', 'cuentas.cuentacontables', 'cuentas.centrocostos',
                'cuotas.hijas', 'archivos.usuarios',
            ]);
            $this->escrituraService->actualizar($sp, $anterior);

            return $sp;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarCabecera(array $data, ?Solicitudpago $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        $tratamiento = (string) ($data['tratamiento'] ?? SolicitudpagoTratamientos::NORMAL);
        if (! in_array($tratamiento, array_column(SolicitudpagoTratamientos::opciones(), 'valor'), true)) {
            $tratamiento = SolicitudpagoTratamientos::NORMAL;
        }

        $estado = (string) ($data['estado'] ?? ($existente->estado ?? SolicitudpagoEstados::EMITIDA));
        if (! in_array($estado, array_column(SolicitudpagoEstados::opciones(), 'valor'), true)) {
            $estado = SolicitudpagoEstados::EMITIDA;
        }

        return [
            'codigo' => $codigo,
            'empresa_id' => (int) ($data['empresa_id'] ?? 0),
            'fecha' => $data['fecha'] ?? now()->toDateString(),
            'tratamiento' => $tratamiento,
            'proveedor_id' => $this->nullableInt($data['proveedor_id'] ?? null),
            'concepto_solicitudpago_id' => $this->nullableInt($data['concepto_solicitudpago_id'] ?? null),
            'formapagosol_id' => $this->nullableInt($data['formapagosol_id'] ?? null),
            'moneda_id' => $this->nullableInt($data['moneda_id'] ?? null),
            'beneficiario' => $this->recortar(trim((string) ($data['beneficiario'] ?? '')), 80) ?: null,
            'endoso' => $this->recortar(trim((string) ($data['endoso'] ?? '')), 80) ?: null,
            'fecha_entrega' => ! empty($data['fecha_entrega'])
                ? $data['fecha_entrega']
                : ($data['fecha'] ?? now()->toDateString()),
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
            'monto' => (float) str_replace(',', '.', (string) ($data['monto'] ?? 0)),
            'observacion' => $this->recortar(trim((string) ($data['observacion'] ?? '')), 160) ?: null,
            'estado' => $estado,
            'sector_solicitudpago_id' => $this->nullableInt($data['sector_solicitudpago_id'] ?? null),
            'detalle' => $this->recortar(trim((string) ($data['detalle'] ?? '')), 180) ?: null,
            'solicitudpago_madre_id' => $this->nullableInt($data['solicitudpago_madre_id'] ?? null),
            'usuario_umod_id' => Auth::id(),
            // CC de cabecera: en update se conserva. En alta: payload interno (ej. cuota) o CC del usuario en sesión.
            // No tomar del request del CRUD (ValidacionSolicitudpago no lo incluye).
            'centrocosto_id' => $existente !== null
                ? ($existente->centrocosto_id ? (int) $existente->centrocosto_id : null)
                : ($this->nullableInt($data['centrocosto_id'] ?? null) ?? $this->centrocostoIdUsuarioSesion()),
        ];
    }

    private function centrocostoIdUsuarioSesion(): ?int
    {
        $id = (int) (Auth::user()->centrocosto_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardarCuentas(Solicitudpago $sp, array $data): void
    {
        EloquentAuditDeleteSupport::each(
            Solicitudpago_Cuenta::query()->where('solicitudpago_id', $sp->id)
        );

        $empresaIds = $data['empresa_ids'] ?? [];
        $cuentaIds = $data['cuentacontable_ids'] ?? [];
        $ccIds = $data['centrocosto_ids'] ?? [];
        $dhs = $data['debe_haberes'] ?? [];
        $montos = $data['montos_cuenta'] ?? [];
        $montosDebe = $data['montos_debe'] ?? null;
        $montosHaber = $data['montos_haber'] ?? null;

        $n = max(count($empresaIds), count($cuentaIds));
        for ($i = 0; $i < $n; $i++) {
            $empresaId = (int) ($empresaIds[$i] ?? 0);
            $cuentaId = (int) ($cuentaIds[$i] ?? 0);
            if ($empresaId <= 0 || $cuentaId <= 0) {
                continue;
            }
            $cc = $this->nullableInt($ccIds[$i] ?? null);

            if (is_array($montosDebe) || is_array($montosHaber)) {
                $debe = (float) str_replace(',', '.', (string) (($montosDebe[$i] ?? '') !== '' ? ($montosDebe[$i] ?? 0) : 0));
                $haber = (float) str_replace(',', '.', (string) (($montosHaber[$i] ?? '') !== '' ? ($montosHaber[$i] ?? 0) : 0));
                if ($haber > 0 && $debe <= 0) {
                    $dh = 'H';
                    $monto = $haber;
                } elseif ($debe > 0) {
                    $dh = 'D';
                    $monto = $debe;
                } else {
                    continue;
                }
            } else {
                $dh = strtoupper(trim((string) ($dhs[$i] ?? 'D')));
                if ($dh !== 'H') {
                    $dh = 'D';
                }
                $monto = (float) str_replace(',', '.', (string) ($montos[$i] ?? 0));
                if ($monto <= 0) {
                    continue;
                }
            }

            Solicitudpago_Cuenta::query()->create([
                'solicitudpago_id' => $sp->id,
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
                'centrocosto_id' => $cc,
                'debe_haber' => $dh,
                'monto' => $monto,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardarCuotas(Solicitudpago $sp, array $data): void
    {
        $madreId = (int) ($data['solicitudpago_madre_id'] ?? $sp->solicitudpago_madre_id ?? 0);
        // SP hija: no arma ni borra plan propio; las cuotas viven en la madre.
        if ($madreId > 0) {
            return;
        }

        EloquentAuditDeleteSupport::each(
            Solicitudpago_Cuota::query()->where('solicitudpago_id', $sp->id)
        );

        $formaConcepto = null;
        $conceptoId = (int) ($data['concepto_solicitudpago_id'] ?? $sp->concepto_solicitudpago_id ?? 0);
        if ($conceptoId > 0) {
            $formaConcepto = Concepto_Solicitudpago::query()->whereKey($conceptoId)->value('forma_pago');
            $formaConcepto = $formaConcepto !== null ? (string) $formaConcepto : null;
        }
        if (! ConceptoSolicitudpagoFormaPago::requiereCuotas($formaConcepto, $madreId)) {
            return;
        }

        $nros = $data['nro_cuotas'] ?? [];
        $vtos = $data['fecha_vencimientos_cuota'] ?? [];
        $montos = $data['montos_cuota'] ?? [];
        $hijas = $data['solicitudpago_hija_ids'] ?? [];

        $n = max(count($nros), count($vtos), count($montos));
        for ($i = 0; $i < $n; $i++) {
            $vto = $vtos[$i] ?? null;
            $monto = (float) str_replace(',', '.', (string) ($montos[$i] ?? 0));
            if (! $vto || $monto == 0.0) {
                continue;
            }
            Solicitudpago_Cuota::query()->create([
                'solicitudpago_id' => $sp->id,
                'nro_cuota' => max(1, (int) ($nros[$i] ?? ($i + 1))),
                'fecha_vencimiento' => $vto,
                'monto' => $monto,
                'solicitudpago_hija_id' => $this->nullableInt($hijas[$i] ?? null),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardarArchivos(Solicitudpago $sp, array $data): void
    {
        if (array_key_exists('archivo_ids_existentes', $data)) {
            $mantener = collect($data['archivo_ids_existentes'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->all();

            $aBorrar = Solicitudpago_Archivo::query()
                ->where('solicitudpago_id', $sp->id)
                ->when($mantener !== [], fn ($q) => $q->whereNotIn('id', $mantener))
                ->get();

            foreach ($aBorrar as $arch) {
                SolicitudpagoArchivoStorageSupport::eliminar($arch, (int) $sp->codigo);
                $arch->delete();
            }
        }

        $nuevos = $data['archivos_nuevos'] ?? [];
        if (! is_array($nuevos)) {
            return;
        }

        $maxLinea = (int) (Solicitudpago_Archivo::query()->where('solicitudpago_id', $sp->id)->max('nro_linea') ?? 0);
        foreach ($nuevos as $file) {
            if (! ($file instanceof UploadedFile) || ! $file->isValid()) {
                continue;
            }
            $maxLinea++;
            $guardado = SolicitudpagoArchivoStorageSupport::guardarUpload($file, (int) $sp->codigo);
            Solicitudpago_Archivo::query()->create([
                'solicitudpago_id' => $sp->id,
                'nro_linea' => $maxLinea,
                'archivo' => $guardado['archivo'],
                'nombre_original' => $guardado['nombre_original'],
                'usuario_id' => Auth::id(),
                'fecha' => now()->toDateString(),
                'hora' => now()->format('H:i'),
            ]);
        }
    }

    private function registrarEstadoLocal(
        Solicitudpago $sp,
        ?string $anterior,
        string $actual,
        string $leyenda
    ): void {
        Solicitudpago_Estado::query()->create([
            'solicitudpago_id' => $sp->id,
            'fecha' => now()->toDateString(),
            'hora' => now()->format('H:i'),
            'usuario_id' => Auth::id(),
            'estado_anterior' => $anterior,
            'estado_actual' => $actual,
            'leyenda' => $this->recortar($leyenda, 80),
        ]);
    }

    private function proximoCodigo(): int
    {
        $maxLocal = (int) ($this->model->newQuery()->max('codigo') ?? 0);
        if (! $this->escrituraService->habilitada()) {
            return $maxLocal + 1;
        }

        $api = new \App\ApiAnita();
        $parsed = \App\ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => $this->escrituraService->sistema(),
            'tabla' => 'solpagomae',
            'campos' => 'max(solpm_id) as max_codigo',
        ]));
        $maxAnita = 0;
        if ($parsed['filas'] !== []) {
            $maxAnita = (int) ($parsed['filas'][0]->max_codigo ?? 0);
        }

        return max($maxLocal, $maxAnita) + 1;
    }

    private function nullableInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}

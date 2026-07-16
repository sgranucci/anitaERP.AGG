<?php

namespace App\Repositories\Solicitudpago;

use App\Models\Solicitudpago\Concepto_Solicitudpago;
use App\Models\Solicitudpago\Concepto_Solicitudpago_Cuenta;
use App\Models\Solicitudpago\Concepto_Solicitudpago_Formapago;
use App\Models\Solicitudpago\Concepto_Solicitudpago_Usuario;
use App\Services\Solicitudpago\ConceptoSolicitudpagoAnitaSyncService;
use App\Support\Solicitudpago\ConceptoSolicitudpagoEstados;
use App\Support\Solicitudpago\ConceptoSolicitudpagoFormaPago;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Conceptos de solicitudes de pago. CRUD local; sync pull Anita sin write-back.
 */
class Concepto_SolicitudpagoRepository implements Concepto_SolicitudpagoRepositoryInterface
{
    protected $model;

    public function __construct(
        Concepto_Solicitudpago $model,
        private ConceptoSolicitudpagoAnitaSyncService $syncService,
    ) {
        $this->model = $model;
    }

    public function all()
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        return $this->model->newQuery()
            ->with('sectores')
            ->orderBy('codigo')
            ->get();
    }

    public function sincronizarConAnita(): array
    {
        return $this->syncService->sincronizar();
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

        return (bool) $this->model->destroy($id);
    }

    public function find($id)
    {
        $registro = $this->model->newQuery()
            ->with([
                'sectores',
                'usuarios.usuarios',
                'cuentas.empresas',
                'cuentas.cuentacontables',
                'cuentas.centrocostos',
                'formapagos.formapagosol',
            ])
            ->find($id);

        if ($registro === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $registro;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }

    public function findPorCodigo(int $codigo)
    {
        return $this->model->newQuery()->where('codigo', $codigo)->first();
    }

    public function guardarCompleto(array $data, ?int $id = null)
    {
        return DB::transaction(function () use ($data, $id) {
            $existente = $id !== null ? $this->model->findOrFail($id) : null;
            $payload = $this->normalizarCabecera($data, $existente);

            if ($existente) {
                $existente->update($payload);
                $concepto = $existente->fresh();
            } else {
                $concepto = $this->model->create($payload);
            }

            $this->guardarUsuarios($concepto, $data);
            $this->guardarCuentas($concepto, $data);
            $this->guardarFormapagos($concepto, $data);

            return $concepto->fresh([
                'sectores',
                'usuarios.usuarios',
                'cuentas.cuentacontables',
                'formapagos.formapagosol',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarCabecera(array $data, ?Concepto_Solicitudpago $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        $formaPago = (string) ($data['forma_pago'] ?? ConceptoSolicitudpagoFormaPago::SIN_CUOTAS);
        if (! in_array($formaPago, [ConceptoSolicitudpagoFormaPago::SIN_CUOTAS, ConceptoSolicitudpagoFormaPago::CUOTAS], true)) {
            $formaPago = ConceptoSolicitudpagoFormaPago::SIN_CUOTAS;
        }

        $estado = (string) ($data['estado'] ?? ConceptoSolicitudpagoEstados::ACTIVO);
        if (! in_array($estado, [ConceptoSolicitudpagoEstados::ACTIVO, ConceptoSolicitudpagoEstados::SUSPENDIDO], true)) {
            $estado = ConceptoSolicitudpagoEstados::ACTIVO;
        }

        $sectorId = $data['sector_solicitudpago_id'] ?? null;
        $sectorId = $sectorId !== null && $sectorId !== '' ? (int) $sectorId : null;

        return [
            'codigo' => $codigo,
            'nombre' => $this->recortar(trim((string) ($data['nombre'] ?? '')), 50),
            'sector_solicitudpago_id' => $sectorId,
            'forma_pago' => $formaPago,
            'estado' => $estado,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardarUsuarios(Concepto_Solicitudpago $concepto, array $data): void
    {
        Concepto_Solicitudpago_Usuario::query()
            ->where('concepto_solicitudpago_id', $concepto->id)
            ->delete();

        $niveles = $data['niveles'] ?? [];
        $usuarioIds = $data['usuario_ids'] ?? [];
        $desdeMontos = $data['desdemontos'] ?? [];

        $count = max(count($niveles), count($usuarioIds), count($desdeMontos));
        for ($i = 0; $i < $count; $i++) {
            $nivel = (int) ($niveles[$i] ?? 0);
            $usuarioId = $usuarioIds[$i] ?? null;
            $usuarioId = $usuarioId !== null && $usuarioId !== '' ? (int) $usuarioId : null;
            if ($nivel <= 0 && $usuarioId === null) {
                continue;
            }
            if ($nivel <= 0) {
                $nivel = $i + 1;
            }

            Concepto_Solicitudpago_Usuario::query()->create([
                'concepto_solicitudpago_id' => $concepto->id,
                'nivel' => $nivel,
                'usuario_id' => $usuarioId,
                'usuario_orig_id' => null,
                'desde_monto' => (float) str_replace(',', '.', (string) ($desdeMontos[$i] ?? 0)),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardarCuentas(Concepto_Solicitudpago $concepto, array $data): void
    {
        Concepto_Solicitudpago_Cuenta::query()
            ->where('concepto_solicitudpago_id', $concepto->id)
            ->delete();

        $empresaIds = $data['empresa_ids'] ?? [];
        $cuentaIds = $data['cuentacontable_ids'] ?? [];
        $centrocostoIds = $data['centrocosto_ids'] ?? [];
        $debeHaberes = $data['debe_haberes'] ?? [];

        $count = max(count($empresaIds), count($cuentaIds));
        $vistos = [];
        for ($i = 0; $i < $count; $i++) {
            $empresaId = (int) ($empresaIds[$i] ?? 0);
            $cuentaId = (int) ($cuentaIds[$i] ?? 0);
            if ($empresaId <= 0 || $cuentaId <= 0) {
                continue;
            }
            $clave = $empresaId.'-'.$cuentaId;
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;

            $ccId = $centrocostoIds[$i] ?? null;
            $ccId = $ccId !== null && $ccId !== '' ? (int) $ccId : null;
            $dh = strtoupper(trim((string) ($debeHaberes[$i] ?? 'D')));
            if ($dh !== 'H') {
                $dh = 'D';
            }

            Concepto_Solicitudpago_Cuenta::query()->create([
                'concepto_solicitudpago_id' => $concepto->id,
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
                'centrocosto_id' => $ccId > 0 ? $ccId : null,
                'debe_haber' => $dh,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardarFormapagos(Concepto_Solicitudpago $concepto, array $data): void
    {
        Concepto_Solicitudpago_Formapago::query()
            ->where('concepto_solicitudpago_id', $concepto->id)
            ->delete();

        $fpIds = $data['formapagosol_ids'] ?? [];
        $vistos = [];
        foreach ($fpIds as $fpId) {
            $fpId = (int) $fpId;
            if ($fpId <= 0 || isset($vistos[$fpId])) {
                continue;
            }
            $vistos[$fpId] = true;

            Concepto_Solicitudpago_Formapago::query()->create([
                'concepto_solicitudpago_id' => $concepto->id,
                'formapagosol_id' => $fpId,
            ]);
        }
    }

    private function proximoCodigo(): int
    {
        return (int) ($this->model->newQuery()->max('codigo') ?? 0) + 1;
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}

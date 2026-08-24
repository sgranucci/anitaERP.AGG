<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Cliente_Exclusion_Percepcion;
use App\Support\Database\EloquentAuditDeleteSupport;
use Auth;

class Cliente_Exclusion_PercepcionRepository implements Cliente_Exclusion_PercepcionRepositoryInterface
{
    protected $model;

    public function __construct(Cliente_Exclusion_Percepcion $modelo)
    {
        $this->model = $modelo;
    }

    public function create(array $data, $id)
    {
        return $this->guardar($data, $id);
    }

    public function update(array $data, $id)
    {
        return $this->guardar($data, $id);
    }

    public function findPorClienteId($cliente_id)
    {
        return $this->model
            ->with('provincias')
            ->with('creousuarios')
            ->where('cliente_id', $cliente_id)
            ->orderBy('tipo')
            ->orderBy('desdefecha')
            ->get();
    }

    private function guardar(array $data, $clienteId)
    {
        $filas = $this->filasDesdeRequest($data);
        $idsConservar = [];

        foreach ($filas as $fila) {
            $fila['cliente_id'] = $clienteId;
            $idExistente = (int) ($fila['id'] ?? 0);
            unset($fila['id']);

            if ($idExistente > 0) {
                $existente = $this->model->where('cliente_id', $clienteId)->find($idExistente);
                if ($existente) {
                    $existente->update($fila);
                    $idsConservar[] = $existente->id;
                    continue;
                }
            }

            $creado = $this->model->create($fila);
            $idsConservar[] = $creado->id;
        }

        EloquentAuditDeleteSupport::exceptIds(
            $this->model->where('cliente_id', $clienteId),
            $idsConservar
        );

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filasDesdeRequest(array $data): array
    {
        $tipos = $data['exclusion_tipos'] ?? [];
        if (! is_array($tipos) || $tipos === []) {
            return [];
        }

        $ids = $data['exclusion_ids'] ?? [];
        $provinciaIds = $data['exclusion_provincia_ids'] ?? [];
        $porcentajes = $data['exclusion_porcentajes'] ?? [];
        $desdefechas = $data['exclusion_desdefechas'] ?? [];
        $hastafechas = $data['exclusion_hastafechas'] ?? [];
        $creousuarioIds = $data['exclusion_creousuario_ids'] ?? [];
        $usuarioActual = Auth::id();
        $filas = [];

        foreach ($tipos as $i => $tipo) {
            $tipo = strtoupper(trim((string) $tipo));
            if ($tipo === '' || ! isset(Cliente_Exclusion_Percepcion::$enumTipo[$tipo])) {
                continue;
            }

            $provinciaId = $tipo === 'IVA'
                ? null
                : $this->enteroONulo($provinciaIds[$i] ?? null);

            if ($tipo === 'IIBB' && $provinciaId === null) {
                continue;
            }

            $porcentaje = $this->normalizarPorcentaje($porcentajes[$i] ?? 0);
            $desdefecha = $this->fechaONula($desdefechas[$i] ?? null);
            $hastafecha = $this->fechaONula($hastafechas[$i] ?? null);
            if ($desdefecha === null && $hastafecha === null) {
                continue;
            }

            $filas[] = [
                'id' => $this->enteroONulo($ids[$i] ?? null),
                'tipo' => $tipo,
                'provincia_id' => $provinciaId,
                'porcentaje' => $porcentaje,
                'desdefecha' => $desdefecha,
                'hastafecha' => $hastafecha,
                'creousuario_id' => $this->enteroONulo($creousuarioIds[$i] ?? null) ?? $usuarioActual,
            ];
        }

        return $filas;
    }

    private function normalizarPorcentaje($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        $numero = round((float) str_replace(',', '.', (string) $valor), 4);
        if ($numero < 0) {
            return 0.0;
        }
        if ($numero > 100) {
            return 100.0;
        }

        return $numero;
    }

    private function enteroONulo($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $entero = (int) $valor;

        return $entero > 0 ? $entero : null;
    }

    private function fechaONula($valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '' || $texto === '0000-00-00') {
            return null;
        }

        return substr($texto, 0, 10);
    }
}

<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Cliente_Aviso;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\ClienteAvisoSupport;
use Auth;
use InvalidArgumentException;

class Cliente_AvisoRepository implements Cliente_AvisoRepositoryInterface
{
    protected $model;

    public function __construct(Cliente_Aviso $modelo)
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
            ->with('creousuarios')
            ->where('cliente_id', $cliente_id)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    private function guardar(array $data, $clienteId)
    {
        if (! array_key_exists('aviso_en_formulario', $data)) {
            return true;
        }

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
        $disparadores = $data['aviso_disparadores'] ?? [];
        if (! is_array($disparadores)) {
            return [];
        }

        $ids = $data['aviso_ids'] ?? [];
        $modos = $data['aviso_modos'] ?? [];
        $textos = $data['aviso_textos'] ?? [];
        $acciones = $data['aviso_acciones'] ?? [];
        $desdefechas = $data['aviso_desdefechas'] ?? [];
        $hastafechas = $data['aviso_hastafechas'] ?? [];
        $sinVencimiento = $data['aviso_sin_vencimiento'] ?? [];
        $creousuarioIds = $data['aviso_creousuario_ids'] ?? [];
        $usuarioActual = Auth::id();
        $filas = [];
        $orden = 0;

        foreach ($disparadores as $i => $disparador) {
            $numero = $i + 1;
            $disparador = strtolower(trim((string) $disparador));
            $modo = strtolower(trim((string) ($modos[$i] ?? '')));
            $texto = trim((string) ($textos[$i] ?? ''));
            $accion = strtolower(trim((string) ($acciones[$i] ?? '')));

            if ($disparador === '' && $texto === '' && $accion === '') {
                continue;
            }

            if (! isset(ClienteAvisoSupport::disparadores()[$disparador])) {
                throw new InvalidArgumentException('El aviso de la fila '.$numero.' no tiene un disparador válido.');
            }

            if (! isset(ClienteAvisoSupport::modos()[$modo])) {
                throw new InvalidArgumentException('El aviso de la fila '.$numero.' no indica si muestra un texto o ejecuta una acción.');
            }

            if ($modo === ClienteAvisoSupport::MODO_AVISO) {
                $accion = '';
                if ($texto === '') {
                    throw new InvalidArgumentException('El aviso de la fila '.$numero.' necesita el texto a mostrar.');
                }
            }

            if ($modo === ClienteAvisoSupport::MODO_ACCION) {
                if (! isset(ClienteAvisoSupport::acciones()[$accion])) {
                    throw new InvalidArgumentException('El aviso de la fila '.$numero.' necesita una acción.');
                }
            }

            if (mb_strlen($texto) > 2000) {
                throw new InvalidArgumentException('El texto del aviso de la fila '.$numero.' supera los 2000 caracteres.');
            }

            $desdefecha = $this->fechaONula($desdefechas[$i] ?? null);
            $sinFin = (string) ($sinVencimiento[$i] ?? '') === '1';
            $hastafecha = $sinFin ? null : $this->fechaONula($hastafechas[$i] ?? null);
            if ($desdefecha !== null && $hastafecha !== null && $hastafecha < $desdefecha) {
                throw new InvalidArgumentException('El aviso de la fila '.$numero.' tiene la fecha hasta anterior a la fecha desde.');
            }

            $filas[] = [
                'id' => $this->enteroONulo($ids[$i] ?? null),
                'disparador' => $disparador,
                'modo' => $modo,
                'texto' => $texto !== '' ? $texto : null,
                'accion' => $accion !== '' ? $accion : null,
                'desdefecha' => $desdefecha,
                'hastafecha' => $hastafecha,
                'orden' => $orden,
                'creousuario_id' => $this->enteroONulo($creousuarioIds[$i] ?? null) ?? $usuarioActual,
            ];
            $orden++;
        }

        return $filas;
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

<?php

namespace App\Http\Requests;

use App\Models\Stock\Depmae;
use App\Models\Stock\Prestamo;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ValidacionPrestamo extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $destTipo = (string) $this->input('destinatario_tipo', Prestamo::DEST_DEPOSITO);
        $espera = filter_var($this->input('espera_devolucion', true), FILTER_VALIDATE_BOOLEAN);

        return [
            'empresa_id' => 'required|integer|exists:empresa,id',
            'tipo' => ['required', Rule::in([Prestamo::TIPO_PRESTAMO, Prestamo::TIPO_REPARACION, Prestamo::TIPO_ENTREGA])],
            'destinatario_tipo' => ['required', Rule::in([Prestamo::DEST_DEPOSITO, Prestamo::DEST_USUARIO, Prestamo::DEST_EXTERNO])],
            'prioridad' => ['nullable', Rule::in([Prestamo::PRIORIDAD_BAJA, Prestamo::PRIORIDAD_NORMAL, Prestamo::PRIORIDAD_ALTA])],
            'fecha_prestamo' => 'required|date',
            'fecha_devolucion_prometida' => ($espera ? 'required' : 'nullable').'|date|after_or_equal:fecha_prestamo',
            'espera_devolucion' => 'nullable|boolean',
            'deposito_origen_id' => 'required|integer|exists:depmae,id',
            'deposito_destino_id' => $destTipo === Prestamo::DEST_DEPOSITO
                ? 'required|integer|exists:depmae,id|different:deposito_origen_id'
                : 'nullable|integer|exists:depmae,id',
            'destinatario_usuario_id' => $destTipo === Prestamo::DEST_USUARIO
                ? 'required|integer|exists:usuario,id'
                : 'nullable|integer|exists:usuario,id',
            'externo_nombre' => $destTipo === Prestamo::DEST_EXTERNO ? 'required|string|max:180' : 'nullable|string|max:180',
            'externo_documento' => 'nullable|string|max:40',
            'externo_telefono' => 'nullable|string|max:60',
            'externo_email' => 'nullable|email|max:120',
            'externo_empresa' => 'nullable|string|max:180',
            'observaciones' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1',
            'items.*.articulo_id' => 'nullable|integer',
            'items.*.descripcion' => 'nullable|string|max:255',
            'items.*.nro_serie' => 'nullable|string|max:80',
            'items.*.condicion_salida' => ['nullable', Rule::in([Prestamo::CONDICION_BUENO, Prestamo::CONDICION_REGULAR, Prestamo::CONDICION_DANADO])],
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.observaciones' => 'nullable|string|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresaId = (int) $this->input('empresa_id', 0);
            $origenId = (int) $this->input('deposito_origen_id', 0);
            $destinoId = (int) $this->input('deposito_destino_id', 0);
            $destTipo = (string) $this->input('destinatario_tipo', Prestamo::DEST_DEPOSITO);

            if ($empresaId > 0 && $origenId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($origenId, $empresaId)) {
                $validator->errors()->add('deposito_origen_id', 'El depósito origen no pertenece a la empresa seleccionada o no está autorizado para su usuario.');
            }

            if ($destTipo === Prestamo::DEST_DEPOSITO && $empresaId > 0 && $destinoId > 0
                && ! Depmae::autorizadoParaUsuarioYEmpresa($destinoId, $empresaId)) {
                $validator->errors()->add('deposito_destino_id', 'El depósito destino no pertenece a la empresa seleccionada o no está autorizado para su usuario.');
            }

            if ($destTipo === Prestamo::DEST_USUARIO) {
                $uid = (int) $this->input('destinatario_usuario_id', 0);
                if ($uid > 0) {
                    $usuario = UsuarioOperativoSupport::query()->whereKey($uid)->first();
                    if (! $usuario || ! UsuarioOperativoSupport::esOperativo($usuario)) {
                        $validator->errors()->add('destinatario_usuario_id', 'El usuario destinatario no está operativo o no existe.');
                    }
                }
            }

            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }
            foreach ($items as $idx => $row) {
                $articuloId = (int) ($row['articulo_id'] ?? 0);
                $descripcion = trim((string) ($row['descripcion'] ?? ''));
                if ($articuloId <= 0 && $descripcion === '') {
                    $validator->errors()->add(
                        "items.$idx.descripcion",
                        'Cada ítem debe tener artículo o una descripción libre.'
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('espera_devolucion')) {
            $tipo = (string) $this->input('tipo', Prestamo::TIPO_PRESTAMO);
            $this->merge([
                'espera_devolucion' => $tipo === Prestamo::TIPO_ENTREGA ? false : true,
            ]);
        } else {
            $this->merge([
                'espera_devolucion' => filter_var($this->input('espera_devolucion'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if (! $this->filled('prioridad')) {
            $this->merge(['prioridad' => Prestamo::PRIORIDAD_NORMAL]);
        }
    }

    public function messages(): array
    {
        return [
            'deposito_destino_id.different' => 'El depósito origen y destino deben ser distintos.',
            'fecha_devolucion_prometida.after_or_equal' => 'La fecha prometida de devolución no puede ser anterior a la fecha de la salida.',
            'items.required' => 'Debe cargar al menos un ítem.',
            'items.*.cantidad.gt' => 'La cantidad de cada ítem debe ser mayor a 0.',
            'externo_nombre.required' => 'Indique el nombre del destinatario externo.',
        ];
    }
}

        <td class="align-middle ms-col-canje-sentido" style="display:none;">
            @php
                $sentidoActual = strtoupper(trim((string) ($sentido ?? 'E')));
                if ($sentidoActual !== 'S' && $sentidoActual !== 'E') {
                    $sentidoActual = 'E';
                }
            @endphp
            <select name="sentidos[]" class="form-control form-control-sm ms-sentido-canje" title="Sale resta stock; Entra suma">
                <option value="S" @if ($sentidoActual === 'S') selected @endif>Sale</option>
                <option value="E" @if ($sentidoActual === 'E') selected @endif>Entra</option>
            </select>
        </td>

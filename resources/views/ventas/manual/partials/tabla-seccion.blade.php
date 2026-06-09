@if (!empty($tabla['caption']))
    <p class="table-caption">{{ $tabla['caption'] }}</p>
@endif
<div class="mc-table-wrap">
    <table>
        <thead>
            <tr>
                @foreach ($tabla['headers'] as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($tabla['rows'] as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

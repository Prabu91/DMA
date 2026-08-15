@props(['colspan' => 1])

<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-12 text-center text-sm text-ink-muted">
        {{ $slot }}
    </td>
</tr>

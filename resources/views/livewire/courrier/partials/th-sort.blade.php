@php($active = ($sortField ?? '') === $field)
<th style="padding:12px {{ $pad ?? '12px' }};font-weight:600">
    <button type="button" wire:click="trier('{{ $field }}')"
        aria-label="Trier par {{ $label }}{{ $active ? ($sortDir === 'asc' ? ' (croissant)' : ' (décroissant)') : '' }}"
        style="background:none;border:0;padding:0;font:inherit;font-weight:600;color:{{ $active ? 'var(--green-deep)' : 'inherit' }};letter-spacing:inherit;text-transform:inherit;cursor:pointer;display:inline-flex;align-items:center;gap:4px">
        {{ $label }}
        <span aria-hidden="true" style="font-size:9px;opacity:{{ $active ? '1' : '.35' }}">{{ $active ? (($sortDir ?? 'desc') === 'asc' ? '▲' : '▼') : '↕' }}</span>
    </button>
</th>

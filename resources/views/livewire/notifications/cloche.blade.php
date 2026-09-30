<div class="card" style="padding:18px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <h2 style="font-family:'Open Sans',sans-serif;font-size:16px;font-weight:600;margin:0;display:flex;align-items:center;gap:8px">
            Notifications
            <span class="badge" style="background:{{ $nonLues > 0 ? '#b4341f' : 'var(--surface-2)' }};color:{{ $nonLues > 0 ? '#fff' : 'var(--muted)' }}">{{ $nonLues }}</span>
        </h2>
        @if ($nonLues > 0)
            <button wire:click="toutMarquerLu" class="btn btn-ghost" style="padding:5px 11px;font-size:13px">Tout marquer lu</button>
        @endif
    </div>

    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px;font-size:14px">
        @forelse ($notifications as $n)
            <li style="border-left:3px solid {{ $n->read_at ? 'var(--line)' : 'var(--green)' }};background:var(--surface-2);padding:10px 12px;border-radius:8px">
                {{ $n->data['message'] ?? 'Notification' }}
                <span style="display:block;font-size:12px;color:var(--muted);margin-top:2px">{{ $n->created_at->diffForHumans() }}</span>
            </li>
        @empty
            <li style="color:var(--muted)">Aucune notification.</li>
        @endforelse
    </ul>
</div>

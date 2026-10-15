@extends('admin.layout')

@section('title', 'إدارة اللاعبين والعملات')
@section('header_title', 'سجل اللاعبين وإدارة الرصيد والحظر')

@section('content')
<!-- Search & Filters -->
<div class="card" style="margin-bottom: 24px; padding: 18px 24px;">
  <form action="{{ route('admin.players') }}" method="GET" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 240px;">
      <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="البحث بالاسم المستعار أو معرف اللاعب (ID)...">
    </div>
    <button type="submit" class="btn btn-gold"><i class="fa-solid fa-magnifying-glass"></i> بحث</button>
    @if(request('search'))
      <a href="{{ route('admin.players') }}" class="btn btn-outline">إلغاء البحث</a>
    @endif
  </form>
</div>

<!-- Players Table -->
<div class="card">
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>اللاعب</th>
          <th>معرف اللاعب (ID)</th>
          <th>العملات 💰</th>
          <th>الانتصارات 🏆</th>
          <th>المباريات 🎮</th>
          <th>نسبة الفوز</th>
          <th>الحالة</th>
          <th>آخر ظهور</th>
          <th>إجراءات الإدارة</th>
        </tr>
      </thead>
      <tbody>
        @forelse($players as $p)
        <tr>
          <td>
            <div style="font-weight: 800; color: #fff;">{{ $p->nickname }}</div>
          </td>
          <td><code>{{ $p->player_id }}</code></td>
          <td>
            <span style="font-weight: 800; color: var(--gold);">{{ number_format($p->coins) }}</span>
          </td>
          <td>{{ $p->stats_wins }}</td>
          <td>{{ $p->stats_matches }}</td>
          <td>
            @php
              $winRate = $p->stats_matches > 0 ? round(($p->stats_wins / $p->stats_matches) * 100) : 0;
            @endphp
            <span class="badge {{ $winRate >= 50 ? 'badge-green' : 'badge-gold' }}">{{ $winRate }}%</span>
          </td>
          <td>
            @if($p->is_banned)
              <span class="badge badge-red"><i class="fa-solid fa-ban"></i> محظور</span>
            @else
              <span class="badge badge-green"><i class="fa-solid fa-check"></i> نشط</span>
            @endif
          </td>
          <td style="font-size: 12px; color: var(--text-muted);">{{ $p->last_seen_at ? $p->last_seen_at->diffForHumans() : '-' }}</td>
          <td>
            <div style="display: flex; gap: 8px; align-items: center;">
              <!-- Edit Coins Form -->
              <form action="{{ route('admin.players.coins', $p->player_id) }}" method="POST" style="display: flex; gap: 4px;">
                @csrf
                <input type="number" name="coins" value="{{ $p->coins }}" style="width: 80px; padding: 4px 8px; font-size: 12px; border-radius: 6px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.15); color: #fff;" required>
                <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px;" title="حفظ العملات">
                  <i class="fa-solid fa-coins"></i>
                </button>
              </form>

              <!-- Ban Toggle Form -->
              <form action="{{ route('admin.players.ban', $p->player_id) }}" method="POST">
                @csrf
                @if($p->is_banned)
                  <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; color: #34d399;" title="إلغاء الحظر">
                    <i class="fa-solid fa-unlock"></i>
                  </button>
                @else
                  <button type="submit" class="btn btn-red" style="padding: 4px 8px; font-size: 11px;" title="حظر اللاعب">
                    <i class="fa-solid fa-ban"></i>
                  </button>
                @endif
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="9" style="text-align: center; padding: 36px; color: var(--text-muted);">
            لم يتم العثور على أي لاعبين يطابقون معايير البحث.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top: 20px;">
    {{ $players->links() }}
  </div>
</div>
@endsection

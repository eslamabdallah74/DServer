@extends('admin.layout')

@section('title', 'تقييمات وآراء اللاعبين')
@section('header_title')
  <i class="fa-solid fa-comments" style="color: var(--gold);"></i>
  <span>تقييمات وآراء اللاعبين</span>
@endsection

@section('content')
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
  <div class="card" style="display: flex; align-items: center; gap: 18px;">
    <div style="width: 54px; height: 54px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); display: flex; align-items: center; justify-content: center; color: var(--gold); font-size: 24px;">
      <i class="fa-solid fa-star"></i>
    </div>
    <div>
      <div style="font-size: 26px; font-weight: 900; color: #fff;">{{ $avgRating }} / 5.0</div>
      <div style="font-size: 13px; color: var(--text-muted);">متوسط تقييمات اللاعبين</div>
    </div>
  </div>

  <div class="card" style="display: flex; align-items: center; gap: 18px;">
    <div style="width: 54px; height: 54px; border-radius: 12px; background: rgba(59, 130, 246, 0.15); display: flex; align-items: center; justify-content: center; color: #60a5fa; font-size: 24px;">
      <i class="fa-solid fa-message"></i>
    </div>
    <div>
      <div style="font-size: 26px; font-weight: 900; color: #fff;">{{ $totalFeedbacks }}</div>
      <div style="font-size: 13px; color: var(--text-muted);">إجمالي التقييمات المستلمة</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-star-half-stroke"></i> آراء وملاحظات اللاعبين</h3>
  </div>

  @if($feedbacks->isEmpty())
    <div style="text-align: center; padding: 48px; color: var(--text-muted);">
      <i class="fa-solid fa-comment-dots" style="font-size: 42px; color: var(--gold); margin-bottom: 12px; display: block;"></i>
      لا توجد تقييمات مسجلة حتى الآن.
    </div>
  @else
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>معرف اللاعب</th>
            <th>التقييم</th>
            <th>التعليق / الملاحظة</th>
            <th>التاريخ</th>
          </tr>
        </thead>
        <tbody>
          @foreach($feedbacks as $fb)
            <tr>
              <td>#{{ $fb->feedback_id }}</td>
              <td>
                <span style="font-family: monospace; color: var(--gold); font-weight: 700;">{{ $fb->player_id ?: 'مجهول' }}</span>
              </td>
              <td>
                <div style="color: #fbbf24; font-size: 13px;">
                  @for($i = 1; $i <= 5; $i++)
                    @if($i <= $fb->rating)
                      <i class="fa-solid fa-star"></i>
                    @else
                      <i class="fa-regular fa-star" style="color: rgba(255,255,255,0.2);"></i>
                    @endif
                  @endfor
                  <span style="margin-right: 4px; font-weight: 700; color: #fff;">({{ $fb->rating }})</span>
                </div>
              </td>
              <td style="max-width: 440px; line-height: 1.5; font-size: 13px;">
                {{ $fb->content ?: ($fb->comment ?: 'بدون تعليق مكتوب') }}
              </td>
              <td style="font-size: 12px; color: var(--text-muted);">{{ $fb->created_at ? $fb->created_at->format('Y/m/d H:i') : '-' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div style="margin-top: 20px;">
      {{ $feedbacks->links() }}
    </div>
  @endif
</div>
@endsection

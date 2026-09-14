@php
    $user = auth()->user();
@endphp

<div>
    @if ($user?->isGuardian())
        <div class="fi-topbar-item guardian-student-selector">
            {{ $this->form }}
        </div>
    @endif
</div>

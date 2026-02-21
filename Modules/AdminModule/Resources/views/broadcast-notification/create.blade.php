@extends('adminmodule::layouts.master')

@section('title', translate('Send Notification'))

@section('content')
<div class="main-content">
    <div class="container-fluid">

        <div class="d-flex align-items-center gap-2 mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('Send Notification') }}</h2>
            <a href="{{ route('admin.broadcast-notification.index') }}" class="btn btn-outline-primary btn-sm ms-auto">
                {{ translate('History') }}
            </a>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                            </div>
                        @endif

                        <form action="{{ route('admin.broadcast-notification.send') }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            {{-- Target --}}
                            <div class="mb-4">
                                <label class="form-label fw-semibold">{{ translate('Target') }}</label>
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach(['all_customers' => 'All Clients', 'all_drivers' => 'All Drivers', 'all_users' => 'Everyone', 'specific_user' => 'Specific User'] as $val => $label)
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="target_type"
                                                   id="target_{{ $val }}" value="{{ $val }}"
                                                   {{ old('target_type', 'all_users') == $val ? 'checked' : '' }}>
                                            <label class="form-check-label" for="target_{{ $val }}">
                                                {{ translate($label) }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Specific user search --}}
                            <div class="mb-4" id="specific-user-section" style="display:none;">
                                <label class="form-label fw-semibold">{{ translate('Search User') }}</label>
                                <input type="text" id="user-search-input" class="form-control"
                                       placeholder="{{ translate('Name or phone...') }}" autocomplete="off">
                                <input type="hidden" name="target_user_id" id="target_user_id">
                                <div id="user-search-results" class="list-group mt-1 shadow-sm" style="z-index:999;position:absolute;width:50%"></div>
                            </div>

                            {{-- Title --}}
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ translate('Title') }}</label>
                                <input type="text" name="title" class="form-control"
                                       value="{{ old('title') }}" maxlength="200" required>
                            </div>

                            {{-- Message --}}
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ translate('Message') }}</label>
                                <textarea name="message" class="form-control" rows="4" required>{{ old('message') }}</textarea>
                            </div>

                            {{-- Image --}}
                            <div class="mb-4">
                                <label class="form-label fw-semibold">{{ translate('Image (optional)') }}</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                            </div>

                            <button type="submit" class="btn btn-primary px-5">
                                {{ translate('Send Now') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@push('script')
<script>
    // Show/hide specific user section
    document.querySelectorAll('input[name="target_type"]').forEach(el => {
        el.addEventListener('change', () => {
            document.getElementById('specific-user-section').style.display =
                el.value === 'specific_user' ? 'block' : 'none';
        });
    });

    // User search autocomplete
    let searchTimer;
    document.getElementById('user-search-input').addEventListener('input', function () {
        clearTimeout(searchTimer);
        const q = this.value.trim();
        const results = document.getElementById('user-search-results');
        if (q.length < 2) { results.innerHTML = ''; return; }
        searchTimer = setTimeout(() => {
            fetch(`{{ route('admin.broadcast-notification.user-search') }}?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(users => {
                    results.innerHTML = '';
                    users.forEach(u => {
                        const item = document.createElement('a');
                        item.href = '#';
                        item.className = 'list-group-item list-group-item-action';
                        item.textContent = `${u.first_name} ${u.last_name} — ${u.phone} (${u.user_type})`;
                        item.addEventListener('click', e => {
                            e.preventDefault();
                            document.getElementById('user-search-input').value = item.textContent;
                            document.getElementById('target_user_id').value = u.id;
                            results.innerHTML = '';
                        });
                        results.appendChild(item);
                    });
                });
        }, 300);
    });
</script>
@endpush
@endsection

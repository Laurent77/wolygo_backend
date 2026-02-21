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
                                <ul class="mb-0">
                                    @foreach($errors->all() as $e)
                                        <li>{{ $e }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('admin.broadcast-notification.send') }}" method="POST"
                              enctype="multipart/form-data" id="notif-form">
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

                                {{-- Selected user badge --}}
                                <div id="selected-user-badge" class="mb-2" style="display:none;">
                                    <span class="badge bg-primary fs-12 d-inline-flex align-items-center gap-2 py-2 px-3">
                                        <i class="bi bi-person-check"></i>
                                        <span id="selected-user-label"></span>
                                        <button type="button" id="clear-user-btn"
                                                class="btn-close btn-close-white ms-1"
                                                style="font-size:0.6rem;" aria-label="Clear"></button>
                                    </span>
                                </div>

                                {{-- Search input wrapper (position:relative pour le dropdown) --}}
                                <div style="position:relative;">
                                    <input type="text" id="user-search-input" class="form-control"
                                           placeholder="{{ translate('Name or phone...') }}"
                                           autocomplete="off">
                                    <input type="hidden" name="target_user_id" id="target_user_id"
                                           value="{{ old('target_user_id') }}">

                                    {{-- Dropdown results --}}
                                    <div id="user-search-results"
                                         class="list-group shadow"
                                         style="display:none; position:absolute; top:100%; left:0; right:0; z-index:1050; max-height:240px; overflow-y:auto;">
                                    </div>
                                </div>

                                <div id="user-search-error" class="text-danger small mt-1" style="display:none;">
                                    {{ translate('Please select a user from the list') }}
                                </div>
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
                                <i class="bi bi-send-fill me-1"></i>
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
(function () {
    const searchInput   = document.getElementById('user-search-input');
    const resultsBox    = document.getElementById('user-search-results');
    const hiddenInput   = document.getElementById('target_user_id');
    const badge         = document.getElementById('selected-user-badge');
    const badgeLabel    = document.getElementById('selected-user-label');
    const clearBtn      = document.getElementById('clear-user-btn');
    const errorMsg      = document.getElementById('user-search-error');
    const section       = document.getElementById('specific-user-section');
    const form          = document.getElementById('notif-form');

    // Show / hide specific-user section on radio change
    document.querySelectorAll('input[name="target_type"]').forEach(el => {
        el.addEventListener('change', function () {
            if (this.value === 'specific_user') {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
                clearSelection();
            }
        });
    });

    // Show section if old value was specific_user (after validation error)
    const checkedRadio = document.querySelector('input[name="target_type"]:checked');
    if (checkedRadio && checkedRadio.value === 'specific_user') {
        section.style.display = 'block';
    }

    // Search with debounce
    let timer;
    searchInput.addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim();
        resultsBox.innerHTML = '';

        if (q.length < 2) {
            resultsBox.style.display = 'none';
            return;
        }

        timer = setTimeout(() => {
            fetch(`{{ route('admin.broadcast-notification.user-search') }}?q=${encodeURIComponent(q)}`)
                .then(r => {
                    if (!r.ok) throw new Error('Network error');
                    return r.json();
                })
                .then(users => {
                    resultsBox.innerHTML = '';

                    if (users.length === 0) {
                        resultsBox.innerHTML = `<div class="list-group-item text-muted">{{ translate('no_data_found') }}</div>`;
                        resultsBox.style.display = 'block';
                        return;
                    }

                    users.forEach(u => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                        item.innerHTML = `
                            <span>
                                <i class="bi bi-person me-2 text-primary"></i>
                                <strong>${u.first_name} ${u.last_name}</strong>
                                <span class="text-muted ms-1">${u.phone}</span>
                            </span>
                            <span class="badge bg-secondary text-capitalize">${u.user_type}</span>
                        `;
                        item.addEventListener('click', () => {
                            selectUser(u);
                        });
                        resultsBox.appendChild(item);
                    });

                    resultsBox.style.display = 'block';
                })
                .catch(() => {
                    resultsBox.innerHTML = `<div class="list-group-item text-danger">{{ translate('Something went wrong') }}</div>`;
                    resultsBox.style.display = 'block';
                });
        }, 300);
    });

    // Select a user
    function selectUser(u) {
        hiddenInput.value      = u.id;
        badgeLabel.textContent = `${u.first_name} ${u.last_name} — ${u.phone}`;
        badge.style.display    = 'block';
        searchInput.value      = '';
        searchInput.style.display = 'none';
        resultsBox.style.display  = 'none';
        errorMsg.style.display    = 'none';
    }

    // Clear selection
    function clearSelection() {
        hiddenInput.value         = '';
        badge.style.display       = 'none';
        badgeLabel.textContent    = '';
        searchInput.value         = '';
        searchInput.style.display = 'block';
    }

    clearBtn.addEventListener('click', clearSelection);

    // Close dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
            resultsBox.style.display = 'none';
        }
    });

    // Client-side validation before submit
    form.addEventListener('submit', function (e) {
        const target = document.querySelector('input[name="target_type"]:checked');
        if (target && target.value === 'specific_user' && !hiddenInput.value) {
            e.preventDefault();
            errorMsg.style.display = 'block';
            searchInput.focus();
        }
    });
})();
</script>
@endpush
@endsection

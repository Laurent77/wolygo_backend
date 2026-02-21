@extends('adminmodule::layouts.master')

@section('title', translate('driver_document_validation_list'))

@section('content')

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mt-4 text-capitalize">{{ translate('driver_document_validation_list') }}</h2>

            <div class="row g-4">
                <div class="col-12">

                    <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-3">

                        {{-- Filtres par statut --}}
                        <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a href="{{ route('admin.driver-documents.index') }}"
                                   class="nav-link {{ !request()->has('status') || request()->get('status') == 'all' ? 'active' : '' }}">
                                    {{ translate('all') }}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="{{ route('admin.driver-documents.index', ['status' => 'pending']) }}"
                                   class="nav-link {{ request()->get('status') == 'pending' ? 'active' : '' }}">
                                    {{ translate('doc_status_pending') }}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="{{ route('admin.driver-documents.index', ['status' => 'rejected']) }}"
                                   class="nav-link {{ request()->get('status') == 'rejected' ? 'active' : '' }}">
                                    {{ translate('doc_status_rejected') }}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="{{ route('admin.driver-documents.index', ['status' => 'approved']) }}"
                                   class="nav-link {{ request()->get('status') == 'approved' ? 'active' : '' }}">
                                    {{ translate('doc_status_approved') }}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="{{ route('admin.driver-documents.index', ['status' => 'expired']) }}"
                                   class="nav-link {{ request()->get('status') == 'expired' ? 'active' : '' }}">
                                    {{ translate('doc_status_expired') }}
                                </a>
                            </li>
                        </ul>

                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted">{{ translate('total_driver') }} :</span>
                            <span class="text-primary fs-16 fw-bold">{{ $drivers->total() }}</span>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">

                            {{-- Barre de recherche --}}
                            <div class="table-top d-flex flex-wrap gap-10 justify-content-between mb-3">
                                <form method="GET" action="{{ route('admin.driver-documents.index') }}"
                                      class="d-flex gap-2 align-items-center">
                                    @if(request()->has('status'))
                                        <input type="hidden" name="status" value="{{ request('status') }}">
                                    @endif
                                    <div class="input-group input-group-merge input-group-custom">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                <i class="bi bi-search"></i>
                                            </div>
                                        </div>
                                        <input type="text" name="search" class="form-control"
                                               placeholder="{{ translate('Search_Here') }}"
                                               value="{{ request('search') }}">
                                    </div>
                                    <button type="submit" class="btn btn-primary">{{ translate('search') }}</button>
                                </form>
                            </div>

                            {{-- Tableau --}}
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle">
                                    <thead class="table-light text-capitalize">
                                        <tr>
                                            <th>{{ translate('SL') }}</th>
                                            <th>{{ translate('driver') }}</th>
                                            <th>{{ translate('phone') }}</th>
                                            <th class="text-center">{{ translate('total_docs') }}</th>
                                            <th class="text-center">{{ translate('doc_status_pending') }}</th>
                                            <th class="text-center">{{ translate('doc_status_rejected') }}</th>
                                            <th class="text-center">{{ translate('doc_status_approved') }}</th>
                                            <th>{{ translate('global_status') }}</th>
                                            <th class="text-center">{{ translate('action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($drivers as $index => $driver)
                                            @php
                                                $docs     = $driver->driverDocuments;
                                                $total    = $docs->count();
                                                $pending  = $docs->where('status', 'pending')->count();
                                                $rejected = $docs->where('status', 'rejected')->count();
                                                $approved = $docs->where('status', 'approved')->count();
                                                $expired  = $docs->whereIn('status', ['expired', 'expiring_soon'])->count();

                                                // Statut global : tous les types SOUMIS doivent avoir au moins 1 doc approuvé
                                                if ($total == 0) {
                                                    $globalClass = 'bg-secondary';
                                                    $globalLabel = translate('no_documents_submitted');
                                                } else {
                                                    $submittedTypes  = $docs->pluck('document_type')->unique();
                                                    $allTypesOk      = true;
                                                    $anyTypeRejected = false;

                                                    foreach ($submittedTypes as $t) {
                                                        $typeDocs = $docs->where('document_type', $t);
                                                        if ($typeDocs->where('status', 'approved')->isEmpty()) {
                                                            $allTypesOk = false;
                                                            if ($typeDocs->where('status', 'rejected')->isNotEmpty()) {
                                                                $anyTypeRejected = true;
                                                            }
                                                        }
                                                    }

                                                    if ($allTypesOk) {
                                                        $globalClass = 'bg-success';
                                                        $globalLabel = translate('doc_status_approved');
                                                    } elseif ($anyTypeRejected) {
                                                        $globalClass = 'bg-danger';
                                                        $globalLabel = translate('doc_status_rejected');
                                                    } elseif ($expired > 0) {
                                                        $globalClass = 'bg-warning text-dark';
                                                        $globalLabel = translate('doc_status_expiring_soon');
                                                    } else {
                                                        $globalClass = 'bg-warning text-dark';
                                                        $globalLabel = translate('doc_status_pending');
                                                    }
                                                }
                                            @endphp
                                            <tr>
                                                <td>{{ $drivers->firstItem() + $index }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="{{ onErrorImage(
                                                            $driver?->profile_image,
                                                            asset('storage/app/public/driver/profile') . '/' . $driver?->profile_image,
                                                            asset('public/assets/admin-module/img/avatar/avatar.png'),
                                                            'driver/profile/'
                                                        ) }}" alt="" class="rounded-circle" width="36" height="36" style="object-fit:cover">
                                                        <div>
                                                            <div class="fw-semibold">{{ $driver->first_name }} {{ $driver->last_name }}</div>
                                                            <div class="text-muted small">{{ $driver->email }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ $driver->phone }}</td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary">{{ $total }}</span>
                                                </td>
                                                <td class="text-center">
                                                    @if($pending > 0)
                                                        <span class="badge bg-warning text-dark">{{ $pending }}</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if($rejected > 0)
                                                        <span class="badge bg-danger">{{ $rejected }}</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if($approved > 0)
                                                        <span class="badge bg-success">{{ $approved }}</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge {{ $globalClass }} text-capitalize">
                                                        {{ $globalLabel }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('admin.driver.show', ['id' => $driver->id, 'tab' => 'documents']) }}"
                                                       class="btn btn-sm btn-outline-primary"
                                                       title="{{ translate('view_documents') }}">
                                                        <i class="bi bi-file-earmark-text"></i>
                                                        {{ translate('view_documents') }}
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center py-5 text-muted">
                                                    <i class="bi bi-folder2-open fs-1 d-block mb-2"></i>
                                                    {{ translate('no_data_found') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            {{-- Pagination --}}
                            <div class="d-flex justify-content-end mt-3">
                                {{ $drivers->appends(request()->all())->links() }}
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->

@endsection

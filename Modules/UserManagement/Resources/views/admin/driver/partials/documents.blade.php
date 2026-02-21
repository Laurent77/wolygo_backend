<div class="tab-pane fade active show" role="tabpanel">
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="d-flex align-items-center gap-2 text-primary text-capitalize mb-4">
                        <i class="bi bi-file-earmark-text"></i>
                        {{ translate('driver_documents') }}
                    </h5>

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if($otherData['documents']->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-folder2-open fs-1 d-block mb-3"></i>
                            <p class="mb-0">{{ translate('no_documents_submitted') }}</p>
                        </div>
                    @else
                        @php
                            // Grouper par type de document
                            $grouped = $otherData['documents']->groupBy('document_type');
                        @endphp

                        @foreach($grouped as $type => $typeDocs)
                            @php
                                $hasApproved  = $typeDocs->where('status', 'approved')->isNotEmpty();
                                $typeCount    = $typeDocs->count();
                            @endphp

                            {{-- En-tête du groupe --}}
                            <div class="d-flex align-items-center gap-2 mt-4 mb-2">
                                <span class="fw-bold text-capitalize fs-6">{{ translate($type) }}</span>
                                @if($typeCount > 1)
                                    <span class="badge bg-light text-secondary border">
                                        {{ $typeCount }} {{ translate('submissions') }}
                                    </span>
                                @endif
                                @if($hasApproved)
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="bi bi-check-circle-fill me-1"></i>{{ translate('doc_status_approved') }}
                                    </span>
                                @endif
                            </div>

                            <div class="table-responsive mb-2">
                                <table class="table table-bordered table-sm align-middle mb-0">
                                    <thead class="table-light text-capitalize small">
                                        <tr>
                                            <th style="width:36px">#</th>
                                            <th>{{ translate('document_number') }}</th>
                                            <th>{{ translate('issued_at') }}</th>
                                            <th>{{ translate('expires_at') }}</th>
                                            <th>{{ translate('files') }}</th>
                                            <th>{{ translate('status') }}</th>
                                            <th>{{ translate('submitted_at') }}</th>
                                            <th>{{ translate('action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($typeDocs->sortByDesc('created_at') as $loopIndex => $doc)
                                            <tr class="{{ $doc->status === 'approved' ? 'table-success' : ($doc->status === 'rejected' ? 'table-danger' : '') }}">
                                                <td class="text-muted small">{{ $loopIndex + 1 }}</td>
                                                <td>{{ $doc->document_number ?? '—' }}</td>
                                                <td class="small">{{ $doc->issued_at ? \Carbon\Carbon::parse($doc->issued_at)->format('d/m/Y') : '—' }}</td>
                                                <td class="small">
                                                    @if($doc->expires_at)
                                                        <span class="{{ \Carbon\Carbon::parse($doc->expires_at)->isPast() ? 'text-danger fw-semibold' : '' }}">
                                                            {{ \Carbon\Carbon::parse($doc->expires_at)->format('d/m/Y') }}
                                                        </span>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($doc->front_image_path)
                                                        <a href="{{ asset('storage/app/public/' . $doc->front_image_path) }}"
                                                           target="_blank" class="btn btn-xs btn-outline-secondary me-1 mb-1 py-0 px-1"
                                                           title="{{ translate('front_image') }}">
                                                            <i class="bi bi-image"></i> {{ translate('front') }}
                                                        </a>
                                                    @endif
                                                    @if($doc->back_image_path)
                                                        <a href="{{ asset('storage/app/public/' . $doc->back_image_path) }}"
                                                           target="_blank" class="btn btn-xs btn-outline-secondary me-1 mb-1 py-0 px-1"
                                                           title="{{ translate('back_image') }}">
                                                            <i class="bi bi-image"></i> {{ translate('back') }}
                                                        </a>
                                                    @endif
                                                    @if($doc->pdf_path)
                                                        <a href="{{ asset('storage/app/public/' . $doc->pdf_path) }}"
                                                           target="_blank" class="btn btn-xs btn-outline-danger mb-1 py-0 px-1"
                                                           title="PDF">
                                                            <i class="bi bi-file-pdf"></i> PDF
                                                        </a>
                                                    @endif
                                                    @if(!$doc->front_image_path && !$doc->back_image_path && !$doc->pdf_path)
                                                        <span class="text-muted small">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                        $statusClass = match($doc->status) {
                                                            'approved'      => 'bg-success',
                                                            'rejected'      => 'bg-danger',
                                                            'expired'       => 'bg-warning text-dark',
                                                            'expiring_soon' => 'bg-warning text-dark',
                                                            default         => 'bg-secondary',
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $statusClass }} text-capitalize">
                                                        {{ translate('doc_status_' . $doc->status) }}
                                                    </span>
                                                    @if($doc->status === 'rejected' && $doc->rejection_reason)
                                                        <div class="text-danger small mt-1">{{ $doc->rejection_reason }}</div>
                                                    @endif
                                                </td>
                                                <td class="small text-muted">
                                                    {{ $doc->created_at?->format('d/m/Y H:i') ?? '—' }}
                                                </td>
                                                <td class="text-nowrap">
                                                    @if($doc->status !== 'approved')
                                                        <form action="{{ route('admin.driver-documents.approve', $doc->id) }}"
                                                              method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-success mb-1"
                                                                onclick="return confirm('{{ translate('approve_document_confirm') }}')">
                                                                <i class="bi bi-check-circle"></i> {{ translate('approve') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                    <button type="button"
                                                            class="btn btn-sm btn-danger mb-1"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#rejectModal{{ $doc->id }}">
                                                        <i class="bi bi-x-circle"></i> {{ translate('reject') }}
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
            </div>{{-- /table-responsive --}}
                        @endforeach

                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reject Modals --}}
@foreach($otherData['documents'] as $doc)
    <div class="modal fade" id="rejectModal{{ $doc->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('admin.driver-documents.reject', $doc->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-capitalize">
                            {{ translate('reject_document') }} — {{ translate($doc->document_type) }}
                            @if($doc->document_number)
                                <small class="text-muted fs-6">({{ $doc->document_number }})</small>
                            @endif
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                {{ translate('rejection_reason') }} <span class="text-danger">*</span>
                            </label>
                            <textarea name="reason" class="form-control" rows="3" required
                                placeholder="{{ translate('enter_rejection_reason') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            {{ translate('cancel') }}
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> {{ translate('reject') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endforeach

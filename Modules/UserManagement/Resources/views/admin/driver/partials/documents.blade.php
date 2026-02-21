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
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle">
                                <thead class="table-light text-capitalize">
                                    <tr>
                                        <th>#</th>
                                        <th>{{ translate('document_type') }}</th>
                                        <th>{{ translate('document_number') }}</th>
                                        <th>{{ translate('issued_at') }}</th>
                                        <th>{{ translate('expires_at') }}</th>
                                        <th>{{ translate('files') }}</th>
                                        <th>{{ translate('status') }}</th>
                                        <th>{{ translate('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($otherData['documents'] as $index => $doc)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td class="text-capitalize fw-semibold">{{ translate($doc->document_type) }}</td>
                                            <td>{{ $doc->document_number ?? '—' }}</td>
                                            <td>{{ $doc->issued_at ?? '—' }}</td>
                                            <td>
                                                @if($doc->expires_at)
                                                    <span class="{{ \Carbon\Carbon::parse($doc->expires_at)->isPast() ? 'text-danger' : '' }}">
                                                        {{ $doc->expires_at }}
                                                    </span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                @if($doc->front_image_path)
                                                    <a href="{{ asset('storage/app/public/' . $doc->front_image_path) }}"
                                                       target="_blank" class="btn btn-sm btn-outline-secondary me-1 mb-1"
                                                       title="{{ translate('front_image') }}">
                                                        <i class="bi bi-image"></i> {{ translate('front') }}
                                                    </a>
                                                @endif
                                                @if($doc->back_image_path)
                                                    <a href="{{ asset('storage/app/public/' . $doc->back_image_path) }}"
                                                       target="_blank" class="btn btn-sm btn-outline-secondary me-1 mb-1"
                                                       title="{{ translate('back_image') }}">
                                                        <i class="bi bi-image"></i> {{ translate('back') }}
                                                    </a>
                                                @endif
                                                @if($doc->pdf_path)
                                                    <a href="{{ asset('storage/app/public/' . $doc->pdf_path) }}"
                                                       target="_blank" class="btn btn-sm btn-outline-danger mb-1"
                                                       title="PDF">
                                                        <i class="bi bi-file-pdf"></i> PDF
                                                    </a>
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
                        </div>
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

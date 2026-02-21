@extends('adminmodule::layouts.master')

@section('title', translate('Notification History'))

@section('content')
<div class="main-content">
    <div class="container-fluid">

        <div class="d-flex align-items-center gap-2 mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('Notification History') }}</h2>
            <a href="{{ route('admin.broadcast-notification.create') }}" class="btn btn-primary btn-sm ms-auto">
                + {{ translate('Send New Notification') }}
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ translate('Title') }}</th>
                                <th>{{ translate('Target') }}</th>
                                <th>{{ translate('Recipients') }}</th>
                                <th>{{ translate('Success') }}</th>
                                <th>{{ translate('Sent By') }}</th>
                                <th>{{ translate('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($notifications as $notif)
                            <tr>
                                <td>
                                    <strong>{{ $notif->title }}</strong>
                                    <br><small class="text-muted">{{ Str::limit($notif->message, 60) }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-info bg-opacity-10 text-info">
                                        {{ translate(str_replace('_', ' ', $notif->target_type)) }}
                                    </span>
                                    @if($notif->targetUser)
                                        <br><small>{{ $notif->targetUser->first_name }} {{ $notif->targetUser->last_name }}</small>
                                    @endif
                                </td>
                                <td>{{ $notif->total_recipients }}</td>
                                <td>
                                    <span class="text-success fw-semibold">{{ $notif->success_count }}</span>
                                    @if($notif->failure_count > 0)
                                        / <span class="text-danger">{{ $notif->failure_count }} {{ translate('failed') }}</span>
                                    @endif
                                </td>
                                <td>{{ $notif->sentBy?->first_name }} {{ $notif->sentBy?->last_name }}</td>
                                <td>{{ $notif->sent_at?->format('d M Y H:i') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    {{ translate('No notifications sent yet.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($notifications->hasPages())
                    <div class="p-3">{{ $notifications->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

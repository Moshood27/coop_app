@extends('layouts.mail')

@section('title', $title ?? 'Payment Update')

@section('content')
    <h2 class="status {{ $status ?? '' }}">{{ strtoupper($status ?? 'UPDATE') }}</h2>
    <h3 style="margin-top: 0;">{{ $title ?? 'Payment Update' }}</h3>

    <div class="card" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px;">
      <p>{{ $messageText ?? '' }}</p>
      @if(!empty($amount))
        <p><strong>Amount:</strong> ₦{{ number_format((float)$amount, 2) }}</p>
      @endif
      @if(!empty($reference))
        <p><strong>Reference:</strong> {{ $reference }}</p>
      @endif
      @if(!empty($channel))
        <p><strong>Channel:</strong> {{ $channel }}</p>
      @endif

      @if(!empty($meta) && is_array($meta))
        <div class="muted">
          <p><strong>Details</strong></p>
          <ul>
          @foreach($meta as $k => $v)
            <li>{{ ucfirst(str_replace('_',' ', (string) $k)) }}: {{ is_scalar($v) ? $v : json_encode($v) }}</li>
          @endforeach
          </ul>
        </div>
      @endif

      @if(!empty($route))
        <p>
          <a href="{{ url($route) }}" class="btn">View in App</a>
        </p>
      @endif

      <p class="muted">If you did not initiate this transaction, please contact support immediately.</p>
    </div>
@endsection

@extends('print.layout')

@section('title', 'Receipt '.$payment->receipt_number)

@section('content')
    @if ($payment->isVoided())
        <div class="void-stamp">VOID</div>
        <p class="muted">Voided on {{ $payment->voided_at->format('j M Y') }}: {{ $payment->void_reason }}</p>
    @endif

    <h2 class="doc-title">OFFICIAL RECEIPT</h2>

    <dl class="details">
        <dt>Receipt no.</dt><dd>{{ $payment->receipt_number }}</dd>
        <dt>Date</dt><dd>{{ $payment->paid_on->format('j M Y') }}</dd>
        <dt>Received from</dt><dd>{{ $student->full_name }}</dd>
        <dt>Adm. no.</dt><dd>{{ $student->admission_number }}</dd>
        <dt>Class</dt><dd>{{ $student->class_and_stream ?? '—' }}</dd>
        <dt>Term</dt><dd>{{ $payment->academicTerm?->name ?? '—' }}</dd>
        <dt>Paid by</dt><dd>{{ $payment->method->getLabel() }}</dd>
        <dt>Reference</dt><dd>{{ $payment->reference ?: '—' }}</dd>
    </dl>

    <div class="amount-box">
        <div class="muted">Amount received</div>
        <div class="big">{{ \App\Support\Money::format($payment->amount) }}</div>
        @if ($inWords)<div>{{ $inWords }}</div>@endif
    </div>

    @if ($balanceAfter !== null)
        <p>Fees balance after this payment: <strong>{{ \App\Support\Money::balance($balanceAfter) }}</strong></p>
        @if ($balanceNow !== $balanceAfter)
            <p class="muted">Balance as at {{ now()->format('j M Y') }}: {{ \App\Support\Money::balance($balanceNow) }}</p>
        @endif
    @endif

    @if ($payment->notes)
        <p class="muted">{{ $payment->notes }}</p>
    @endif

    <div class="signatures">
        <div>Received by: {{ $payment->receivedBy?->name ?? '—' }}</div>
        <div>Signature &amp; stamp</div>
    </div>
@endsection

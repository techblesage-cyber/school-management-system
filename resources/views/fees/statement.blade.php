@extends('print.layout')

@section('title', 'Fee statement · '.$student->full_name)

@section('content')
    <h2 class="doc-title">FEES STATEMENT</h2>

    @php($parent = $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first())

    <dl class="details">
        <dt>Student</dt><dd>{{ $student->full_name }}</dd>
        <dt>Adm. no.</dt><dd>{{ $student->admission_number }}</dd>
        <dt>Class</dt><dd>{{ $student->class_and_stream ?? '—' }}</dd>
        <dt>Residence</dt><dd>{{ $student->residence->getLabel() }}</dd>
        @if ($parent)
            <dt>Parent</dt><dd>{{ $parent->name }}</dd>
            <dt>Phone</dt><dd>{{ $parent->phone }}</dd>
        @endif
    </dl>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Details</th>
                <th class="num">Billed</th>
                <th class="num">Paid</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $line['date']->format('j M Y') }}</td>
                    <td>{{ $line['details'] }}</td>
                    <td class="num">{{ $line['debit'] ? number_format($line['debit']) : '' }}</td>
                    <td class="num">{{ $line['credit'] ? number_format($line['credit']) : '' }}</td>
                    <td class="num">{{ number_format($line['balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No invoices or payments yet.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Totals ({{ \App\Support\Money::currency() }})</td>
                <td class="num">{{ number_format($totals['billed']) }}</td>
                <td class="num">{{ number_format($totals['paid']) }}</td>
                <td class="num">{{ number_format($totals['balance']) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="amount-box">
        <div class="muted">Balance</div>
        <div class="big">{{ \App\Support\Money::balance($totals['balance']) }}</div>
    </div>
@endsection

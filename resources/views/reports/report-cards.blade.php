@php
    use App\Support\Money;
    use App\Support\Results\TermResults;

    $assessments = $results->assessments;
    $bands = $results->gradingKey()->values();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report {{ $cards->count() === 1 ? '· '.$cards->first()['result']->student->full_name : '· '.$results->class->name }} · {{ $term->name }}</title>
    @include('print.styles')
    <style>
        .report-card header.school { position: relative; padding-right: 96px; }
        .report-card .photo { position: absolute; right: 0; top: 0; width: 84px; height: 108px; object-fit: cover; border: 1px solid var(--line); border-radius: 4px; }
        .report-card .photo.empty { display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 11px; text-align: center; }
        .report-card table.marks th, .report-card table.marks td { padding: 6px 5px; font-size: 13px; }
        .report-card table.marks td.grade { font-weight: 700; text-align: center; }
        .report-card table.marks th.grade { text-align: center; }
        .report-card .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; margin: 0 0 16px; }
        .report-card .summary div { border: 1px solid var(--line); border-radius: 6px; padding: 8px 10px; }
        .report-card .summary span { display: block; color: var(--muted); font-size: 12px; }
        .report-card .summary strong { font-size: 16px; }
        .report-card .comment { border: 1px solid var(--line); border-radius: 6px; padding: 10px 12px; margin-bottom: 10px; }
        .report-card .comment .who { display: flex; justify-content: space-between; gap: 12px; color: var(--muted); font-size: 12px; margin-bottom: 4px; }
        .report-card .comment p { margin: 0; min-height: 1.4em; }
        .report-card .comment .sign { margin-top: 18px; border-top: 1px solid var(--ink); width: 180px; padding-top: 3px; font-size: 11px; color: var(--muted); }
        .report-card .key { font-size: 11px; color: var(--muted); margin-top: 12px; }
        .report-card + .report-card { margin-top: 32px; }
        @media print {
            .report-card { page-break-after: always; break-after: page; }
            .report-card:last-of-type { page-break-after: auto; break-after: auto; }
            .report-card + .report-card { margin-top: 0; }
        }
        @media (max-width: 600px) {
            .report-card header.school { padding-right: 0; }
            .report-card .photo { position: static; margin-bottom: 8px; }
            .report-card table.marks th.opt, .report-card table.marks td.opt { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print{{ $cards->count() > 1 ? ' all '.$cards->count() : '' }}</button>
    </div>

    @foreach ($cards as $card)
        @php
            /** @var \App\Support\Results\StudentResult $result */
            $result = $card['result'];
            $student = $result->student;
        @endphp
        <main class="page report-card">
            <header class="school">
                @if ($school->logo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($school->logo) }}" alt="">
                @endif
                <div>
                    <h1>{{ $school->name }}</h1>
                    @if ($school->motto)<p><em>{{ $school->motto }}</em></p>@endif
                    <p>{{ collect([$school->address, $school->phone, $school->email])->filter()->implode(' · ') }}</p>
                </div>
                @if ($student->photoUrl())
                    <img class="photo" src="{{ $student->photoUrl() }}" alt="">
                @else
                    <div class="photo empty">No photo</div>
                @endif
            </header>

            <h2 class="doc-title">TERM REPORT · {{ strtoupper($term->name) }}</h2>

            <dl class="details">
                <dt>Name</dt><dd>{{ $student->full_name }}</dd>
                <dt>Adm. no.</dt><dd>{{ $student->admission_number }}</dd>
                <dt>Class</dt><dd>{{ $result->classLabel }}</dd>
                <dt>LIN</dt><dd>{{ $student->lin ?: '—' }}</dd>
            </dl>

            <table class="marks">
                <thead>
                    <tr>
                        <th>Subject</th>
                        @foreach ($assessments as $assessment)
                            <th class="num opt">{{ $assessment->name }}<br><span class="muted">/{{ $assessment->max_score }}</span></th>
                        @endforeach
                        <th class="num">Mark %</th>
                        <th class="grade">Grade</th>
                        <th class="opt">Remark</th>
                        <th class="opt">Teacher</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($result->subjects as $subject)
                        <tr>
                            <td>{{ $subject->subject->name }}</td>
                            @foreach ($assessments as $assessment)
                                <td class="num opt">{{ array_key_exists($assessment->getKey(), $subject->scores) ? TermResults::number($subject->scores[$assessment->getKey()]) : '—' }}</td>
                            @endforeach
                            <td class="num"><strong>{{ TermResults::number($subject->final) ?: '—' }}</strong></td>
                            <td class="grade">{{ $subject->grade ?? '—' }}</td>
                            <td class="opt">{{ $subject->remark }}</td>
                            <td class="opt">{{ $subject->teacher }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $assessments->count() + 5 }}" class="muted">No marks this term.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="summary">
                <div><span>Average</span><strong>{{ $result->average !== null ? TermResults::number($result->average).'%' : '—' }}</strong></div>
                @if ($result->aggregate !== null)
                    <div><span>Aggregate</span><strong>{{ $result->aggregate }}</strong></div>
                    <div><span>Division</span><strong>{{ $result->division }}</strong></div>
                @elseif ($result->points !== null)
                    <div><span>Total points</span><strong>{{ TermResults::number($result->points) }}</strong></div>
                @elseif ($result->grade)
                    <div><span>Overall grade</span><strong>{{ $result->grade }}</strong>{{ $result->remark ? ' · '.$result->remark : '' }}</div>
                @endif
                @if ($showPositions && $result->position)
                    <div><span>Position in class</span><strong>{{ $result->position }}</strong> of {{ $result->outOf }}</div>
                    @if ($result->streamPosition)
                        <div><span>Position in stream</span><strong>{{ $result->streamPosition }}</strong> of {{ $result->streamOutOf }}</div>
                    @endif
                @endif
                @if ($card['attendance'])
                    <div><span>Attendance</span><strong>{{ $card['attendance']['present'] }}</strong> present · {{ $card['attendance']['absent'] }} absent</div>
                @endif
            </div>

            <div class="comment">
                <div class="who"><strong>Class teacher's comment</strong><span>{{ $card['classTeacher'] }}</span></div>
                <p>{{ $result->classTeacherComment }}</p>
                <div class="sign">Signature</div>
            </div>
            <div class="comment">
                <div class="who"><strong>Head teacher's comment</strong><span>{{ $headTeacher }}</span></div>
                <p>{{ $result->headTeacherComment }}</p>
                <div class="sign">Signature &amp; stamp</div>
            </div>

            <dl class="details">
                @if ($card['feesBalance'] !== null)
                    <dt>Fees balance</dt><dd>{{ Money::balance($card['feesBalance']) }}</dd>
                @endif
                <dt>Term ends</dt><dd>{{ $term->ends_on?->format('j M Y') ?? '—' }}</dd>
                <dt>Next term begins</dt><dd>{{ $nextTerm?->starts_on?->format('j M Y') ?? '—' }}</dd>
            </dl>

            @if ($bands->isNotEmpty())
                <p class="key">
                    Grading:
                    @foreach ($bands as $index => $band)
                        @php($upper = $index === 0 ? 100 : $bands[$index - 1]->min_score - 1)
                        {{ $band->grade }} {{ TermResults::number($band->min_score) }}–{{ TermResults::number(max($band->min_score, $upper)) }}{{ $band->remark ? ' ('.$band->remark.')' : '' }}{{ $loop->last ? '' : ' · ' }}
                    @endforeach
                    · Mark % combines the term's exams by their weights.
                </p>
            @endif

            <footer class="printed">Printed {{ now()->format('j M Y, H:i') }}</footer>
        </main>
    @endforeach
</body>
</html>

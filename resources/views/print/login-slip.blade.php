@extends('print.layout')

@section('title', 'Parents\' portal login')

@section('content')
    <h2 class="doc-title">Parents' portal login</h2>

    <p>Dear {{ $guardian->name }},</p>
    <p>You can now follow
        {{ $children->map->first_name->join(', ', ' and ') ?: 'your child' }}'s report cards, marks, attendance, fees and the school's circulars on your phone or computer.</p>

    <dl class="details" style="grid-template-columns: max-content 1fr; margin: 1rem 0; font-size: 15px;">
        <dt>Open</dt><dd>{{ $address }}</dd>
        <dt>Phone number</dt><dd>{{ $phone }}</dd>
        <dt>First password</dt><dd style="font-size: 20px; letter-spacing: .08em;">{{ $password }}</dd>
    </dl>

    <ol>
        <li>Open the address above on your phone or computer.</li>
        <li>Type your phone number and the first password.</li>
        <li>Choose your own password. Keep it secret.</li>
    </ol>

    <p class="muted">This login is for {{ $school->name }} only. Keep this slip safe or tear off the password once you have signed in.</p>
@endsection

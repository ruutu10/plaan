{{--
    The submitted technical plan, mailed to the performer and the technical
    team.

    The document itself comes in already rendered as `$doc`, by the same rules
    the wizard's review page and the printout use — see
    App\Http\Resources\PlanDocument. Nothing here decides how a value reads.
--}}
@php
    $cell = 'border:1px solid #dce0e7; padding:8px 11px; vertical-align:top; font-size:14px; line-height:1.5; color:#3d4557;';
    $labelCell = $cell.' width:34%; background-color:#eef0f4; font-weight:700; color:#0c0f16;';
    $headCell = 'border:1px solid #11234f; background-color:#11234f; padding:7px 10px; text-align:left; font-size:12px; font-weight:700; color:#ffffff;';
    $sectionTitle = 'margin:26px 0 10px 0; font-family:\'Futura\',\'Century Gothic\',\'Roboto\',Arial,sans-serif; font-size:14px; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; color:#11234f;';
    $link = 'color:#e85f30; text-decoration:underline;';
@endphp
@extends('emails.layout')

@section('title', 'Esitatud tehnikaplaan')

@section('preheader', $doc['formatName'].' · tehnikaplaan on esitatud.')

@section('content')
    <h1 style="margin:0 0 16px 0; font-family:'Futura','Century Gothic','Roboto',Arial,sans-serif; font-size:24px; line-height:1.15; font-weight:700; letter-spacing:0.01em; text-transform:uppercase; color:#0c0f16;">
        {{ $doc['formatName'] }}
    </h1>

    @if ($isAuthor)
        <p style="margin:0 0 24px 0; font-size:16px; line-height:1.6;">
            Sinu tehnikaplaan on tehnikutiimile esitatud. Allpool on plaan tervikuna —
            hoia see kiri alles. Kui midagi vajab muutmist, ava plaan lingi kaudu, tee
            parandused ja esita uuesti.
        </p>
    @else
        <p style="margin:0 0 24px 0; font-size:16px; line-height:1.6;">
            Esineja esitas uue tehnikaplaani. Allpool on plaan tervikuna;
            küsimustega saab pöörduda plaani koostaja poole aadressil
            <a href="mailto:{{ $contactEmail }}" style="{{ $link }}">{{ $doc['contact'] }}</a>.
        </p>
    @endif

    {{-- Sharing link --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f2f4f7; border-radius:14px;">
        <tr>
            <td style="padding:18px 22px;">
                <div style="font-size:11px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:#11234f;">
                    Plaani link
                </div>
                <p style="margin:8px 0 0 0; font-size:13px; line-height:1.6; word-break:break-all;">
                    <a href="{{ $publicUrl }}" target="_blank" style="{{ $link }}">{{ $publicUrl }}</a>
                </p>
                <p style="margin:8px 0 0 0; font-size:12px; line-height:1.6; color:#6b7386;">
                    Plaani võti: <strong style="color:#3d4557;">{{ $doc['token'] }}</strong>
                </p>
            </td>
        </tr>
    </table>

    <div style="{{ $sectionTitle }}">Etendus</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
        <tr>
            <td style="{{ $labelCell }}">Esineja</td>
            <td style="{{ $cell }}">{{ $doc['performer'] }}</td>
        </tr>
        <tr>
            <td style="{{ $labelCell }}">Kontakt</td>
            <td style="{{ $cell }}">{{ $doc['contact'] }}</td>
        </tr>
        <tr>
            <td style="{{ $labelCell }}">Etenduse aeg</td>
            <td style="{{ $cell }}">{{ $doc['performanceDate'] }} {{ $doc['startTime'] }}</td>
        </tr>
        <tr>
            <td style="{{ $labelCell }}">Kestus</td>
            <td style="{{ $cell }}">{{ $doc['durationLabel'] }}</td>
        </tr>
        <tr>
            <td style="{{ $labelCell }}">Lühikirjeldus</td>
            <td style="{{ $cell }} white-space:pre-line;">{{ $doc['description'] }}</td>
        </tr>
    </table>

    <div style="{{ $sectionTitle }}">Heliplaan</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
        <tr>
            <td style="{{ $labelCell }}">Mikrofonid</td>
            <td style="{{ $cell }} white-space:pre-line;">{{ $doc['micsSummary'] }}</td>
        </tr>
        <tr>
            <td style="{{ $labelCell }}">Oma muusik</td>
            <td style="{{ $cell }} white-space:pre-line;">{{ $doc['musicianSummary'] }}</td>
        </tr>
    </table>

    <div style="{{ $sectionTitle }}">Stseenid</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
        <tr>
            <th style="{{ $headCell }} text-align:center;">Nr</th>
            <th style="{{ $headCell }}">Nimi</th>
            <th style="{{ $headCell }}">Valgus</th>
            <th style="{{ $headCell }}">Heli</th>
            <th style="{{ $headCell }}">Märkmed</th>
        </tr>
        @foreach ($doc['scenes'] as $scene)
            <tr>
                <td style="{{ $cell }} text-align:center; font-weight:700; color:#11234f;">{{ $scene['num'] }}</td>
                <td style="{{ $cell }} font-weight:700; word-break:break-word;">{{ $scene['name'] }}</td>
                <td style="{{ $cell }} word-break:break-word; white-space:pre-line;">{{ $scene['light'] }}</td>
                <td style="{{ $cell }} word-break:break-word;">
                    {{-- The uploaded file gets its own line so it stays clickable. --}}
                    @if ($scene['soundFile'])
                        <a href="{{ $scene['soundFile']['url'] }}" style="{{ $link }}">{{ $scene['soundFile']['name'] }}</a>
                        ({{ $scene['soundFile']['sizeLabel'] }})<br>
                    @endif
                    @if ($scene['soundUrl'])
                        <a href="{{ $scene['soundUrl'] }}" style="{{ $link }}">{{ $scene['soundUrl'] }}</a><br>
                    @endif
                    <span style="white-space:pre-line;">{{ $scene['soundText'] }}</span>
                </td>
                <td style="{{ $cell }} word-break:break-word; white-space:pre-line;">{{ $scene['notes'] }}</td>
            </tr>
        @endforeach
    </table>

    <div style="{{ $sectionTitle }}">Erivahendid &amp; load</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
        @foreach ($doc['equipmentItems'] as $item)
            <tr>
                <td style="{{ $labelCell }}">{{ $item['name'] }}</td>
                <td style="{{ $cell }}">{{ $item['use'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td style="{{ $labelCell }}">Suitsuefektid</td>
            <td style="{{ $cell }}">{{ $doc['smokeSummary'] }}</td>
        </tr>
        <tr>
            <td style="{{ $labelCell }}">Tehniku pakkumised</td>
            <td style="{{ $cell }} white-space:pre-line;">{{ $doc['suggestionsLine'] }}</td>
        </tr>
    </table>

    <div style="{{ $sectionTitle }}">Lisainfo</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
        <tr>
            <td style="{{ $cell }} white-space:pre-line;">{{ $doc['notes'] }}</td>
        </tr>
    </table>

    @if ($doc['files'])
        <p style="margin:14px 0 6px 0; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#11234f;">
            Manused
        </p>
        @foreach ($doc['files'] as $file)
            <p style="margin:0 0 5px 0; font-size:13px; line-height:1.5; word-break:break-all;">
                <a href="{{ $file['url'] }}" style="{{ $link }}">{{ $file['name'] }}</a>
                <span style="color:#6b7386;">({{ $file['sizeLabel'] }})</span>
            </p>
        @endforeach
    @endif
@endsection

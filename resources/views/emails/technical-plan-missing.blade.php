{{--
    The reminder that a performance is coming up and no technical plan has been
    handed in for it.

    Two readers, two copies. A performer's copy ($isPerformer) carries a magic
    link that signs them in and opens the wizard on the right night — which is
    why it is addressed to them alone and to nobody else. The technical team's
    copy carries the same facts, the roster of who was chased, and a plain link
    that signs nobody in.
--}}
@php
    $cell = 'border:1px solid #dce0e7; padding:8px 11px; vertical-align:top; font-size:14px; line-height:1.5; color:#3d4557;';
    $labelCell = $cell.' width:34%; background-color:#eef0f4; font-weight:700; color:#0c0f16;';
    $sectionTitle = 'margin:26px 0 10px 0; font-family:\'Futura\',\'Century Gothic\',\'Roboto\',Arial,sans-serif; font-size:14px; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; color:#11234f;';
    $link = 'color:#e85f30; text-decoration:underline;';
@endphp
@extends('emails.layout')

@section('title', $isPerformer ? 'Tehnikaplaan on esitamata' : 'Tehnikaplaan on endiselt ootel')

@section('preheader', $formatName.' · tehnikaplaan on veel esitamata.')

@section('content')
    <h1 style="margin:0 0 16px 0; font-family:'Futura','Century Gothic','Roboto',Arial,sans-serif; font-size:24px; line-height:1.15; font-weight:700; letter-spacing:0.01em; text-transform:uppercase; color:#0c0f16;">
        {{ $formatName }}
    </h1>

    @if ($isPerformer)
        <p style="margin:0 0 24px 0; font-size:16px; line-height:1.6;">
            Sinu etendus on {{ $noticeLabel }} — <strong style="color:#0c0f16;">{{ $startsAt->format('d.m.Y') }}</strong>
            kell <strong style="color:#0c0f16;">{{ $startsAt->format('H:i') }}</strong> — kuid tehnikaplaan on veel esitamata.
            Ilma plaanita ei tea tehnik, millist valgust, heli ja erivahendeid te laval vajate.
        </p>
    @else
        <p style="margin:0 0 24px 0; font-size:16px; line-height:1.6;">
            Etendus on {{ $noticeLabel }} — <strong style="color:#0c0f16;">{{ $startsAt->format('d.m.Y') }}</strong>
            kell <strong style="color:#0c0f16;">{{ $startsAt->format('H:i') }}</strong> — ja tehnikaplaani pole endiselt esitatud.
            Esinejatele läks samal ajal välja meeldetuletus; see kiri on teadmiseks tehnikutiimile.
        </p>
    @endif

    {{-- The one thing this mail is for. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f2f4f7; border-radius:14px;">
        <tr>
            <td align="center" style="padding:26px 22px;">
                <a href="{{ $planUrl }}"
                   target="_blank"
                   style="display:inline-block; padding:14px 30px; border-radius:999px; background-color:#e85f30; font-family:'Futura','Century Gothic','Roboto',Arial,sans-serif; font-size:14px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#ffffff; text-decoration:none;">
                    {{ $isPerformer ? 'Täida tehnikaplaan' : 'Vaata tehnikaplaane' }}
                </a>
                @if ($isPerformer)
                    <p style="margin:14px 0 0 0; font-size:12px; line-height:1.6; color:#6b7386;">
                        Link logib sind ise sisse ja avab plaani õige etendusega.
                    </p>
                @endif
            </td>
        </tr>
    </table>

    <div style="{{ $sectionTitle }}">Etendus</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
        <tr>
            <td style="{{ $labelCell }}">Formaat</td>
            <td style="{{ $cell }}">{{ $formatName }}</td>
        </tr>
        @if ($performer)
            <tr>
                <td style="{{ $labelCell }}">Esineja</td>
                <td style="{{ $cell }}">{{ $performer }}</td>
            </tr>
        @endif
        <tr>
            <td style="{{ $labelCell }}">Kuupäev</td>
            <td style="{{ $cell }}">{{ $startsAt->format('d.m.Y') }}</td>
        </tr>
        <tr>
            <td style="{{ $labelCell }}">Algus</td>
            <td style="{{ $cell }}">{{ $startsAt->format('H:i') }}</td>
        </tr>
        <tr>
            <td style="{{ $labelCell }}">Kestus</td>
            <td style="{{ $cell }}">{{ $duration ? $duration.' min' : '—' }}</td>
        </tr>
    </table>

    @if (! $isPerformer && $chased)
        {{-- Who to chase by hand, if it comes to that. --}}
        <div style="{{ $sectionTitle }}">Meeldetuletus saadeti</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
            @foreach ($chased as $performer)
                <tr>
                    <td style="{{ $labelCell }}">{{ $performer['name'] }}</td>
                    <td style="{{ $cell }}">
                        <a href="mailto:{{ $performer['email'] }}" style="{{ $link }}">{{ $performer['email'] }}</a>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($isPerformer)
        <p style="margin:24px 0 0 0; font-size:13px; line-height:1.6; color:#6b7386;">
            Kui plaan on juba teel või etendus ära jääb, anna palun teada aadressil
            <a href="mailto:{{ $techEmail }}" style="{{ $link }}">{{ $techEmail }}</a>.
        </p>
    @endif
@endsection

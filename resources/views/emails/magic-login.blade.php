{{-- The link that lets a performer back in to finish their plan. --}}
@php($width = 560)
@extends('emails.layout')

@section('title', 'Sinu sisselogimislink')

@section('preheader', 'Sinu sisselogimislink tehnikaplaani keskkonda.')

@section('content')
    <h1 style="margin:0 0 16px 0; font-family:'Futura','Century Gothic','Roboto',Arial,sans-serif; font-size:26px; line-height:1.15; font-weight:700; letter-spacing:0.01em; text-transform:uppercase; color:#0c0f16;">
        Tehnikaplaani esitamine
    </h1>

    <p style="margin:0 0 12px 0; font-size:16px; line-height:1.6; color:#3d4557;">
        Tere{{ ! empty($name) ? ', '.$name : '' }}!
    </p>
    <p style="margin:0 0 12px 0; font-size:16px; line-height:1.6; color:#3d4557;">
        Plaan on Ruutu10 improteatri tehnikaplaneerimise süsteem, kus esinevad trupid annavad tehnikatiimile üle oma etenduse valgus- ja helivajadused. Said selle kirja, sest küsisid tehnikaplaani keskkonda sisselogimiseks lingi.
    </p>
    <p style="margin:0 0 28px 0; font-size:16px; line-height:1.6; color:#3d4557;">
        Sisselogimiseks vajuta alloleval nupul.
        Seejärel saad jätkata tehnikaplaani esitamist.
    </p>

    {{-- Bulletproof CTA button --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 28px auto;">
        <tr>
            <td align="center" bgcolor="#ff7f50" style="border-radius:999px;">
                <a href="{{ $url }}"
                   target="_blank"
                   style="display:inline-block; padding:15px 34px; font-family:'Roboto','Helvetica Neue',Arial,sans-serif; font-size:15px; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; color:#11234f; text-decoration:none; border-radius:999px;">
                    Logi sisse
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 6px 0; font-size:13px; line-height:1.6; color:#6b7386;">
        Link kehtib <strong style="color:#3d4557;">30 minutit</strong>. Peale tehnikaplaani esitamist jääd mõneks ajaks sisselogituks.
    </p>
    <p style="margin:0; font-size:13px; line-height:1.6; color:#6b7386;">
        Kui sa seda linki ei tellinud, võid selle kirja eirata.
    </p>

    {{-- Divider --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0 0;">
        <tr>
            <td style="border-top:1px solid #dce0e7; line-height:1px; font-size:1px;">&nbsp;</td>
        </tr>
    </table>

    {{-- Fallback URL --}}
    <p style="margin:22px 0 6px 0; font-size:12px; line-height:1.5; color:#6b7386;">
        Kui nupp ei tööta, kopeeri see aadress brauserisse:
    </p>
    <p style="margin:0; font-size:12px; line-height:1.5; word-break:break-all;">
        <a href="{{ $url }}" target="_blank" style="color:#e85f30; text-decoration:underline;">{{ $url }}</a>
    </p>
@endsection

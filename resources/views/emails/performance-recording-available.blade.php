{{--
    The word to a plan's author that the video of their night is up. Short by
    design — it carries nothing about the recording itself, only that there is
    one and where to watch it.
--}}
@php
    $cell = 'border:1px solid #dce0e7; padding:8px 11px; vertical-align:top; font-size:14px; line-height:1.5; color:#3d4557;';
    $labelCell = $cell.' width:34%; background-color:#eef0f4; font-weight:700; color:#0c0f16;';
    $sectionTitle = 'margin:26px 0 10px 0; font-family:\'Futura\',\'Century Gothic\',\'Roboto\',Arial,sans-serif; font-size:14px; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; color:#11234f;';
    $link = 'color:#e85f30; text-decoration:underline;';
@endphp
@extends('emails.layout')

@section('title', 'Etenduse salvestus on saadaval')

@section('preheader', $formatName.' · etenduse salvestus on nüüd vaadatav.')

@section('content')
    <h1 style="margin:0 0 16px 0; font-family:'Futura','Century Gothic','Roboto',Arial,sans-serif; font-size:24px; line-height:1.15; font-weight:700; letter-spacing:0.01em; text-transform:uppercase; color:#0c0f16;">
        {{ $formatName }}
    </h1>

    <p style="margin:0 0 24px 0; font-size:16px; line-height:1.6;">
        Sinu etenduse salvestus on jõudnud Jellyfini ja on nüüd järelvaadatav.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f2f4f7; border-radius:14px;">
        <tr>
            <td align="center" style="padding:26px 22px;">
                <a href="{{ $recordingUrl }}"
                   target="_blank"
                   style="display:inline-block; padding:14px 30px; border-radius:999px; background-color:#e85f30; font-family:'Futura','Century Gothic','Roboto',Arial,sans-serif; font-size:14px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#ffffff; text-decoration:none;">
                    Vaata salvestust
                </a>
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
        @if ($startsAt)
            <tr>
                <td style="{{ $labelCell }}">Kuupäev</td>
                <td style="{{ $cell }}">{{ $startsAt->format('d.m.Y') }}</td>
            </tr>
        @endif
        @if ($location)
            <tr>
                <td style="{{ $labelCell }}">Asukoht</td>
                <td style="{{ $cell }}">{{ $location }}</td>
            </tr>
        @endif
    </table>

    <p style="margin:24px 0 0 0; font-size:13px; line-height:1.6; color:#6b7386;">
        Küsimuste korral kirjuta aadressil
        <a href="mailto:{{ $techEmail }}" style="{{ $link }}">{{ $techEmail }}</a>.
    </p>
@endsection

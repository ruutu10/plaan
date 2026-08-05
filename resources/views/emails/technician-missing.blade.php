{{--
    The technical team's daily digest of upcoming performances nobody has
    signed on to run sound and light for.

    Sent again, list and all, for as long as any gap remains — see
    App\Console\Commands\RemindAboutMissingTechnicians — so this is not a
    one-off notice but the same running list, until each night drops off it
    by getting a technician or by being played.
--}}
@php
    $cell = 'border:1px solid #dce0e7; padding:8px 11px; vertical-align:top; font-size:14px; line-height:1.5; color:#3d4557;';
    $labelCell = $cell.' background-color:#eef0f4; font-weight:700; color:#0c0f16;';
    $link = 'color:#e85f30; text-decoration:underline;';
@endphp
@extends('emails.layout')

@section('title', 'Tehnik puudu')

@section('preheader', 'Etendused, millele ei ole veel tehnikut määratud.')

@section('content')
    <h1 style="margin:0 0 16px 0; font-family:'Futura','Century Gothic','Roboto',Arial,sans-serif; font-size:24px; line-height:1.15; font-weight:700; letter-spacing:0.01em; text-transform:uppercase; color:#0c0f16;">
        Tehnik puudu
    </h1>

    <p style="margin:0 0 24px 0; font-size:16px; line-height:1.6;">
        Järgnevatele etendustele ei ole veel tehnikut määratud. Kui saad mõne
        enda kanda võtta, märgi end etenduse Planka kaardile.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
        <tr>
            <td style="{{ $labelCell }}">Etendus</td>
            <td style="{{ $labelCell }}">Aeg</td>
            <td style="{{ $labelCell }}">Plankas</td>
        </tr>
        @foreach ($performances as $performance)
            <tr>
                <td style="{{ $cell }}">{{ $performance['label'] }}</td>
                <td style="{{ $cell }}">{{ $performance['startsAt']->format('d.m.Y H:i') }}</td>
                <td style="{{ $cell }}">
                    @if ($performance['cardUrl'])
                        <a href="{{ $performance['cardUrl'] }}" style="{{ $link }}">Vaata kaarti</a>
                    @else
                        —
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@endsection

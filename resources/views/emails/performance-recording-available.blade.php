{{--
    The word to a plan's author that the video of their night is up: that there
    is one, where to watch it, and — once read off its subtitles — what the
    night was about.
--}}
@php
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
        Aitäh, et esinesid hiljuti Ruutu10 laval! Sinu etendusest tehti ka videosalvestis ja see on nüüd järelvaatamiseks saadaval.
    </p>

    <p style="margin:0 0 24px 0; font-size:16px; line-height:1.6;">
        Salvestis on mõeldud eelkõige esinejatele endile: oma show analüüsiks ja grupi tutvustamiseks sotsiaalmeedias. Enne avalikku jagamist küsi palun nõusolekut kõigilt grupi liikmetelt.
    </p>

    @if ($recordingUrl || $downloadUrl)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f2f4f7; border-radius:14px;">
            <tr>
                <td align="center" style="padding:22px 22px;">
                    @if ($recordingUrl)
                        <a href="{{ $recordingUrl }}"
                           target="_blank"
                           style="display:inline-block; margin:4px; padding:14px 30px; border-radius:999px; background-color:#e85f30; font-family:'Futura','Century Gothic','Roboto',Arial,sans-serif; font-size:14px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#ffffff; text-decoration:none;">
                            Vaata Jellyfinis
                        </a>
                    @endif
                    @if ($downloadUrl)
                        <a href="{{ $downloadUrl }}"
                           target="_blank"
                           style="display:inline-block; margin:4px; padding:14px 30px; border-radius:999px; background-color:#0c0f16; font-family:'Futura','Century Gothic','Roboto',Arial,sans-serif; font-size:14px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#ffffff; text-decoration:none;">
                            Laadi alla
                        </a>
                        @if ($recordingFile)
                            <p style="margin:14px 0 0 0; font-size:14px; line-height:1.5; color:#0c0f16; word-break:break-word;">
                                {{ $recordingFile->name }}
                            </p>
                            <p style="margin:2px 0 0 0; font-size:13px; line-height:1.5; color:#6b7386;">
                                {{ $recordingFile->sizeLabel() }}@if ($recordingFile->addedOnLabel()) · lisatud {{ $recordingFile->addedOnLabel() }}@endif
                            </p>
                        @endif
                        <p style="margin:12px 0 0 0; font-size:13px; line-height:1.5; color:#6b7386;">
                            Allalaadimiseks logi Plaani sisse.@unless ($recordingFile) Fail võib olla mitu gigabaiti suur.@endunless
                        </p>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    @if ($plotSummary)
        <h2 style="margin:28px 0 8px 0; font-family:'Futura','Century Gothic','Roboto',Arial,sans-serif; font-size:16px; line-height:1.3; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; color:#0c0f16;">
            Sisukokkuvõte
        </h2>

        <p style="margin:0 0 12px 0; font-size:16px; line-height:1.6; white-space:pre-line;">{{ $plotSummary }}</p>

        @if ($plotTags !== [])
            <p style="margin:0 0 12px 0; font-size:13px; line-height:2;">
                @foreach ($plotTags as $tag)
                    <span style="display:inline-block; margin:0 4px 0 0; padding:0 10px; border-radius:999px; background-color:#f2f4f7; color:#0c0f16; white-space:nowrap;">{{ $tag }}</span>
                @endforeach
            </p>
        @endif

        @if ($plotSummaryIsGenerated)
            <p style="margin:0 0 4px 0; font-size:13px; line-height:1.5; color:#6b7386;">
                Kokkuvõte ja sildid on koostatud tehisaru abil salvestuse automaatsetest subtiitritest ning võib sisaldada vigu.
            </p>
        @endif
    @endif

    <x-emails.performance-details :details="$details" />

@endsection

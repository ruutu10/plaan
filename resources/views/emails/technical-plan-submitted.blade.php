{{--
    The submitted technical plan, mailed to the performer and the technical
    team (Ruutu10 theme). Table-based, inline-styled layout for broad e-mail
    client support; the logo is embedded via CID so it renders without an
    external request.

    Mirrors the wizard's final review page, so the mail and the printout the
    performer sees carry the same document.
--}}
@php
    /** A value the plan may have left empty, shown as an em dash. */
    $dash = fn ($value): string => trim((string) $value) !== '' ? trim((string) $value) : '—';

    /** A "yes/no" answer plus its free-text detail, on one line. */
    $answer = fn (string $mode, string $detail): string => $mode === 'yes'
        ? 'Jah'.(trim($detail) !== '' ? ' — '.trim($detail) : '')
        : 'Ei';

    $smoke = match ($plan['equipment']['smoke']) {
        'no' => 'Ei tohi',
        'yes' => 'Jah',
        default => 'Jah, kuid minimaalselt',
    };

    $suggestions = ($plan['equipment']['suggestions'] === 'yes' ? 'Jah' : 'Ei')
        .(trim($plan['equipment']['suggestNote']) !== '' ? ' — '.trim($plan['equipment']['suggestNote']) : '');

    $equipmentItems = array_filter(
        $plan['equipment']['items'],
        fn (array $item): bool => trim($item['name']) !== '' || trim($item['use']) !== '',
    );

    $cell = 'border:1px solid #dce0e7; padding:8px 11px; vertical-align:top; font-size:14px; line-height:1.5; color:#3d4557;';
    $labelCell = $cell.' width:34%; background-color:#f2f4f7; font-weight:700; color:#0c0f16;';
    $headCell = 'border:1px solid #11234f; background-color:#11234f; padding:7px 10px; text-align:left; font-size:12px; font-weight:700; color:#ffffff;';
    $sectionTitle = 'margin:26px 0 10px 0; font-family:\'Futura\',\'Century Gothic\',\'Roboto\',Arial,sans-serif; font-size:14px; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; color:#11234f;';
    $link = 'color:#e85f30; text-decoration:underline;';
@endphp
<!DOCTYPE html>
<html lang="et" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Esitatud tehnikaplaan</title>
</head>
<body style="margin:0; padding:0; background-color:#f7f8fa; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    {{-- Preheader (hidden preview text) --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:#f7f8fa; font-size:1px; line-height:1px;">
        {{ $dash($plan['meta']['showName']) }} · tehnikaplaan on esitatud.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f7f8fa;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0" style="width:680px; max-width:100%; background-color:#ffffff; border-radius:22px; overflow:hidden; box-shadow:0 6px 18px rgba(10,14,23,0.10);">

                    {{-- Header: navy band with the logo --}}
                    <tr>
                        <td align="center" style="background-color:#11234f; padding:34px 30px 30px 30px;">
                            <img src="{{ $message->embed(public_path('logo-white.png')) }}"
                                 alt="Ruutu10"
                                 width="180"
                                 style="display:block; width:180px; max-width:60%; height:auto; border:0;">
                            <div style="margin-top:18px; font-family:'Roboto','Helvetica Neue',Arial,sans-serif; font-size:12px; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; color:#ff7f50;">
                                Etenduse tehnikaplaan
                            </div>
                        </td>
                    </tr>

                    {{-- Orange accent rule --}}
                    <tr>
                        <td style="height:4px; background-color:#ff7f50; line-height:4px; font-size:4px;">&nbsp;</td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:34px 40px 32px 40px; font-family:'Roboto','Helvetica Neue',Arial,sans-serif; color:#3d4557;">
                            <h1 style="margin:0 0 16px 0; font-family:'Futura','Century Gothic','Roboto',Arial,sans-serif; font-size:24px; line-height:1.15; font-weight:700; letter-spacing:0.01em; text-transform:uppercase; color:#0c0f16;">
                                {{ $dash($plan['meta']['showName']) }}
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
                                    <a href="mailto:{{ $contactEmail }}" style="{{ $link }}">{{ $dash($contactEmail) }}</a>.
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
                                            Plaani võti: <strong style="color:#3d4557;">{{ $plan['token'] }}</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <div style="{{ $sectionTitle }}">Etendus</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="{{ $labelCell }}">Esineja</td>
                                    <td style="{{ $cell }}">{{ $dash($plan['meta']['performer']) }}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $labelCell }}">Kontakt</td>
                                    <td style="{{ $cell }}">{{ $dash($contactEmail) }}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $labelCell }}">Kuupäev</td>
                                    <td style="{{ $cell }}">{{ $dash($plan['meta']['showDate']) }}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $labelCell }}">Kestus</td>
                                    <td style="{{ $cell }}">{{ $plan['meta']['duration'] ? $plan['meta']['duration'].' min' : '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $labelCell }}">Lühikirjeldus</td>
                                    <td style="{{ $cell }} white-space:pre-line;">{{ $dash($plan['meta']['description']) }}</td>
                                </tr>
                            </table>

                            <div style="{{ $sectionTitle }}">Heliplaan</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="{{ $labelCell }}">Mikrofonid</td>
                                    <td style="{{ $cell }} white-space:pre-line;">{{ $answer($plan['sound']['micsMode'], $plan['sound']['micsDetail']) }}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $labelCell }}">Oma muusik</td>
                                    <td style="{{ $cell }} white-space:pre-line;">{{ $answer($plan['sound']['musicianMode'], $plan['sound']['musicianDetail']) }}</td>
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
                                @foreach ($plan['scenes'] as $index => $scene)
                                    <tr>
                                        <td style="{{ $cell }} text-align:center; font-weight:700; color:#11234f;">{{ $index + 1 }}</td>
                                        <td style="{{ $cell }} font-weight:700; word-break:break-word;">{{ $dash($scene['name']) }}</td>
                                        <td style="{{ $cell }} word-break:break-word; white-space:pre-line;">{{ $dash($scene['light']) }}</td>
                                        <td style="{{ $cell }} word-break:break-word;">
                                            {{-- The uploaded file gets its own line so it stays clickable. --}}
                                            @if ($scene['soundFile'])
                                                <a href="{{ $scene['soundFile']['url'] }}" style="{{ $link }}">{{ $scene['soundFile']['name'] }}</a>
                                                ({{ \Illuminate\Support\Number::fileSize($scene['soundFile']['size'], precision: 1) }})<br>
                                            @endif
                                            @if ($scene['soundUrl'])
                                                <a href="{{ $scene['soundUrl'] }}" style="{{ $link }}">{{ $scene['soundUrl'] }}</a><br>
                                            @endif
                                            <span style="white-space:pre-line;">{{ $scene['sound'] !== '' || (! $scene['soundFile'] && ! $scene['soundUrl']) ? $dash($scene['sound']) : '' }}</span>
                                        </td>
                                        <td style="{{ $cell }} word-break:break-word; white-space:pre-line;">{{ $dash($scene['notes']) }}</td>
                                    </tr>
                                @endforeach
                            </table>

                            <div style="{{ $sectionTitle }}">Erivahendid &amp; load</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                @foreach ($equipmentItems as $item)
                                    <tr>
                                        <td style="{{ $labelCell }}">{{ $dash($item['name']) }}</td>
                                        <td style="{{ $cell }}">{{ $dash($item['use']) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td style="{{ $labelCell }}">Suitsuefektid</td>
                                    <td style="{{ $cell }}">{{ $smoke }}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $labelCell }}">Tehniku pakkumised</td>
                                    <td style="{{ $cell }} white-space:pre-line;">{{ $suggestions }}</td>
                                </tr>
                            </table>

                            <div style="{{ $sectionTitle }}">Lisainfo</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="{{ $cell }} white-space:pre-line;">{{ $dash($plan['extra']['notes']) }}</td>
                                </tr>
                            </table>

                            @if ($plan['extra']['files'])
                                <p style="margin:14px 0 6px 0; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#11234f;">
                                    Manused
                                </p>
                                @foreach ($plan['extra']['files'] as $file)
                                    <p style="margin:0 0 5px 0; font-size:13px; line-height:1.5; word-break:break-all;">
                                        <a href="{{ $file['url'] }}" style="{{ $link }}">{{ $file['name'] }}</a>
                                        <span style="color:#6b7386;">({{ \Illuminate\Support\Number::fileSize($file['size'], precision: 1) }})</span>
                                    </p>
                                @endforeach
                            @endif
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 40px 30px 40px; background-color:#f7f8fa; font-family:'Roboto','Helvetica Neue',Arial,sans-serif; text-align:center;">
                            <p style="margin:0; font-size:11px; line-height:1.6; letter-spacing:0.02em; color:#6b7386;">
                                {{ date('Y') }} Ruutu10 tehnikud | {{ config('technical_plan.tech_email') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>

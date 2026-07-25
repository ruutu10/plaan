{{--
    Branded magic-login email (Ruutu10 theme).
    Table-based, inline-styled layout for broad e-mail client support.
    The logo is embedded via CID so it renders without an external request.
--}}
<!DOCTYPE html>
<html lang="et" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Sinu sisselogimislink</title>
</head>
<body style="margin:0; padding:0; background-color:#f7f8fa; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    {{-- Preheader (hidden preview text) --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:#f7f8fa; font-size:1px; line-height:1px;">
        Sinu sisselogimislink tehnikaplaani keskkonda.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f7f8fa;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:560px; max-width:100%; background-color:#ffffff; border-radius:22px; overflow:hidden; box-shadow:0 6px 18px rgba(10,14,23,0.10);">

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
                        <td style="padding:38px 40px 32px 40px; font-family:'Roboto','Helvetica Neue',Arial,sans-serif; color:#3d4557;">
                            <h1 style="margin:0 0 16px 0; font-family:'Futura','Century Gothic','Roboto',Arial,sans-serif; font-size:26px; line-height:1.15; font-weight:700; letter-spacing:0.01em; text-transform:uppercase; color:#0c0f16;">
                                Tehnikaplaani esitamine
                            </h1>

                            <p style="margin:0 0 12px 0; font-size:16px; line-height:1.6; color:#3d4557;">
                                Tere{{ !empty($name) ? ', '.$name : '' }}!
                            </p>
                            <p style="margin:0 0 28px 0; font-size:16px; line-height:1.6; color:#3d4557;">
                                Sisselogimiseks tehnikaplaani keskkonda vajuta alloleval nupul.
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
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 40px 30px 40px; background-color:#f7f8fa; font-family:'Roboto','Helvetica Neue',Arial,sans-serif; text-align:center;">
                            <p style="margin:0; font-size:11px; line-height:1.6; letter-spacing:0.02em; color:#6b7386;">
                                {{ date('Y') }} Ruutu10 tehnikud | tehnikud@ruutu10.ee
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>

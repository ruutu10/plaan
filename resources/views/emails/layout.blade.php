{{--
    The frame every mail this app sends is drawn in (Ruutu10 theme): the navy
    band with the logo, the orange rule under it, and the footer.

    Table-based and inline-styled for broad e-mail client support; the logo is
    embedded via CID so it renders without an external request.

    Children set `$width` before extending — the plan mail is wider than the
    others because it carries a table of scenes.
--}}
<!DOCTYPE html>
<html lang="et" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title')</title>
</head>
<body style="margin:0; padding:0; background-color:#f7f8fa; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    {{-- Preheader (hidden preview text) --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:#f7f8fa; font-size:1px; line-height:1px;">
        @yield('preheader')
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f7f8fa;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="{{ $width ?? 680 }}" cellpadding="0" cellspacing="0" border="0" style="width:{{ $width ?? 680 }}px; max-width:100%; background-color:#ffffff; border-radius:22px; overflow:hidden; box-shadow:0 6px 18px rgba(10,14,23,0.10);">

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
                            @yield('content')
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

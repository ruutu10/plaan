{{--
    The "Etendus" table every mail about a night carries, so each letter names
    the night by the same rows in the same order. `$details` comes in already
    rendered — see App\Http\Resources\PerformanceDetails — and only the venue
    is ever left out, for a night in the house's own room. Rows only one letter
    needs (who commented, who confirmed, …) go in the slot, below the common
    ones.
--}}
@props(['details'])
@php
    $cell = 'border:1px solid #dce0e7; padding:8px 11px; vertical-align:top; font-size:14px; line-height:1.5; color:#3d4557;';
    $labelCell = $cell.' width:34%; background-color:#eef0f4; font-weight:700; color:#0c0f16;';
    $sectionTitle = 'margin:26px 0 10px 0; font-family:\'Futura\',\'Century Gothic\',\'Roboto\',Arial,sans-serif; font-size:14px; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; color:#11234f;';
@endphp
<div style="{{ $sectionTitle }}">Etendus</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
    <tr>
        <td style="{{ $labelCell }}">Formaat</td>
        <td style="{{ $cell }}">{{ $details['formatName'] }}</td>
    </tr>
    <tr>
        <td style="{{ $labelCell }}">Esineja</td>
        <td style="{{ $cell }}">{{ $details['performer'] }}</td>
    </tr>
    <tr>
        <td style="{{ $labelCell }}">Tehnik</td>
        <td style="{{ $cell }}">{{ $details['technicians'] }}</td>
    </tr>
    <tr>
        <td style="{{ $labelCell }}">Kuupäev</td>
        <td style="{{ $cell }}">{{ $details['date'] }}</td>
    </tr>
    <tr>
        <td style="{{ $labelCell }}">Algus</td>
        <td style="{{ $cell }}">{{ $details['startTime'] }}</td>
    </tr>
    <tr>
        <td style="{{ $labelCell }}">Kestus</td>
        <td style="{{ $cell }}">{{ $details['duration'] }}</td>
    </tr>
    @if ($details['location'])
        <tr>
            <td style="{{ $labelCell }}">Asukoht</td>
            <td style="{{ $cell }}">{{ $details['location'] }}</td>
        </tr>
    @endif
    {{ $slot }}
</table>

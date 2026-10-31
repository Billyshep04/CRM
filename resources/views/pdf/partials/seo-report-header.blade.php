{{-- Opens a report page; pdf.partials.seo-report-footer closes it. --}}
<div class="page{{ $number === 6 ? ' last' : '' }}">
    <div class="rule"></div>
    <div class="band">
        <table>
            <tr>
                <td>
                    @if ($logo)
                        <img class="band-logo" src="{{ $logo }}" alt="Web Stamp">
                    @endif
                </td>
                <td class="band-right">
                    <div class="band-eyebrow">{{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }} · {{ $section }}</div>
                    <div class="band-title">{{ $title }}</div>
                </td>
            </tr>
        </table>
    </div>

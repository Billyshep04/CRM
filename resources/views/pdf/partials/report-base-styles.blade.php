{{-- Styles shared by the monthly report PDFs. Included inside their <style> blocks. --}}
        @foreach ($fonts as $font)
        @font-face { font-family: '{{ $font['family'] }}'; font-style: normal; font-weight: {{ $font['weight'] }}; src: url('{{ $font['path'] }}') format('truetype'); }
        @endforeach

        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            color: #0d1c23;
            background: #ffffff;
            font-family: Inter, DejaVu Sans, sans-serif;
            font-size: 9pt;
            line-height: 1.4;
        }
        table { border-collapse: collapse; }
        td { padding: 0; vertical-align: top; }

        .page { position: relative; width: 210mm; height: 296.8mm; overflow: hidden; page-break-after: always; }
        .page.last { page-break-after: auto; }

        /* Shared header for every page after the cover */
        .rule { height: 2mm; background: #30b8f0; }
        .band { height: 17.8mm; padding: 5.5mm 12.75mm 0; background: #0d1c23; }
        .band table { width: 100%; }
        .band td { vertical-align: middle; }
        .band-logo { width: 24mm; height: 11.6mm; }
        .band-right { text-align: right; }
        .band-eyebrow { color: #30b8f0; font-size: 7.6pt; font-weight: 700; letter-spacing: .55mm; text-transform: uppercase; }
        .band-title { margin-top: .6mm; color: #ffffff; font-size: 11.8pt; font-weight: 700; }

        .content { padding: 8.5mm 12.75mm 0; }
        .page-title { font-size: 22pt; font-weight: 800; letter-spacing: -.25mm; line-height: 1.1; }
        .page-subtitle { margin-top: 2.2mm; color: #4d626c; font-size: 9.3pt; }

        .eyebrow { color: #4d626c; font-size: 6.9pt; font-weight: 700; letter-spacing: .4mm; text-transform: uppercase; }
        .eyebrow.accent { color: #30b8f0; }
        .section-label { margin: 6.8mm 0 3mm; }

        .footer { position: absolute; left: 12.75mm; right: 12.75mm; bottom: 7.5mm; border-top: .45mm solid #dfe8ec; padding-top: 4.2mm; }
        .footer table { width: 100%; }
        .footer td { color: #4d626c; font-size: 7.6pt; font-weight: 600; }
        .footer .left { width: 35%; font-weight: 700; letter-spacing: .3mm; text-transform: uppercase; }
        .footer .middle { width: 30%; text-align: center; }
        .footer .right { width: 35%; text-align: right; font-weight: 700; letter-spacing: .3mm; }

        /* Cards and grids. Gutters are explicit spacer cells: DomPDF's border-spacing would also pad the outer edges. */
        .grid { width: 100%; table-layout: fixed; }
        .col3 { width: 31.9%; }
        .gap3 { width: 2.15%; }
        .col4 { width: 23.2%; }
        .gap { width: 2.4%; }
        .card { border-radius: 2.6mm; }
        .card-outline { border: .45mm solid #dfe8ec; background: #ffffff; }
        .card-grey { background: #f2f7f9; }
        .card-light { background: #e8f5fc; }
        .card-accent { background: #30b8f0; }
        .card-dark { background: #0d1c23; color: #ffffff; }

        .big-number { font-family: Montserrat, Inter, DejaVu Sans, sans-serif; font-weight: 800; letter-spacing: -.3mm; line-height: 1.05; }

        .pill { display: inline-block; vertical-align: middle; padding: .9mm 2.6mm; border-radius: 3mm; font-size: 8pt; font-weight: 700; white-space: nowrap; }
        .pill-up { background: #e3f3fb; color: #0a6ea6; }
        .pill-down { background: #fdece4; color: #b2471b; }
        .pill-flat { background: #eef3f5; color: #4d626c; }
        .pill-dark { background: #0d1c23; color: #ffffff; }
        .arrow { font-family: DejaVu Sans, sans-serif; font-size: 6pt; }
        .was { vertical-align: middle; padding-left: 2mm; color: #4d626c; font-size: 8.4pt; }

        .table-head { border-radius: 2.6mm; background: #0d1c23; }
        .table-head table, .data-table { width: 100%; table-layout: fixed; }
        .table-head td { height: 9.8mm; padding: 0 4.2mm; vertical-align: middle; color: #ffffff; font-size: 7.4pt; font-weight: 700; letter-spacing: .3mm; text-transform: uppercase; white-space: nowrap; }
        .data-table td { height: 9.4mm; padding: 0 4.2mm; vertical-align: middle; border-bottom: .35mm solid #dfe8ec; font-size: 9.6pt; }
        .data-table td.name { font-weight: 600; }
        .r { text-align: right; }
        .c { text-align: center; }

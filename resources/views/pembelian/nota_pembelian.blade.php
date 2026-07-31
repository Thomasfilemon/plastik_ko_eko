@php
use Riskihajar\Terbilang\Facades\Terbilang;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Pembelian #{{ $purchase->nota }}</title>
    <style>
        /* Epson LX-310 continuous form (Half Letter) */
        * { box-sizing: border-box; }

        @page {
            size: 215.9mm 139.7mm;
            /* 5mm top so printer feed does not cut the header */
            margin: 5mm 4mm 2mm 4mm;
        }

        html, body {
            width: 215.9mm;
            height: 139.7mm;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            line-height: 1.15;
            color: #000;
            display: block;
        }

        .page {
            width: 100%;
            height: auto;
            min-height: 0;
            padding: 0 1mm;
            margin: 0 auto;
            display: block;
        }

        .header { text-align: center; line-height: 1.15; }
        .header strong { font-size: 11pt; }

        .info-row { display: flex; justify-content: space-between; margin-bottom: 2px; font-size: 9pt; }

        .status-badge {
            font-size: 8pt;
            padding: 1px 3px;
            margin-top: 1px;
            border: 1px solid #000;
            display: inline-block;
            font-weight: bold;
        }
        .status-canceled {
            background-color: #ffdddd;
            color: #cc0000;
        }

        .item-table { width: 100%; border-collapse: collapse; margin-top: 2px; table-layout: fixed; }
        .item-table th,
        .item-table td {
            border: 1px solid #000;
            padding: 1.2mm 1.2mm;
            font-size: 9pt;
            vertical-align: middle;
            word-wrap: break-word;
        }
        .item-table th { font-weight: bold; text-align: center; }

        tr.empty-row td {
            border-bottom: 1px solid #eee;
            border-top: 1px solid #eee;
            color: #fff;
            height: 6mm;
        }
        tr.empty-row td:first-child { border-left: 1px solid #000; }
        tr.empty-row td:last-child { border-right: 1px solid #000; }
        tr.last-empty-row td { border-bottom: 1px solid #000; }

        .footer-container {
            width: 100%;
            margin-top: 1.5mm;
            padding-top: 1mm;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 2px;
        }
        .summary-table th, .summary-table td { border: 1px solid #000; padding: 1mm 1.5mm; }
        .summary-table th { text-align: left; font-weight: bold; }

        .notes-terbilang-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            width: 100%;
            clear: both;
            padding-top: 2px;
        }

        .notes-section {
            flex-basis: 60%;
            font-size: 8.5pt;
            line-height: 1.15;
        }

        .terbilang-section {
            flex-basis: 40%;
            font-size: 8.5pt;
            font-style: italic;
            text-align: right;
            padding-left: 6px;
        }

        .signature-row {
            width: 100%;
            overflow: hidden;
            margin-top: 4mm;
            font-size: 9pt;
        }
        .signature-left { float: left; text-align: center; width: 28%; }
        .signature-right { float: right; text-align: center; width: 28%; }

        .edit-info-box { font-size: 7pt; margin-top: 2mm; padding: 2px; border: 1px solid #ccc; line-height: 1.1; clear: both; }

        .right { text-align: right; }
        .center { text-align: center; }

        .no-print { position: fixed; top: 10px; right: 10px; z-index: 999; }
        .no-print button, .no-print a { margin-left: 8px; padding: 6px 16px; font-size: 14px; border: none; border-radius: 4px; background: #007bff; color: #fff; cursor: pointer; text-decoration: none; }
        .no-print a { background: #6c757d; }

        .page-break { page-break-after: always; break-after: page; }

        @media print {
            .no-print { display: none !important; }
            html, body { width: 215.9mm; height: auto; }
            .page {
                height: auto;
                min-height: 0;
                max-width: none;
                padding: 0 1mm;
            }
        }
    </style>
</head>
<body>

@php
    $defaultCompany = \App\Models\Perusahaan::where('is_default', true)->first() ?? new \App\Models\Perusahaan();
    $itemsPerPage = 5; // <--- SET MAX ITEMS PER PAGE HERE
    $groupedItems = $purchase->items->chunk($itemsPerPage);
    $totalPages = $groupedItems->count();
    $pageNum = 0;
@endphp

<div class="no-print">
    <a href="{{ route('pembelian.nota.list') }}">Kembali</a>
    <button onclick="window.print()">Print</button>
</div>

@foreach ($groupedItems as $chunk)
    @php $pageNum++; @endphp
    <div class="page">
        {{-- HEADER --}}
        <div class="header">
            <strong>{{ $defaultCompany->nama ?? 'CV. ALUMKA CIPTA PRIMA' }}</strong><br>
            {{ $defaultCompany->alamat ?? 'JL. SINAR RAGA ABI HASAN NO.1553 RT.022 RW.008' }}<br>
            {{ $defaultCompany->kota ?? '8 ILIR' }}, {{ $defaultCompany->kode_pos ?? 'ILIR TIMUR II' }}<br>
            TELP. {{ $defaultCompany->telepon ?? '(0711) 311158' }} &nbsp;&nbsp; FAX {{ $defaultCompany->fax ?? '(0711) 311158' }}<br>
            NO FAKTUR PEMBELIAN: {{ $purchase->nota }}
            
            @if($purchase->status == 'canceled')
                <div class="status-badge status-canceled">DIBATALKAN</div>
            @endif
        </div>

        {{-- SUPPLIER INFO & DATE --}}
        <div class="info-row" style="margin-top: 5px;">
            <div>
                <strong>Dari:</strong><br>
                Supplier: {{ $purchase->kode_supplier }} - {{ $purchase->supplierRelation->nama ?? '-' }}<br>
                Telp: {{ $purchase->supplierRelation->telepon_fax ?? '-' }}<br>
                Alamat: {{ $purchase->supplierRelation->alamat ?? '-' }}<br>
                <strong>No. Surat Jalan:</strong> {{ $purchase->no_surat_jalan ?: '-' }}
            </div>
            <div class="right">
                {{ \Carbon\Carbon::parse($purchase->created_at)->format('d M Y H:i') }}<br>
                HALAMAN: {{ $pageNum }} / {{ $totalPages }}<br>
                Dicetak oleh: {{ auth()->user()->name ?? 'SYSTEM' }}
            </div>
        </div>

        {{-- ITEMS TABLE --}}
        <table class="item-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No.</th>
                    <th style="width: 32%;">Nama Barang</th>
                    <th style="width: 15%;">Satuan Besar</th>
                    <th style="width: 16%;">Kuantiti</th>
                    <th style="width: 16%;">Harga @</th>
                    <th style="width: 16%;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @php $rowCount = 0; @endphp
                @foreach ($chunk as $i => $item)
                    @php $rowCount++; @endphp
                    <tr>
                        <td class="center">{{ (($pageNum - 1) * $itemsPerPage) + $i + 1 }}</td>
                        <td>{{ $item->nama_barang }}</td>
                        <td class="center">
                            @if(!empty($item->satuan_besar))
                                {{ rtrim(rtrim(number_format($item->qty_besar, 2, '.', ''), '0'), '.') }} {{ $item->satuan_besar }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="center">
                            {{ rtrim(rtrim(number_format($item->qty, 2, '.', ''), '0'), '.') }} {{ $item->satuan ?? '-' }}
                        </td>
                        <td class="center">Rp {{ number_format($item->harga, 0, ',', '.') }}</td>
                        <td class="center">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                
                {{-- Add empty rows to fill the table on the last page --}}
                @if ($loop->last)
                    @for ($j = $rowCount; $j < $itemsPerPage; $j++)
                        <tr class="empty-row {{ ($j == $itemsPerPage - 1) ? 'last-empty-row' : '' }}">
                            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                        </tr>
                    @endfor
                @endif
            </tbody>
        </table>

        {{-- FOOTER - ONLY SHOWN ON THE LAST PAGE --}}
        @if ($loop->last)
        <div class="footer-container">
            
            <table class="summary-table">
                <tr>
                    <th style="width: 86%">TOTAL</th>
                    <td class="right" style="width: 14%">Rp {{ number_format($purchase->subtotal, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>DISC (%)</th>
                    <td class="right">Rp {{ number_format($purchase->diskon, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>PPN</th>
                    <td class="right">Rp {{ number_format($purchase->ppn, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>GRAND TOTAL</th>
                    <td class="right"><strong>Rp {{ number_format($purchase->grand_total, 0, ',', '.') }}</strong></td>
                </tr>
            </table>

            <div class="notes-terbilang-wrapper">
                <div class="terbilang-section">
                    Terbilang: {{ ucwords(Terbilang::make($purchase->grand_total, ' rupiah')) }}
                </div>
            </div>

            <div class="signature-row">
                <div class="signature-left">
                    HORMAT KAMI<br><br><br><br>
                    (_____________)
                </div>
                <div class="signature-right">
                    PENERIMA<br><br><br><br>
                    (_____________)
                </div>
            </div>

            {{-- Combined Edit and Cancel Info Box --}}
            @if((isset($purchase->is_edited) && $purchase->is_edited) || $purchase->status == 'canceled')
            <div class="edit-info-box">
                @if(isset($purchase->is_edited) && $purchase->is_edited)
                    <strong>Informasi Edit:</strong>
                    Diedit oleh: {{ $purchase->edited_by ?? 'Unknown' }} pada {{ isset($purchase->edited_at) ? \Carbon\Carbon::parse($purchase->edited_at)->format('d M Y H:i') : 'Unknown' }}.
                    Alasan: {{ $purchase->edit_reason ?? 'No reason provided' }}
                    @if($purchase->status == 'canceled') <br> @endif
                @endif
                @if($purchase->status == 'canceled')
                    <strong>Informasi Pembatalan:</strong>
                    Dibatalkan oleh: {{ $purchase->canceled_by }} pada {{ \Carbon\Carbon::parse($purchase->canceled_at)->format('d M Y H:i') }}.
                    Alasan: {{ $purchase->cancel_reason }}
                @endif
            </div>
            @endif

        </div>
        @endif
    </div>

    {{-- Add a page break unless it's the last page --}}
    @if (!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach

<script>
    // If the URL contains ?print=1, trigger the print dialog automatically.
    window.addEventListener('load', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('print')) {
            window.print();
        }
    });
</script>

</body>
</html>
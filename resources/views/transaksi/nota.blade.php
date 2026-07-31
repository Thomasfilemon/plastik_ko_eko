@php
use Riskihajar\Terbilang\Facades\Terbilang;
@endphp

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nota Transaksi</title>
    <link href="https://fonts.googleapis.com/css2?family=DejaVu+Sans+Mono:wght@400;700&display=swap" rel="stylesheet">
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
            font-family: 'Courier New', 'DejaVu Sans Mono', monospace;
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
        .header { text-align: center; line-height: 1.15; margin-bottom: 1.5mm; }
        .header strong { font-size: 11pt; }
        .info-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5mm; font-size: 9pt; width: 100%; }
        .info-left-section { flex-basis: 65%; text-align: left; }
        .info-right-section { flex-basis: 35%; text-align: right; }
        .item-table { width: 100%; border-collapse: collapse; margin-top: 1.5mm; table-layout: fixed; }
        .item-table th, .item-table td {
            border: 1px solid #000;
            padding: 1.2mm 1.2mm;
            font-size: 9pt;
            vertical-align: middle;
            word-wrap: break-word;
            white-space: normal;
        }
        .item-table th { font-weight: bold; text-align: center; font-size: 9pt; }
        .item-table th:nth-child(1), .item-table td:nth-child(1) { width: 5%; text-align: center; }
        .item-table th:nth-child(2), .item-table td:nth-child(2) { width: 34%; text-align: left; }
        .item-table th:nth-child(3), .item-table td:nth-child(3) { width: 14%; text-align: center; }
        .item-table th:nth-child(4), .item-table td:nth-child(4) { width: 15%; text-align: center; }
        .item-table th:nth-child(5), .item-table td:nth-child(5) { width: 16%; text-align: center; }
        .item-table th:nth-child(6), .item-table td:nth-child(6) { width: 16%; text-align: center; }
        tr.empty-row td { border: 1px solid #000; border-color: #eee; color: #fff; height: 5mm; }
        tr.empty-row td:first-child { border-left-color: #000; }
        tr.empty-row td:last-child { border-right-color: #000; }
        .footer-container { width: 100%; margin-top: 1.5mm; padding-top: 1mm; }
        .summary-table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 1.5mm; }
        .summary-table th, .summary-table td { border: 1px solid #000; padding: 1mm 1.5mm; }
        .summary-table th { text-align: left; font-weight: bold; }
        .notes-details-wrapper { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: nowrap; width: 100%; margin-top: 1mm; }
        .notes-section { flex: 1 1 0%; min-width: 45%; padding-right: 2mm; font-size: 8.5pt; line-height: 1.25; }
        .details-row { flex: 0 1 auto; min-width: 40%; display: flex; flex-direction: column; font-size: 8.5pt; text-align: right; padding-left: 2mm; }
        .payment-info { margin-bottom: 1mm; }
        .terbilang-section { font-style: italic; }
        .signature-row {
            width: 100%;
            display: flex;
            justify-content: flex-end;
            gap: 8%;
            margin-top: 3mm;
            font-size: 9pt;
            padding-top: 0;
            line-height: 1.2;
        }
        .signature-spacer { flex: 1 1 auto; }
        .signature-left { text-align: center; width: 24%; flex: 0 0 24%; }
        .signature-right { text-align: center; width: 24%; flex: 0 0 24%; }
        .edit-info-box { font-size: 7pt; margin-top: 2mm; padding: 0.5mm; border: 1px solid #ccc; line-height: 1.0; clear: both; }
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
    $itemsPerPage = 5; // larger rows to fill half-letter LX-310 form
    $groupedItems = $transaction->items->chunk($itemsPerPage);
    $totalPages = $groupedItems->count();
    $pageNum = 0;
@endphp

<div class="no-print">
    <a href="{{ route('transaksi.listnota') }}">Kembali</a>
    <button onclick="window.print()">Print</button>
    @if($transaction->status != 'canceled')
        <a href="{{ route('transaksi.edit', $transaction->id) }}">Edit</a>
    @endif
</div>

@foreach ($groupedItems as $chunk)
    @php $pageNum++; @endphp
    <div class="page">
        {{-- BAGIAN HEADER --}}
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div style="text-align: left;">
                    <strong>{{ $defaultCompany->nama ?? '' }}</strong><br>
                    {{ $defaultCompany->alamat ?? '' }}<br>
                    {{ $defaultCompany->kota ?? '' }}{{ $defaultCompany->kode_pos ? ', '.$defaultCompany->kode_pos : '' }}<br>
                    @if(!empty($defaultCompany->telepon)) TELP. {{ $defaultCompany->telepon }} @endif
                    @if(!empty($defaultCompany->fax)) &nbsp;&nbsp; FAX {{ $defaultCompany->fax }} @endif
                </div>
                <div style="text-align: right;">
                    {{ \Carbon\Carbon::parse($transaction->tanggal)->format('d/M/Y') }}<br>
                    HALAMAN: {{ $pageNum }} / {{ $totalPages }}
                </div>
            </div>
        </div>

        {{-- INFO PELANGGAN & TRANSAKSI --}}
        <table style="width: 100%; table-layout: fixed; margin-bottom: 2px; font-size: 9pt;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <strong>Kepada Yth: {{ $transaction->customer->nama ?? '-' }}</strong><br>
                    {{ $transaction->customer->alamat ?? '-' }}
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <table style="width: 100%; font-size: 9pt;">
                        <tr>
                            <td style="width: 30%;"><strong>Faktur:</strong></td>
                            <td style="width: 70%;">{{ $transaction->no_transaksi }}</td>
                        </tr>
                        <tr>
                            <td><strong>Salesman:</strong></td>
                            <td>{{ $transaction->salesman->keterangan ?? 'OFFICE' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Pengirim:</strong></td>
                            <td>{{ $defaultCompany->nama ?? '' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Pembayaran:</strong></td>
                            <td>{{ $transaction->cara_bayar ?? 'Tunai' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- TABEL DAFTAR BARANG --}}
        <table class="item-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Barang</th>
                    <th>Satuan Besar</th>
                    <th>Kuantiti</th>
                    <th>Harga @</th>
                    <th>Jumlah</th>
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
                        <td class="center">{{ number_format($item->qty, 2) }} {{ $item->satuan ?? 'PAK' }}</td>
                        <td class="center">@if($item->harga == 0) Bonus @else Rp {{ number_format($item->harga, 0, ',', '.') }} @endif</td>
                        <td class="center">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                    </tr>
                @endforeach

                {{-- TEKNIK BARIS KOSONG OTOMATIS --}}
                @if ($loop->last)
                    @for ($j = $rowCount; $j < $itemsPerPage; $j++)
                        <tr class="empty-row">
                            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                        </tr>
                    @endfor
                @endif
            </tbody>
        </table>

        {{-- HANYA TAMPILKAN FOOTER DI HALAMAN TERAKHIR --}}
        @if ($loop->last)
        <div class="footer-container">

            <table class="summary-table">
                <tr>
                    <th style="width: 85%">TOTAL</th>
                    <td class="right" style="width: 15%">Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>DISCOUNT</th>
                    <td class="right">Rp {{ number_format($transaction->discount, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>PPN</th>
                    <td class="right">Rp {{ number_format($transaction->ppn, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>GRAND TOTAL</th>
                    <td class="right"><strong>Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</strong></td>
                </tr>
            </table>

            <div class="notes-details-wrapper">
                <div class="notes-section">
                    <strong>Pembayaran Transfer:</strong><br>
                    BCA 0202377881<br>
                    Novita Sari
                </div>

                <div class="details-row">
                    <div class="terbilang-section">
                        Terbilang: {{ ucwords(Terbilang::make($transaction->grand_total, ' rupiah')) }}
                    </div>
                </div>
            </div>

            <div class="signature-row">
                <div class="signature-spacer"></div>
                <div class="signature-left">
                    HORMAT KAMI<br><br>
                    (_____________)
                </div>
                <div class="signature-right">
                    PENERIMA<br><br>
                    (_____________)
                </div>
            </div>

            @if($transaction->is_edited || $transaction->status == 'canceled')
            <div class="edit-info-box">
                @if($transaction->is_edited)
                    <strong>Informasi Edit:</strong> Diedit oleh: {{ $transaction->edited_by }} pada {{ \Carbon\Carbon::parse($transaction->edited_at)->format('d M Y H:i') }}<br>Alasan: {{ $transaction->edit_reason }}
                @endif
                @if($transaction->is_edited && $transaction->status == 'canceled') <br> @endif
                @if($transaction->status == 'canceled')
                    <strong>Informasi Pembatalan:</strong> Dibatalkan oleh: {{ $transaction->canceled_by }} pada {{ \Carbon\Carbon::parse($transaction->canceled_at)->format('d M Y H:i') }}<br>Alasan: {{ $transaction->cancel_reason }}
                @endif
            </div>
            @endif

        </div>
        @endif
    </div>

    @if (!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach

</body>
</html>

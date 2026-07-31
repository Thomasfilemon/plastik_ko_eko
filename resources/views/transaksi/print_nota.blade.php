<?php
use Riskihajar\Terbilang\Facades\Terbilang;
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nota Transaksi</title>
    <link href="https://fonts.googleapis.com/css2?family=DejaVu+Sans+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }

        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        body {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 8pt;
            line-height: 1.2;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .page {
            width: 100%;
            padding: 5mm 7mm;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
        }

        .header {
            text-align: center;
            margin-bottom: 6px;
        }
        .header strong { font-size: 9pt; }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .header-company { text-align: left; }
        .header-right { text-align: right; font-size: 7pt; }

        .customer-info {
            width: 100%;
            margin: 10px 0;
            font-size: 8pt;
        }

        .customer-info td { vertical-align: top; padding: 2px 4px; }

        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .item-table th,
        .item-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            font-size: 7.5pt;
            line-height: 1.1;
        }
        .item-table th { background: #f5f5f5; }

        .item-table td.center, .item-table th.center { text-align: center; }
        .item-table td.right, .item-table th.right { text-align: right; }

        tr.empty-row td {
            border-color: #eee;
            color: #fff;
        }

        .footer-container {
            width: 100%;
            margin-top: auto;
            font-size: 8pt;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }
        .summary-table th, .summary-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            font-size: 7.5pt;
        }
        .summary-table th { text-align: left; }

        .payment-box {
            border: 1px solid #000;
            padding: 6px 10px;
            font-size: 7.5pt;
            line-height: 1.2;
        }

        .signatures {
            display: flex;
            justify-content: flex-end;
            gap: 8%;
            margin-top: 18px;
        }
        .sign-col {
            width: 28%;
            text-align: center;
            font-size: 8pt;
        }
        .sign-col strong { display: block; margin-bottom: 35px; }
        .sign-col small { display: block; margin-top: 3px; }

        .edit-info-box {
            font-size: 7pt;
            margin-top: 10px;
            padding: 6px;
            border: 1px solid #ccc;
            line-height: 1.2;
        }

        .page-break { page-break-after: always; }

        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>

@php
    $defaultCompany = \App\Models\Perusahaan::where('is_default', true)->first() ?? new \App\Models\Perusahaan();
    $itemsPerPage = 10;
    $groupedItems = $transaction->items->chunk($itemsPerPage);
    $totalPages = $groupedItems->count();
    $pageNum = 0;
@endphp

@foreach ($groupedItems as $chunk)
    @php $pageNum++; @endphp
    <div class="page">

        {{-- HEADER --}}
        <div class="header">
            <div class="header-top">
                <div class="header-company">
                    <strong>{{ $defaultCompany->nama ?? '' }}</strong><br>
                    {{ $defaultCompany->alamat ?? '' }}<br>
                    {{ $defaultCompany->kota ?? '' }}{{ $defaultCompany->kode_pos ? ', '.$defaultCompany->kode_pos : '' }}<br>
                    @if(!empty($defaultCompany->telepon)) TELP. {{ $defaultCompany->telepon }} @endif
                    @if(!empty($defaultCompany->fax)) &nbsp;&nbsp; FAX {{ $defaultCompany->fax }} @endif
                </div>
                <div class="header-right">
                    {{ \Carbon\Carbon::parse($transaction->tanggal)->format('d/M/Y') }}<br>
                    Halaman: {{ $pageNum }} / {{ $totalPages }}<br>
                    Dicetak oleh: {{ auth()->user()->name ?? 'SYSTEM' }}
                </div>
            </div>
            <div style="margin: 6px 0; font-size: 11pt; font-weight: bold;">NOTA TRANSAKSI</div>
        </div>

        {{-- CUSTOMER INFO --}}
        <table class="customer-info">
            <tr>
                <td style="width:50%;">
                    <strong>Kepada Yth: {{ $transaction->customer->nama ?? '-' }}</strong><br>
                    {{ $transaction->customer->alamat ?? '-' }}
                </td>
                <td style="width:50%;">
                    <table style="width:100%; font-size:8pt;">
                        <tr><td><strong>Faktur:</strong></td><td>{{ $transaction->no_transaksi }}</td></tr>
                        <tr><td><strong>Salesman:</strong></td><td>{{ $transaction->salesman->keterangan ?? 'OFFICE' }}</td></tr>
                        <tr><td><strong>Pengirim:</strong></td><td>{{ $defaultCompany->nama ?? '' }}</td></tr>
                        <tr><td><strong>Pembayaran:</strong></td><td>{{ $transaction->cara_bayar ?? 'Tunai' }}</td></tr>
                        @if($transaction->tanggal_jatuh_tempo)
                        <tr><td><strong>Jatuh Tempo:</strong></td><td>{{ \Carbon\Carbon::parse($transaction->tanggal_jatuh_tempo)->format('d/M/Y') }}</td></tr>
                        @endif
                        @if(!empty($transaction->hari_tempo))
                        <tr><td><strong>Hari Tempo:</strong></td><td>{{ $transaction->hari_tempo }} hari</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        {{-- ITEM TABLE --}}
        <table class="item-table">
            <thead>
                <tr>
                    <th class="center" style="width:5%;">No.</th>
                    <th style="width:35%;">Nama Barang</th>
                    <th class="center" style="width:12%;">Satuan Besar</th>
                    <th class="center" style="width:13%;">Kuantiti (Satuan Kecil)</th>
                    <th class="center" style="width:15%;">Harga @</th>
                    <th class="center" style="width:20%;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @php $rowCount = 0; @endphp
                @foreach ($chunk as $i => $item)
                    @php $rowCount++; @endphp
                    <tr>
                        <td class="center">{{ (($pageNum - 1) * $itemsPerPage) + $i + 1 }}</td>
                        <td>{{ $item->nama_barang }}</td>
                        @php
                            // Ambil unit dasar dari master kode barang jika tersedia
                            $kb = \App\Models\KodeBarang::where('kode_barang', $item->kode_barang)->first();
                            $unitDasar = $kb->unit_dasar ?? ($item->satuan ?? 'LBR');

                            // Utamakan data satuan besar yang sudah disimpan pada item
                            $bigUnit = $item->satuan_besar ?? null;
                            $bigQty = $item->qty_besar ?? null;

                            // Fallback untuk data lama: hitung dari konversi jika satuan item adalah turunan
                            if (empty($bigUnit)) {
                                $satuanItem = $item->satuan ?? $unitDasar;
                                if ($kb && $satuanItem !== $unitDasar) {
                                    $conv = \App\Models\UnitConversion::active()
                                        ->where('kode_barang_id', $kb->id)
                                        ->where('unit_turunan', $satuanItem)
                                        ->first();
                                    if ($conv) {
                                        $bigUnit = $satuanItem;
                                        $bigQty = $conv->nilai_konversi > 0 ? ($item->qty / $conv->nilai_konversi) : $item->qty;
                                    }
                                }
                            }
                        @endphp
                        <td class="center">
                            @if(!empty($bigUnit))
                                {{ rtrim(rtrim(number_format($bigQty, 2, '.', ''), '0'), '.') }} {{ $bigUnit }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="center">{{ number_format($item->qty, 2) }} {{ $unitDasar }}</td>
                        <td class="center">{{ $item->harga == 0 ? 'Bonus' : 'Rp '.number_format($item->harga, 0, ',', '.') }}</td>
                        <td class="center">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
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

        {{-- FOOTER --}}
        @if ($loop->last)
        <div class="footer-container">

            <table class="summary-table">
                <tr><th style="width:85%;">TOTAL</th><td class="right" style="width:15%;">Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</td></tr>
                <tr><th>DISCOUNT</th><td class="right">Rp {{ number_format($transaction->discount, 0, ',', '.') }}</td></tr>
                <tr><th>PPN</th><td class="right">Rp {{ number_format($transaction->ppn, 0, ',', '.') }}</td></tr>
                <tr><th>GRAND TOTAL</th><td class="right"><strong>Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</strong></td></tr>
            </table>

            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                <div style="text-align:left; font-size:8.5pt; line-height:1.3;">
                    <strong>Pembayaran Transfer:</strong><br>
                    BCA 0202377881<br>
                    Novita Sari
                </div>
                <div style="text-align:right;">
                    <em>Terbilang: {{ ucwords(Terbilang::make($transaction->grand_total, ' rupiah')) }}</em>
                </div>
            </div>

            <div class="signatures">
                <div class="sign-col" style="visibility:hidden;">
                    &nbsp;
                </div>
                <div class="sign-col">
                    <strong>Hormat Kami</strong>
                    ( _____________ )<br>
                    <small>Tanda tangan & Nama jelas</small>
                </div>
                <div class="sign-col">
                    <strong>Diterima Oleh</strong>
                    ( _____________ )<br>
                    <small>Tanda tangan & Nama jelas</small>
                </div>
            </div>

            @if($transaction->notes)
            <div class="edit-info-box"><strong>Catatan:</strong> {{ $transaction->notes }}</div>
            @endif

            @if($transaction->is_edited || $transaction->status == 'canceled')
            <div class="edit-info-box">
                @if($transaction->is_edited)
                    <strong>Edit:</strong> {{ $transaction->editedBy->name ?? 'Admin' }} - {{ \Carbon\Carbon::parse($transaction->edited_at)->format('d M Y H:i') }}<br>
                    Alasan: {{ $transaction->edit_reason }}
                @endif
                @if($transaction->is_edited && $transaction->status == 'canceled') <br> @endif
                @if($transaction->status == 'canceled')
                    <strong>Pembatalan:</strong> {{ $transaction->canceled_by }} - {{ \Carbon\Carbon::parse($transaction->canceled_at)->format('d M Y H:i') }}<br>
                    Alasan: {{ $transaction->cancel_reason }}
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

@if(request('auto_print'))
<script>
    window.addEventListener('load', function () {
        setTimeout(function(){
            window.print();
            setTimeout(function(){ window.close(); }, 500);
        }, 200);
    });
</script>
@endif

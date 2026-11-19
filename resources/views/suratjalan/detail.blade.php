<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            /* * PERUBAHAN: Ukuran diset secara eksplisit agar sama dengan
             * 'Statement' dalam mode Landscape (21.59cm x 13.97cm).
             */
            size: 21.59cm 13.97cm;
            margin: 0mm;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 9pt;
            line-height: 1.2;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .page {
            width: 100%;
            height: 100%;
            /* Memberi sedikit padding agar tidak terlalu mepet ke tepi */
            padding: 10mm 8mm 5mm 8mm;
        }

        /* Sisa dari CSS Anda tidak perlu diubah karena sudah bagus */
        /* ... (semua style .header, .row, .item-table, dll tetap sama) ... */
        .header { text-align: center; line-height: 1.1; }
        .header strong { font-size: 10pt; }
        .row { display: flex; justify-content: space-between; margin-bottom: 2px; }
        table.item-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        table.item-table th,
        table.item-table td { border: 1px solid #000; padding: 2px 3px; font-size: 8pt; vertical-align: top; }
        table.item-table th { font-weight: bold; }
        tr.empty-row td { border: 1px solid #000; }
        .notes-section { font-size: 8pt; line-height: 1.2; padding-top: 20px; }
        table.signature-table { width: 100%; margin-top: 10px; }
        table.signature-table td { border: none; text-align: center; font-size: 9pt; }
        .right { text-align: right; }
        .center { text-align: center; }
        .no-print { position: fixed; top: 10px; right: 10px; z-index: 999; }
        .no-print a, .no-print button { display:inline-block; margin-left: 8px; padding: 6px 16px; font-size: 14px; border: none; border-radius: 4px; background: #007bff; color: #fff; cursor: pointer; text-decoration: none; }
        .no-print a { background: #6c757d; }

        @media print {
            .no-print { display: none !important; }
            body { font-size: 9pt; background: none; }
        }
    </style>
</head>
<body>
    @php
        $defaultCompany = \App\Models\Perusahaan::forNota();
    @endphp

    <div class="no-print">
        <a href="{{ route('suratjalan.history') }}">&#8592; Kembali</a>
        <button onclick="window.print()">&#128424; Print</button>
    </div>

    <div class="page">
        <div class="header">
            <strong>{{ $defaultCompany->nama ?? 'PLASINDOMART' }}</strong><br>
            {{ $defaultCompany->alamat ?? 'JL. SINAR RAGA ABI HASAN NO.1553 RT.022 RW.008' }}<br>
            {{ $defaultCompany->kota ?? '8 ILIR' }}, {{ $defaultCompany->kode_pos ?? 'ILIR TIMUR II' }}<br>
            TELP. {{ $defaultCompany->telepon ?? '(0711) 311158' }} &nbsp;&nbsp; FAX {{ $defaultCompany->fax ?? '(0711) 311158' }}<br>
        </div>

        <div class="row" style="margin-top: 5px;">
            <div>
                <strong>No. Surat Jalan:</strong> {{ $suratJalan->no_suratjalan ?? '-' }}<br>
                <strong>No. Pesanan:</strong> {{ $suratJalan->no_transaksi ?? '-' }}
            </div>
            <div class="right">
                <strong>Tanggal:</strong> {{ \Carbon\Carbon::parse($suratJalan->tanggal)->format('d M Y') }}<br>
                <strong>Waktu:</strong> {{ \Carbon\Carbon::parse($suratJalan->transaksi->created_at)->format('H:i:s') }}
            </div>
        </div>
        <div class="row">
            <div>
                <strong>Kepada Yth:</strong> {{ $suratJalan->customer->nama ?? '-' }}<br>
                <strong>Alamat:</strong> {{ $suratJalan->alamat_suratjalan ?? '-' }}<br>
                <strong>Telp:</strong> {{ $suratJalan->customer->telepon ?? '-' }}
            </div>
        </div>

        <table class="item-table">
            <thead>
                <tr>
                    <th width="5%">No.</th>
                    <th width="20%">Kode Barang</th>
                    <th width="35%">Nama Barang</th>
                    <th width="10%">Panjang</th>
                    <th width="10%">Jumlah</th>
                    <th width="10%">Satuan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($suratJalan->transaksi->items as $i => $item)
                @php
                    // Get kode barang info for unit conversion
                    $kodeBarang = \App\Models\KodeBarang::where('kode_barang', $item->kode_barang)->first();
                    $unitDasar = $kodeBarang ? $kodeBarang->unit_dasar : 'Pcs';
                    $unitTurunan = $kodeBarang ? $kodeBarang->unit_turunan : 'Pcs';
                    
                    // For Surat Jalan, we show quantity in the base unit (satuan besar)
                    $displayQty = $item->qty;
                    $displayUnit = $unitDasar;
                @endphp
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $item->kode_barang }}</td>
                    <td>{{ $item->nama_barang }}</td>
                    <td class="center">{{ $item->panjang ?? '-' }}</td>
                    <td class="center">{{ $displayQty }}</td>
                    <td class="center">{{ $displayUnit }}</td>
                </tr>
                @endforeach

                {{-- Menambahkan baris kosong otomatis --}}
                @php
                    $max_items = 8;
                @endphp
                @for ($j = count($suratJalan->transaksi->items); $j < $max_items; $j++)
                    <tr class="empty-row">
                        <td>&nbsp;</td> <td>&nbsp;</td> <td>&nbsp;</td>
                        <td>&nbsp;</td> <td>&nbsp;</td> <td>&nbsp;</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <div class="notes-section">
            <strong>Catatan:</strong><br>
            Mohon periksa barang sebelum menandatangani. Kerusakan setelah tanda tangan bukan tanggung jawab pengirim.<br>
            Barang masih titipan dari {{ $defaultCompany->nama ?? 'Plasindomart' }}, bila belum dilunasi.
        </div>
        <table class="signature-table">
            <tr>
                <td>Dibuat Oleh
                <div style="height: 10px;"></div>    
                <br><br><br>(_____________)</td>
                <td>Diantar Oleh
                <div style="height: 10px;"></div>
                <br><br><br>(_____________)</td>
                <td>Diterima Oleh
                <div style="height: 10px;"></div>
                <br><br><br>(_____________)</td>
            </tr>
        </table>
    </div>

    <script>
        // Script tidak berubah
    </script>
</body>
</html>

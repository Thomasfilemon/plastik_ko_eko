@extends('layout.Nav')

@section('content')
<div class="container">
    <div class="title-box">
        <h2><i class="fas fa-edit mr-2"></i>Edit Transaksi Pembelian</h2>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Data Transaksi</h5>
        </div>
        <div class="card-body">
            <form id="transactionForm">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="no_nota">No. Nota</label>
                            <input type="text" class="form-control" id="no_nota" name="nota" value="{{ $purchase->nota }}" readonly style="background-color: #ffc107; color: #000; font-weight: bold;">
                        </div>
                        <div class="form-group">
                            <label for="no_surat_jalan">No. Surat Jalan</label>
                            <input type="text" class="form-control" id="no_surat_jalan" name="no_surat_jalan" placeholder="Masukkan nomor surat jalan supplier">
                            <small class="form-text text-muted">Masukkan nomor surat jalan yang tertera pada dokumen pengiriman dari supplier</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="tanggal">Tanggal</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="tanggal" name="tanggal" value="{{ $purchase->tanggal->format('Y-m-d') }}">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="supplier">Supplier</label>
                            <input type="text" id="supplier" name="supplier_display" class="form-control" value="{{ $supplier }}" placeholder="Masukkan kode atau nama supplier">
                            <input type="hidden" id="kode_supplier" name="kode_supplier" value="{{ $purchase->kode_supplier }}">
                            <div id="supplierDropdown" class="dropdown-menu" style="display: none; position: absolute; width: 100%;"></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="metode_pembayaran">Metode Pembayaran</label>
                            <select class="form-control" id="metode_pembayaran" name="metode_pembayaran">
                                <option selected disabled value=""> Pilih Metode Pembayaran</option>
                                <option value="Tunai" {{ $purchase->pembayaran == 'Tunai' ? 'selected' : '' }}>Tunai</option>
                                <option value="Non Tunai" {{ $purchase->pembayaran == 'Transfer' || $purchase->pembayaran == 'Kredit' || $purchase->pembayaran == 'Debit' ? 'selected' : '' }}>Non Tunai</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cara_bayar">Cara Bayar</label>
                            <select class="form-control" id="cara_bayar" name="cara_bayar">
                                <option value="{{ $purchase->cara_bayar }}">{{ $purchase->cara_bayar }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="edit_reason">Alasan Edit <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_reason" name="edit_reason" placeholder="Masukkan alasan perubahan nota pembelian" required>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Items Section -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Rincian Barang</h5>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addItemModal">
                <i class="fas fa-plus-circle"></i> Tambah Barang
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="itemsTable">
                    <thead>
                        <tr>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Keterangan</th>
                            <th>Harga Beli</th>
                            <th>Qty</th>
                            <th>Satuan Kecil</th>
                            <th>Satuan Besar</th>
                            <th>Total</th>
                            <th>Diskon</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="itemsList">
                        <!-- Dynamic items will be loaded by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Summary Section -->
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Total</label>
                        <input type="text" class="form-control text-right" id="total" name="total" readonly value="{{ number_format($purchase->subtotal, 0, ',', '.') }}">
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <input type="checkbox" id="discount_checkbox" {{ $purchase->diskon > 0 ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="input-group-prepend">
                                <span class="input-group-text">Disc(%)</span>
                            </div>
                            <input type="number" class="form-control" id="discount_percent" value="{{ $purchase->diskon > 0 ? ($purchase->diskon / $purchase->subtotal) * 100 : 0 }}" {{ $purchase->diskon > 0 ? '' : 'disabled' }}>
                            <input type="text" class="form-control text-right" id="discount_amount" value="{{ number_format($purchase->diskon, 0, ',', '.') }}" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <input type="checkbox" id="ppn_checkbox" {{ $purchase->ppn > 0 ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="input-group-prepend">
                                <span class="input-group-text">PPN</span>
                            </div>
                            <input type="text" class="form-control text-right" id="ppn_amount" value="{{ number_format($purchase->ppn, 0, ',', '.') }}" readonly>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Cara Bayar</label>
                        <select class="form-control" id="cara_bayar_akhir">
                            <option value="Cash" {{ $purchase->cara_bayar == 'Cash' ? 'selected' : '' }}>Cash</option>
                            <option value="Credit Card" {{ $purchase->cara_bayar == 'Credit Card' ? 'selected' : '' }}>Credit Card</option>
                            <option value="Debit" {{ $purchase->cara_bayar == 'Debit' ? 'selected' : '' }}>Debit</option>
                            <option value="Cicilan" {{ $purchase->cara_bayar == 'Cicilan' ? 'selected' : '' }}>Cicilan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Grand Total</label>
                        <input type="text" class="form-control text-right" id="grand_total" readonly value="{{ number_format($purchase->grand_total, 0, ',', '.') }}" style="font-size: 18px; font-weight: bold;">
                    </div>
                    <div class="form-group text-right mt-4">
                        <button type="button" class="btn btn-success" id="updateTransaction">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <a href="{{ route('pembelian.nota.show', $purchase->id) }}" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel">Tambah Barang</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="addItemForm">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Updated kode_barang input group with dropdown -->
                            <div class="form-group position-relative">
                                <label for="kode_barang">Kode Barang</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="kode_barang" name="kode_barang" placeholder="Masukkan kode barang" autocomplete="off" required>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="findItem" data-toggle="modal" data-target="#kodeBarangSearchModal">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Autocomplete dropdown -->
                                <div class="dropdown-menu" id="kodeBarangDropdown" style="display: none; max-height: 280px; overflow-y: auto; width: 100%;"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="nama_barang">Nama Barang</label>
                                <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="keterangan">Keterangan</label>
                                <textarea class="form-control" id="keterangan" name="keterangan" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="harga">Harga Beli <small class="text-muted">(per satuan kecil)</small></label>
                                <input type="number" class="form-control" id="harga" name="harga" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="quantity">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="0.01" step="0.01" required>
                                <small class="form-text text-muted" id="qtyConversionHint" style="display:none;"></small>
                            </div>

                            
                            <div class="form-group">
                                <label for="diskon">Diskon (%)</label>
                                <input type="number" class="form-control" id="diskon" name="diskon" value="0" min="0" max="100">
                            </div>
                            
                            <div class="form-group">
                                <label for="satuanKecil">Satuan Kecil</label>
                                <select class="form-control" id="satuanKecil" name="satuanKecil">
                                    <option value="PCS">PCS</option>
                                    <option value="LBR">LBR</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="satuanBesar">Satuan Besar</label>
                                <select class="form-control" id="satuanBesar" name="satuanBesar">
                                    <option value="BOX">BOX</option>
                                    <option value="DUS">DUS</option>
                                    <option value="UNIT">UNIT</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Kode</th>
                                            <th>Keterangan</th>
                                            <th>Harga Beli</th>
                                            <th>Qty</th>
                                            <th>Panjang</th>
                                            <th>Total</th>
                                            <th>Satuan Kecil</th>
                                            <th>Satuan Besar</th>
                                            <th>Disc(%)</th>
                                            <th>Sub Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemPreview">
                                        <!-- Item preview will be shown here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="saveItemBtn">Tambahkan</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" role="dialog" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editItemModalLabel">Edit Barang</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_item_index">
                <div class="form-group">
                    <label>Nama Barang</label>
                    <input type="text" class="form-control" id="edit_item_nama">
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Qty</label>
                        <input type="number" class="form-control" id="edit_item_qty" step="0.01" min="0.01">
                        <small class="form-text text-muted" id="edit_item_qty_hint" style="display:none;"></small>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Harga Beli <small class="text-muted">(per satuan kecil)</small></label>
                        <input type="number" class="form-control" id="edit_item_harga" step="0.01" min="0">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Satuan Kecil</label>
                        <input type="text" class="form-control" id="edit_item_satuan_kecil" readonly>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Satuan Besar</label>
                        <select class="form-control" id="edit_item_satuan_besar"></select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Diskon (%)</label>
                        <input type="number" class="form-control" id="edit_item_diskon" min="0" max="100">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Panjang</label>
                        <input type="number" class="form-control" id="edit_item_panjang" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label>Keterangan</label>
                    <input type="text" class="form-control" id="edit_item_keterangan">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="saveEditItemBtn">
                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for searching Kode Barang -->
<div class="modal fade" id="kodeBarangSearchModal" tabindex="-1" role="dialog" aria-labelledby="kodeBarangSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kodeBarangSearchModalLabel">Cari Kode Barang</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="searchKodeBarangInput" placeholder="Masukkan kode atau nama barang">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="button" id="searchKodeBarangBtn">
                            <i class="fas fa-search"></i> Cari
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Panjang</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="kodeBarangSearchResults">
                            <!-- Search results will be added here by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
{{-- IMPORTANT: Define global variables here that will be used in the external JS file --}}
<script>
    // Expose Laravel routes as global window variables
    window.supplierSearchUrl = "{{ route('api.suppliers.search') }}";
    window.updateTransactionUrl = "{{ route('pembelian.update', $purchase->id) }}";
    window.notaShowUrl = "{{ route('pembelian.nota.show', $purchase->id) }}";
    window.csrfToken = "{{ csrf_token() }}";
    window.purchaseId = "{{ $purchase->id }}";
    window.grandTotal = "{{ $purchase->grand_total }}";
    
    // Add kode barang search routes
    window.kodeBarangSearchUrl = "{{ route('kodeBarang.search') }}";
    window.getPanelInfoUrl = "{{ route('panel.by.kodeBarang') }}";
    window.availableUnitsUrl = "{{ url('sales-order/available-units') }}";
    
    // Initial items: qty in satuan input (besar if any), harga always per satuan kecil
    const initialItems = {!! json_encode($purchase->items->map(function($item) {
        $kode = $item->kodeBarang;
        $hasBesar = !empty($item->satuan_besar) && !empty($item->qty_besar) && (float)$item->qty_besar > 0;
        $factor = $hasBesar ? ((float)$item->qty / (float)$item->qty_besar) : 1.0;
        $qtyDisplay = $hasBesar ? (float)$item->qty_besar : (float)$item->qty;
        $satuanKecil = $item->satuan ?: (optional($kode)->unit_dasar ?? 'LBR');

        return [
            'kodeBarang' => $item->kode_barang,
            'kodeBarangId' => optional($kode)->id,
            'namaBarang' => $item->nama_barang,
            'keterangan' => $item->keterangan,
            'harga' => (float)$item->harga,
            'qty' => $qtyDisplay,
            'qtyKecil' => (float)$item->qty,
            'unitFactor' => $factor,
            'satuan' => $hasBesar ? $item->satuan_besar : $satuanKecil,
            'satuanKecil' => $satuanKecil,
            'satuanBesar' => $item->satuan_besar ?: '',
            'panjang' => 0,
            'diskon' => (float)$item->diskon,
            'total' => (float)$item->total,
        ];
    })) !!};

    $('#metode_pembayaran').on('change', function () {
        const metode = $(this).val();
        $('#cara_bayar').html('<option value="">Loading...</option>');
        
        $.ajax({
            url: '{{ url("api/cara-bayar/by-metode") }}',
            method: 'GET',
            data: { metode: metode },
            success: function (data) {
                let options = '<option value="">-- Pilih Cara Bayar --</option>';
                data.forEach(cb => {
                    options += `<option value="${cb.nama}">${cb.nama}</option>`;
                });
                $('#cara_bayar').html(options);
            },
            error: function () {
                $('#cara_bayar').html('<option value="">Gagal load data</option>');
            }
        });
    });

    $('#cara_bayar').on('change', function () {
        const selected = $(this).val();
        $('#cara_bayar_akhir')
            .html(`<option value="${selected}">${selected}</option>`)
            .val(selected);
    });
</script>

{{-- Include the external JS file using file_get_contents to load directly from views directory --}}
<script>
{!! file_get_contents(resource_path('views/scripts/editpembelian.js')) !!}
</script>
@endsection
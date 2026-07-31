@extends('layout.Nav')

@section('content')
<div class="container">
    <div class="title-box">
        <h2><i class="fas fa-plus mr-2"></i>Tambah Transaksi Pembelian</h2>
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
                            <input type="text" class="form-control" id="no_nota" name="nota" value="{{ $nota }}" readonly style="background-color: #ffc107; color: #000; font-weight: bold;">
                        </div>
                        <div class="form-group">
                            <label for="no_surat_jalan">No. Referensi</label>
                            <input type="text" class="form-control" id="no_surat_jalan" name="no_surat_jalan" placeholder="Masukkan nomor referensi">
                            <small class="form-text text-muted">Masukkan nomor referensi</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="tanggal">Tanggal</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="tanggal" name="tanggal" value="{{ date('Y-m-d') }}">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="supplier">Supplier</label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="supplier" name="supplier" placeholder="Ketik nama supplier..." autocomplete="off">
                                <input type="hidden" id="kode_supplier" name="kode_supplier">
                                <div class="dropdown-menu" id="supplierDropdown" style="display: none; max-height: 200px; overflow-y: auto; width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="pembayaran">Metode Pembayaran</label>
                            <select class="form-control" id="pembayaran" name="pembayaran">
                                <option value="">Pilih Metode Pembayaran</option>
                                <option value="Tunai">Tunai</option>
                                <option value="Non Tunai">Non Tunai</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="cara_bayar">Cara Bayar</label>
                            <select class="form-control" id="cara_bayar" name="cara_bayar">
                                <option value="">Pilih Cara Bayar</option>
                            </select>
                        </div>
                        <div class="form-group" id="hariTempoGroup" style="display:none;">
                            <label for="hari_tempo">Hari Tempo</label>
                            <input type="number" class="form-control" id="hari_tempo" name="hari_tempo" min="0" value="0">
                            <small class="form-text text-muted">Isi 0 jika tanpa tempo</small>
                        </div>
                        <div class="form-group" id="jatuhTempoGroup" style="display:none;">
                            <label for="tanggal_jatuh_tempo">Tanggal Jatuh Tempo</label>
                            <input type="date" class="form-control" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo">
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
        </div>
        <div class="card-body">
            <!-- Form Tambah Barang seperti Sales Order -->
            <div id="items-container">
                <div class="item-row" data-index="0">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Barang</label>
                                <select class="form-control item-barang" id="kode_barang_select">
                                    <option value="">Pilih Barang</option>
                                    @if(isset($kodeBarangs) && $kodeBarangs)
                                        @foreach($kodeBarangs as $barang)
                                            <option value="{{ $barang->id }}" 
                                                data-harga="{{ $barang->cost }}"
                                                data-unit-dasar="{{ $barang->unit_dasar }}"
                                                data-kode="{{ $barang->kode_barang }}"
                                                data-nama="{{ $barang->name }}">
                                                {{ $barang->kode_barang }} - {{ $barang->name }}
                                            </option>
                                        @endforeach
                                    @else
                                        <option value="">Tidak ada data barang</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Qty</label>
                                <input type="number" class="form-control item-qty" id="quantity" step="0.01" min="0.01">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Satuan Kecil</label>
                                <select class="form-control item-satuan-kecil" id="satuanKecil">
                                    <option value="LBR">LBR</option>
                                </select>
                                <input type="hidden" class="item-satuan" id="satuan" value="LBR">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Satuan Besar</label>
                                <select class="form-control item-satuan-besar" id="satuanBesar"></select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Harga Beli</label>
                                <input type="number" class="form-control item-harga" id="harga" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Total</label>
                                <input type="number" class="form-control item-total" id="item_total" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Diskon (%)</label>
                                <input type="number" class="form-control" id="diskon" placeholder="0" min="0" max="100">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Panjang</label>
                                <input type="number" class="form-control" id="panjang" placeholder="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="form-group">
                                <label>Keterangan</label>
                                <input type="text" class="form-control" id="keterangan" placeholder="Keterangan">
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-end justify-content-end">
                            <button type="button" class="btn btn-success btn-sm" id="addItemBtn">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Items List -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Daftar Barang</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Keterangan</th>
                            <th>Harga Beli</th>
                            <th>Qty & Satuan</th>
                            <th>Satuan Besar</th>
                            <th>Total</th>
                            <th>Panjang</th>
                            <th>Diskon</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="itemsList">
                        <!-- Items will be added here -->
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
                        <input type="text" class="form-control text-right" id="total" name="total" readonly>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <input type="checkbox" id="discount_checkbox">
                                </div>
                            </div>
                            <div class="input-group-prepend">
                                <span class="input-group-text">Disc(%)</span>
                            </div>
                            <input type="number" class="form-control" id="discount_percent" disabled>
                            <input type="text" class="form-control text-right" id="discount_amount" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <input type="checkbox" id="ppn_checkbox">
                                </div>
                            </div>
                            <div class="input-group-prepend">
                                <span class="input-group-text">PPN(11%)</span>
                            </div>
                            <input type="text" class="form-control text-right" id="ppn_amount" readonly>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Grand Total</label>
                        <input type="text" class="form-control text-right font-weight-bold" id="grand_total" name="grand_total" readonly style="font-size: 1.2em; background-color: #e9ecef;">
                    </div>
                    <div class="form-group">
                        <button type="button" class="btn btn-primary btn-lg" id="saveTransaction">
                            <i class="fas fa-save mr-2"></i>Simpan Transaksi
                        </button>
                        <button type="button" class="btn btn-secondary btn-lg ml-2" id="cancelTransaction">
                            <i class="fas fa-times mr-2"></i>Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Invoice -->
<div class="modal fade" id="invoiceModal" tabindex="-1" role="dialog" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalLabel">Transaksi Berhasil Disimpan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success">
                    <h6>Detail Transaksi:</h6>
                    <p><strong>No. Nota:</strong> <span id="invoiceNota"></span></p>
                    <p><strong>Tanggal:</strong> <span id="invoiceTanggal"></span></p>
                    <p><strong>Supplier:</strong> <span id="invoiceSupplier"></span></p>
                    <p><strong>Grand Total:</strong> <span id="invoiceGrandTotal"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="printInvoiceBtn">
                    <i class="fas fa-print mr-2"></i>Print Invoice
                </button>
                <button type="button" class="btn btn-secondary" id="backToFormBtn">
                    <i class="fas fa-arrow-left mr-2"></i>Kembali ke Form
                </button>
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
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Harga Beli</label>
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

<!-- Kode Barang Search Modal -->
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
                <div class="form-group">
                    <label for="searchKodeBarangInput">Masukkan kata kunci pencarian:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchKodeBarangInput" placeholder="Ketik kode barang atau nama barang...">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button" id="searchKodeBarangBtn">
                                <i class="fas fa-search"></i> Cari
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Panjang</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="kodeBarangSearchResults">
                            <!-- Search results will be shown here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')

<script>
    // Expose Laravel routes as global window variables
    window.supplierSearchUrl = "{{ route('api.suppliers.search') }}";
    window.storeTransactionUrl = "{{ route('pembelian.store') }}";
    window.printInvoiceUrl = "{{ url('pembelian/lihatnota') }}/";
    window.backToPembelian = "{{ route('pembelian.index') }}"
    window.kodeBarangSearchUrl = "{{ route('kodeBarang.search') }}";
    window.getPanelInfoUrl = "{{ route('panel.by.kodeBarang') }}";
    window.availableUnitsUrl = "{{ route('sales-order.available-units', '') }}";

    window.csrfToken = "{{ csrf_token() }}";
</script>

{{-- Include the external JS file using file_get_contents to load directly from views directory --}}
<script>

$('#pembayaran').on('change', function () {
        const metode = $(this).val();
        $('#cara_bayar').html('<option value="">Loading...</option>');
        
        // Show/Hide tempo fields based on pembayaran (Non Tunai assumed kredit/tempo)
        if (metode && metode.toLowerCase() !== 'tunai') {
            $('#hariTempoGroup').show();
            $('#jatuhTempoGroup').show();
        } else {
            $('#hariTempoGroup').hide();
            $('#jatuhTempoGroup').hide();
        }

        $.ajax({
            url: '{{ url("api/cara-bayar/by-metode") }}',
            method: 'GET',
            data: { metode: metode },
            success: function (data) {
                let options = '';
                // Preselect first option and reflect to summary selector
                data.forEach(cb => {
                    options += `<option value="${cb.nama}">${cb.nama}</option>`;
                });
                $('#cara_bayar').html(options);
                const first = data.length > 0 ? data[0].nama : '';
                if (first) {
                    $('#cara_bayar').val(first).trigger('change');
                }
            },
            error: function () {
                $('#cara_bayar').html('<option value="">Gagal load data</option>');
            }
        });
    });

    // Auto-calc tanggal_jatuh_tempo when tanggal or hari_tempo changes
    function recalcJatuhTempo(){
        const base = $('#tanggal').val();
        const hari = parseInt($('#hari_tempo').val()||'0',10);
        if(!base || isNaN(hari)) return;
        const d = new Date(base);
        d.setDate(d.getDate()+hari);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth()+1).padStart(2,'0');
        const dd = String(d.getDate()).padStart(2,'0');
        $('#tanggal_jatuh_tempo').val(`${yyyy}-${mm}-${dd}`);
    }
    $('#tanggal').on('change', recalcJatuhTempo);
    $('#hari_tempo').on('input', recalcJatuhTempo);

{!! file_get_contents(resource_path('views/scripts/pembelian.js')) !!}
</script>
@endsection

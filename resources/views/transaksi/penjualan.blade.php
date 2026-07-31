@extends('layout.Nav')

@section('content')
<div id="loadingOverlay" style="display:none;position:fixed;z-index:9999;top:0;left:0;width:100vw;height:100vh;background:rgba(255,255,255,0.7);backdrop-filter:blur(2px);justify-content:center;align-items:center;">
    <div style="font-size:1.5rem;color:#333;">
        <span class="spinner-border text-primary" role="status"></span>
        <span class="ml-2">Memproses...</span>
    </div>
</div>
<div class="container">
    <div class="title-box">
        <h2><i class="fas fa-file-invoice mr-2"></i>Transaksi Penjualan</h2>
    </div>
    @if(session('warning'))
<script>
    alert("{{ session('warning') }}");
</script>
@endif
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
                            <label for="no_transaksi">No. Transaksi</label>
                            <input type="text" class="form-control" id="no_transaksi" value="{{ $noTransaksi ?? '' }}" readonly style="background-color: #ffc107; color: #000; font-weight: bold;">
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
                            <label for="customer">Customer</label>
                            <div class="input-group">
                                <input type="text" id="customer" name="customer_display" class="form-control" placeholder="Masukkan nama customer">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addCustomerModal" title="Tambah Customer Baru">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" id="kode_customer" name="kode_customer">
                            <div id="customerDropdown" class="dropdown-menu" style="display: none; position: relative; width: 100%;"></div>
                        </div>
                        <div class="form-group">
                            <label for="customer">Alamat Customer</label>
                            <input type="text" id="alamatCustomer" name="customer-alamat" class="form-control" readonly>
                        </div>

                        <div class="form-group">
                            <label for="customer">No HP / Telp Customer</label>
                            <input type="text" id="hpCustomer" name="customer-hp" class="form-control" readonly>
                        </div>

                        <div class="form-group">
                            <label for="sales_order">Sales Order (Opsional)</label>
                            <div class="input-group">
                                <input type="text" id="sales_order_search" name="sales_order_display" class="form-control" placeholder="Cari Sales Order...">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-info" id="load_sales_order_btn">
                                        <i class="fas fa-search"></i> Cari
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" id="sales_order_id" name="sales_order_id">
                            <div id="salesOrderDropdown" class="dropdown-menu" style="display: none; position: relative; width: 100%;"></div>
                            <small class="form-text text-muted">Pilih Sales Order untuk mengisi otomatis data transaksi</small>
                        </div>

                    </div>

                    <div class="col-md-6">

                        <div class="form-group">
                            <label for="sales">Sales</label>
                            <input type="text" id="sales" name="sales_display" class="form-control" placeholder="Masukkan kode atau nama sales">
                            <input type="hidden" id="kode_sales" name="sales"> <!-- Hanya kode_sales yang dikirim -->
                            <div id="salesDropdown" class="dropdown-menu" style="display: none; position: relative; width: 100%;"></div>
                        </div>

                        <div class="form-group">
                            <label for="metode_pembayaran">Metode Pembayaran</label>
                            <select class="form-control" id="metode_pembayaran" name="metode_pembayaran">
                                <option value="Non Tunai" selected>Non Tunai</option>
                            </select>
                            <small class="form-text text-muted">Semua pembayaran menggunakan kredit/tempo</small>
                        </div>

                        <div class="form-group">
                            <label for="cara_bayar">Cara Bayar</label>
                            <select class="form-control" id="cara_bayar" name="cara_bayar">
                                <option value="Kredit" selected>Kredit</option>
                            </select>
                        </div>

                        <div class="form-group" id="hariTempoGroup" style="display:block;">
                            <label for="hari_tempo">Hari Tempo</label>
                            <input type="number" class="form-control" id="hari_tempo" name="hari_tempo" min="0" value="0">
                            <small class="form-text text-muted">Isi 0 jika tanpa tempo</small>
                        </div>
                        <div class="form-group" id="jatuhTempoGroup" style="display:block;">
                            <label for="tanggal_jatuh_tempo">Tanggal Jatuh Tempo</label>
                            <input type="date" class="form-control" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo">
                        </div>

                        <div class="form-group">
                            <label for="tanggal_jadi">Tanggal Jadi</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="tanggal_jadi" name="tanggal_jadi" value="{{ date('Y-m-d') }}">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                </div>
                            </div>
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
                                                data-harga="{{ $barang->harga_jual }}"
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
                                <small class="form-text text-muted item-qty-hint" style="display:none;"></small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Satuan Kecil</label>
                                <select class="form-control item-satuan-kecil" id="satuanKecil">
                                    <option value=""></option>
                                </select>
                                <input type="hidden" class="item-satuan" id="satuan" value="">
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
                                <label>Harga</label>
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
                                <label>Ongkos Kuli</label>
                                <input type="number" class="form-control" id="ongkos_kuli" placeholder="0">
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

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="itemsTable">
                    <thead>
                        <tr>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Keterangan</th>
                            <th>Harga Jual</th>
                            <th>Qty & Satuan</th>
                            <th>Satuan Besar</th>
                            <th>Total</th>
                            <th>Ongkos Kuli</th>
                            <th>Diskon</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="itemsList">
                        <!-- Dynamic items will be added here -->
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
                        <input type="text" class="form-control text-right" id="total" name="total" readonly value="0">
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
                            <input type="number" class="form-control" id="discount_percent" value="0" disabled>
                            <input type="text" class="form-control text-right" id="discount_amount" value="0" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <input type="checkbox" id="disc_rp_checkbox">
                                </div>
                            </div>
                            <div class="input-group-prepend">
                                <span class="input-group-text">Disc(Rp.)</span>
                            </div>
                            <input type="number" class="form-control" id="disc_rp" value="0" disabled>
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
                                <span class="input-group-text">PPN</span>
                            </div>
                            <input type="text" class="form-control text-right" id="ppn_amount" value="0" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <input type="checkbox" id="dp_checkbox">
                                </div>
                            </div>
                            <div class="input-group-prepend">
                                <span class="input-group-text">DP</span>
                            </div>
                            <input type="number" class="form-control" id="dp_amount" value="0" disabled>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Cara Bayar</label>
                        <select class="form-control" id="cara_bayar_akhir" disabled>
                            <option value="">-- Belum Dipilih --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Grand Total</label>
                        <input type="text" class="form-control text-right" id="grand_total" readonly value="0" style="font-size: 18px; font-weight: bold;">
                    </div>
                    <div class="form-group">
                        <label for="notes">Catatan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Masukkan catatan tambahan (opsional)"></textarea>
                    </div>
                    <div class="form-group text-right mt-4">
                        <button type="button" class="btn btn-success" id="saveTransaction">
                            <i class="fas fa-save"></i> Simpan Transaksi
                        </button>
                        <button type="button" class="btn btn-warning" id="buatPOBtn">
                            <i class="fas fa-file-alt"></i> Buat PO
                        </button>
                        <button type="button" class="btn btn-secondary" id="cancelTransaction">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Simplified Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border: 3px solid black;">
            <form id="addCustomerForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Customer Baru</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Kode customer akan dibuat otomatis
                    </div>
                    <div class="form-group">
                        <label for="nama">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama" name="nama" required>
                    </div>
                    <div class="form-group">
                        <label for="alamat">Alamat <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="2" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="hp">HP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="hp" name="hp" required>
                    </div>
                    <div class="form-group">
                        <label for="telepon">Telepon</label>
                        <input type="text" class="form-control" id="telepon" name="telepon">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Simpan Customer
                    </button>
                </div>
            </form>
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
                        <label>Harga <small class="text-muted">(per satuan kecil)</small></label>
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
                        <label>Ongkos Kuli</label>
                        <input type="number" class="form-control" id="edit_item_ongkos_kuli" step="0.01" min="0">
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


<!-- Modal Invoice -->
<div class="modal fade" id="invoiceModal" tabindex="-1" role="dialog" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalLabel">Invoice Transaksi</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="invoiceContent">
                    <h4>No Transaksi: <span id="invoiceNoTransaksi"></span></h4>
                    <p>Tanggal: <span id="invoiceTanggal"></span></p>
                    <p>Customer: <span id="invoiceCustomer"></span></p>
                    <p>Grand Total: <span id="invoiceGrandTotal"></span></p>
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="printInvoiceBtn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button type="button" class="btn btn-secondary" id="backToFormBtn">
                    <i class="fas fa-arrow-left"></i> Kembali
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')

<script>
    function showLoading() {
        $('#loadingOverlay').fadeIn(100);
    }
    function hideLoading() {
        $('#loadingOverlay').fadeOut(100);
    }
    $(document).ready(function() {
        // Initialize variables
        let items = [];
        let grandTotal = 0;

        // Auto-fill form jika ada data Sales Order
        @if(isset($salesOrder) && $salesOrder)
            // Set sales order data
            $('#sales_order_id').val('{{ $salesOrder->id }}');
            $('#sales_order_search').val('{{ $salesOrder->no_so }} - {{ $salesOrder->customer->nama }}');
            
            // Set customer data
            $('#kode_customer').val('{{ $salesOrder->customer->kode_customer }}');
            $('#customer').val('{{ $salesOrder->customer->kode_customer }} - {{ $salesOrder->customer->nama }}');
            $('#alamatCustomer').val('{{ $salesOrder->customer->alamat }}');
            $('#hpCustomer').val('{{ $salesOrder->customer->hp ?? "" }} / {{ $salesOrder->customer->telepon ?? "" }}');
            
            // Set salesman data
            $('#sales').val('{{ $salesOrder->salesman->kode_stok_owner }}');
            $('#kode_sales').val('{{ $salesOrder->salesman->kode_stok_owner }}');
            
            // Set payment method to kredit/tempo
            $('#metode_pembayaran').val('Non Tunai');
            $('#cara_bayar').html('<option value="Kredit">Kredit</option>').val('Kredit');
            $('#hariTempoGroup').show();
            $('#jatuhTempoGroup').show();
            // Fill tempo fields from Sales Order if available
            @if(!is_null($salesOrder->hari_tempo))
                $('#hari_tempo').val('{{ $salesOrder->hari_tempo }}');
            @endif
            @if(!is_null($salesOrder->tanggal_jatuh_tempo))
                $('#tanggal_jatuh_tempo').val('{{ optional($salesOrder->tanggal_jatuh_tempo)->format('Y-m-d') }}');
            @endif
            
            // Set cara bayar after a delay to ensure dropdown is loaded
            setTimeout(() => {
                $('#cara_bayar').val('{{ $salesOrder->cara_bayar }}').trigger('change');
            }, 500);
            
            // Set tanggal jadi if available
            @if($salesOrder->tanggal_estimasi)
                $('#tanggal_jadi').val('{{ $salesOrder->tanggal_estimasi }}');
            @endif
            
            // Load sales order items
            loadSalesOrderItems({{ $salesOrder->id }});
        @endif

        // Metode Pembayaran
        // Force kredit/tempo mode
        $('#metode_pembayaran').val('Non Tunai');
        $('#cara_bayar').html('<option value="Kredit">Kredit</option>').val('Kredit');
        function recalcJatuhTempoPenjualan(){
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
        $('#tanggal').on('change', recalcJatuhTempoPenjualan);
        $('#hari_tempo').on('input', recalcJatuhTempoPenjualan);

        $('#cara_bayar').on('change', function () {
            const selected = $(this).val();
            $('#cara_bayar_akhir')
                .html(`<option value="${selected}">${selected}</option>`)
                .val(selected);
        });

        // Search customers (show name only in dropdown)
        $('#customer').on('input', function () {
            const keyword = $(this).val();
            if (keyword.length > 0) {
                $.ajax({
                    url: "{{ route('api.customers.search') }}",
                    method: "GET",
                    data: { keyword },
                    success: function (data) {
                        let dropdown = '';
                        if (data.length > 0) {
                            data.forEach(customer => {
                                dropdown += `<a class="dropdown-item customer-item" 
                                    data-kode="${customer.kode_customer}" 
                                    data-name="${customer.nama}"
                                    data-alamat="${customer.alamat}"
                                    data-hp="${customer.hp}"
                                    data-telp="${customer.telepon}"
                                    data-limit-kredit="${customer.limit_kredit || 0}"
                                    data-hari-tempo="${customer.limit_hari_tempo || 0}">
                                ${customer.kode_customer} - ${customer.nama} - ${customer.alamat} - ${customer.hp}</a>`;
                            });
                        } else {
                            dropdown = '<a class="dropdown-item disabled">Tidak ada customer ditemukan</a>';
                        }
                        $('#customerDropdown').html(dropdown).show();
                    },
                    error: function () {
                        alert('Terjadi kesalahan saat mencari customer.');
                    }
                });
            } else {
                $('#customerDropdown').hide();
            }
        });

        // Select Customer
        $(document).on('click', '.customer-item', function () {
            const kodeCustomer = $(this).data('kode');
            const namaCustomer = $(this).data('name');
            const alamatCustomer = $(this).data('alamat');
            const hpCustomer = $(this).data('hp');
            const telpCustomer = $(this).data('telp');
            const limitKredit = parseFloat($(this).data('limit-kredit')) || 0;
            const hariTempo = parseInt($(this).data('hari-tempo') || '0', 10);
            $('#kode_customer').val(kodeCustomer); // Isi input hidden dengan kode customer
            $('#customer').val(`${kodeCustomer} - ${namaCustomer}`); // Tampilkan kode dan nama customer di input utama
            $('#alamatCustomer').val(alamatCustomer);
            $('#hpCustomer').val(`${hpCustomer} / ${telpCustomer}`);
            $('#customerDropdown').hide();

            // Auto apply credit terms when adding manual invoice
            if (limitKredit > 0) {
                $('#metode_pembayaran').val('Non Tunai');
                $('#cara_bayar').html('<option value="Kredit">Kredit</option>').val('Kredit');
                $('#hariTempoGroup').show();
                $('#jatuhTempoGroup').show();
                $('#hari_tempo').val(hariTempo);
                // recalc jatuh tempo
                const base = $('#tanggal').val();
                if (base) {
                    const d = new Date(base);
                    d.setDate(d.getDate()+hariTempo);
                    const yyyy = d.getFullYear();
                    const mm = String(d.getMonth()+1).padStart(2,'0');
                    const dd = String(d.getDate()).padStart(2,'0');
                    $('#tanggal_jatuh_tempo').val(`${yyyy}-${mm}-${dd}`);
                }
            } else {
                // If no credit limit, default to Tunai-style UI
                $('#cara_bayar').html('<option value="Tunai">Tunai</option>').val('Tunai');
                $('#hariTempoGroup').hide();
                $('#jatuhTempoGroup').hide();
                $('#hari_tempo').val(0);
                $('#tanggal_jatuh_tempo').val('');
            }
        });

        // Search Sales Order
        $('#sales_order_search').on('input', function () {
            const keyword = $(this).val();
            if (keyword.length > 0) {
                $.ajax({
                    url: "{{ route('api.sales-order.search') }}",
                    method: "GET",
                    data: { keyword },
                    success: function (data) {
                        let dropdown = '';
                        if (data.length > 0) {
                            data.forEach(so => {
                                dropdown += `<a class="dropdown-item sales-order-item" 
                                    data-id="${so.id}" 
                                    data-no-so="${so.no_so}"
                                    data-customer="${so.customer?.nama || ''}"
                                    data-salesman="${so.salesman?.keterangan || ''}"
                                    data-tanggal="${so.tanggal}"
                                    data-cara-bayar="${so.cara_bayar}"
                                    data-hari-tempo="${so.hari_tempo || ''}"
                                    data-tanggal-jatuh-tempo="${so.tanggal_jatuh_tempo || ''}"
                                    data-tanggal-estimasi="${so.tanggal_estimasi || ''}"
                                    data-subtotal="${so.subtotal}"
                                    data-grand-total="${so.grand_total}">
                                ${so.no_so} - ${so.customer?.nama || ''} - ${so.tanggal} - ${so.grand_total}</a>`;
                            });
                        } else {
                            dropdown = '<a class="dropdown-item disabled">Tidak ada Sales Order ditemukan</a>';
                        }
                        $('#salesOrderDropdown').html(dropdown).show();
                    },
                    error: function () {
                        alert('Terjadi kesalahan saat mencari Sales Order.');
                    }
                });
            } else {
                $('#salesOrderDropdown').hide();
            }
        });

        // Select Sales Order
        $(document).on('click', '.sales-order-item', function () {
            const soId = $(this).data('id');
            const noSo = $(this).data('no-so');
            const customer = $(this).data('customer');
            const salesman = $(this).data('salesman');
            const tanggal = $(this).data('tanggal');
            const caraBayar = $(this).data('cara-bayar');
            const hariTempo = $(this).data('hari-tempo');
            const tanggalJatuhTempo = $(this).data('tanggal-jatuh-tempo');
            const tanggalEstimasi = $(this).data('tanggal-estimasi');
            const subtotal = $(this).data('subtotal');
            const grandTotal = $(this).data('grand-total');

            // Set sales order ID
            $('#sales_order_id').val(soId);
            $('#sales_order_search').val(`${noSo} - ${customer}`);

            // Auto-fill customer if not already set
            if (!$('#kode_customer').val()) {
                // Trigger customer search and selection
                $('#customer').val(customer).trigger('input');
                // Note: You might need to adjust this based on your customer data structure
            }

            // Auto-fill salesman
            if (salesman) {
                $('#sales').val(salesman).trigger('input');
            }

            // Force kredit/tempo and fill tempo values
            $('#metode_pembayaran').val('Non Tunai');
            $('#cara_bayar').html('<option value="Kredit">Kredit</option>').val('Kredit');
            $('#hariTempoGroup').show();
            $('#jatuhTempoGroup').show();
            if (hariTempo !== undefined && hariTempo !== '') {
                $('#hari_tempo').val(hariTempo);
            }
            if (tanggalJatuhTempo) {
                $('#tanggal_jatuh_tempo').val(tanggalJatuhTempo);
            }

            // Auto-fill tanggal jadi if available
            if (tanggalEstimasi) {
                $('#tanggal_jadi').val(tanggalEstimasi);
            }

            // Load sales order items
            loadSalesOrderItems(soId);

            $('#salesOrderDropdown').hide();
        });

        // Load Sales Order Items
        function loadSalesOrderItems(soId) {
            $.ajax({
                url: `{{ url('api/sales-order') }}/${soId}/items`,
                method: "GET",
                success: function (data) {
                    // Debug logging
                    console.log('Sales Order Items Data:', data);
                    
                    // Clear existing items
                    items = [];
                    
                    // Add items from sales order
                    data.forEach(item => {
                        console.log('Processing item:', item);
                        console.log('KodeBarang relation:', item.kode_barang);
                        
                        // Determine satuan kecil and satuan besar based on Sales Order data
                        const unitDasar = item.kode_barang?.unit_dasar || 'LBR';
                        const satuanSO = item.satuan;
                        
                        // Satuan kecil selalu unit dasar (LBR)
                        // Satuan besar adalah unit turunan (JEGG) jika ada
                        const newItem = {
                            kodeBarang: item.kode_barang?.kode_barang || item.kode_barang_id || '',
                            namaBarang: item.kode_barang?.name || item.nama_barang || '',
                            keterangan: item.keterangan || '',
                            harga: parseFloat(item.harga),
                            qty: parseFloat(item.qty),
                            satuan: unitDasar, // Satuan kecil selalu unit dasar
                            satuanBesar: satuanSO !== unitDasar ? satuanSO : '', // Satuan besar jika berbeda dari unit dasar
                            total: parseFloat(item.total),
                            diskon: 0,
                            ongkosKuli: 0
                        };
                        
                        console.log('New item created:', newItem);
                        items.push(newItem);
                    });
                    
                    // Render items and calculate totals
                    renderItems();
                    calculateTotals();
                    
                    // Auto-select satuan in form if there's only one item
                    if (data.length === 1) {
                        const item = data[0];
                        const unitDasar = item.kode_barang?.unit_dasar || 'LBR';
                        const satuanSO = item.satuan;
                        
                        // Set satuan kecil (always unit dasar)
                        const satuanKecilSelect = $('.item-satuan-kecil');
                        satuanKecilSelect.val(unitDasar);
                        $('.item-satuan').val(unitDasar);
                        
                        // Set satuan besar (only if different from unit dasar)
                        const satuanBesarSelect = $('.item-satuan-besar');
                        if (satuanSO !== unitDasar) {
                            satuanBesarSelect.val(satuanSO);
                        } else {
                            satuanBesarSelect.val(''); // Clear if same as unit dasar
                        }
                        
                        console.log('Auto-selected units:', {
                            unitDasar: unitDasar,
                            satuanSO: satuanSO,
                            satuanKecil: unitDasar,
                            satuanBesar: satuanSO !== unitDasar ? satuanSO : ''
                        });
                    }
                    
                    // Show success message
                    alert('Sales Order berhasil dimuat! Data transaksi telah diisi otomatis.');
                },
                error: function (xhr) {
                    console.error('Error loading sales order items:', xhr.responseText);
                    alert('Gagal memuat item Sales Order.');
                }
            });
        }

        // Search Sales
        $('#sales').on('input', function () {
            const keyword = $(this).val();
            if (keyword.length > 0) {
                $.ajax({
                    url: "{{ route('api.sales.search') }}",
                    method: "GET",
                    data: { keyword },
                    success: function (data) {
                        let dropdown = '';
                        if (data.length > 0) {
                            data.forEach(sales => {
                                dropdown += `<a class="dropdown-item sales-item" data-kode="${sales.kode_stok_owner}" data-name="${sales.keterangan}">${sales.kode_stok_owner} - ${sales.keterangan}</a>`;
                            });
                        } else {
                            dropdown = '<a class="dropdown-item disabled">Tidak ada sales ditemukan</a>';
                        }
                        $('#salesDropdown').html(dropdown).show();
                    },
                    error: function () {
                        alert('Terjadi kesalahan saat mencari sales.');
                    }
                });
            } else {
                $('#salesDropdown').hide();
            }
        });

        // Select Sales
        $(document).on('click', '.sales-item', function () {
            const kodeSales = $(this).data('kode'); // Ambil kode sales
            const namaSales = $(this).data('name'); // Ambil nama sales
            $('#kode_sales').val(kodeSales); // Isi input hidden dengan kode sales
            $('#sales').val(`${kodeSales}`); // Tampilkan kode dan nama sales di input utama
            $('#salesDropdown').hide();
        });

        // ===== INTEGRASI SISTEM FAKTUR FIFO =====
        
        // Auto-populate harga dan ongkos kuli saat barang/satuan kecil dipilih
        $(document).on('change', '#kode_barang, #satuanKecil', function() {
            const kodeBarang = $('#kode_barang').val();
            const satuan = $('#satuanKecil').val();
            const customerId = $('#kode_customer').val();
            
            if (kodeBarang && satuan && customerId) {
                getHargaDanOngkos(kodeBarang, satuan, customerId);
            }
        });

        // Get harga dan ongkos kuli via AJAX
        function getHargaDanOngkos(kodeBarang, satuan, customerId) {
            // Cari kode_barang_id dari kode_barang
            $.ajax({
                url: "{{ route('kodeBarang.search') }}",
                method: "GET",
                data: { keyword: kodeBarang },
                success: function(data) {
                    if (data.length > 0) {
                        const kodeBarangData = data[0];
                        const kodeBarangId = kodeBarangData.id;
                        
                        // Sekarang panggil API getHargaDanOngkos
                        $.ajax({
                            url: "{{ route('api.transaksi.harga-ongkos') }}",
                            method: "GET",
                            data: {
                                customer_id: customerId,
                                kode_barang_id: kodeBarangId,
                                satuan: satuan
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Auto-populate harga dan ongkos kuli
                                    $('#harga').val(response.harga_jual);
                                    $('#ongkos_kuli').val(response.ongkos_kuli);
                                    
                                    // Update preview jika ada
                                    updateItemPreview();
                                }
                            },
                            error: function(xhr) {
                                console.log('Error getting harga dan ongkos kuli:', xhr.responseText);
                            }
                        });
                    }
                },
                error: function(xhr) {
                    console.log('Error searching kode barang:', xhr.responseText);
                }
            });
        }

        // Manual get ongkos kuli button
        $(document).on('click', '#getOngkosKuliBtn', function() {
            const kodeBarang = $('#kode_barang').val();
            const satuan = $('#satuanKecil').val();
            const customerId = $('#kode_customer').val();
            
            if (!kodeBarang || !satuan || !customerId) {
                alert('Pilih customer, kode barang, dan satuan terlebih dahulu!');
                return;
            }
            
            getHargaDanOngkos(kodeBarang, satuan, customerId);
        });

        // Update item preview
        function updateItemPreview() {
            const harga = parseInt($('#harga').val()) || 0;
            const qty = parseInt($('#quantity').val()) || 0;
            const diskon = parseInt($('#diskon').val()) || 0;
            const ongkosKuli = parseInt($('#ongkos_kuli').val()) || 0;
            
            const subtotal = harga * qty;
            const diskonAmount = (subtotal * diskon) / 100;
            const total = subtotal - diskonAmount;
            
            // Update preview table
            $('#itemPreview').html(`
                <tr>
                    <td>${$('#kode_barang').val() || '-'}</td>
                    <td>${$('#nama_barang').val() || '-'}</td>
                    <td class="text-right">${formatCurrency(harga)}</td>
                    <td>${$('#panjang').val() || 0}</td>
                    <td>${qty} ${$('#satuanKecil').val() || 'LBR'}</td>
                    <td class="text-right">${formatCurrency(total)}</td>
                    <td>${$('#satuanKecil').val() || 'LBR'}</td>
                    <td>${diskon}%</td>
                    <td class="text-right">${formatCurrency(diskonAmount)}</td>
                    <td class="text-right">${formatCurrency(total)}</td>
                </tr>
            `);
        }

        // Update preview saat input berubah
        $('#harga, #quantity, #diskon, #ongkos_kuli').on('input', function() {
            updateItemPreview();
        });

        // Hide dropdown when clicking outside
        $(document).click(function (e) {
            if (!$(e.target).closest('#customer, #customerDropdown').length) {
                $('#customerDropdown').hide();
            }
            if (!$(e.target).closest('#sales, #salesDropdown').length) {
                $('#salesDropdown').hide();
            }
        });


        $('#addCustomerForm').on('submit', function (e) {
            e.preventDefault();
            
            const formData = $(this).serialize();
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...').prop('disabled', true);
            
            $.ajax({
                url: "{{ route('customers.store') }}",
                method: "POST",
                data: formData,
                success: function (response) {
                    alert('Customer berhasil ditambahkan!');
                    
                    // Auto-populate customer field
                    const customer = response.customer;
                    $('#kode_customer').val(customer.kode_customer);
                    $('#customer').val(`${customer.kode_customer} - ${customer.nama}`);
                    $('#alamatCustomer').val(customer.alamat);
                    $('#hpCustomer').val(`${customer.hp}${customer.telepon ? ' / ' + customer.telepon : ''}`);
                    
                    $('#addCustomerModal').modal('hide');
                    $('#addCustomerForm')[0].reset();
                },
                error: function (xhr) {
                    alert('Terjadi kesalahan saat menyimpan customer.');
                },
                complete: function() {
                    submitBtn.html(originalText).prop('disabled', false);
                }
            });
        });



        // Toggle discount and DP inputs
        $('#discount_checkbox').change(function() {
            $('#discount_percent').prop('disabled', !this.checked);
            calculateTotals();
        });

        $('#disc_rp_checkbox').change(function() {
            $('#disc_rp').prop('disabled', !this.checked);
            calculateTotals();
        });

        $('#ppn_checkbox').change(function() {
            calculateTotals();
        });

        $('#dp_checkbox').change(function() {
            $('#dp_amount').prop('disabled', !this.checked);
            calculateTotals();
        });

        // Calculate input changes
        $('#discount_percent, #disc_rp, #dp_amount').on('input', function() {
            calculateTotals();
        });

        // Handle barang change (samakan seperti Sales Order)
        $(document).on('change', '.item-barang', function() {
            const row = $(this).closest('.item-row');
            const selectedOption = $(this).find('option:selected');
            const harga = selectedOption.data('harga') || 0;
            const unitDasarFromOption = selectedOption.data('unit-dasar') || 'LBR';
            
            row.find('.item-harga').val(harga);
            
            // Set small unit from selected item's unit_dasar and populate big units via available-units
            const kecilSelect = row.find('.item-satuan-kecil');
            const besarSelect = row.find('.item-satuan-besar');
            kecilSelect.empty();
            besarSelect.empty();
            kecilSelect.append('<option value="'+unitDasarFromOption+'">'+unitDasarFromOption+'</option>');
            besarSelect.append('<option value="">- (pakai satuan kecil)</option>');
            row.find('.item-satuan').val(unitDasarFromOption);
            row.data('unit-factors', { [unitDasarFromOption]: 1 });

            const kodeBarangId = $(this).val();
            if (kodeBarangId) {
                $.ajax({
                    url: `{{ route('sales-order.available-units', '') }}/${kodeBarangId}`,
                    method: 'GET',
                    success: function(res) {
                        const parsed = normalizeUnitsResponse(res);
                        row.data('unit-factors', parsed.factors);
                        (parsed.units || []).forEach(function(unit) {
                            if (unit !== unitDasarFromOption) {
                                const factor = parsed.factors[unit] || 1;
                                besarSelect.append(`<option value="${unit}" data-factor="${factor}">${unit} (1 = ${factor} ${unitDasarFromOption})</option>`);
                            }
                        });
                        calculateItemTotal(row);
                    },
                    error: function() {
                        calculateItemTotal(row);
                    }
                });
            } else {
                // reset to empty if no product
                row.find('.item-satuan-kecil').html('<option value=""></option>');
                row.find('.item-satuan-besar').html('');
                row.find('.item-satuan').val('');
                row.find('.item-qty-hint').hide().text('');
                calculateItemTotal(row);
            }
        });

        // Handle qty change
        $(document).on('input', '.item-qty', function() {
            calculateItemTotal($(this).closest('.item-row'));
        });

        // Handle harga change
        $(document).on('input', '.item-harga', function() {
            calculateItemTotal($(this).closest('.item-row'));
        });

        // Handle satuan kecil change (samakan dengan Sales Order: clear big selection)
        $(document).on('change', '.item-satuan-kecil', function() {
            const row = $(this).closest('.item-row');
            const unit = $(this).val();
            row.find('.item-satuan').val(unit);
            row.find('.item-satuan-besar').val('');
            calculateItemTotal(row);
        });

        // Handle satuan besar change — qty diisi dalam satuan besar, harga tetap per satuan kecil
        $(document).on('change', '.item-satuan-besar', function() {
            calculateItemTotal($(this).closest('.item-row'));
        });

        function normalizeUnitsResponse(res) {
            if (Array.isArray(res)) {
                const factors = {};
                res.forEach(u => { factors[u] = 1; });
                return { units: res, factors };
            }
            return {
                units: res.units || [],
                factors: res.factors || {}
            };
        }

        function getUnitFactor(row, unit) {
            if (!unit) return 1;
            const factors = row.data('unit-factors') || {};
            const factor = parseFloat(factors[unit]);
            return factor > 0 ? factor : 1;
        }

        // Calculate item total
        // Qty boleh dalam satuan besar; harga selalu per satuan kecil.
        // Total = qty_input * factor * harga_kecil
        function calculateItemTotal(row) {
            const qty = parseFloat(row.find('.item-qty').val()) || 0;
            const harga = parseFloat(row.find('.item-harga').val()) || 0;
            const satuanKecil = row.find('.item-satuan-kecil').val() || '';
            const satuanBesar = row.find('.item-satuan-besar').val() || '';
            const factor = satuanBesar ? getUnitFactor(row, satuanBesar) : 1;
            const qtyKecil = qty * factor;
            const total = qtyKecil * harga;
            row.find('.item-total').val(total);

            const hint = row.find('.item-qty-hint');
            if (satuanBesar && qty > 0 && factor > 1) {
                hint.text(`${qty} ${satuanBesar} = ${qtyKecil} ${satuanKecil || 'satuan kecil'} (harga × ${satuanKecil || 'kecil'})`).show();
            } else {
                hint.hide().text('');
            }
        }

        // Add Item Button
        $('#addItemBtn').click(function() {
            const row = $(this).closest('.item-row');
            const kodeBarangSelect = row.find('.item-barang');
            const selectedOption = kodeBarangSelect.find('option:selected');
            
            const kodeBarang = selectedOption.data('kode') || selectedOption.text().split(' - ')[0];
            const namaBarang = selectedOption.data('nama') || selectedOption.text().split(' - ')[1];
            const kodeBarangId = kodeBarangSelect.val();
            const keterangan = row.find('#keterangan').val();
            const harga = parseFloat(row.find('.item-harga').val()) || 0; // always per satuan kecil
            const qty = parseFloat(row.find('.item-qty').val()) || 0;
            const satuanKecil = row.find('.item-satuan-kecil').val();
            const satuanBesar = row.find('.item-satuan-besar').val();
            // If satuan besar dipilih, qty diinput dalam satuan besar; backend konversi ke kecil
            const satuan = satuanBesar ? satuanBesar : satuanKecil;
            const factor = satuanBesar ? getUnitFactor(row, satuanBesar) : 1;
            const diskon = parseFloat(row.find('#diskon').val()) || 0;
            const ongkosKuli = parseFloat(row.find('#ongkos_kuli').val()) || 0;

            if (!kodeBarangId || !kodeBarang || !namaBarang || harga === undefined || harga === null || !qty) {
                alert('Mohon lengkapi data barang!');
                return;
            }

            // Total pakai qty terkonversi ke satuan kecil × harga kecil
            const qtyKecil = qty * factor;
            const subtotal = harga * qtyKecil;
            const diskonAmount = (subtotal * diskon) / 100;
            const total = subtotal - diskonAmount;

            const newItem = {
                kodeBarang,
                kodeBarangId,
                namaBarang,
                keterangan,
                harga: harga, // per satuan kecil
                qty, // qty sesuai satuan yang dipilih (besar jika ada)
                qtyKecil,
                unitFactor: factor,
                satuanKecil,
                satuan,
                satuanBesar,
                diskon,
                ongkosKuli,
                total
            };

            items.push(newItem);
            renderItems();
            calculateTotals();

            // Reset form
            row.find('select, input').val('');
            row.find('.item-satuan-kecil').html('<option value=""></option>');
            row.find('.item-satuan-besar').empty();
            row.find('.item-satuan').val('');
        });


        // Function to render items table
        function renderItems() {
            const tbody = $('#itemsList');
            tbody.empty();

            items.forEach((item, index) => {
                tbody.append(`
                    <tr>
                        <td>${item.kodeBarang}</td>
                        <td>${item.namaBarang}</td>
                        <td>${item.keterangan || '-'}</td>
                        <td class="text-right">${formatCurrency(item.harga)}</td>
                        <td>${item.qty} ${item.satuanBesar || item.satuan || item.satuanKecil || ''}${item.satuanBesar && item.qtyKecil ? ` <small class="text-muted">(= ${item.qtyKecil} ${item.satuanKecil || ''})</small>` : ''}</td>
                        <td>${item.satuanBesar || '-'}</td>
                        <td class="text-right">${formatCurrency(item.total)}</td>
                        <td class="text-right">${formatCurrency(item.ongkosKuli || 0)}</td>
                        <td class="text-right">${item.diskon || 0}%</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary edit-item" data-index="${index}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger remove-item" data-index="${index}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });

            // Remove item handling
            $('.remove-item').click(function() {
                const index = $(this).data('index');
                items.splice(index, 1);
                renderItems();
                calculateTotals();
            });

            // Edit item handling
            $('.edit-item').click(function() {
                openEditItemModal($(this).data('index'));
            });

            $('#addItemModal').modal('hide');
        }

        // Populate & open the edit modal for a line item
        function openEditItemModal(index) {
            const item = items[index];
            if (!item) return;

            $('#edit_item_index').val(index);
            $('#edit_item_nama').val(item.namaBarang || '');
            $('#edit_item_qty').val(item.qty);
            $('#edit_item_harga').val(item.harga);
            $('#edit_item_satuan_kecil').val(item.satuanKecil || item.satuan || 'LBR');
            $('#edit_item_diskon').val(item.diskon || 0);
            $('#edit_item_ongkos_kuli').val(item.ongkosKuli || 0);
            $('#edit_item_keterangan').val(item.keterangan || '');

            const besarSelect = $('#edit_item_satuan_besar');
            besarSelect.empty();
            besarSelect.append('<option value="">- (pakai satuan kecil)</option>');

            const unitDasar = item.satuanKecil || item.satuan || 'LBR';
            const seedFactors = { [unitDasar]: 1 };
            if (item.satuanBesar && item.unitFactor) {
                seedFactors[item.satuanBesar] = item.unitFactor;
            }
            $('#editItemModal').data('unit-factors', seedFactors);

            if (item.kodeBarangId) {
                $.ajax({
                    url: `{{ route('sales-order.available-units', '') }}/${item.kodeBarangId}`,
                    method: 'GET',
                    success: function(units) {
                        const unitList = Array.isArray(units) ? units : (units.units || []);
                        const factors = Array.isArray(units) ? {} : (units.factors || {});
                        $('#editItemModal').data('unit-factors', Object.assign({ [unitDasar]: 1 }, factors));
                        unitList.forEach(function(u) {
                            if (u !== unitDasar) {
                                const factor = factors[u] || 1;
                                besarSelect.append(`<option value="${u}">${u} (1 = ${factor} ${unitDasar})</option>`);
                            }
                        });
                        besarSelect.val(item.satuanBesar || '');
                        updateEditItemQtyHint();
                    },
                    error: function() {
                        if (item.satuanBesar) {
                            besarSelect.append(`<option value="${item.satuanBesar}">${item.satuanBesar}</option>`);
                        }
                        besarSelect.val(item.satuanBesar || '');
                        updateEditItemQtyHint();
                    }
                });
            } else if (item.satuanBesar) {
                besarSelect.append(`<option value="${item.satuanBesar}">${item.satuanBesar}</option>`);
                besarSelect.val(item.satuanBesar);
            }

            $('#editItemModal').modal('show');
            updateEditItemQtyHint();
        }

        function updateEditItemQtyHint() {
            const qty = parseFloat($('#edit_item_qty').val()) || 0;
            const satuanKecil = $('#edit_item_satuan_kecil').val() || 'LBR';
            const satuanBesar = $('#edit_item_satuan_besar').val() || '';
            const factors = $('#editItemModal').data('unit-factors') || {};
            const factor = satuanBesar ? (parseFloat(factors[satuanBesar]) || 1) : 1;
            const hint = $('#edit_item_qty_hint');
            if (satuanBesar && qty > 0 && factor > 1) {
                hint.text(`${qty} ${satuanBesar} = ${qty * factor} ${satuanKecil} (harga × ${satuanKecil})`).show();
            } else {
                hint.hide().text('');
            }
        }

        $(document).on('input change', '#edit_item_qty, #edit_item_satuan_besar', updateEditItemQtyHint);

        // Save edited item
        $('#saveEditItemBtn').click(function() {
            const index = parseInt($('#edit_item_index').val(), 10);
            const item = items[index];
            if (!item) return;

            const qty = parseFloat($('#edit_item_qty').val()) || 0;
            const harga = parseFloat($('#edit_item_harga').val()) || 0;
            const diskon = parseFloat($('#edit_item_diskon').val()) || 0;
            const ongkosKuli = parseFloat($('#edit_item_ongkos_kuli').val()) || 0;
            const keterangan = $('#edit_item_keterangan').val();
            const satuanKecil = item.satuanKecil || item.satuan || 'LBR';
            const satuanBesar = $('#edit_item_satuan_besar').val();
            const factors = $('#editItemModal').data('unit-factors') || {};
            const factor = satuanBesar ? (parseFloat(factors[satuanBesar]) || item.unitFactor || 1) : 1;

            if (!qty) {
                alert('Mohon lengkapi qty dan harga!');
                return;
            }

            const qtyKecil = qty * factor;
            const subtotal = harga * qtyKecil;
            const total = subtotal - (subtotal * diskon) / 100;

            items[index] = Object.assign({}, item, {
                namaBarang: $('#edit_item_nama').val() || item.namaBarang,
                qty,
                qtyKecil,
                unitFactor: factor,
                harga,
                diskon,
                ongkosKuli,
                keterangan,
                satuanKecil,
                satuanBesar,
                satuan: satuanBesar ? satuanBesar : satuanKecil,
                total
            });

            $('#editItemModal').modal('hide');
            renderItems();
            calculateTotals();
        });

        // Calculate all totals
        function calculateTotals() {
            // Calculate subtotal
            const subtotal = items.reduce((sum, item) => sum + item.total, 0);
            $('#total').val(formatCurrency(subtotal));

            // Calculate discount
            let discountAmount = 0;
            if ($('#discount_checkbox').is(':checked')) {
                const discountPercent = parseFloat($('#discount_percent').val()) || 0;
                discountAmount = (subtotal * discountPercent) / 100;
            }
            $('#discount_amount').val(formatCurrency(discountAmount));

            // Calculate additional discount
            let discRp = 0;
            if ($('#disc_rp_checkbox').is(':checked')) {
                discRp = parseFloat($('#disc_rp').val()) || 0;
            }

            // Calculate PPN
            let ppnAmount = 0;
            if ($('#ppn_checkbox').is(':checked')) {
                ppnAmount = ((subtotal - discountAmount - discRp) * 0.11); // Using 11% for PPN
            }
            $('#ppn_amount').val(formatCurrency(ppnAmount));

            // Calculate DP
            let dpAmount = 0;
            if ($('#dp_checkbox').is(':checked')) {
                dpAmount = parseFloat($('#dp_amount').val()) || 0;
            }

            // Calculate grand total
            grandTotal = subtotal - discountAmount - discRp + ppnAmount - dpAmount;
            $('#grand_total').val(formatCurrency(grandTotal));
        }

        // Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID').format(amount);
        }

        // Save transaction
        $('#saveTransaction').click(function() {
    if (confirm('Apakah Anda Yakin ingin menyimpan?')){
        if (!$('#kode_customer').val()) {
            alert('Pilih customer dari daftar yang tersedia!');
            return;
        }

        if (items.length === 0) {
            alert('Tidak ada barang yang ditambahkan!');
            return;
        }

        const transactionData = {
            _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
            tanggal: $('#tanggal').val(),
            kode_customer: $('#kode_customer').val(),
            sales_order_id: $('#sales_order_id').val() || null,
            sales: $('#sales').val(),
            pembayaran: $('#metode_pembayaran').val(),
            cara_bayar: $('#cara_bayar').val() || $('#cara_bayar_akhir').val() || 'Tunai',
            hari_tempo: parseInt($('#hari_tempo').val()||'0',10),
            tanggal_jatuh_tempo: $('#tanggal_jatuh_tempo').val() || null,
            tanggal_jadi: $('#tanggal_jadi').val(),
            items: items,
            subtotal: $('#total').val().replace(/\./g, ''),
            discount: $('#discount_checkbox').is(':checked') ? parseFloat($('#discount_percent').val()) || 0 : 0,
            disc_rp: $('#disc_rp_checkbox').is(':checked') ? parseFloat($('#disc_rp').val()) || 0 : 0,
            ppn: $('#ppn_checkbox').is(':checked') ? parseFloat($('#ppn_amount').val().replace(/\./g, '')) || 0 : 0,
            dp: $('#dp_checkbox').is(':checked') ? parseFloat($('#dp_amount').val()) || 0 : 0,
            grand_total: grandTotal,
            notes: $('#notes').val()
        };

        showLoading();

        // Simpan transaksi ke backend
        $.ajax({
            url: "{{ route('transaksi.store') }}",
            method: "POST",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: transactionData,
            success: function(response) {
                hideLoading();
                // Tampilkan modal invoice
                $('#invoiceNoTransaksi').text(response.no_transaksi);
                $('#invoiceTanggal').text(response.tanggal);
                $('#invoiceCustomer').text(response.customer);
                $('#invoiceGrandTotal').text('Rp ' + new Intl.NumberFormat('id-ID').format(response.grand_total || 0));

                // Simpan ID transaksi untuk tombol Print
                const transactionId = response.id;

                // Tombol Print
                $('#printInvoiceBtn').off('click').on('click', function() {
                    // --- PERUBAHAN DI SINI ---
                    // Buka nota di tab baru dengan parameter auto_print=1
                    const printUrl = `{{ url('transaksi/lihatnota') }}/${transactionId}?auto_print=1`;
                    window.open(printUrl, '_blank');
                });

                $('#invoiceModal').modal('show');
            },
            error: function(xhr) {
                hideLoading();
                alert('Terjadi kesalahan: ' + xhr.responseJSON.message);
            }
        });

        // Tombol Kembali
        $('#backToFormBtn').off('click').on('click', function(){
            $('#invoiceModal').modal('hide');
            $('#transactionForm')[0].reset();
            items = [];
            renderItems();
            calculateTotals();
            window.location.href = "{{ route('transaksi.penjualan') }}";
        });

        // You would typically send this data to your backend using AJAX
        console.log('Transaction data:', transactionData);
    }
});

        // Button Buat PO
        $('#buatPOBtn').click(function(){
        if (confirm('Simpan sebagai PO (tidak mempengaruhi stok)?')) {
            if (!$('#kode_customer').val()) {
                alert('Pilih customer dari daftar yang tersedia!');
                return;
            }

            if (items.length === 0) {
                alert('Tidak ada barang yang ditambahkan!');
                return;
            }

            // Format items for PO
            const poItems = items.map(item => ({
                kodeBarang: item.kodeBarang,
                namaBarang: item.namaBarang,
                keterangan: item.keterangan || '',
                harga: item.harga,
                panjang: item.panjang || 0,
                qty: item.qty,
                total: item.total,
                diskon: item.diskon || 0
            }));

            // Create the PO data
            const formData = new FormData();
            
            // Add form fields
            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            formData.append('tanggal', $('#tanggal').val());
            formData.append('kode_customer', $('#kode_customer').val());
            formData.append('sales', $('#kode_sales').val());
            formData.append('pembayaran', $('#metode_pembayaran').val());
            formData.append('cara_bayar', $('#cara_bayar').val());
            formData.append('subtotal', parseFloat($('#total').val().replace(/\./g, '')) || 0);
            formData.append('discount', $('#discount_checkbox').is(':checked') ? parseFloat($('#discount_percent').val()) || 0 : 0);
            formData.append('disc_rupiah', $('#disc_rp_checkbox').is(':checked') ? parseFloat($('#disc_rp').val()) || 0 : 0);
            formData.append('ppn', $('#ppn_checkbox').is(':checked') ? parseFloat($('#ppn_amount').val().replace(/\./g, '')) || 0 : 0);
            formData.append('dp', $('#dp_checkbox').is(':checked') ? parseFloat($('#dp_amount').val()) || 0 : 0);
            formData.append('grand_total', grandTotal);
            
            // Add items as JSON string
            formData.append('items', JSON.stringify(poItems));
                showLoading();
                $.ajax({
                    url: "{{ route('purchase-order.store') }}", // Ubah ini ke route PO
                    method: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        hideLoading();
                        alert('PO berhasil disimpan!');
                        // Opsional: reset form
                        $('#transactionForm')[0].reset();
                        items = [];
                        renderItems();
                        calculateTotals();
                        window.location.href = "{{ route('transaksi.penjualan') }}";
                    },
                    error: function(xhr) {
                        hideLoading();
                        alert('Gagal menyimpan PO: ' + xhr.responseJSON.message);
                    }
                });
            }
        });

        // Cancel transaction
        $('#cancelTransaction').click(function() {
            if (confirm('Batalkan transaksi? Semua data akan hilang.')) {
                $('#transactionForm')[0].reset();
                items = [];
                renderItems();
                calculateTotals();
            }
        });
    });
</script>
@endsection

@extends('layout.Nav')

@section('content')
<style>
.invoice-amount.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}
#invoicesTable tbody tr.row-selected {
    background-color: #e8f4ff;
}
#invoicesTable tbody tr {
    cursor: pointer;
}
#invoicesTable input[type=checkbox] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
</style>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-plus mr-2"></i>Tambah Pembayaran Piutang
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('pembayaran-piutang.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left mr-1"></i>Kembali
                        </a>
                    </div>
                </div>
                <form id="pembayaranForm" method="POST" action="{{ route('pembayaran-piutang.store') }}">
                    @csrf
                    <div class="card-body">
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="no_pembayaran">No Pembayaran</label>
                                    <input type="text" class="form-control" id="no_pembayaran"
                                           value="{{ $noPembayaran ?? '' }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tanggal_bayar">Tanggal Pembayaran <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="tanggal_bayar" name="tanggal_bayar"
                                           value="{{ old('tanggal_bayar', date('Y-m-d')) }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="customer_id">Pilih Pelanggan <span class="text-danger">*</span></label>
                                    <select class="form-control" id="customer_id" name="customer_id" required>
                                        <option value="">Pilih Pelanggan</option>
                                        @foreach(($customers ?? []) as $c)
                                            <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                                                {{ $c->nama }} ({{ $c->kode_customer }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="metode_pembayaran">Metode Pembayaran <span class="text-danger">*</span></label>
                                    <select class="form-control" id="metode_pembayaran" name="metode_pembayaran" required>
                                        @foreach(['Transfer', 'Tunai', 'Cek', 'Giro'] as $metode)
                                            <option value="{{ $metode }}" {{ old('metode_pembayaran', 'Transfer') == $metode ? 'selected' : '' }}>{{ $metode }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cara_bayar">Cara Bayar <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="cara_bayar" name="cara_bayar"
                                           value="{{ old('cara_bayar', 'Bank Transfer') }}" required placeholder="Contoh: Bank BCA, Kas, dll">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="no_referensi">No Referensi</label>
                                    <input type="text" class="form-control" id="no_referensi" name="no_referensi"
                                           value="{{ old('no_referensi') }}" placeholder="No cek, no transfer, dll">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="keterangan">Keterangan</label>
                                    <textarea class="form-control" id="keterangan" name="keterangan"
                                              rows="2" placeholder="Keterangan tambahan...">{{ old('keterangan') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <h5><i class="fas fa-file-invoice mr-2"></i>Faktur Penjualan yang Akan Dibayar</h5>
                                <small class="text-muted d-block mb-2">Centang faktur yang dibayar. Jumlah otomatis terisi sisa piutang; ubah hanya jika bayar sebagian.</small>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="invoicesTable">
                                        <thead>
                                            <tr>
                                                <th width="5%" class="text-center">Pilih</th>
                                                <th width="13%">No Faktur</th>
                                                <th width="9%">Tanggal</th>
                                                <th width="9%">Jatuh Tempo</th>
                                                <th width="12%">Total Faktur</th>
                                                <th width="11%">Sudah Dibayar</th>
                                                <th width="12%">Sisa Piutang</th>
                                                <th width="15%">Jumlah Dilunasi</th>
                                                <th width="9%">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="invoicesTableBody">
                                            <tr>
                                                <td colspan="9" class="text-center text-muted">
                                                    Pilih pelanggan terlebih dahulu untuk melihat faktur
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Ringkasan Pembayaran</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check mb-3">
                                            <input type="checkbox" class="form-check-input" id="lunasiSemua">
                                            <label class="form-check-label" for="lunasiSemua">
                                                <strong>Lunasi Semua Piutang</strong>
                                            </label>
                                        </div>
                                        <div class="row">
                                            <div class="col-6"><strong>Faktur Dipilih:</strong></div>
                                            <div class="col-6 text-right"><span id="jumlahFaktur">0</span></div>
                                        </div>
                                        <div class="row">
                                            <div class="col-6"><strong>Total Piutang Pelanggan:</strong></div>
                                            <div class="col-6 text-right"><span id="totalPiutang">Rp 0</span></div>
                                        </div>
                                        <hr>
                                        <div class="row">
                                            <div class="col-6"><strong>Total Pembayaran:</strong></div>
                                            <div class="col-6 text-right"><strong><span id="totalPembayaran">Rp 0</span></strong></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="total_bayar">Total yang Dibayar <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="total_bayar" name="total_bayar"
                                               value="{{ old('total_bayar', 0) }}" step="any" min="0" required>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-info" id="btnBagiFifo" title="Bagi nominal ini ke faktur terlama dulu">
                                                Bagi Otomatis (FIFO)
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted">Terisi otomatis dari faktur yang dicentang. Jika uang yang diterima tidak pas, ketik nominalnya lalu klik "Bagi Otomatis".</small>
                                </div>
                            </div>
                        </div>

                        <div id="paymentDetailsContainer"></div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i>Simpan Pembayaran
                        </button>
                        <a href="{{ route('pembayaran-piutang.index') }}" class="btn btn-secondary">
                            <i class="fas fa-times mr-1"></i>Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const urlInvoices = "{{ route('api.pembayaran-piutang.customer-invoices') }}";
    const tbody = document.getElementById('invoicesTableBody');
    const totalBayarInput = document.getElementById('total_bayar');
    const lunasiSemua = document.getElementById('lunasiSemua');
    const form = document.getElementById('pembayaranForm');

    function formatNumber(n) {
        return new Intl.NumberFormat('id-ID').format(n || 0);
    }

    function setEmptyRow(text, cls) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center ${cls || 'text-muted'}">${text}</td></tr>`;
    }

    function rows() {
        return Array.from(tbody.querySelectorAll('tr[data-id]'));
    }

    function loadInvoices(customerId) {
        document.getElementById('totalPiutang').textContent = 'Rp 0';
        lunasiSemua.checked = false;
        lunasiSemua.indeterminate = false;

        if (!customerId) {
            setEmptyRow('Pilih pelanggan terlebih dahulu untuk melihat faktur');
            updateSummary();
            return;
        }

        setEmptyRow('Memuat faktur...');
        fetch(`${urlInvoices}?customer_id=${customerId}`, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    setEmptyRow(res.message || 'Gagal memuat faktur', 'text-danger');
                    return;
                }
                const invoices = res.invoices || [];
                document.getElementById('totalPiutang').textContent = `Rp ${formatNumber(res.summary?.total_piutang)}`;

                if (invoices.length === 0) {
                    setEmptyRow('Tidak ada faktur yang belum dibayar');
                    updateSummary();
                    return;
                }

                tbody.innerHTML = '';
                invoices.forEach(inv => {
                    const sisa = parseFloat(inv.sisa_tagihan) || 0;
                    const status = inv.status_piutang || 'belum_dibayar';
                    const tr = document.createElement('tr');
                    tr.dataset.id = inv.id;
                    tr.dataset.sisa = sisa;
                    tr.innerHTML = `
                        <td class="text-center"><input type="checkbox" class="invoice-checkbox"></td>
                        <td>${inv.no_transaksi}</td>
                        <td>${inv.tanggal}</td>
                        <td class="${inv.is_jatuh_tempo ? 'text-danger font-weight-bold' : ''}">${inv.tanggal_jatuh_tempo || '-'}</td>
                        <td class="text-right">Rp ${formatNumber(inv.total_faktur)}</td>
                        <td class="text-right">Rp ${formatNumber(inv.sudah_dibayar)}</td>
                        <td class="text-right">Rp ${formatNumber(sisa)}</td>
                        <td>
                            <input type="number" class="form-control form-control-sm invoice-amount"
                                   value="${sisa}" min="0" max="${sisa}" step="any" disabled>
                        </td>
                        <td>
                            <span class="badge badge-${status === 'sebagian' ? 'info' : 'warning'}">${status}</span>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
                updateSummary();
            })
            .catch(() => setEmptyRow('Gagal memuat faktur', 'text-danger'));
    }

    function setRowChecked(tr, checked, amount) {
        const chk = tr.querySelector('.invoice-checkbox');
        const inp = tr.querySelector('.invoice-amount');
        const sisa = parseFloat(tr.dataset.sisa) || 0;
        chk.checked = checked;
        inp.disabled = !checked;
        inp.value = checked && amount !== undefined ? amount : sisa;
        inp.classList.remove('is-invalid');
        tr.classList.toggle('row-selected', checked);
    }

    function selectedTotal() {
        return rows().reduce((sum, tr) => {
            if (!tr.querySelector('.invoice-checkbox').checked) return sum;
            return sum + (parseFloat(tr.querySelector('.invoice-amount').value) || 0);
        }, 0);
    }

    function updateSummary() {
        const all = rows();
        const checked = all.filter(tr => tr.querySelector('.invoice-checkbox').checked);
        const total = selectedTotal();

        document.getElementById('jumlahFaktur').textContent = checked.length;
        document.getElementById('totalPembayaran').textContent = `Rp ${formatNumber(total)}`;
        totalBayarInput.value = total;

        lunasiSemua.checked = all.length > 0 && checked.length === all.length;
        lunasiSemua.indeterminate = checked.length > 0 && checked.length < all.length;
    }

    document.getElementById('customer_id').addEventListener('change', function () {
        loadInvoices(this.value);
    });

    // Click anywhere on the row (except the amount box) to toggle it
    tbody.addEventListener('click', function (e) {
        const tr = e.target.closest('tr[data-id]');
        if (!tr || e.target.classList.contains('invoice-amount')) return;
        const chk = tr.querySelector('.invoice-checkbox');
        const checked = e.target === chk ? chk.checked : !chk.checked;
        setRowChecked(tr, checked);
        updateSummary();
    });

    tbody.addEventListener('input', function (e) {
        if (!e.target.classList.contains('invoice-amount')) return;
        const max = parseFloat(e.target.max) || 0;
        const val = parseFloat(e.target.value) || 0;
        e.target.classList.toggle('is-invalid', val < 0 || val > max);
        updateSummary();
    });

    tbody.addEventListener('change', function (e) {
        if (!e.target.classList.contains('invoice-amount')) return;
        const max = parseFloat(e.target.max) || 0;
        const val = Math.max(0, Math.min(parseFloat(e.target.value) || 0, max));
        e.target.value = val;
        e.target.classList.remove('is-invalid');
        updateSummary();
    });

    lunasiSemua.addEventListener('change', function () {
        rows().forEach(tr => setRowChecked(tr, lunasiSemua.checked));
        updateSummary();
    });

    // Spread a typed amount over the oldest invoices first
    document.getElementById('btnBagiFifo').addEventListener('click', function () {
        let remaining = parseFloat(totalBayarInput.value) || 0;
        if (rows().length === 0) { alert('Pilih pelanggan dahulu'); return; }
        if (remaining <= 0) { alert('Isi Total yang Dibayar dahulu'); return; }

        rows().forEach(tr => {
            const sisa = parseFloat(tr.dataset.sisa) || 0;
            if (remaining > 0) {
                const alloc = Math.min(sisa, remaining);
                setRowChecked(tr, true, alloc);
                remaining -= alloc;
            } else {
                setRowChecked(tr, false);
            }
        });
        const typed = parseFloat(totalBayarInput.value) || 0;
        updateSummary();
        if (remaining > 0) {
            totalBayarInput.value = typed;
            alert(`Nominal melebihi total piutang. Kelebihan Rp ${formatNumber(remaining)} tidak teralokasi.`);
        }
    });

    form.addEventListener('submit', function (e) {
        const container = document.getElementById('paymentDetailsContainer');
        container.innerHTML = '';
        let idx = 0;
        let sum = 0;

        rows().forEach(tr => {
            if (!tr.querySelector('.invoice-checkbox').checked) return;
            const bayar = parseFloat(tr.querySelector('.invoice-amount').value) || 0;
            if (bayar <= 0) return;
            container.insertAdjacentHTML('beforeend',
                `<input type="hidden" name="payment_details[${idx}][transaksi_id]" value="${tr.dataset.id}">` +
                `<input type="hidden" name="payment_details[${idx}][jumlah_dilunasi]" value="${bayar}">`);
            sum += bayar;
            idx++;
        });

        const totalBayar = parseFloat(totalBayarInput.value) || 0;
        if (idx === 0) { e.preventDefault(); alert('Centang minimal satu faktur.'); return; }
        if (sum > totalBayar + 0.001) { e.preventDefault(); alert('Total yang Dibayar lebih kecil dari jumlah faktur yang dicentang.'); return; }

        const btn = form.querySelector('button[type=submit]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan...'; }
    });

    const preselected = document.getElementById('customer_id').value;
    if (preselected) loadInvoices(preselected);
})();
</script>
@endsection

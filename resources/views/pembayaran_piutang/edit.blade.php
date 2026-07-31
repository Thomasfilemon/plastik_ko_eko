@extends('layout.Nav')

@section('content')
<div class="card">
	<div class="card-header">Edit Pembayaran Piutang — {{ $pembayaran->no_pembayaran }}</div>
	<div class="card-body">
		@if(session('error'))
			<div class="alert alert-danger">{{ session('error') }}</div>
		@endif
		@if ($errors->any())
			<div class="alert alert-danger">
				<ul class="mb-0">
					@foreach ($errors->all() as $error)
						<li>{{ $error }}</li>
					@endforeach
				</ul>
			</div>
		@endif
		<form id="pembayaranForm" method="post" action="{{ route('pembayaran-piutang.update', $pembayaran->id) }}">
			@csrf
			@method('PUT')
			<div class="form-row">
				<div class="form-group col-md-4">
					<label>Pelanggan</label>
					<select name="customer_id" class="form-control" required disabled>
						@foreach(($customers ?? []) as $c)
							<option value="{{ $c->id }}" {{ $pembayaran->customer_id == $c->id ? 'selected' : '' }}>{{ $c->nama }}</option>
						@endforeach
					</select>
					<input type="hidden" id="customer_id_hidden" value="{{ $pembayaran->customer_id }}">
				</div>
				<div class="form-group col-md-3">
					<label>Tanggal Bayar</label>
					<input type="date" name="tanggal_bayar" class="form-control" value="{{ optional($pembayaran->tanggal_bayar)->format('Y-m-d') }}" required>
				</div>
				<div class="form-group col-md-3">
					<label>Total Bayar</label>
					<input type="number" name="total_bayar" id="total_bayar" class="form-control" min="0" step="100" value="{{ $pembayaran->total_bayar }}" required>
				</div>
			</div>
			<div class="form-row">
				<div class="form-group col-md-3">
					<label>Metode Pembayaran</label>
					<input type="text" name="metode_pembayaran" class="form-control" value="{{ $pembayaran->metode_pembayaran }}" required>
				</div>
				<div class="form-group col-md-3">
					<label>Cara Bayar</label>
					<input type="text" name="cara_bayar" class="form-control" value="{{ $pembayaran->cara_bayar }}" required>
				</div>
				<div class="form-group col-md-3">
					<label>No Referensi</label>
					<input type="text" name="no_referensi" class="form-control" value="{{ $pembayaran->no_referensi }}">
				</div>
			</div>

			<div class="form-group">
				<label>Keterangan</label>
				<textarea name="keterangan" class="form-control" rows="2">{{ $pembayaran->keterangan }}</textarea>
			</div>

			<hr>
			<h6>Alokasi ke Faktur</h6>
			<div class="mb-2">
				<button type="button" id="btnMuatFaktur" class="btn btn-sm btn-secondary">Muat / Refresh Faktur</button>
				<button type="button" id="btnSuggest" class="btn btn-sm btn-info">Sugesti Otomatis (FIFO)</button>
			</div>

			<div class="table-responsive">
				<table class="table table-bordered table-sm" id="tabelFaktur">
					<thead>
						<tr>
							<th>Pilih</th>
							<th>No Faktur</th>
							<th>Tanggal</th>
							<th>Total</th>
							<th>Sudah Dibayar</th>
							<th>Sisa</th>
							<th>Bayar</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>

			<div id="paymentDetailsContainer"></div>

			<button type="submit" class="btn btn-primary">Simpan Perubahan</button>
			<a href="{{ route('pembayaran-piutang.show', $pembayaran->id) }}" class="btn btn-secondary">Batal</a>
		</form>
	</div>
</div>
@endsection

@section('scripts')
<script>
(function(){
    const urlInvoices = "{{ route('api.pembayaran-piutang.customer-invoices') }}";
    const urlSuggest = "{{ route('api.pembayaran-piutang.payment-suggestion') }}";
    const pembayaranId = {{ $pembayaran->id }};
    const existingDetails = @json($pembayaran->details->map(fn($d) => [
        'transaksi_id' => $d->transaksi_id,
        'jumlah_dilunasi' => (float) $d->jumlah_dilunasi,
    ]));

    function formatNumber(x){
        return new Intl.NumberFormat('id-ID').format(x||0);
    }

    function getCustomerId(){
        return document.getElementById('customer_id_hidden').value;
    }

    function loadInvoices(){
        const customerId = getCustomerId();
        if(!customerId){ alert('Pelanggan tidak ditemukan'); return; }
        fetch(urlInvoices+`?customer_id=${customerId}&exclude_pembayaran_id=${pembayaranId}`)
            .then(r=>r.json())
            .then(res=>{
                const tbody = document.querySelector('#tabelFaktur tbody');
                tbody.innerHTML = '';
                if(!res.success){ alert(res.message||'Gagal memuat faktur'); return; }
                (res.invoices||[]).forEach(inv=>{
                    const existing = existingDetails.find(d => String(d.transaksi_id) === String(inv.id));
                    const bayar = existing ? existing.jumlah_dilunasi : (inv.jumlah_dilunasi_saat_ini || 0);
                    const tr = document.createElement('tr');
                    tr.dataset.id = inv.id;
                    tr.innerHTML = `
                        <td><input type="checkbox" class="chk-include" ${bayar > 0 ? 'checked' : ''}></td>
                        <td>${inv.no_transaksi}</td>
                        <td>${inv.tanggal}</td>
                        <td class="text-right">${formatNumber(inv.total_faktur)}</td>
                        <td class="text-right">${formatNumber(inv.sudah_dibayar)}</td>
                        <td class="text-right sisa">${formatNumber(inv.sisa_tagihan)}</td>
                        <td><input type="number" class="form-control form-control-sm input-bayar" min="0" step="100" value="${bayar}"></td>
                    `;
                    tbody.appendChild(tr);
                });
            })
            .catch(()=>alert('Gagal memuat faktur'));
    }

    function suggestAlloc(){
        const customerId = getCustomerId();
        const totalBayar = parseFloat(document.getElementById('total_bayar').value||0);
        if(!customerId){ alert('Pelanggan tidak ditemukan'); return; }
        if(!totalBayar||totalBayar<=0){ alert('Isi total bayar dahulu'); return; }
        fetch(urlSuggest+`?customer_id=${customerId}&total_bayar=${totalBayar}`)
            .then(r=>r.json())
            .then(res=>{
                if(!res.success){ alert(res.message||'Gagal mengambil sugesti'); return; }
                const map = new Map((res.suggestions||[]).map(s=>[String(s.transaksi_id), s.suggested_payment]));
                document.querySelectorAll('#tabelFaktur tbody tr').forEach(tr=>{
                    const id = tr.dataset.id;
                    const inp = tr.querySelector('.input-bayar');
                    const chk = tr.querySelector('.chk-include');
                    const val = map.get(id) || 0;
                    if(inp) inp.value = val;
                    if(chk) chk.checked = val > 0;
                });
            })
            .catch(()=>alert('Gagal mengambil sugesti'));
    }

    function buildPaymentDetails(e){
        const container = document.getElementById('paymentDetailsContainer');
        container.innerHTML = '';
        let idx = 0;
        let sum = 0;
        document.querySelectorAll('#tabelFaktur tbody tr').forEach(tr=>{
            const chk = tr.querySelector('.chk-include');
            const inpBayar = tr.querySelector('.input-bayar');
            const bayar = parseFloat(inpBayar && inpBayar.value ? inpBayar.value : 0);
            if(chk && chk.checked && bayar>0){
                const transaksiId = tr.dataset.id;
                const i1 = document.createElement('input');
                i1.type='hidden'; i1.name=`payment_details[${idx}][transaksi_id]`; i1.value=transaksiId;
                const i2 = document.createElement('input');
                i2.type='hidden'; i2.name=`payment_details[${idx}][jumlah_dilunasi]`; i2.value=bayar;
                container.appendChild(i1); container.appendChild(i2);
                sum += bayar; idx++;
            }
        });
        const totalBayar = parseFloat(document.getElementById('total_bayar').value||0);
        if(idx===0){ e.preventDefault(); alert('Pilih minimal satu faktur dan isi jumlah bayar.'); return; }
        if(sum>totalBayar){ e.preventDefault(); alert('Total alokasi melebihi Total Bayar.'); return; }
    }

    const btnMuat = document.getElementById('btnMuatFaktur');
    const btnSugg = document.getElementById('btnSuggest');
    if(btnMuat) btnMuat.addEventListener('click', loadInvoices);
    if(btnSugg) btnSugg.addEventListener('click', suggestAlloc);
    const form = document.getElementById('pembayaranForm');
    if(form) form.addEventListener('submit', function(e){ buildPaymentDetails(e); });

    // Auto-load current allocations on open
    loadInvoices();
})();
</script>
@endsection

@extends('layout.Nav')

@section('content')
<div class="card">
	<div class="card-header">Detail Pembayaran Piutang</div>
	<div class="card-body">
		@if(session('success'))
			<div class="alert alert-success">{{ session('success') }}</div>
		@endif
		@if(session('error'))
			<div class="alert alert-danger">{{ session('error') }}</div>
		@endif
		@if(isset($pembayaran))
			<div class="mb-3">
				<strong>No Pembayaran:</strong> {{ $pembayaran->no_pembayaran }}<br>
				<strong>Tanggal:</strong> {{ optional($pembayaran->tanggal_bayar)->format('d/m/Y') }}<br>
				<strong>Customer:</strong> {{ optional($pembayaran->customer)->nama ?? '-' }}<br>
				<strong>Total Bayar:</strong> Rp {{ number_format($pembayaran->total_bayar, 0, ',', '.') }}<br>
				<strong>Status:</strong> {{ $pembayaran->status }}
			</div>

			<h6>Rincian Pelunasan</h6>
			<div class="table-responsive">
				<table class="table table-bordered table-sm">
					<thead>
						<tr>
							<th>No Faktur</th>
							<th>Total</th>
							<th>Sudah Dibayar (sebelum)</th>
							<th>Dibayar</th>
							<th>Sisa</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
						@forelse(($pembayaran->details ?? []) as $d)
							<tr>
								<td>{{ $d->no_transaksi }}</td>
								<td class="text-right">{{ number_format($d->total_faktur, 0, ',', '.') }}</td>
								<td class="text-right">{{ number_format($d->sudah_dibayar, 0, ',', '.') }}</td>
								<td class="text-right">{{ number_format($d->jumlah_dilunasi, 0, ',', '.') }}</td>
								<td class="text-right">{{ number_format($d->sisa_tagihan, 0, ',', '.') }}</td>
								<td>{{ $d->status_pelunasan }}</td>
							</tr>
						@empty
							<tr><td colspan="6" class="text-center">Tidak ada detail</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		@endif

		<a href="{{ route('pembayaran-piutang.index') }}" class="btn btn-secondary mt-2">Kembali</a>
		@if(isset($pembayaran) && $pembayaran->status !== 'cancelled')
			@can('edit pembayaran piutang')
			<a href="{{ route('pembayaran-piutang.edit', $pembayaran->id) }}" class="btn btn-warning mt-2">Edit</a>
			<button type="button" class="btn btn-danger mt-2 btn-cancel-pembayaran" data-id="{{ $pembayaran->id }}" data-no="{{ $pembayaran->no_pembayaran }}">Batalkan Pembayaran</button>
			@endcan
		@endif
	</div>
</div>
@endsection

@section('scripts')
<script>
document.querySelectorAll('.btn-cancel-pembayaran').forEach(btn => {
	btn.addEventListener('click', function() {
		const id = this.dataset.id;
		const no = this.dataset.no;
		if (!confirm('Batalkan pembayaran ' + no + '? Piutang faktur akan dikembalikan.')) return;
		fetch(`{{ url('pembayaran-piutang') }}/${id}/cancel`, {
			method: 'POST',
			headers: {
				'X-CSRF-TOKEN': '{{ csrf_token() }}',
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			}
		})
		.then(r => r.json())
		.then(res => {
			alert(res.message || (res.success ? 'Berhasil' : 'Gagal'));
			if (res.success) location.href = '{{ route('pembayaran-piutang.index') }}';
		})
		.catch(() => alert('Gagal membatalkan pembayaran'));
	});
});
</script>
@endsection

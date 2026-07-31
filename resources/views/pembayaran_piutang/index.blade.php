@extends('layout.Nav')

@section('content')
<div class="card">
	<div class="card-header d-flex justify-content-between align-items-center">
		<span>Daftar Pembayaran Piutang</span>
		@can('create pembayaran piutang')
		<a href="{{ route('pembayaran-piutang.create') }}" class="btn btn-sm btn-primary">
			<i class="fas fa-circle-plus mr-1"></i> Tambah Pembayaran
		</a>
		@endcan
	</div>
	<div class="card-body">
		@if(isset($summary))
			<div class="mb-3">
				<strong>Pembayaran Hari Ini:</strong> Rp {{ number_format($summary['total_pembayaran_hari_ini'] ?? 0, 0, ',', '.') }} |
				<strong>Pembayaran Bulan Ini:</strong> Rp {{ number_format($summary['total_pembayaran_bulan_ini'] ?? 0, 0, ',', '.') }} |
				<strong>Total Piutang Tertagih:</strong> Rp {{ number_format($summary['total_piutang_tertagih'] ?? 0, 0, ',', '.') }} |
				<strong>Total Piutang Jatuh Tempo:</strong> Rp {{ number_format($summary['total_piutang_jatuh_tempo'] ?? 0, 0, ',', '.') }}
			</div>
		@endif

		<div class="table-responsive">
			<table class="table table-bordered table-sm">
				<thead>
					<tr>
						<th>No Pembayaran</th>
						<th>Tanggal</th>
						<th>Customer</th>
						<th>Total Bayar</th>
						<th>Status</th>
						<th>Aksi</th>
					</tr>
				</thead>
				<tbody>
					@forelse(($pembayarans ?? []) as $p)
						<tr>
							<td>{{ $p->no_pembayaran }}</td>
							<td>{{ optional($p->tanggal_bayar)->format('d/m/Y') }}</td>
							<td>{{ optional($p->customer)->nama ?? '-' }}</td>
							<td class="text-right">{{ number_format($p->total_bayar, 0, ',', '.') }}</td>
							<td>{{ $p->status }}</td>
							<td>
								<a class="btn btn-sm btn-secondary" href="{{ route('pembayaran-piutang.show', $p->id) }}">Detail</a>
								@if($p->status !== 'cancelled')
									@can('edit pembayaran piutang')
									<a class="btn btn-sm btn-warning" href="{{ route('pembayaran-piutang.edit', $p->id) }}">Edit</a>
									<button type="button" class="btn btn-sm btn-danger btn-cancel-pembayaran" data-id="{{ $p->id }}" data-no="{{ $p->no_pembayaran }}">Batal</button>
									@endcan
								@endif
							</td>
						</tr>
					@empty
						<tr><td colspan="6" class="text-center">Belum ada data pembayaran</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>

		@if(method_exists(($pembayarans ?? null), 'links'))
			<div class="mt-2">{{ $pembayarans->links() }}</div>
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
			if (res.success) location.reload();
		})
		.catch(() => alert('Gagal membatalkan pembayaran'));
	});
});
</script>
@endsection


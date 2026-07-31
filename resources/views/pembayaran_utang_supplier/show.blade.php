@extends('layout.Nav')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-eye mr-2"></i>Detail Pembayaran Utang ke Supplier
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('pembayaran-utang-supplier.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left mr-1"></i>Kembali
                        </a>
                        @if($pembayaranUtangSupplier->status === 'pending')
                            @can('edit pembayaran utang supplier')
                            <a href="{{ route('pembayaran-utang-supplier.edit', $pembayaranUtangSupplier) }}" 
                               class="btn btn-warning btn-sm">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </a>
                            @endcan
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <!-- Header Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="40%"><strong>No Pembayaran:</strong></td>
                                    <td>{{ $pembayaranUtangSupplier->no_pembayaran }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Tanggal Pembayaran:</strong></td>
                                    <td>{{ $pembayaranUtangSupplier->tanggal_bayar->format('d/m/Y') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Supplier:</strong></td>
                                    <td>{{ $pembayaranUtangSupplier->supplier->nama ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Kode Supplier:</strong></td>
                                    <td>{{ $pembayaranUtangSupplier->supplier->kode_supplier ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="40%"><strong>Metode Pembayaran:</strong></td>
                                    <td>{{ $pembayaranUtangSupplier->metode_pembayaran }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Cara Bayar:</strong></td>
                                    <td>{{ $pembayaranUtangSupplier->cara_bayar }}</td>
                                </tr>
                                <tr>
                                    <td><strong>No Referensi:</strong></td>
                                    <td>{{ $pembayaranUtangSupplier->no_referensi ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        @if($pembayaranUtangSupplier->status === 'pending')
                                            <span class="badge badge-warning">Pending</span>
                                        @elseif($pembayaranUtangSupplier->status === 'confirmed')
                                            <span class="badge badge-success">Confirmed</span>
                                        @else
                                            <span class="badge badge-danger">Cancelled</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3>Rp {{ number_format($pembayaranUtangSupplier->total_bayar, 0, ',', '.') }}</h3>
                                    <p>Total Pembayaran</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3>Rp {{ number_format($pembayaranUtangSupplier->total_nota_debit, 0, ',', '.') }}</h3>
                                    <p>Total Nota Debit</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-receipt"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3>Rp {{ number_format($pembayaranUtangSupplier->total_utang, 0, ',', '.') }}</h3>
                                    <p>Total Utang</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3>Rp {{ number_format($pembayaranUtangSupplier->sisa_utang, 0, ',', '.') }}</h3>
                                    <p>Sisa Utang</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($pembayaranUtangSupplier->keterangan)
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Keterangan</h6>
                                </div>
                                <div class="card-body">
                                    {{ $pembayaranUtangSupplier->keterangan }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Payment Details -->
                    <div class="row">
                        <div class="col-12">
                            <h5><i class="fas fa-file-invoice mr-2"></i>Detail Faktur yang Dibayar</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>No Pembelian</th>
                                            <th>Tanggal</th>
                                            <th>Total Faktur</th>
                                            <th>Sudah Dibayar</th>
                                            <th>Jumlah Dilunasi</th>
                                            <th>Sisa Tagihan</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pembayaranUtangSupplier->details as $detail)
                                        <tr>
                                            <td>{{ $detail->no_pembelian }}</td>
                                            <td>{{ $detail->pembelian->tanggal->format('d/m/Y') }}</td>
                                            <td class="text-right">Rp {{ number_format($detail->total_faktur, 0, ',', '.') }}</td>
                                            <td class="text-right">Rp {{ number_format($detail->sudah_dibayar, 0, ',', '.') }}</td>
                                            <td class="text-right">Rp {{ number_format($detail->jumlah_dilunasi, 0, ',', '.') }}</td>
                                            <td class="text-right">Rp {{ number_format($detail->sisa_tagihan, 0, ',', '.') }}</td>
                                            <td>
                                                <span class="badge badge-{{ $detail->status_pelunasan === 'lunas' ? 'success' : 'warning' }}">
                                                    {{ $detail->status_pelunasan }}
                                                </span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Nota Debit Details -->
                    @if($pembayaranUtangSupplier->notaDebits->count() > 0)
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5><i class="fas fa-receipt mr-2"></i>Detail Nota Debit yang Digunakan</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>No Nota Debit</th>
                                            <th>Tanggal</th>
                                            <th>Total Nota Debit</th>
                                            <th>Jumlah Digunakan</th>
                                            <th>Sisa Nota Debit</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pembayaranUtangSupplier->notaDebits as $notaDebit)
                                        <tr>
                                            <td>{{ $notaDebit->no_nota_debit }}</td>
                                            <td>{{ $notaDebit->notaDebit->tanggal->format('d/m/Y') }}</td>
                                            <td class="text-right">Rp {{ number_format($notaDebit->total_nota_debit, 0, ',', '.') }}</td>
                                            <td class="text-right">Rp {{ number_format($notaDebit->jumlah_digunakan, 0, ',', '.') }}</td>
                                            <td class="text-right">Rp {{ number_format($notaDebit->sisa_nota_debit, 0, ',', '.') }}</td>
                                            <td>{{ $notaDebit->keterangan ?? '-' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Audit Information -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Informasi Pembuatan</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Dibuat oleh:</strong> {{ $pembayaranUtangSupplier->createdBy->name ?? 'N/A' }}</p>
                                    <p><strong>Tanggal dibuat:</strong> {{ $pembayaranUtangSupplier->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        </div>
                        @if($pembayaranUtangSupplier->confirmed_by)
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Informasi Konfirmasi</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Dikonfirmasi oleh:</strong> {{ $pembayaranUtangSupplier->confirmedBy->name ?? 'N/A' }}</p>
                                    <p><strong>Tanggal dikonfirmasi:</strong> {{ $pembayaranUtangSupplier->confirmed_at->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

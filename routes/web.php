<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoomsController;
use App\Http\Controllers\ItemsController;
use App\Http\Controllers\KasController;
use App\Http\Controllers\BookingsController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\AccountsController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\StokOwnerController;
use App\Http\Controllers\KodeBarangController;
use App\Http\Controllers\SuratJalanController;
use App\Http\Controllers\SuratJalanItemController;
use App\Http\Controllers\PembelianController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\CaraBayarController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PerusahaanController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\GrupBarangController;
use App\Http\Controllers\WilayahController;
use App\Http\Controllers\UnitConversionController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\PembayaranPiutangController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\CustomerPriceController;

use App\Models\StokOwner;
use App\Models\Supplier;
use App\Models\KodeBarang;
use App\Models\Bookings;
use App\Models\Logistics;
use App\Models\Transactions;
use App\Models\Customer;
use App\Models\Pembelian;
use App\Models\Transaksi;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group.
|
*/

// Return Barang API routes (accessible without authentication)
Route::get('/return-barang-api/search-transactions', [App\Http\Controllers\ReturnBarangController::class, 'searchTransactions'])->name('return-barang-api.search-transactions')->withoutMiddleware(['web', 'auth']);
Route::get('/return-barang-api/transaction-items', [App\Http\Controllers\ReturnBarangController::class, 'getTransactionItems'])->name('return-barang-api.transaction-items')->withoutMiddleware(['web', 'auth']);

// Public routes (authentication)
Route::middleware(['web'])->group(function () {
    Route::post('/signin', [AccountsController::class, 'login']);
    Route::post('/logout', [AccountsController::class, 'logout']);
    Route::get('/signin', function () {
        return view('signin');
    })->name('signin');

    Route::get('/login', function () {
        return redirect()->route('signin');
    })->name('login');
});

// Protected routes (need authentication)
Route::middleware(['web', 'auth'])->group(function () {
    // Dashboard route
    Route::get('', [PanelController::class, 'viewBarang'])
        ->middleware('permission:view dashboard')
        ->name('master.barang');
    
    // Profile route - accessible by all authenticated users
    Route::get('/profile', [AccountsController::class, 'profile']);
    Route::post('/updateProfile', [AccountsController::class, 'updateProfile'])->name('updateProfile');
    Route::post('/switchDatabase', [AccountsController::class, 'switchDatabase']);
    
    // ==============================
    // USER MANAGEMENT SECTION
    // ==============================
    
    // User Management routes
    Route::group(['middleware' => ['permission:edit users']], function () {
        Route::get('/account-maintenance', [AccountsController::class, 'accountMaintenance'])->name('accounts.maintenance');
        Route::post('/editAccount', [AccountsController::class, 'editAccount']);
        Route::get('/createAccount', [AccountsController::class, 'createAccount'])->name('createAccount');
        Route::post('/storeAccount', [AccountsController::class, 'storeAccount'])->name('storeAccount');
    });
    
    // Role Management routes
    Route::group(['middleware' => ['permission:manage roles'], 'prefix' => 'roles'], function () {
        Route::get('/createRole', [AccountsController::class, 'createRole'])->name('createRole');
        Route::post('/storeRole', [AccountsController::class, 'storeRole'])->name('storeRole');
        Route::get('/view/{id}', [AccountsController::class, 'viewRole'])->name('viewRole');
        Route::get('/edit/{id}', [AccountsController::class, 'editRole'])->name('editRole');
        Route::put('/update/{id}', [AccountsController::class, 'updateRole'])->name('updateRole');
        Route::delete('/delete/{id}', [AccountsController::class, 'deleteRole'])->name('deleteRole');
    });
    
    // ==============================
    // MASTER DATA SECTION
    // ==============================
    
    // Master Barang routes
    Route::group(['middleware' => ['permission:view master data'], 'prefix' => 'master'], function () {
        Route::get('/barang', [PanelController::class, 'viewBarang'])->name('master.barang');
        
        // Mutasi Stok Barang
        Route::get('/mutasistokbarang', function () {
            return view('master.mutasistokbarang');
        })->name('master.mutasistokbarang');
    });
    
    // Customer Management routes - View and Create
    Route::group(['middleware' => ['permission:manage customers'], 'prefix' => 'master'], function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    });
    
    // Customer Management routes - Edit
    Route::group(['middleware' => ['permission:edit customers'], 'prefix' => 'master'], function () {
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::patch('/customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
    });
    
    // Customer Management routes - Delete
    Route::group(['middleware' => ['permission:delete customers'], 'prefix' => 'master'], function () {
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });
    
    // Customer Price Management routes - View and Create
    Route::group(['middleware' => ['permission:manage customers'], 'prefix' => 'customer-price'], function () {
        Route::get('/', [CustomerPriceController::class, 'index'])->name('customer-price.index');
        Route::get('/create', [CustomerPriceController::class, 'create'])->name('customer-price.create');
        Route::post('/', [CustomerPriceController::class, 'store'])->name('customer-price.store');
        Route::get('/{customerPrice}', [CustomerPriceController::class, 'show'])->name('customer-price.show');
        Route::get('/get-price', [CustomerPriceController::class, 'getCustomerPrice'])->name('customer-price.get-price');
    });
    
    // Customer Price Management routes - Edit
    Route::group(['middleware' => ['permission:edit customers'], 'prefix' => 'customer-price'], function () {
        Route::get('/{customerPrice}/edit', [CustomerPriceController::class, 'edit'])->name('customer-price.edit');
        Route::put('/{customerPrice}', [CustomerPriceController::class, 'update'])->name('customer-price.update');
        Route::patch('/{customerPrice}/toggle-status', [CustomerPriceController::class, 'toggleStatus'])->name('customer-price.toggle-status');
    });
    
    // Customer Price Management routes - Delete
    Route::group(['middleware' => ['permission:delete customers'], 'prefix' => 'customer-price'], function () {
        Route::delete('/{customerPrice}', [CustomerPriceController::class, 'destroy'])->name('customer-price.destroy');
    });
    
    // Supplier Management routes - View and Create
    Route::group(['middleware' => ['permission:manage suppliers'], 'prefix' => 'master'], function () {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    });
    
    // Supplier Management routes - Edit
    Route::group(['middleware' => ['permission:edit suppliers'], 'prefix' => 'master'], function () {
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::patch('/suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('suppliers.toggle-status');
    });
    
    // Supplier Management routes - Delete
    Route::group(['middleware' => ['permission:delete suppliers'], 'prefix' => 'master'], function () {
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });
    
    // Stok Owner Management routes - View and Create
    Route::group(['middleware' => ['permission:manage stok owner'], 'prefix' => 'master'], function () {
        Route::get('/stok_owner', [StokOwnerController::class, 'index'])->name('stok_owner.index');
        Route::post('/stok_owner', [StokOwnerController::class, 'store'])->name('stok_owner.store');
    });
    
    // Stok Owner Management routes - Edit
    Route::group(['middleware' => ['permission:manage stok owner'], 'prefix' => 'master'], function () {
        Route::put('/stok_owner/{stokOwner}', [StokOwnerController::class, 'update'])->name('stok_owner.update');
    });

    // Stok Owner Management routes - Delete
    Route::group(['middleware' => ['permission:delete stok owner'], 'prefix' => 'master'], function () {
        Route::delete('/stok_owner/{stokOwner}', [StokOwnerController::class, 'destroy'])->name('stok_owner.destroy');
    });
    
    // Cara Bayar Management routes - View and Create
    Route::group(['middleware' => ['permission:manage cara bayar'], 'prefix' => 'master'], function () {
        Route::get('/cara_bayar', [CaraBayarController::class, 'index'])->name('master.cara_bayar');
        Route::post('/cara_bayar', [CaraBayarController::class, 'store'])->name('master.cara_bayar.store');
    });
    
    // Cara Bayar Management routes - Delete
    Route::group(['middleware' => ['permission:delete cara bayar'], 'prefix' => 'master'], function () {
        Route::delete('/cara_bayar/{id}', [CaraBayarController::class, 'destroy'])->name('master.cara_bayar.destroy');
    });
    
    // Perusahaan Management routes - View and Create
    Route::group(['middleware' => ['permission:manage perusahaan']], function () {
        Route::get('/perusahaan', [PerusahaanController::class, 'index'])->name('perusahaan.index');
        Route::get('/perusahaan/create', [PerusahaanController::class, 'create'])->name('perusahaan.create');
        Route::post('/perusahaan', [PerusahaanController::class, 'store'])->name('perusahaan.store');
        Route::post('/perusahaan/{id}/set-default', [PerusahaanController::class, 'setDefault'])->name('perusahaan.set-default');
    });
    
    // Perusahaan Management routes - Edit
    Route::group(['middleware' => ['permission:edit perusahaan']], function () {
        Route::get('/perusahaan/{id}/edit', [PerusahaanController::class, 'edit'])->name('perusahaan.edit');
        Route::put('/perusahaan/{id}', [PerusahaanController::class, 'update'])->name('perusahaan.update');
    });
    
    // Perusahaan Management routes - Delete
    Route::group(['middleware' => ['permission:delete perusahaan']], function () {
        Route::delete('/perusahaan/{id}', [PerusahaanController::class, 'destroy'])->name('perusahaan.destroy');
    });
    
    // Grup Barang routes - View and Create
    Route::group(['middleware' => ['permission:manage categories'], 'prefix' => 'grup_barang'], function () {
        Route::get('/', [GrupBarangController::class, 'index'])->name('grup_barang.index');
        Route::get('/create', [GrupBarangController::class, 'create'])->name('grup_barang.create');
        Route::post('/', [GrupBarangController::class, 'store'])->name('grup_barang.store');
    });
    
    // Grup Barang routes - Edit
    Route::group(['middleware' => ['permission:edit categories'], 'prefix' => 'grup_barang'], function () {
        Route::get('/{id}/edit', [GrupBarangController::class, 'edit'])->name('grup_barang.edit');
        Route::put('/{id}', [GrupBarangController::class, 'update'])->name('grup_barang.update');
        Route::patch('/{id}/toggle-status', [GrupBarangController::class, 'toggleStatus'])->name('grup_barang.toggle-status');
    });
    
    // Grup Barang routes - Delete
    Route::group(['middleware' => ['permission:delete categories'], 'prefix' => 'grup_barang'], function () {
        Route::delete('/{id}', [GrupBarangController::class, 'destroy'])->name('grup_barang.destroy');
    });
    
    // Kode Barang Management routes - View and Create
    Route::group(['middleware' => ['permission:manage kode barang'], 'prefix' => 'kode_barang'], function () {
        Route::get('/add', [KodeBarangController::class, 'createCode'])->name('code.create-code');
        Route::get('/view', [KodeBarangController::class, 'viewCode'])->name('code.view-code');
        Route::post('/add', [KodeBarangController::class, 'storeCode'])->name('code.store-code');
        Route::get('/get-next-code', [KodeBarangController::class, 'getNextItemCode'])->name('code.get-next-code');
    });
    
    // Kode Barang Management routes - Edit
    Route::group(['middleware' => ['permission:edit kode barang'], 'prefix' => 'kode_barang'], function () {
        Route::get('/edit/{id}', [KodeBarangController::class, 'edit'])->name('code.edit');
        Route::put('/update/{id}', [KodeBarangController::class, 'update'])->name('code.update');
        Route::post('/{id}/toggle-status', [KodeBarangController::class, 'toggleStatus'])->name('code.toggle-status');
    });
    
    // Kode Barang Management routes - Delete
    Route::group(['middleware' => ['permission:delete kode barang'], 'prefix' => 'kode_barang'], function () {
        Route::delete('/delete/{id}', [KodeBarangController::class, 'destroy'])->name('code.delete');
    });

    // Unit conversion inline AJAX endpoints
    Route::prefix('unit-conversion')->group(function () {
        Route::get('{kodeBarangId}/list', [\App\Http\Controllers\UnitConversionController::class, 'list'])->name('unit_conversion.list');
        Route::post('{kodeBarangId}', [\App\Http\Controllers\UnitConversionController::class, 'store'])->name('unit_conversion.store');
        Route::post('{kodeBarangId}/{id}/toggle', [\App\Http\Controllers\UnitConversionController::class, 'toggleStatus'])->name('unit_conversion.toggle');
        Route::delete('{kodeBarangId}/{id}', [\App\Http\Controllers\UnitConversionController::class, 'destroy'])->name('unit_conversion.destroy');
        // by kode string for panels/edit view
        Route::get('by-kode/{kodeBarang}', [\App\Http\Controllers\UnitConversionController::class, 'listByKode'])->name('unit_conversion.list_by_kode');
        Route::post('by-kode/{kodeBarang}', [\App\Http\Controllers\UnitConversionController::class, 'storeByKode'])->name('unit_conversion.store_by_kode');
        Route::put('by-kode/{kodeBarang}/{id}', [\App\Http\Controllers\UnitConversionController::class, 'updateByKode'])->name('unit_conversion.update_by_kode');
        Route::post('by-kode/{kodeBarang}/{id}/toggle', [\App\Http\Controllers\UnitConversionController::class, 'toggleByKode'])->name('unit_conversion.toggle_by_kode');
        Route::delete('by-kode/{kodeBarang}/{id}', [\App\Http\Controllers\UnitConversionController::class, 'destroyByKode'])->name('unit_conversion.destroy_by_kode');
    });
    
    // Wilayah Management routes - View and Create
    Route::group(['middleware' => ['permission:manage wilayah'], 'prefix' => 'wilayah'], function () {
        Route::get('/', [WilayahController::class, 'index'])->name('wilayah.index');
        Route::get('/create', [WilayahController::class, 'create'])->name('wilayah.create');
        Route::post('/', [WilayahController::class, 'store'])->name('wilayah.store');
    });
    
    // Wilayah Management routes - View
    Route::group(['middleware' => ['permission:view wilayah'], 'prefix' => 'wilayah'], function () {
        Route::get('/{wilayah}', [WilayahController::class, 'show'])->name('wilayah.show');
    });
    
    // Wilayah Management routes - Edit
    Route::group(['middleware' => ['permission:edit wilayah'], 'prefix' => 'wilayah'], function () {
        Route::get('/{wilayah}/edit', [WilayahController::class, 'edit'])->name('wilayah.edit');
        Route::put('/{wilayah}', [WilayahController::class, 'update'])->name('wilayah.update');
        Route::patch('/{wilayah}/toggle-status', [WilayahController::class, 'toggleStatus'])->name('wilayah.toggle-status');
    });
    
    // Wilayah Management routes - Delete
    Route::group(['middleware' => ['permission:delete wilayah'], 'prefix' => 'wilayah'], function () {
        Route::delete('/{wilayah}', [WilayahController::class, 'destroy'])->name('wilayah.destroy');
    });
    
    // Unit Conversion Management routes
    Route::group(['middleware' => ['permission:manage kode barang'], 'prefix' => 'unit_conversion'], function () {
        Route::get('/{kodeBarangId}', [UnitConversionController::class, 'index'])->name('unit_conversion.index');
        Route::get('/{kodeBarangId}/create', [UnitConversionController::class, 'create'])->name('unit_conversion.create');
        Route::post('/{kodeBarangId}', [UnitConversionController::class, 'store'])->name('unit_conversion.store');
        Route::get('/{kodeBarangId}/{id}/edit', [UnitConversionController::class, 'edit'])->name('unit_conversion.edit');
        Route::put('/{kodeBarangId}/{id}', [UnitConversionController::class, 'update'])->name('unit_conversion.update');
        Route::patch('/{kodeBarangId}/{id}/toggle', [UnitConversionController::class, 'toggleStatus'])->name('unit_conversion.toggle_status');
        Route::delete('/{kodeBarangId}/{id}', [UnitConversionController::class, 'destroy'])->name('unit_conversion.destroy');
    });
    
    // ==============================
    // PANEL MANAGEMENT SECTION
    // ==============================
    
    // Panel Inventory routes - View and Create
    Route::group(['middleware' => ['permission:manage panels'], 'prefix' => 'panels'], function () {
        // Panel Inventory
        Route::get('/inventory', [PanelController::class, 'inventory'])->name('panels.inventory');
        
        // Create Order Form
        Route::get('/order', [PanelController::class, 'createOrder'])->name('panels.create-order');
        Route::post('/order', [PanelController::class, 'repackOrder'])->name('panels.store-order');
        
        // Add to Inventory Form
        Route::get('/add', [PanelController::class, 'createInventory'])->name('panels.create-inventory');
        Route::post('/add', [PanelController::class, 'storeInventory'])->name('panels.store-inventory');
        
        // Repack
        Route::get('/repack', [PanelController::class, 'repack'])->name('panels.repack');
        Route::get('/print-receipt/{id}', [PanelController::class, 'printReceipt'])->name('panels.print-receipt');
        Route::get('/view-order/{id}', [PanelController::class, 'viewOrder'])->name('panels.view-order');
    });
    
    // Panel Inventory routes - Edit
    Route::group(['middleware' => ['permission:edit panels'], 'prefix' => 'panels'], function () {
        Route::get('/edit/{id}', [PanelController::class, 'editInventory'])->name('panels.edit-inventory');
        Route::post('/edit', [PanelController::class, 'updateInventory'])->name('panels.update-inventory');
    });
    
    // Panel Inventory routes - Delete
    Route::group(['middleware' => ['permission:delete panels'], 'prefix' => 'panels'], function () {
        Route::post('/delete/{id}', [PanelController::class, 'deleteInventory'])->name('panels.delete-inventory');
    });
    
    // ==============================
    // TRANSACTION SECTION
    // ==============================
    
    // Kas Management routes - View
    Route::group(['middleware' => ['permission:view kas|manage kas']], function () {
        Route::get('/viewKas', [KasController::class, 'viewKas'])->name('kas.view');
        Route::get('/viewSlide', [KasController::class, 'viewSlide']);
    });
    
    // Kas Management routes - Create
    Route::group(['middleware' => ['permission:manage kas'], 'prefix' => 'kas'], function () {
        Route::get('/add', [KasController::class, 'create'])->name('kas.create');
        Route::post('/add', [KasController::class, 'store'])->name('kas.store');
        Route::get('/addtransaction', [KasController::class, 'index']);
    });
    
    // Kas Management routes - Edit
    Route::group(['middleware' => ['permission:edit kas'], 'prefix' => 'kas'], function () {
        Route::post('/edit', [KasController::class, 'edit_kas']);
        Route::post('/update', [KasController::class, 'update_kas']);
    });
    
    // Kas Management routes - Cancel
    Route::group(['middleware' => ['permission:cancel kas'], 'prefix' => 'kas'], function () {
        Route::post('/cancel', [KasController::class, 'cancel_kas'])->name('kas.cancel');
    });
    
    // Kas Management routes - Delete
    Route::group(['middleware' => ['permission:delete kas'], 'prefix' => 'kas'], function () {
        Route::post('/delete', [KasController::class, 'delete_kas'])->name('kas.delete');
    });
    
    // Transaksi Penjualan routes - View and Create
    Route::group(['middleware' => ['permission:manage penjualan'], 'prefix' => 'transaksi'], function () {
        // Static routes first (no parameters)
        Route::get('/', [TransaksiController::class, 'index'])->name('transaksi.index');
        Route::get('/penjualan', [TransaksiController::class, 'penjualan'])->name('transaksi.penjualan');
        Route::post('/store', [TransaksiController::class, 'store'])->name('transaksi.store');
        
        // IMPORTANT: Fixed route name to match what's used in the controller
        Route::get('/lihat_nota', [TransaksiController::class, 'listNota'])->name('transaksi.listnota');
        
        // Display view for penjualan
        Route::get('/displaypenjualan', function () {
            return view('transaksi.displaypenjualan');
        })->name('transaksi.displaypenjualan');
        
        // View routes
        Route::get('/lihatnota/{id}', [TransaksiController::class, 'showNota'])->name('transaksi.lihatnota');
        Route::get('/shownota/{id}', [TransaksiController::class, 'showNota'])->name('transaksi.shownota');
        Route::get('/show/{id}', [TransaksiController::class, 'showNota'])->name('transaksi.show');
        Route::get('/nota/{id}', [TransaksiController::class, 'nota'])->name('transaksi.nota');
        
        // This catch-all route should be LAST
        Route::get('/{id}', [TransaksiController::class, 'getTransaction'])->name('transaksi.get');
    });
    
    // Transaksi Penjualan routes - Edit
    Route::group(['middleware' => ['permission:edit penjualan'], 'prefix' => 'transaksi'], function () {
        Route::get('/edit/{id}', [TransaksiController::class, 'edit'])->name('transaksi.edit');
        Route::post('/update/{id}', [TransaksiController::class, 'update'])->name('transaksi.update');
    });
    
    // Transaksi Penjualan routes - Cancel & Re-approve
    Route::group(['middleware' => ['permission:cancel penjualan'], 'prefix' => 'transaksi'], function () {
        Route::post('/cancel/{id}', [TransaksiController::class, 'cancelTransaction'])->name('transaksi.cancel');
        Route::post('/reapprove/{id}', [TransaksiController::class, 'reApproveTransaction'])->name('transaksi.reapprove');
    });
    
    // Additional Penjualan routes
    Route::get('/penjualanpercustomer', [TransaksiController::class, 'penjualanPercustomer'])
        ->middleware('permission:manage penjualan')
        ->name('transaksi.penjualancustomer');
    
    Route::get('/panel/{group_id}', [TransaksiController::class, 'getByGroupId'])
        ->middleware('permission:view master data')
        ->name('api.panel.get');
    
    // Pembelian (Purchase) routes - View and Create
    Route::group(['middleware' => ['permission:manage pembelian'], 'prefix' => 'pembelian'], function () {
        // Main routes
        Route::get('/', [PembelianController::class, 'index'])->name('pembelian.index');
        Route::post('/store', [PembelianController::class, 'store'])->name('pembelian.store'); 
        Route::get('/{id}', [PembelianController::class, 'getPurchase'])->name('pembelian.get');
        
        // Show invoice
        Route::get('/lihatnota/{id}', [PembelianController::class, 'showNota'])->name('pembelian.nota.show');
        Route::get('/nota/{nota}', [PembelianController::class, 'nota'])->name('pembelian.nota');
        Route::get('/lihat/nota', [PembelianController::class, 'listNota'])->name('pembelian.nota.list');
    });
    
    // Pembelian (Purchase) routes - Edit
    Route::group(['middleware' => ['permission:edit pembelian'], 'prefix' => 'pembelian'], function () {
        Route::get('/edit/{id}', [PembelianController::class, 'edit'])->name('pembelian.edit');
        Route::post('/update/{id}', [PembelianController::class, 'update'])->name('pembelian.update');
    });
    
    // Pembelian (Purchase) routes - Cancel
    Route::group(['middleware' => ['permission:cancel pembelian'], 'prefix' => 'pembelian'], function () {
        Route::post('/cancel/{id}', [PembelianController::class, 'cancel'])->name('pembelian.cancel');
    });
    
    // Pembelian (Purchase) routes - Delete
    Route::group(['middleware' => ['permission:delete pembelian'], 'prefix' => 'pembelian'], function () {
        Route::delete('/delete/{id}', [PembelianController::class, 'destroy'])->name('pembelian.delete');
    });
    
    // Surat Jalan routes - View and Create
    Route::group(['middleware' => ['permission:manage surat jalan'], 'prefix' => 'suratjalan'], function () {
        Route::get('/create', [SuratJalanController::class, 'create'])->name('suratjalan.create');
        Route::post('/store', [SuratJalanController::class, 'store'])->name('suratjalan.store');
        Route::get('/history', [SuratJalanController::class, 'history'])->name('suratjalan.history');
        Route::get('/detail/{id}', [SuratJalanController::class, 'detail'])->name('suratjalan.detail');
        Route::get('/available-stock', [SuratJalanController::class, 'getAvailableStock'])->name('suratjalan.available-stock');
        Route::get('/fifo-allocation/{suratJalanItemId}', [SuratJalanController::class, 'getFifoAllocation'])->name('suratjalan.fifo-allocation');
    });
    
    // Surat Jalan routes - Edit (if you have edit functionality)
    Route::group(['middleware' => ['permission:edit surat jalan'], 'prefix' => 'suratjalan'], function () {
        // Add edit routes for surat jalan if needed
        // Route::get('/edit/{id}', [SuratJalanController::class, 'edit'])->name('suratjalan.edit');
        // Route::post('/update/{id}', [SuratJalanController::class, 'update'])->name('suratjalan.update');
    });
    
    // Surat Jalan routes - Cancel (if you have cancel functionality)
    Route::group(['middleware' => ['permission:cancel surat jalan'], 'prefix' => 'suratjalan'], function () {
        // Add cancel routes for surat jalan if needed
        // Route::post('/cancel/{id}', [SuratJalanController::class, 'cancel'])->name('suratjalan.cancel');
    });
    
    // Purchase Order routes - View and Create
    Route::group(['middleware' => ['permission:manage purchase orders'], 'prefix' => 'purchase-order'], function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('transaksi.purchaseorder');
        Route::get('/{id}', [PurchaseOrderController::class, 'show'])->name('purchase-order.show');
        Route::post('/store', [PurchaseOrderController::class, 'store'])->name('purchase-order.store');
        Route::post('/{id}/complete', [PurchaseOrderController::class, 'completeTransaction'])->name('purchase-order.complete');
    });
    
    // Purchase Order routes - Edit
    Route::group(['middleware' => ['permission:edit purchase orders'], 'prefix' => 'purchase-order'], function () {
        Route::put('/{id}', [PurchaseOrderController::class, 'update'])->name('purchase-order.update');
    });
    
    // Purchase Order routes - Cancel
    Route::group(['middleware' => ['permission:cancel purchase orders'], 'prefix' => 'purchase-order'], function () {
        Route::patch('/{id}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-order.cancel');
    });
    
    // ==============================
    // STOCK MANAGEMENT SECTION
    // ==============================
    
    // Stock Management routes
    Route::group(['middleware' => ['permission:view stock'], 'prefix' => 'stock'], function () {
        Route::get('/mutasi', [StockController::class, 'mutasiStock'])->name('stock.mutasi');
        Route::get('/print-good', [StockController::class, 'printGoodStock'])->name('stock.print.good');
        Route::get('/get', [StockController::class, 'getStock'])->name('stock.get');
        Route::get('/mutations', [StockController::class, 'getStockMutations'])->name('stock.mutations');
    });
    
    // Stock Adjustment routes - View and Create
    Route::group(['middleware' => ['permission:manage stock adjustment'], 'prefix' => 'stock-adjustment'], function () {
        Route::get('/', [StockAdjustmentController::class, 'index'])->name('stock.adjustment.index');
        Route::get('/history', [StockAdjustmentController::class, 'history'])->name('stock.adjustment.history');
        Route::get('/create', [StockAdjustmentController::class, 'create'])->name('stock.adjustment.create');
        Route::post('/store', [StockAdjustmentController::class, 'store'])->name('stock.adjustment.store');
        Route::get('/adjust/{kodeBarang}', [StockAdjustmentController::class, 'adjust'])->name('stock.adjustment.adjust');
        Route::get('/{id}', [StockAdjustmentController::class, 'show'])->name('stock.adjustment.show');
    });
    
    // Stock Adjustment routes - Edit
    Route::group(['middleware' => ['permission:edit stock adjustment'], 'prefix' => 'stock-adjustment'], function () {
        // Add edit routes for stock adjustment if needed
        // Route::get('/edit/{id}', [StockAdjustmentController::class, 'edit'])->name('stock.adjustment.edit');
        // Route::post('/update/{id}', [StockAdjustmentController::class, 'update'])->name('stock.adjustment.update');
    });
    
    // Stock Adjustment routes - Cancel
    Route::group(['middleware' => ['permission:cancel stock adjustment'], 'prefix' => 'stock-adjustment'], function () {
        // Add cancel routes for stock adjustment if needed
        // Route::post('/cancel/{id}', [StockAdjustmentController::class, 'cancel'])->name('stock.adjustment.cancel');
    });
    
    // ==============================
    // SALES ORDER SECTION
    // ==============================
    
    // Sales Order routes - View and Create
    Route::group(['middleware' => ['permission:view sales order'], 'prefix' => 'sales-order'], function () {
        Route::get('/', [SalesOrderController::class, 'index'])->name('sales-order.index');
        Route::get('/create', [SalesOrderController::class, 'create'])->name('sales-order.create');
        Route::post('/store', [SalesOrderController::class, 'store'])->name('sales-order.store');
        // Place static paths BEFORE wildcard to avoid conflicts
        Route::get('/customer-price', [SalesOrderController::class, 'getCustomerPrice'])->name('sales-order.customer-price');
        Route::get('/available-units/{kodeBarangId}', [SalesOrderController::class, 'getAvailableUnits'])->name('sales-order.available-units');
        Route::get('/{salesOrder}', [SalesOrderController::class, 'show'])->name('sales-order.show');
    });
    
    // Sales Order routes - Edit
    Route::group(['middleware' => ['permission:edit sales order'], 'prefix' => 'sales-order'], function () {
        Route::get('/{salesOrder}/edit', [SalesOrderController::class, 'edit'])->name('sales-order.edit');
        Route::put('/{salesOrder}', [SalesOrderController::class, 'update'])->name('sales-order.update');
        Route::delete('/{salesOrder}', [SalesOrderController::class, 'destroy'])->name('sales-order.destroy');
    });
    
    // Sales Order routes - Status Management
    Route::group(['middleware' => ['permission:manage sales order'], 'prefix' => 'sales-order'], function () {
        Route::post('/{salesOrder}/approve', [SalesOrderController::class, 'approve'])->name('sales-order.approve');
        Route::post('/{salesOrder}/reapprove', [SalesOrderController::class, 'reApprove'])->name('sales-order.reapprove');
        Route::post('/{salesOrder}/process', [SalesOrderController::class, 'process'])->name('sales-order.process');
        Route::post('/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->name('sales-order.cancel');
        Route::post('/sales-order/{salesOrder}/convert', [SalesOrderController::class, 'convertToTransaksi'])
        ->name('sales-order.convert');
    });

    // ==============================
    // ROLE GROUPS SECTION
    // ==============================
    
    // Role Groups routes
    Route::group(['middleware' => ['permission:manage roles'], 'prefix' => 'role-groups'], function () {
        Route::get('/', [App\Http\Controllers\RoleGroupController::class, 'index'])->name('role-groups.index');
        Route::get('/create', [App\Http\Controllers\RoleGroupController::class, 'create'])->name('role-groups.create');
        Route::post('/', [App\Http\Controllers\RoleGroupController::class, 'store'])->name('role-groups.store');
        Route::get('/{roleGroup}', [App\Http\Controllers\RoleGroupController::class, 'show'])->name('role-groups.show');
        Route::get('/{roleGroup}/edit', [App\Http\Controllers\RoleGroupController::class, 'edit'])->name('role-groups.edit');
        Route::put('/{roleGroup}', [App\Http\Controllers\RoleGroupController::class, 'update'])->name('role-groups.update');
        Route::delete('/{roleGroup}', [App\Http\Controllers\RoleGroupController::class, 'destroy'])->name('role-groups.destroy');
        Route::post('/{roleGroup}/assign-role', [App\Http\Controllers\RoleGroupController::class, 'assignRole'])->name('role-groups.assign-role');
        Route::delete('/{roleGroup}/remove-role/{role}', [App\Http\Controllers\RoleGroupController::class, 'removeRole'])->name('role-groups.remove-role');
        Route::post('/{roleGroup}/toggle-status', [App\Http\Controllers\RoleGroupController::class, 'toggleStatus'])->name('role-groups.toggle-status');
    });

    // ==============================
    // RETURN BARANG SECTION
    // ==============================
    

    
    // Return Barang routes - View and Create
    Route::group(['middleware' => ['permission:view return barang'], 'prefix' => 'return-barang'], function () {
        Route::get('/', [App\Http\Controllers\ReturnBarangController::class, 'index'])->name('return-barang.index');
        Route::get('/create', [App\Http\Controllers\ReturnBarangController::class, 'create'])->name('return-barang.create');
        Route::post('/store', [App\Http\Controllers\ReturnBarangController::class, 'store'])->name('return-barang.store');
        Route::get('/{returnBarang}', [App\Http\Controllers\ReturnBarangController::class, 'show'])->name('return-barang.show');
    });
    
    // Return Barang routes - Edit
    Route::group(['middleware' => ['permission:edit return barang'], 'prefix' => 'return-barang'], function () {
        Route::get('/{returnBarang}/edit', [App\Http\Controllers\ReturnBarangController::class, 'edit'])->name('return-barang.edit');
        Route::put('/{returnBarang}', [App\Http\Controllers\ReturnBarangController::class, 'update'])->name('return-barang.update');
        Route::delete('/{returnBarang}', [App\Http\Controllers\ReturnBarangController::class, 'destroy'])->name('return-barang.destroy');
    });
    
    // Return Barang routes - Management
    Route::group(['middleware' => ['permission:manage return barang'], 'prefix' => 'return-barang'], function () {
        Route::post('/{returnBarang}/approve', [App\Http\Controllers\ReturnBarangController::class, 'approve'])->name('return-barang.approve');
        Route::post('/{returnBarang}/reject', [App\Http\Controllers\ReturnBarangController::class, 'reject'])->name('return-barang.reject');
        Route::post('/{returnBarang}/process', [App\Http\Controllers\ReturnBarangController::class, 'process'])->name('return-barang.process');
    });

    // ==============================
    // RETUR PENJUALAN SECTION
    // ==============================
    
    // Retur Penjualan routes - View and Create
    Route::group(['middleware' => ['permission:view retur penjualan'], 'prefix' => 'retur-penjualan'], function () {
        Route::get('/', [App\Http\Controllers\ReturPenjualanController::class, 'index'])->name('retur-penjualan.index');
        Route::get('/create', [App\Http\Controllers\ReturPenjualanController::class, 'create'])->name('retur-penjualan.create');
        Route::post('/store', [App\Http\Controllers\ReturPenjualanController::class, 'store'])->name('retur-penjualan.store');
        Route::get('/{returPenjualan}', [App\Http\Controllers\ReturPenjualanController::class, 'show'])->name('retur-penjualan.show');
    });
    
    // Retur Penjualan routes - Edit and Manage
    Route::group(['middleware' => ['permission:edit retur penjualan'], 'prefix' => 'retur-penjualan'], function () {
        Route::get('/{returPenjualan}/edit', [App\Http\Controllers\ReturPenjualanController::class, 'edit'])->name('retur-penjualan.edit');
        Route::put('/{returPenjualan}', [App\Http\Controllers\ReturPenjualanController::class, 'update'])->name('retur-penjualan.update');
        Route::delete('/{returPenjualan}', [App\Http\Controllers\ReturPenjualanController::class, 'destroy'])->name('retur-penjualan.destroy');
    });
    
    // Retur Penjualan routes - Approve/Reject/Process
    Route::group(['middleware' => ['permission:manage retur penjualan'], 'prefix' => 'retur-penjualan'], function () {
        Route::post('/{returPenjualan}/approve', [App\Http\Controllers\ReturPenjualanController::class, 'approve'])->name('retur-penjualan.approve');
        Route::post('/{returPenjualan}/reject', [App\Http\Controllers\ReturPenjualanController::class, 'reject'])->name('retur-penjualan.reject');
        Route::post('/{returPenjualan}/process', [App\Http\Controllers\ReturPenjualanController::class, 'process'])->name('retur-penjualan.process');
    });

    // ==============================
    // RETUR PEMBELIAN SECTION
    // ==============================
    
    // Retur Pembelian routes - View and Create
    Route::group(['middleware' => ['permission:view retur pembelian'], 'prefix' => 'retur-pembelian'], function () {
        Route::get('/', [App\Http\Controllers\ReturPembelianController::class, 'index'])->name('retur-pembelian.index');
        Route::get('/create', [App\Http\Controllers\ReturPembelianController::class, 'create'])->name('retur-pembelian.create');
        Route::post('/store', [App\Http\Controllers\ReturPembelianController::class, 'store'])->name('retur-pembelian.store');
        Route::get('/{returPembelian}', [App\Http\Controllers\ReturPembelianController::class, 'show'])->name('retur-pembelian.show');
    });
    
    // Retur Pembelian routes - Edit and Manage
    Route::group(['middleware' => ['permission:edit retur pembelian'], 'prefix' => 'retur-pembelian'], function () {
        Route::get('/{returPembelian}/edit', [App\Http\Controllers\ReturPembelianController::class, 'edit'])->name('retur-pembelian.edit');
        Route::put('/{returPembelian}', [App\Http\Controllers\ReturPembelianController::class, 'update'])->name('retur-pembelian.update');
        Route::delete('/{returPembelian}', [App\Http\Controllers\ReturPembelianController::class, 'destroy'])->name('retur-pembelian.destroy');
    });
    
    // Retur Pembelian routes - Approve/Reject/Process
    Route::group(['middleware' => ['permission:manage retur pembelian'], 'prefix' => 'retur-pembelian'], function () {
        Route::post('/{returPembelian}/approve', [App\Http\Controllers\ReturPembelianController::class, 'approve'])->name('retur-pembelian.approve');
        Route::post('/{returPembelian}/reject', [App\Http\Controllers\ReturPembelianController::class, 'reject'])->name('retur-pembelian.reject');
        Route::post('/{returPembelian}/process', [App\Http\Controllers\ReturPembelianController::class, 'process'])->name('retur-pembelian.process');
    });

    // ==============================
    // PEMBAYARAN PIUTANG SECTION
    // ==============================
    
    // Pembayaran Piutang — list / laporan
    Route::group(['middleware' => ['permission:view pembayaran piutang'], 'prefix' => 'pembayaran-piutang'], function () {
        Route::get('/', [PembayaranPiutangController::class, 'index'])->name('pembayaran-piutang.index');
        Route::get('/laporan', [PembayaranPiutangController::class, 'laporanPiutang'])->name('pembayaran-piutang.laporan');
    });

    // Pembayaran Piutang — create (must be before {pembayaran} wildcard)
    Route::group(['middleware' => ['permission:create pembayaran piutang'], 'prefix' => 'pembayaran-piutang'], function () {
        Route::get('/create', [PembayaranPiutangController::class, 'create'])->name('pembayaran-piutang.create');
        Route::post('/store', [PembayaranPiutangController::class, 'store'])->name('pembayaran-piutang.store');
    });

    // Pembayaran Piutang — detail
    Route::group(['middleware' => ['permission:view pembayaran piutang'], 'prefix' => 'pembayaran-piutang'], function () {
        Route::get('/{pembayaran}', [PembayaranPiutangController::class, 'show'])->name('pembayaran-piutang.show');
    });
    
    // Pembayaran Piutang — edit / cancel
    Route::group(['middleware' => ['permission:edit pembayaran piutang'], 'prefix' => 'pembayaran-piutang'], function () {
        Route::get('/{pembayaran}/edit', [PembayaranPiutangController::class, 'edit'])->name('pembayaran-piutang.edit');
        Route::put('/{pembayaran}', [PembayaranPiutangController::class, 'update'])->name('pembayaran-piutang.update');
        Route::delete('/{pembayaran}', [PembayaranPiutangController::class, 'destroy'])->name('pembayaran-piutang.destroy');
        Route::post('/{pembayaran}/confirm', [PembayaranPiutangController::class, 'confirm'])->name('pembayaran-piutang.confirm');
        Route::post('/{pembayaran}/cancel', [PembayaranPiutangController::class, 'cancel'])->name('pembayaran-piutang.cancel');
    });
    
    // ==============================
    // PEMBAYARAN UTANG SUPPLIER SECTION
    // ==============================
    
    // Utang supplier — list
    Route::group(['middleware' => ['permission:view pembayaran utang supplier'], 'prefix' => 'pembayaran-utang-supplier'], function () {
        Route::get('/', [App\Http\Controllers\PembayaranUtangSupplierController::class, 'index'])->name('pembayaran-utang-supplier.index');
    });

    // Utang supplier — create (must be before wildcard)
    Route::group(['middleware' => ['permission:create pembayaran utang supplier'], 'prefix' => 'pembayaran-utang-supplier'], function () {
        Route::get('/create', [App\Http\Controllers\PembayaranUtangSupplierController::class, 'create'])->name('pembayaran-utang-supplier.create');
        Route::post('/store', [App\Http\Controllers\PembayaranUtangSupplierController::class, 'store'])->name('pembayaran-utang-supplier.store');
    });

    // Utang supplier — detail
    Route::group(['middleware' => ['permission:view pembayaran utang supplier'], 'prefix' => 'pembayaran-utang-supplier'], function () {
        Route::get('/{pembayaranUtangSupplier}', [App\Http\Controllers\PembayaranUtangSupplierController::class, 'show'])->name('pembayaran-utang-supplier.show');
    });
    
    // Utang supplier — edit
    Route::group(['middleware' => ['permission:edit pembayaran utang supplier'], 'prefix' => 'pembayaran-utang-supplier'], function () {
        Route::get('/{pembayaranUtangSupplier}/edit', [App\Http\Controllers\PembayaranUtangSupplierController::class, 'edit'])->name('pembayaran-utang-supplier.edit');
        Route::put('/{pembayaranUtangSupplier}', [App\Http\Controllers\PembayaranUtangSupplierController::class, 'update'])->name('pembayaran-utang-supplier.update');
        Route::delete('/{pembayaranUtangSupplier}', [App\Http\Controllers\PembayaranUtangSupplierController::class, 'destroy'])->name('pembayaran-utang-supplier.destroy');
    });
    
    // Utang supplier — confirm/cancel
    Route::group(['middleware' => ['permission:manage pembayaran utang supplier'], 'prefix' => 'pembayaran-utang-supplier'], function () {
        Route::post('/{pembayaranUtangSupplier}/confirm', [App\Http\Controllers\PembayaranUtangSupplierController::class, 'confirm'])->name('pembayaran-utang-supplier.confirm');
        Route::post('/{pembayaranUtangSupplier}/cancel', [App\Http\Controllers\PembayaranUtangSupplierController::class, 'cancel'])->name('pembayaran-utang-supplier.cancel');
    });

    // ==============================
    // LAPORAN SECTION
    // ==============================
    
    // Laporan routes - View
    Route::group(['middleware' => ['permission:view laporan'], 'prefix' => 'laporan'], function () {
        Route::get('/', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('/laba-per-faktur', [LaporanController::class, 'labaPerFaktur'])->name('laporan.laba-per-faktur');
        Route::get('/laba-per-faktur/print', [LaporanController::class, 'printLabaPerFaktur'])->name('laporan.laba-per-faktur.print');
        Route::get('/laba-per-barang', [LaporanController::class, 'labaPerBarang'])->name('laporan.laba-per-barang');
        Route::get('/ongkos-kuli', [LaporanController::class, 'ongkosKuli'])->name('laporan.ongkos-kuli');
        Route::get('/ongkos-kuli/print', [LaporanController::class, 'printOngkosKuli'])->name('laporan.ongkos-kuli.print');
        Route::get('/komisi-sales/detail/{salesCode}', [LaporanController::class, 'komisiSalesDetail'])->name('laporan.komisi_sales.detail');
        Route::get('/komisi-sales/invoice-detail/{transaksiId}', [LaporanController::class, 'komisiSalesInvoiceDetail'])->name('laporan.komisi_sales.invoice_detail');
        Route::get('/komisi-sales', [LaporanController::class, 'komisiSales'])->name('laporan.komisi-sales');
        Route::get('/stok', [LaporanController::class, 'laporanStok'])->name('laporan.stok');
        Route::get('/stok/pergerakan/{kodeBarang}', [LaporanController::class, 'detailPergerakanBarang'])->name('laporan.stok.pergerakan');
        Route::get('/piutang', [LaporanController::class, 'laporanPiutang'])->name('laporan.piutang');
        Route::get('/utang-supplier', [LaporanController::class, 'laporanUtangSupplier'])->name('laporan.utang-supplier');
        Route::get('/retur', [App\Http\Controllers\LaporanReturController::class, 'index'])->name('laporan.retur');
    });

    // ==============================
    // API SECTION
    // ==============================
    
    // API routes for AJAX calls
    Route::prefix('api')->group(function () {
        // General API routes
        Route::get('/cara-bayar/by-metode', function (Illuminate\Http\Request $request) {
            $metode = $request->query('metode');
            return \App\Models\CaraBayar::where('metode', $metode)->get();
        });

        // Transaction API routes
        Route::get('/transaksi/{id}/items', function ($id) {
            try {
                $transaksi = \App\Models\Transaksi::findOrFail($id);
                $items = \App\Models\TransaksiItem::where('transaksi_id', $id)
                    ->with('kodeBarang')
                    ->get();
                
                $itemsData = $items->map(function($item) {
                    // Hitung subtotal jika NULL atau kosong
                    $qty = (float) $item->qty;
                    $harga = (float) $item->harga;
                    $subtotal = (!is_null($item->subtotal) && $item->subtotal !== '') ? (float) $item->subtotal : ($qty * $harga);
                    
                    return [
                        'kode_barang' => $item->kode_barang,
                        'nama_barang' => $item->kodeBarang->nama ?? $item->nama_barang ?? '-',
                        'qty' => $qty,
                        'satuan' => $item->satuan,
                        'harga' => $harga,
                        'subtotal' => $subtotal
                    ];
                });
                
                return response()->json([
                    'success' => true,
                    'data' => $itemsData
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan'
                ], 404);
            }
        });

        // Customer API routes
        Route::get('/customers/search', [CustomerController::class, 'search'])->name('api.customers.search');
        Route::get('/customers', [CustomerController::class, 'searchsuratjalan'])->name('api.customers');
        Route::post('/customers/create', [TransaksiController::class, 'createCustomer'])->name('api.customers.create');
        
        // Sales API routes
        Route::get('/sales/search', [StokOwnerController::class, 'search'])->name('api.sales.search');
        Route::get('/stok-owner/search', [StokOwnerController::class, 'search'])->name('api.stok-owner.search');
        
        // Sales Order API routes
        Route::get('/sales-order/search', [SalesOrderController::class, 'search'])->name('api.sales-order.search');
        Route::get('/sales-order/{salesOrder}/items', [SalesOrderController::class, 'getItems'])->name('api.sales-order.items');
        
        // Return Barang API routes
        Route::get('/return-barang/search-transactions', [App\Http\Controllers\ReturnBarangController::class, 'searchTransactions'])->name('api.return-barang.search-transactions');
        Route::get('/return-barang/transaction-items', [App\Http\Controllers\ReturnBarangController::class, 'getTransactionItems'])->name('api.return-barang.transaction-items');
        

        
        // Panels API routes
        Route::get('/panels/search', [PanelController::class, 'search'])->name('api.panels.search');
        Route::get('/kode-barang/search', [KodeBarangController::class, 'searchKodeBarang'])->name('kodeBarang.search');
        Route::get('/panels/search-available', [PanelController::class, 'searchAvailablePanels'])->name('panels.searchAvailable');
        Route::get('/panel-by-kode-barang', [PanelController::class, 'getPanelByKodeBarang'])->name('panel.by.kodeBarang');
        
        // Transaksi API routes
        Route::get('/products/search', [TransaksiController::class, 'searchProducts'])->name('api.products.search');
        Route::get('/transaksi', [TransaksiController::class, 'getTransaksi'])->name('api.transaksi');
        Route::get('/searchfaktur', [TransaksiController::class,'getTransaksiByCustomer'])->name('api.faktur.search');
        Route::get('/suratjalan/transaksiitem/{transaksiId}', [TransaksiController::class, 'getRincianTransaksi'])->name('api.rinciantransaksi');
        Route::get('/transaksi/items/{transaksiId}', [TransaksiController::class, 'getTransaksiItems'])->name('api.transaksi.items');
        Route::get('/transaksi/harga-ongkos', [TransaksiController::class, 'getHargaDanOngkos'])->name('api.transaksi.harga-ongkos');
        Route::post('/transaksi/store-from-sj', [TransaksiController::class, 'storeFromSuratJalan'])->name('api.transaksi.store-from-sj');
        
        // Pembayaran Piutang API routes
        Route::get('/pembayaran-piutang/customer-invoices', [PembayaranPiutangController::class, 'getCustomerInvoices'])->name('api.pembayaran-piutang.customer-invoices');
        Route::get('/pembayaran-piutang/customer-nota-kredit', [PembayaranPiutangController::class, 'getCustomerNotaKredit'])->name('api.pembayaran-piutang.customer-nota-kredit');
        Route::get('/pembayaran-piutang/payment-suggestion', [PembayaranPiutangController::class, 'getPaymentSuggestion'])->name('api.pembayaran-piutang.payment-suggestion');
        Route::get('/pembayaran-piutang/statistics', [PembayaranPiutangController::class, 'getPaymentStatistics'])->name('api.pembayaran-piutang.statistics');
        
        // Supplier API routes
        Route::get('/suppliers/search', [SupplierController::class, 'search'])->name('api.suppliers.search');
        
        // Penjualan API routes
        Route::get('/getpenjualancustomer', [TransaksiController::class, 'getPenjualan']);
        
        // Retur Penjualan API routes
        Route::get('/retur-penjualan/transaction-items', [App\Http\Controllers\ReturPenjualanController::class, 'getTransactionItems'])->name('api.retur-penjualan.transaction-items');
        
        // Retur Pembelian API routes
        Route::get('/retur-pembelian/pembelian-items', [App\Http\Controllers\ReturPembelianController::class, 'getPembelianItems'])->name('api.retur-pembelian.pembelian-items');
        
        // Laporan Retur API routes
        Route::get('/laporan/retur-penjualan', [App\Http\Controllers\LaporanReturController::class, 'getReturPenjualanReport'])->name('api.laporan.retur-penjualan');
        Route::get('/laporan/retur-pembelian', [App\Http\Controllers\LaporanReturController::class, 'getReturPembelianReport'])->name('api.laporan.retur-pembelian');
        Route::get('/laporan/dampak-stok-retur', [App\Http\Controllers\LaporanReturController::class, 'getDampakStokRetur'])->name('api.laporan.dampak-stok-retur');
        Route::get('/laporan/summary-retur', [App\Http\Controllers\LaporanReturController::class, 'getSummaryRetur'])->name('api.laporan.summary-retur');
        
        // Pembayaran Utang Supplier API routes
        Route::get('/pembayaran-utang-supplier/supplier-invoices', [App\Http\Controllers\PembayaranUtangSupplierController::class, 'getSupplierInvoices'])->name('api.pembayaran-utang-supplier.supplier-invoices');
        Route::get('/pembayaran-utang-supplier/supplier-nota-debits', [App\Http\Controllers\PembayaranUtangSupplierController::class, 'getSupplierNotaDebits'])->name('api.pembayaran-utang-supplier.supplier-nota-debits');
    });
});
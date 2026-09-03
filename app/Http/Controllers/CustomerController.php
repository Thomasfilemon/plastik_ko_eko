<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{

    public function index(Request $request)
    {
        $searchBy = $request->input('search_by');
        $keyword = $request->input('search');
        $query = Customer::query();

        if ($searchBy && $keyword) {
            if ($searchBy === 'nama') {
                $query->where('nama', 'like', "%{$keyword}%");
            } elseif ($searchBy === 'kode_customer') {
                $query->where('kode_customer', 'like', "%{$keyword}%");
            } elseif ($searchBy === 'alamat') {
                $query->where('alamat', 'like', "%{$keyword}%");
            } elseif ($searchBy === 'hp') {
                $query->where('hp', 'like', "%{$keyword}%");
            } elseif ($searchBy === 'telepon') {
                $query->where('telepon', 'like', "%{$keyword}%");
            }
        } elseif ($keyword) {
            // Default: cari di nama dan kode_customer
            $query->where(function($q) use ($keyword) {
                $q->where('nama', 'like', "%{$keyword}%")
                ->orWhere('kode_customer', 'like', "%{$keyword}%");
            });
        }

        // Filter by credit status
        $creditStatus = $request->input('credit_status');
        if ($creditStatus) {
            switch ($creditStatus) {
                case 'tunai':
                    $query->where('limit_hari_tempo', 0);
                    break;
                case 'kredit':
                    $query->where('limit_hari_tempo', '>', 0);
                    break;
            }
        }

        $customers = $query->with(['wilayah'])->orderBy('id', 'desc')->paginate(10)->withQueryString();

        // Hitung sisa piutang untuk setiap customer
        $customers->getCollection()->transform(function ($customer) {
            // Hitung total piutang dari transaksi
            $totalPiutang = \App\Models\Transaksi::where('kode_customer', $customer->kode_customer)
                ->outstandingPiutang()
                ->sum('sisa_piutang');
            
            $customer->sisa_piutang = $totalPiutang;
            return $customer;
        });

        $lastCustomer = Customer::orderBy('id', 'desc')->first();
        $newKodeCustomer = $lastCustomer ? str_pad($lastCustomer->id + 1, 4, '0', STR_PAD_LEFT) : '0001';

        // Get wilayah list for forms
        $wilayahs = \App\Models\Wilayah::active()->orderBy('nama_wilayah')->get();

        return view('master.customers', compact('customers', 'newKodeCustomer', 'keyword', 'searchBy', 'wilayahs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required',
            'alamat' => 'required',
            'hp' => 'required',
            'telepon' => 'nullable',
            'limit_kredit' => 'nullable|numeric|min:0',
            'limit_hari_tempo' => 'nullable|integer|min:0',
            'wilayah_id' => 'nullable|exists:wilayahs,id',
        ]);

        // Your existing code generation logic
        $lastCustomer = Customer::orderBy('id', 'desc')->first();
        $newKodeCustomer = $lastCustomer ? str_pad($lastCustomer->id + 1, 4, '0', STR_PAD_LEFT) : '0001';
        $validated['kode_customer'] = $newKodeCustomer;
        $validated['is_active'] = true; // Set default to active

        $customer = Customer::create($validated);

        // Handle AJAX requests (from modal)
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer berhasil ditambahkan',
                'customer' => $customer
            ]);
        }

        // Regular web requests (from main page)
        return redirect()->route('customers.index')->with('success', 'Customer added successfully.');
    }


    public function getCustomers(Request $request)
    {
        $keyword = $request->keyword;

        $customers = Customer::where('nama', 'like', "%{$keyword}%")
            ->orWhere('kode_customer', 'like', "%{$keyword}%")
            ->limit(10)
            ->get();

        return response()->json($customers);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'kode_customer' => 'required|unique:customers,kode_customer,' . $customer->id,
            'nama' => 'required',
            'alamat' => 'required',
            'hp' => 'required',
            'telepon' => 'nullable',
            'limit_kredit' => 'nullable|numeric|min:0',
            'limit_hari_tempo' => 'nullable|integer|min:0',
            'wilayah_id' => 'nullable|exists:wilayahs,id',
        ]);

        $customer->update($validated);
        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }
    
    public function search(Request $request)
    {
        $keyword = $request->get('keyword');
        $customers = DB::table('customers')
            ->where('nama', 'like', "%{$keyword}%")
            ->orWhere('kode_customer', 'like', "%{$keyword}%")
            ->limit(10)
            ->get();

        return response()->json($customers);
    }

    public function searchsuratjalan(Request $request)
    {
        $query = $request->get('query');

        $customers = Customer::where('nama', 'like', "%{$query}%")
            ->orWhere('kode_customer', 'like', "%{$query}%")
            ->get(['kode_customer', 'nama', 'alamat']); // Ambil kolom yang diperlukan saja

        return response()->json($customers);
    }

    /**
     * Toggle active status
     */
    public function toggleStatus(Customer $customer)
    {
        $customer->update([
            'is_active' => !$customer->is_active
        ]);

        $status = $customer->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->route('customers.index')
            ->with('success', "Customer berhasil {$status}!");
    }
}
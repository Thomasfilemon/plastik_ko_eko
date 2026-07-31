<?php

namespace App\Services;

use App\Models\KodeBarang;
use App\Models\UnitConversion;
use App\Models\CustomerPrice;
use App\Models\Customer;
use Exception;

class UnitConversionService
{
    /**
     * Konversi quantity dari unit turunan ke unit dasar
     * 
     * @param int $kodeBarangId
     * @param float $qty
     * @param string $fromUnit
     * @return float
     */
    public function convertToBaseUnit(int $kodeBarangId, float $qty, string $fromUnit): float
    {
        $kodeBarang = KodeBarang::find($kodeBarangId);
        if (!$kodeBarang) {
            throw new Exception("Kode barang tidak ditemukan");
        }

        // Jika unit sudah sama dengan unit dasar, return qty as is
        if ($fromUnit === $kodeBarang->unit_dasar) {
            return $qty;
        }

        // Cari konversi unit
        $conversion = UnitConversion::where('kode_barang_id', $kodeBarangId)
            ->where('unit_turunan', $fromUnit)
            ->active()
            ->first();

        if (!$conversion) {
            // Tolerant fallback: treat unknown unit as base unit (no conversion)
            return $qty;
        }

        return $qty * $conversion->nilai_konversi;
    }

    /**
     * Konversi quantity dari unit dasar ke unit turunan
     * 
     * @param int $kodeBarangId
     * @param float $qty
     * @param string $toUnit
     * @return float
     */
    public function convertFromBaseUnit(int $kodeBarangId, float $qty, string $toUnit): float
    {
        $kodeBarang = KodeBarang::find($kodeBarangId);
        if (!$kodeBarang) {
            throw new Exception("Kode barang tidak ditemukan");
        }

        // Jika unit sudah sama dengan unit dasar, return qty as is
        if ($toUnit === $kodeBarang->unit_dasar) {
            return $qty;
        }

        // Cari konversi unit
        $conversion = UnitConversion::where('kode_barang_id', $kodeBarangId)
            ->where('unit_turunan', $toUnit)
            ->active()
            ->first();

        if (!$conversion) {
            // Tolerant fallback: treat unknown unit as base unit (no conversion)
            return $qty;
        }

        return $qty / $conversion->nilai_konversi;
    }

    /**
     * Dapatkan harga jual untuk customer tertentu
     * 
     * @param int $customerId
     * @param int $kodeBarangId
     * @param string $unit
     * @return array
     */
    public function getCustomerPrice(int $customerId, int $kodeBarangId, string $unit = 'LBR'): array
    {
        $kodeBarang = KodeBarang::find($kodeBarangId);
        if (!$kodeBarang) {
            throw new Exception("Kode barang tidak ditemukan");
        }

        // Cari harga khusus customer
        $customerPrice = CustomerPrice::where('customer_id', $customerId)
            ->where('kode_barang_id', $kodeBarangId)
            ->active()
            ->first();

        if ($customerPrice) {
            $hargaJual = $customerPrice->harga_jual_khusus;
            $ongkosKuli = $customerPrice->ongkos_kuli_khusus ?? $kodeBarang->ongkos_kuli_default;
            $unitJual = $customerPrice->unit_jual;
        } else {
            // Gunakan harga default
            $hargaJual = $kodeBarang->harga_jual;
            $ongkosKuli = $kodeBarang->ongkos_kuli_default;
            $unitJual = $kodeBarang->unit_dasar;
        }

        // Konversi harga jika unit berbeda
        if ($unit !== $unitJual) {
            $hargaJual = $this->convertPrice($kodeBarangId, $hargaJual, $unitJual, $unit);
        }

        return [
            'harga_jual' => $hargaJual,
            'ongkos_kuli' => $ongkosKuli,
            'unit' => $unit,
            'is_custom_price' => $customerPrice !== null
        ];
    }

    /**
     * Konversi harga antar unit
     * 
     * @param int $kodeBarangId
     * @param float $harga
     * @param string $fromUnit
     * @param string $toUnit
     * @return float
     */
    public function convertPrice(int $kodeBarangId, float $harga, string $fromUnit, string $toUnit): float
    {
        $kodeBarang = KodeBarang::find($kodeBarangId);
        if (!$kodeBarang) {
            throw new Exception("Kode barang tidak ditemukan");
        }

        // Jika unit sama, return harga as is
        if ($fromUnit === $toUnit) {
            return $harga;
        }

        // Konversi ke unit dasar dulu
        $qtyInBaseUnit = $this->convertToBaseUnit($kodeBarangId, 1, $fromUnit);
        $hargaPerBaseUnit = $harga / max($qtyInBaseUnit, 1e-9);

        // Konversi dari unit dasar ke unit target
        $qtyInTargetUnit = $this->convertToBaseUnit($kodeBarangId, 1, $toUnit);
        
        return $hargaPerBaseUnit * $qtyInTargetUnit;
    }

    /**
     * Dapatkan semua unit yang tersedia untuk barang tertentu
     * 
     * @param int $kodeBarangId
     * @return array
     */
    public function getAvailableUnits(int $kodeBarangId): array
    {
        $kodeBarang = KodeBarang::find($kodeBarangId);
        if (!$kodeBarang) {
            return [];
        }

        $units = [$kodeBarang->unit_dasar];

        $conversions = UnitConversion::where('kode_barang_id', $kodeBarangId)
            ->active()
            ->get();

        foreach ($conversions as $conversion) {
            $units[] = $conversion->unit_turunan;
        }

        return array_unique($units);
    }

    /**
     * Units + conversion factors to base unit (1 besar = N kecil).
     *
     * @return array{units: array<int, string>, factors: array<string, float>}
     */
    public function getAvailableUnitsWithFactors(int $kodeBarangId): array
    {
        $kodeBarang = KodeBarang::find($kodeBarangId);
        if (!$kodeBarang) {
            return ['units' => [], 'factors' => []];
        }

        $unitDasar = $kodeBarang->unit_dasar ?? 'LBR';
        $units = [$unitDasar];
        $factors = [$unitDasar => 1.0];

        $conversions = UnitConversion::where('kode_barang_id', $kodeBarangId)
            ->active()
            ->get();

        foreach ($conversions as $conversion) {
            $units[] = $conversion->unit_turunan;
            $factors[$conversion->unit_turunan] = (float) $conversion->nilai_konversi;
        }

        return [
            'units' => array_values(array_unique($units)),
            'factors' => $factors,
        ];
    }

    /**
     * Validasi apakah unit valid untuk barang tertentu
     * 
     * @param int $kodeBarangId
     * @param string $unit
     * @return bool
     */
    public function isValidUnit(int $kodeBarangId, string $unit): bool
    {
        $availableUnits = $this->getAvailableUnits($kodeBarangId);
        return in_array($unit, $availableUnits);
    }
}

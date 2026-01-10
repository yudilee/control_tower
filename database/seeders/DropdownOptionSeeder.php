<?php

namespace Database\Seeders;

use App\Models\DropdownOption;
use Illuminate\Database\Seeder;

class DropdownOptionSeeder extends Seeder
{
    public function run(): void
    {
        // Remove old English work statuses if they exist
        DropdownOption::where('type', 'work_status')
            ->whereIn('value', ['pending', 'in_progress', 'waiting_parts', 'waiting_approval', 'completed'])
            ->delete();
        
        $options = [
            // Work Status - Indonesian Workflow
            // Work Status - Indonesian Workflow (13 Steps)
            // Values match Job::WORK_STATUSES constant exactly
            ['type' => 'work_status', 'value' => '1. Belum diproses (Tunggu Antrian)', 'label' => '1. Belum diproses', 'icon' => 'inbox', 'color' => 'secondary', 'sort_order' => 1],
            ['type' => 'work_status', 'value' => '2. Pengerjaan Diagnosa Awal', 'label' => '2. Diagnosa Awal', 'icon' => 'search', 'color' => 'info', 'sort_order' => 2],
            ['type' => 'work_status', 'value' => '3. Estimasi (Proses Warranty -> Tips case, Eskulab, Xsp)', 'label' => '3. Estimasi', 'icon' => 'calculator', 'color' => 'warning', 'sort_order' => 3],
            ['type' => 'work_status', 'value' => '4. Acc Customer/Warranty', 'label' => '4. Acc Customer', 'icon' => 'hand-thumbs-up', 'color' => 'success', 'sort_order' => 4],
            ['type' => 'work_status', 'value' => '5. Buka RQ (Qrder Parts)', 'label' => '5. Buka RQ', 'icon' => 'cart', 'color' => 'secondary', 'sort_order' => 5],
            ['type' => 'work_status', 'value' => '6. Parts Datang (Parts Received)', 'label' => '6. Parts Datang', 'icon' => 'box-seam', 'color' => 'primary', 'sort_order' => 6],
            ['type' => 'work_status', 'value' => '7. Penjadwalan (Unit dibawa customer)', 'label' => '7. Penjadwalan', 'icon' => 'calendar-date', 'color' => 'info', 'sort_order' => 7],
            ['type' => 'work_status', 'value' => '8. Pengerjaan', 'label' => '8. Pengerjaan', 'icon' => 'tools', 'color' => 'primary', 'sort_order' => 8],
            ['type' => 'work_status', 'value' => '9. Pemberkasan (Body Paint/Cash/Warranty)', 'label' => '9. Pemberkasan', 'icon' => 'folder', 'color' => 'secondary', 'sort_order' => 9],
            ['type' => 'work_status', 'value' => '10. Proses Close Job (Pengerjaan selesai)', 'label' => '10. Proses Close', 'icon' => 'check-lg', 'color' => 'success', 'sort_order' => 10],
            ['type' => 'work_status', 'value' => '11. Proses Invoice', 'label' => '11. Invoice', 'icon' => 'receipt', 'color' => 'info', 'sort_order' => 11],
            ['type' => 'work_status', 'value' => '12. Menunggu Pembayaran', 'label' => '12. Tunggu Bayar', 'icon' => 'hourglass', 'color' => 'warning', 'sort_order' => 12],
            ['type' => 'work_status', 'value' => '13. Sudah Dibayar', 'label' => '13. Lunas', 'icon' => 'cash-coin', 'color' => 'success', 'sort_order' => 13],

            // Payment Type
            ['type' => 'payment_type', 'value' => 'cash', 'label' => 'Cash', 'icon' => 'cash', 'color' => 'success', 'sort_order' => 1],
            ['type' => 'payment_type', 'value' => 'credit', 'label' => 'Credit', 'icon' => 'credit-card', 'color' => 'primary', 'sort_order' => 2],
            ['type' => 'payment_type', 'value' => 'transfer', 'label' => 'Transfer', 'icon' => 'bank', 'color' => 'info', 'sort_order' => 3],
            ['type' => 'payment_type', 'value' => 'warranty', 'label' => 'Warranty', 'icon' => 'shield-check', 'color' => 'warning', 'sort_order' => 4],
            ['type' => 'payment_type', 'value' => 'internal', 'label' => 'Internal', 'icon' => 'building', 'color' => 'secondary', 'sort_order' => 5],
            
            // Block/Bay
            ['type' => 'block', 'value' => 'A', 'label' => 'Block A', 'icon' => 'grid', 'color' => 'primary', 'sort_order' => 1],
            ['type' => 'block', 'value' => 'B', 'label' => 'Block B', 'icon' => 'grid', 'color' => 'success', 'sort_order' => 2],
            ['type' => 'block', 'value' => 'C', 'label' => 'Block C', 'icon' => 'grid', 'color' => 'warning', 'sort_order' => 3],
            ['type' => 'block', 'value' => 'D', 'label' => 'Block D', 'icon' => 'grid', 'color' => 'info', 'sort_order' => 4],
            ['type' => 'block', 'value' => 'BP', 'label' => 'Body Paint', 'icon' => 'palette', 'color' => 'danger', 'sort_order' => 5],
        ];

        foreach ($options as $option) {
            DropdownOption::updateOrCreate(
                ['type' => $option['type'], 'value' => $option['value']],
                $option
            );
        }
    }
}

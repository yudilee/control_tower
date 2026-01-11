<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Job;
use App\Models\JobInvoice;
use App\Models\SavedReport;
use App\Models\UserDashboardPreference;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Dashboard Settings Controller.
 * 
 * Handles user dashboard customization, widget configuration,
 * and preference management.
 */
class DashboardSettingsController extends Controller
{
    /**
     * Show dashboard customization page.
     */
    public function index(): View
    {
        $user = auth()->user();
        $preference = $user->getDashboardPreference();
        
        // Get available widgets for this user's role
        $availableWidgets = UserDashboardPreference::getAvailableWidgetsForRole($user->role);
        
        // Get current widget configuration
        $currentWidgets = $preference->getEffectiveWidgets();
        
        // Map current config to widget metadata
        $widgetsWithMeta = [];
        foreach ($currentWidgets as $widget) {
            $widgetId = $widget['id'];
            if (isset($availableWidgets[$widgetId])) {
                $widgetsWithMeta[] = array_merge($availableWidgets[$widgetId], [
                    'id' => $widgetId,
                    'enabled' => $widget['enabled'] ?? true,
                    'position' => $widget['position'] ?? 0,
                ]);
            }
        }
        
        // Add any available widgets not in current config
        foreach ($availableWidgets as $id => $meta) {
            $exists = collect($widgetsWithMeta)->contains('id', $id);
            if (!$exists) {
                $widgetsWithMeta[] = array_merge($meta, [
                    'id' => $id,
                    'enabled' => false,
                    'position' => count($widgetsWithMeta),
                ]);
            }
        }
        
        // Sort by position
        usort($widgetsWithMeta, fn($a, $b) => $a['position'] <=> $b['position']);
        
        return view('dashboard.customize', [
            'widgets' => $widgetsWithMeta,
            'preference' => $preference,
        ]);
    }

    /**
     * Save widget configuration.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $preference = $user->getDashboardPreference();
        
        $enabledWidgets = $request->input('widgets', []);
        $positions = $request->input('positions', []);
        
        // Build new config
        $widgets = [];
        foreach ($positions as $position => $widgetId) {
            $widgets[] = [
                'id' => $widgetId,
                'enabled' => in_array($widgetId, $enabledWidgets),
                'position' => (int) $position,
            ];
        }
        
        $preference->setWidgetConfig($widgets);
        
        return redirect()->route('dashboard')
            ->with('success', 'Dashboard customization saved successfully!');
    }

    /**
     * Reset to role defaults.
     */
    public function reset(): RedirectResponse
    {
        $user = auth()->user();
        $preference = $user->getDashboardPreference();
        $preference->resetToDefault();
        
        return redirect()->route('dashboard')
            ->with('success', 'Dashboard reset to default configuration.');
    }

    /**
     * Reorder widgets via AJAX.
     */
    public function reorder(Request $request)
    {
        $user = auth()->user();
        $preference = $user->getDashboardPreference();
        
        $order = $request->input('order', []);
        
        $currentWidgets = $preference->getEffectiveWidgets();
        $widgetMap = collect($currentWidgets)->keyBy('id');
        
        $newConfig = [];
        foreach ($order as $position => $widgetId) {
            $existing = $widgetMap->get($widgetId);
            $newConfig[] = [
                'id' => $widgetId,
                'enabled' => $existing['enabled'] ?? true,
                'position' => (int) $position,
            ];
        }
        
        $preference->setWidgetConfig($newConfig);
        
        return response()->json(['success' => true]);
    }

    /**
     * Get widget-specific data (for AJAX loading).
     */
    public function getWidgetData(Request $request, string $widgetId)
    {
        $user = auth()->user();
        
        return match ($widgetId) {
            'my_jobs' => $this->getMyJobsData($user),
            'bookings_today' => $this->getBookingsTodayData(),
            'pending_invoices' => $this->getPendingInvoicesData(),
            'saved_filters' => $this->getSavedFiltersData($user),
            default => response()->json(['error' => 'Unknown widget'], 404),
        };
    }

    /**
     * Get "My Jobs" data for current user.
     */
    protected function getMyJobsData($user): array
    {
        $query = Job::uninvoiced()->latest();
        
        // Filter by SA or Foreman if user is linked
        if ($user->serviceAdvisor) {
            $query->where('service_advisor', $user->serviceAdvisor->name);
        } elseif ($user->foreman) {
            $query->where('foreman', $user->foreman->name);
        }
        
        return ['myJobs' => $query->take(10)->get()];
    }

    /**
     * Get today's bookings.
     */
    protected function getBookingsTodayData(): array
    {
        $bookings = Booking::whereDate('booking_date', today())
            ->orderBy('booking_time')
            ->take(5)
            ->get();
            
        return ['bookingsToday' => $bookings];
    }

    /**
     * Get pending invoices.
     */
    protected function getPendingInvoicesData(): array
    {
        $invoices = JobInvoice::whereIn('status', ['pending', 'partially_paid'])
            ->with('job')
            ->orderByDesc('invoice_date')
            ->take(5)
            ->get();
            
        return ['pendingInvoices' => $invoices];
    }

    /**
     * Get saved filters for user.
     */
    protected function getSavedFiltersData($user): array
    {
        $filters = SavedReport::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->take(5)
            ->get();
            
        return ['savedFilters' => $filters];
    }
}

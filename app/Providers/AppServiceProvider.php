<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use App\Models\Job;
use App\Models\Announcement;
use App\Observers\JobObserver;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        
        // Register Job Observer for workflow automation
        // Register Job Observer for workflow automation
        Job::observe(JobObserver::class);

        // Share important announcements with layout
        View::composer('layouts.app', function ($view) {
            $importantAnnouncements = collect([]);
            
            if (Auth::check()) {
                $importantAnnouncements = Announcement::active()
                    ->where('is_important', true)
                    ->forRole(Auth::user()->role)
                    ->orderByDesc('created_at')
                    ->get()
                    ->filter(function ($announcement) {
                        return !$announcement->isDismissedBy(Auth::user());
                    });
            }
            
            $view->with('importantAnnouncements', $importantAnnouncements);
        });
    }
}

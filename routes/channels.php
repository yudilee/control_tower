<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private job channel - authenticated users can listen to job updates
Broadcast::channel('job.{jobId}', function ($user, $jobId) {
    // Any authenticated user can listen to job updates
    // In a more restrictive setup, you could check if the user has access to this job
    return $user !== null;
});

// Private notification channel - users can only listen to their own notifications
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

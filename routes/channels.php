<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channel Authorisation Routes
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// User private channel — each user may only subscribe to their own channel.
// This prevents one user from receiving another user's booking events.
Broadcast::channel('user.{id}', function ($user, $id) {
    return $user->id == $id;
});

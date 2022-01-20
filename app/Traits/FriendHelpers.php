<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\Friend;

trait FriendHelpers
{
    private function getMyFriendIds(Array $include_statii = ['accepted', 'blocked', 'requested', 'received']) 
    {
        $myFriends_1 = Friend::where('requester_id', '=', Auth::id())
                            ->whereIn('status', $include_statii)
                            ->select('receiver_id')
                            ->pluck('receiver_id')->toArray();
                            
        $myFriends_2 = Friend::where('receiver_id', '=', Auth::id())
                            ->whereIn('status', $include_statii)
                            ->select('requester_id')
                            ->pluck('requester_id')->toArray();

        return array_merge($myFriends_1, $myFriends_2);

    }

    private function getMemberFriendIds(Array $user_ids, Array $include_statii = ['accepted', 'blocked', 'requested', 'received']) 
    {
        $memberFriends_1 = Friend::whereIn('requester_id', $user_ids)
                            ->whereIn('status', $include_statii)
                            ->select('receiver_id')
                            ->pluck('receiver_id')->toArray();
                            
        $memberFriends_2 = Friend::whereIn('requester_id', $user_ids)
                            ->whereIn('status', $include_statii)
                            ->select('requester_id')
                            ->pluck('requester_id')->toArray();

        return array_merge($memberFriends_1, $memberFriends_2);

    }
}
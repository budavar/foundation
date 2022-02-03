<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; 
use App\Models\Friend;
use App\Models\GroupMember;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PeopleController extends API_Controller
{
    private $friend_id;
    private $friend;

    public function myFriends(Request $request) {
        $this->v_object_function_authority_check = false;
        $this->_process_control($request, __FUNCTION__, $this->rest_200_ok, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function search(Request $request) {
        $this->v_object_function_authority_check = false;
        $this->_process_control($request, __FUNCTION__, $this->rest_200_ok, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

// PROCESSING

    protected function p_myFriends($request) {

        if ($request->query('filter_directive') === 'exclude') {
            $exclude_user_ids = $this->filterExcludeDirective($request->query('entity'), $request->query('entity_id'));
        } else {
            $exclude_user_ids = [];
        }

        $friends_ids = $this->getFriendUserIds(); 

        $filterd_ids = array_diff($friends_ids, $exclude_user_ids);

        $friends_list = DB::table('users')
                            ->select('name', 'id', 'avatar')
                            ->whereIn('id', $filterd_ids)
                            ->orderBy('name')
                            ->get();

        $this->response_payload['list'] = $friends_list;

        return true;
    }

    protected function p_search(Request $request) {

        if ($request->query('filter_directive') === 'exclude') {
            $exclude_user_ids = $this->filterExcludeDirective($request->query('entity'), $request->query('entity_id'));
        } else {
            $exclude_user_ids = [];
        }

        $friends_ids = $this->getFriendUserIds(); 

        $filteerd_ids = $exclude_user_ids;
        
        $users_list = DB::table('users')
                            ->select('name', 'id', 'avatar')
                            ->where('id', '!=', Auth::id())
                            ->where('email_verified_at', '!=', null)
                            ->whereNotIn('id', $filteerd_ids)
                            ->where('name', 'LIKE', '%'.$request->query('search_string').'%')
                            ->orderBy('name')
                            ->get(100);

        $this->response_payload['list'] = $users_list; 
        $this->response_payload['friend_ids'] = $friends_ids; 

        return true;
    }

    protected function filterExcludeDirective($entity, $entity_id) {

        if ($entity === 'group') {
            $exclude_ids = GroupMember::where('group_id', '=', $entity_id)
                                      ->pluck('user_id')
                                      ->toArray();    
        }
        return $exclude_ids;
    } 
    
    protected function getFriendUserIds() {

        $friend_ids_1 = Friend::where('requester_id', '=', Auth::id())
                                ->where('status', '=', 'accepted')
                                ->pluck('receiver_id')
                                ->toArray();

        $friend_ids_2 = Friend::where('receiver_id', '=', Auth::id())
                                ->where('status', '=', 'accepted')
                                ->pluck('requester_id')
                                ->toArray();

        return array_merge($friend_ids_1, $friend_ids_2);
    }        
}
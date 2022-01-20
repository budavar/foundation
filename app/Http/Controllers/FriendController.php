<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; 
use App\Models\Friend;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FriendController extends API_Controller
{
    private $friend_id;
    private $friend;

    public function accept(Request $request, $friend_id) {
        $this->friend_id = $friend_id;
        $this->v_object_function_authority_check = false;
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Friend Request set to Accepted');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function delete(Request $request, $friend_id) {
        $this->friend_id = $friend_id;
        $this->v_object_function_authority_check = false;
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Friend Request Removed');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function block(Request $request, $friend_id) {
        $this->friend_id = $friend_id;
        $this->v_object_function_authority_check = false;
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Friend Request set to Blocked');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function list(Request $request) {
        $this->v_object_function_authority_check = false;
        $this->_process_control($request, __FUNCTION__, $this->rest_200_ok, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function search(Request $request) {
        sleep(1);
        $this->v_object_function_authority_check = false;
        $this->_process_control($request, __FUNCTION__, $this->rest_200_ok, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function sendFriendRequest(Request $request) {
        $this->v_object_function_authority_check = false;
        $this->v_422_rules = [ 
            'user_id' => 'required'
        ];
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Friend Request Sent');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

// VALIDATIONS

    protected function v_4XX_accept(Request $request) {
        return $this->actionValidation('accept', 'accepted');
    }

    protected function v_4XX_block(Request $request) {
        return $this->actionValidation('block', 'blocked');
    }

    protected function v_4XX_delete(Request $request) {
        return $this->actionValidation('delete', null);
    }

    protected function v_4XX_sendFriendRequest(Request $request) {

        $user = Auth::user();
        $this->rest_response = null;

        if ($user->id == $request->user_id) {
            $this->rest_response = $this->rest_422_unprocessableData;
            $this->response_payload['data_errors'] = ['user_id' => 'You may not friend yoruself'];
        } else {
            $friend_user = User::find($request->user_id);
            if (!$friend_user) {
                $this->rest_response = $this->rest_422_unprocessableData;
                $this->response_payload['data_errors'] = ['user_id' => 'Cannot find user']; 
            }
            if ($friend_user && $friend_user->email_verified_at == null) {
                $this->rest_response = $this->rest_422_unprocessableData;
                $this->response_payload['data_errors'] = ['user_id' => 'User has not verified his ID yet']; 
            }
        } 

        if ($this->rest_response == null) {
            // We have a valid friend ID.  Check for any existing relationships
            $friend_requested = Friend::where('requester_id', '=', $user->id)
                                    ->where('receiver_id', '=', $request->user_id)
                                    ->first();
            $friend_received = Friend::where('requester_id', '=', $request->user_id)
                                    ->where('receiver_id', '=', $user->id)
                                    ->first();
            // See state of any previous requests initiated by the user
            if ($friend_requested) {
                switch ($friend_requested->status) {
                    case 'accepted':
                        $this->rest_response = $this->rest_405_methodNotAllowed;
                        $this->response_payload['logic_errors'] = 'already_friends';
                        break; 
                    case 'blocked':
                        if ($friend_requested->blocked_by_id == $request->friend_id) {
                            $this->rest_response = $this->rest_405_methodNotAllowed;
                            $this->response_payload['logic_errors'] = 'friend_not_accepting_request'; 
                        } else {
                            $this->rest_response = $this->rest_405_methodNotAllowed;
                            $this->response_payload['logic_errors'] = 'you_blocked_friend';
                        }
                        break;
                    case 'requested':
                        $this->rest_response = $this->rest_405_methodNotAllowed;
                        $this->response_payload['logic_errors'] = 'friend_request_pending';
                        break; 
                }
            }
            // See state of any previous requests initiated by the friend id
            if ($friend_received != null) {
                switch ($friend_received->status) {
                    case 'accepted':
                        $this->rest_response = $this->rest_405_methodNotAllowed;
                        $this->response_payload['logic_errors'] = 'already_friends';
                        break; 
                    case 'blocked':
                        if ($friend_received->blocked_by_id == $request->friend_id) {
                            $this->rest_response = $this->rest_405_methodNotAllowed;
                            $this->response_payload['logic_errors'] = 'friend_not_accepting_request'; 
                        } else {
                            $this->rest_response = $this->rest_405_methodNotAllowed;
                            $this->response_payload['logic_errors'] = 'you_blocked_friend';
                        }
                    break; 
                    case 'requested':
                        $this->rest_response = $this->rest_405_methodNotAllowed;
                        $this->response_payload['logic_errors'] = 'friend_request_pending';
                        break; 
                }
            }
        }

        if ($this->rest_response != null) { 
            return false;
        }

        return true;
    }

// PROCESSING

    protected function p_accept(Request $request) {
        return $this->actionProcessing('accept', 'accepted');
    }

    protected function p_block(Request $request) {
        return $this->actionProcessing('block', 'blocked');
    }

    protected function p_delete(Request $request) {
        return $this->actionProcessing('delete', null);
    }

    protected function p_list($request) {

        if ($request->query('list_type') == 'accepted') {
            $friends_req = $this->getFriends(['accepted'], 'requested');        
            $friends_rec = $this->getFriends(['accepted'], 'received');
        } else {
            $friends_req = $this->getFriends(['accepted', 'blocked', 'requested'], 'requested');        
            $friends_rec = $this->getFriends(['accepted', 'blocked', 'requested'], 'received');
        }
        $this->response_payload['result'] = $friends_req->merge($friends_rec); 

        return true;
    }

    protected function p_search(Request $request) {

        $friend_ids_1 = Friend::where('requester_id', '=', Auth::id())
                                ->pluck('receiver_id')
                                ->toArray();

        $friend_ids_2 = Friend::where('receiver_id', '=', Auth::id())
                                ->pluck('requester_id')
                                ->toArray();

        $friends_ids = array_merge($friend_ids_1, $friend_ids_2, [Auth::id()]); 
        
        $friends_list = DB::table('users')
                            ->select('name', 'id')
                            ->whereNotIn('id', $friends_ids)
                            ->where('email_verified_at', '!=', null)
                            ->where('name', 'LIKE', '%'.$request->query('searchString').'%')
                            ->orderBy('name')
                            ->get(100);

        $this->rest_response = $this->rest_200_ok;
        $this->response_payload['result'] = $friends_list; 

        return true;
    }

    protected function p_sendFriendRequest(Request $request) {

        $friend = New Friend;
        $friend->requester_id = Auth::id();
        $friend->receiver_id = $request->user_id;
        $friend->status = 'requested';
        $friend->blocked_by_id = null;
        $friend->save();

        $friend_user = User::find($request->user_id);

        $return_data = [
            'name' => $friend_user->name, 
            'avatar' => $friend_user->avatar, 
            'id' => $request->user_id,
            'status' => $friend->status, 
            'friend_resource_id' => $friend->id,
            'requester_id' => $friend->requester_id,
            'receiver_id' => $friend->receiver_id,
            'status' => $friend->status,
            'updated_at' => $friend->updated_at
        ];

        $this->response_payload['result'] = $return_data; 

        return true;
    }

// PROCESSING AND VALIDATION UTILITY FUNCTIONS

    private function actionProcessing($action, $to_status) {
            
        if ($action == 'delete') {
            $this->friend->delete();
            $this->response_payload['result'] = $this->friend;
        } else {
            $this->friend->status = $to_status;
            if ($action == 'block') {
                $this->friend->blocked_by_id = Auth::id();
            } else {
                $this->friend->blocked_by_id = NULL;
            }
            $this->friend->save();
            $this->response_payload['result'] = $this->friend;
        }

        return true;
    }

    private function actionValidation($action, $to_status) {
        
        $this->friend = Friend::find($this->friend_id);

        // Not a valid friend request resource id
        if (!$this->friend) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic_errors'] = 'invalid_resource_identifier';
            return false;
        // Valid resource ID but not related to user
        } elseif ($this->friend->requester_id != Auth::id() && $this->friend->receiver_id != Auth::id()) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic_errors'] = 'unprocessable_resource_identifier';  
            return false;  
        // Check for allowable actions based on resource status
        } elseif (!$this->validAction($this->friend->status, $action)) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic_errors'] = 'friend_action_not_allowed';  
            $this->response_payload['result'] ['friend'] = $this->friend;
            $this->response_payload['result'] ['action'] = $action;
            return false;  
        }

        return true;

    }

    private function getFriends ($status, $direction) {

        if ($direction == 'received') {
            $friends_list = DB::table('friends')
                                ->join('users', 'users.id', '=', 'friends.requester_id')
                                ->select('users.name', 'users.id', 'friends.requester_id', 'friends.receiver_id', 'friends.blocked_by_id', 'friends.status', 'friends.updated_at', \DB::raw('friends.id as friend_resource_id'))
                                ->where('friends.receiver_id', '=', Auth::id())
                                ->whereIn('status', $status)
                                ->get();
        } elseif ($direction == 'requested') {
            $friends_list = DB::table('friends')
                                  ->join('users', 'users.id', '=', 'friends.receiver_id')
                                  ->select('users.name', 'users.id', 'friends.requester_id', 'friends.receiver_id', 'friends.blocked_by_id', 'friends.status', 'friends.updated_at', \DB::raw('friends.id as friend_resource_id'))
                                  ->where('friends.requester_id', '=', Auth::id())
                                  ->whereIn('status', $status)
                                  ->get();
        }

        return $friends_list->filter(function ($row, $key) {
            return in_array($row->blocked_by_id, [Auth::id(), null]);
        });
    }

    private function validAction($friend_status, $action) {

        $valid_combos = [
            'accepted' => ['block', 'delete'],
            'blocked' => ['accept', 'delete'],
            'requested' => ['block', 'delete', 'accept'],
        ];

        if (in_array($action, $valid_combos[$friend_status])) {
            return true;
        } else {
            return false;
        }
    }

}
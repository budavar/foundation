<?php

namespace App\Http\Controllers\API;

use Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

class GroupMemberController extends API_Controller
{
    use \App\Traits\FriendHelpers;

    private $group;
    private $group_id;
    private $group_member;
    private $group_member_id;

    // ENTRY POINTS
    
    public function approve(Request $request, $group_member_id) { 
        $this->group_member_id = $group_member_id;
        $this->v_4xx_validation = true;
        $this->p_processing_data['valid_statii'] = ['pending'];
        $this->_process_control($request, $group_member_id, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }    
    
    public function block(Request $request, $group_member_id) { 
        $this->group_member_id = $group_member_id;
        $this->v_4xx_validation = true;
        $this->p_processing_data['valid_statii'] = ['active', 'pending'];
        $this->_process_control($request, $group_member_id, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }  

    public function cancelRequest(Request $request, $group_member_id) { 
        $this->group_member_id = $group_member_id;
        $this->v_4xx_validation = true;
        $this->p_processing_data['valid_statii'] = ['pending'];
        $this->_process_control($request, $group_member_id, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function create(Request $request, $group_id) {
        $this->group_id = $group_id;
        $this->v_422_rules = [ 
            'user_id' => 'required'
        ];
        $this->v_4xx_validation = true;
        $this->_process_control($request, $group_id, __FUNCTION__, $this->rest_202_accepted, 'member_created');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function delete(Request $request, $group_member_id) { 
        $this->group_member_id = $group_member_id;
        $this->v_4xx_validation = true;
        $this->p_processing_data['valid_statii'] = ['active', 'blocked', 'pending'];
        $this->_process_control($request, $group_member_id, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function list(Request $request, $group_id) {
        $this->group_id = $group_id;
        $this->_process_control($request, $group_id, __FUNCTION__, $this->rest_200_ok, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function makeAdmin(Request $request, $group_member_id) { 
        $this->group_member_id = $group_member_id;
        $this->v_4xx_validation = true;
        $this->p_processing_data['valid_statii'] = ['active'];
        $this->_process_control($request, $group_member_id, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function makeMember(Request $request, $group_member_id) { 
        $this->group_member_id = $group_member_id;
        $this->v_4xx_validation = true;
        $this->p_processing_data['valid_statii'] = ['active', 'blocked'];
        $this->_process_control($request, $group_member_id, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function requestToJoin(Request $request, $group_id) {
        $this->group_id = $group_id;
        $this->v_422_rules = [ 
            'user_id' => 'required'
        ];
        $this->v_4xx_validation = true;
        $this->_process_control($request, $group_id, __FUNCTION__, $this->rest_202_accepted, 'request_created');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function unblock(Request $request, $group_member_id) { 
        $this->group_member_id = $group_member_id;
        $this->v_4xx_validation = true;
        $this->p_processing_data['valid_statii'] = ['blocked'];
        $this->_process_control($request, $group_member_id, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }  

    // PROCESSING LOGIC

    protected function p_approve(Request $request) {
        return $this->updateGroupMemberStatus('active');
    }

    protected function p_block(Request $request) {
        return $this->updateGroupMemberStatus('blocked');
    }

    protected function p_cancelRequest(Request $request) {
        return $this->removeGroupMember();
    }

    protected function p_create(Request $request) {
        $this->response_payload['result'] ['new_member'] = $this->createGroupMember($request, 'active');
        return true;
    }

    protected function p_delete(Request $request) {
        return $this->removeGroupMember();
    }

    protected function p_list(Request $request) {

        if ($request->query('scope') == '*') { 
            $this->response_payload['result'] = GroupMember::with('user')
                                                            ->where('group_id', '=', $this->group->id)
                                                            ->get();

        } else {
            $this->response_payload['result'] = GroupMember::with('user')
                                                            ->where('group_id', '=', $this->group->id)
                                                            ->where('status', '=', $request->query('scope'))
                                                            ->get();
        }
        return true;
    }
    
    protected function p_makeAdmin(Request $request) {
        return $this->updateGroupMemberRole('admin');
    }
    
    protected function p_makeMember(Request $request) {
        return $this->updateGroupMemberRole('member');
    }

    protected function p_requestToJoin(Request $request) {

        if ($this->group->join_rule == 'auto') {
            $this->response_payload['result'] ['new_member'] = $this->createGroupMember($request, 'active');
        } else {
            $this->response_payload['result'] ['new_member'] = $this->createGroupMember($request, 'pending');
        }
        return true;
    }

    protected function p_unblock(Request $request) {
        return $this->updateGroupMemberStatus('active');
    }
    
    // VALIDATION LOGIC
    protected function v_4XX_approve(Request $request) {
        return $this->checkMemberStatus();
    }

    protected function v_4XX_block(Request $request) {
        return $this->checkMemberStatus();
    }

    protected function v_4XX_cancelRequest(Request $request) {
        return $this->checkMemberStatus();
    }

    protected function v_4XX_create(Request $request) {

        //Cannot already be a member or have request pending
        
        if ($this->checkAlreadyMember($request->user_id)) {
            return false;
        }

        // Must be a friend of the user

        if (!in_array($request->user_id, $this->getMyFriendIds(['accepted']))) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'You can only invite your friends'; 
            return false;
        }

        return true;

    }

    protected function v_4XX_delete(Request $request) {
        return $this->checkMemberStatus();
    }

    protected function v_4XX_makeAdmin(Request $request) {
        return $this->checkMemberStatus();
    }

    protected function v_4XX_makeMember(Request $request) {
        return $this->checkMemberStatus();
    }

    protected function v_4XX_requestToJoin(Request $request) {
        //Cannot already be a member or have request pending

        if ($this->checkAlreadyMember($request->user_id)) {
            return false;
        }

        return true;
    }

    protected function v_4XX_unblock(Request $request) {
        return $this->checkMemberStatus();
    }
    
    // COMMON LOGIC

    protected function checkAlreadyMember($user_id) {

        if (GroupMember::where('group_id', '=', $this->group->id)->where('user_id', '=', $user_id)->exists()
            || $this->group->owner_id == $user_id) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'user_already_member'; 
            return true;
        } else {
            return false;
        }

    }

    protected function checkMemberStatus() {
        if (in_array($this->group_member->status, $this->p_processing_data['valid_statii'])) {
            return true;
        } else {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'Function and Group member Status Mismatch'; 
            $this->response_payload['result'] ['a'] = $this->group_member->status; 
            $this->response_payload['result'] ['b'] = $this->p_processing_data['valid_statii']; 
            return false;
        }
    }

    protected function createGroupMember($request, $status) {
        $group_member = new GroupMember;
        $group_member->group_id = $this->group->id;
        $group_member->user_id = $request->user_id;
        $group_member->role = 'member';
        $group_member->status = $status;

        $group_member->save();

        return GroupMember::with('user')
                            ->find($group_member->id);
    }

    protected function removeGroupMember() {
        $this->group_member->delete();
        return true;
    }

    protected function updateGroupMemberRole($new_role) {
        $this->group_member->role = $new_role;
        $this->group_member->save();
        $this->response_payload['result'] ['updated_member'] = GroupMember::with('user')->find($this->group_member->id);
        return true;
    }

    protected function updateGroupMemberStatus($new_status) {
        $this->group_member->status = $new_status;
        if ($new_status == 'blocked') {
            $this->group_member->role = 'member';
        }
        $this->group_member->save();
        $this->response_payload['result'] ['updated_member'] = GroupMember::with('user')->find($this->group_member->id);
        return true;
    }

    protected function object_function_authority_check($action) {
        
        if ($this->group_member_id != null) {
            $this->group_member = GroupMember::find($this->group_member_id);
            if (!$this->group_member) {
                $this->rest_response = $this->rest_405_methodNotAllowed;
                $this->response_payload['logic_errors'] = 'Invalid group member resource identifier - ' . $this->group_member_id;
                return false;
            } else {
                $this->group_id = $this->group_member->group_id;
            }
        }

        $this->group = Group::find($this->group_id);

        // Valid Resource
        if (!$this->group) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic_errors'] = 'Invalid group resource identifier - ' . $this->group_id;
            return false;
        } else {
            if ($action == 'list') {
                $this->group_member = GroupMember::where('group_id', '=', $this->group->id)
                                                    ->where('user_id', '=', Auth::id())
                                                    ->first();
                if ($this->group_member) {
                    $this->group_member_id = $this->group_member->id;
                }
            }
        }

        if ($this->group->owner_id == Auth::id()) {
            $user_role = 'owner';
            $user_status = 'active';
            Log::info('GOT TO OWNER');
        } else {
            if ($this->group_member) {
                $user_role = $this->group_member->role;
                $user_status = $this->group_member->status;
                Log::info('GOT TO MEMBER');
                Log::info($this->group_member);
            } else {
                $user_role = 'not-a-member';
                $user_status = 'XXX';
            }
        }

        $conditions_array = ['owner.active.approve',
                             'owner.active.block',
                             'owner.active.create',
                             'owner.active.delete',
                             'owner.active.list',
                             'owner.active.makeAdmin',
                             'owner.active.makeMember',
                             'owner.active.unblock',
                             'admin.active.approve',
                             'admin.active.block',
                             'admin.active.create',
                             'admin.active.delete',
                             'admin.active.list',
                             'admin.active.makeAdmin',
                             'admin.active.makeMember',
                             'admin.active.unblock',
                             'member.active.delete',
                             'member.pending.cancelRequest', 
                             'not-a-member.XXX.requestToJoin'
                            ];
        
        // Story not in correct state for requested action
        $check_condition = $user_role . '.' . $user_status . '.' . $action;

        if (!in_array($check_condition, $conditions_array)) {
            $this->response_payload['add-on-data'] = ['check_condition' => $check_condition, 
                                                        'conditions_array' => $conditions_array];
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic_errors'] = 'Group resource Status / Action Mismatch';
            return false;
        }

        if ($action == 'delete' && $user_role == 'member') {
            if (Auth::id() != $this->group_member->user_id) {
                $this->response_payload['add-on-data'] = ['check_condition' => $check_condition, 
                'conditions_array' => $conditions_array];
                $this->rest_response = $this->rest_405_methodNotAllowed;
                $this->response_payload['logic_errors'] = 'Group resource authority / Action Mismatch for member delete';
                return false;
            }
        }
        
        return true;
    
    }

}


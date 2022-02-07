<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use \Carbon\Carbon;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

class GroupMemberController extends API_Controller
{
    use \App\Traits\FriendHelpers;

    private $group;
    private $group_id;
    private $group_member;
    private $my_group_member;
    private $group_member_id;

    // ENTRY POINTS
    
    public function activate(Request $request, $group_member_id) { 
        $this->group_member_id = $group_member_id;
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }
    
    public function block(Request $request, $group_member_id) { 
        $this->group_member_id = $group_member_id;
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    } 
    
    public function changeRole(Request $request, $group_member_id) { 
        $this->group_member_id = $group_member_id;
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    } 

    public function joinRequest(Request $request, $group_id) {
        $this->group_id = $group_id;
        $this->v_422_rules = [ 
            'user_id' => 'required'
        ];
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'member_created');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function remove(Request $request, $group_member_id) { 
        $this->group_member_id = $group_member_id;
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function updateSettings(Request $request, $group_member_id) { 
        $this->group_member_id = $group_member_id;
        $this->v_422_rules = [ 
            'mute_notifications' => 'required|in:do-not-mute,one-week,one-month,always',
        ];
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    // PROCESSING LOGIC

    protected function p_activate(Request $request) {
        $this->group_member->status = 'active';
        $this->group_member->role = 'member';
        $this->group_member->update();
        $this->response_payload = $this->returnMember($this->group_member_id);
        return true;
    }

    protected function p_block(Request $request) {
        $this->group_member->status = 'blocked';
        $this->group_member->role = 'member';
        $this->group_member->update();
        $this->response_payload = $this->returnMember($this->group_member_id);
        return true;
    }

    protected function p_changeRole(Request $request) {
        $this->group_member->role = $request->change_to_role;
        $this->group_member->update();
        $this->response_payload = $this->returnMember($this->group_member_id);
        return true;
    }

    protected function p_joinRequest(Request $request) {

        $group_member = new GroupMember;
        $group_member->group_id = $this->group->id;
        $group_member->user_id = $request->user_id;
        $group_member->role = 'member';

        $group_member->mute_notifications_until = Carbon::now()->setTimezone('UTC')->format('Y-m-d H:i:s');

        if (Auth::id() == $request->user_id) {
            $group_member->status = 'requested';
        } else {
            $group_member->status = 'invited';
        }

        if ($this->group->request_to_join_rule == 'automatic') {
            $group_member->status = 'active';
        }

        $group_member->save();

        $this->response_payload = $this->returnMember($group_member->id);
        return true;
    }

    protected function p_remove(Request $request) {
        $this->group_member->delete();
        $this->response_payload = $this->returnMember($this->group_member->id);
        return true;
    }

    protected function p_updateSettings(Request $request) {

        switch ($request->mute_notifications) {
            case 'do-not-mute':
                $date = Carbon::now()->setTimezone('UTC');
                break;
            case 'one-week':
                $date = Carbon::now()->setTimezone('UTC')->add(1, 'week');
                break;
            case 'one-month':
                $date = Carbon::now()->setTimezone('UTC')->add(1, 'month');
                break;
            case 'always':
                $date = Carbon::now()->setTimezone('UTC')->add(100, 'year');
                break;
        };

        $this->group_member->mute_notifications = $request->mute_notifications;
        $this->group_member->mute_notifications_until = $date->toDateTimeString();

        $this->group_member->update();
        $this->response_payload = $this->returnMember($this->group_member->id);
        return true;
    }

    // VALIDATION LOGIC

    protected function v_4XX_activate(Request $request) {

        if ($this->group_member->status != 'invited' 
            && $this->group_member->status != 'requested' 
            && $this->group_member->status != 'blocked' ) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'Mismatch between Member Status and Action'; 
            return false;
        }

        // You can only accept an invite for your own invitation
        if ($this->group_member->user_id == Auth::id() && $this->group_member->status != 'invited') {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'User may only self-accept invitation status'; 
            return false;
        }

        return true;        
    }

    protected function v_4XX_block(Request $request) {

        if ($this->group_member->status != 'invited' 
            && $this->group_member->status != 'requested' 
            && $this->group_member->status != 'active' ) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'Mismatch between Member Status and Action'; 
            return false;
        }

        if ($this->group_member->user_id == Auth::id()) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'You cannot block yourself'; 
            return false;
        }

        return true;        
    }

    protected function v_4XX_changeRole(Request $request) {

        if ($request->change_to_role != 'member' 
            && $request->change_to_role != 'admin') {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'Invalid To-Role requested - ' . $request->query('role'); 
            return false;
        }

        if ($this->group_member->role == 'owner') {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'You are not allowed to change the owner'; 
            return false;
        }

        return true;        
    }

    protected function v_4XX_joinRequest(Request $request) {
        //Cannot already be a member or pending request

        if (GroupMember::where('group_id', '=', $this->group->id)->where('user_id', '=', $request->user_id)->exists()) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'User is already a member'; 
            return false;
        }

        return true;
    }

    protected function v_4XX_remove(Request $request) {

        if ($this->group_member->role == 'owner') {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'Not allowed to remove the owner'; 
            return false;
        }

        if ($this->my_group_member->role == 'member' && $this->group_member->user_id != Auth::id()) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'You tried to remove a member other than yourself'; 
            return false;
        }

        return true;        
    }

    // COMMON LOGIC

    protected function returnMember($id) {
        $member = GroupMember::with('user')->find($id);
        return $member;
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

    protected function object_function_authority_check($action) {
        
        $target_member_role = 'not-a-member';
        $target_member_status = 'not-a-member';
        $target_member_id = null;

        if ($this->group_member_id != null) {
            $this->group_member = GroupMember::find($this->group_member_id);
            if (!$this->group_member) {
                $this->rest_response = $this->rest_405_methodNotAllowed;
                $this->response_payload['logic_errors'] = 'Invalid group member resource identifier - ' . $this->group_member_id;
                return false;
            } else {
                $target_member_role = $this->group_member->role;
                $target_member_status = $this->group_member->status;
                $target_member_id = $this->group_member->id;
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
            if ($this->group->status != 'active') {
                $this->rest_response = $this->rest_405_methodNotAllowed;
                $this->response_payload['logic_errors'] = 'Invalid status on group resource identifier - ' . $this->group_id;
                return false;
            }
        }

        $member_role = 'not-a-member';
        $member_status = 'not-a-member';
        $member_id = null;

        $this->my_group_member = GroupMember::where('group_id', '=', $this->group->id)
                                            ->where('user_id', '=', Auth::id())
                                            ->first();

        if ($this->my_group_member) {
            $member_role = $this->my_group_member->role;
            $member_status = $this->my_group_member->status;
            $member_id = $this->my_group_member->id;
        }

        if ($target_member_id == $member_id) {
            $action_on_member = 'self';
        } else {
            $action_on_member = 'other';
        }

        $conditions_array = ['owner.active.activate.other',
                             'owner.active.block.other',
                             'owner.active.changeRole.other',
                             'owner.active.remove.other',
                             'owner.active.joinRequest.other',
                             'owner.active.update.other',
                             'owner.active.updateSettings.self',
                             'owner.closed.updateSettings.self',
                             'admin.active.activate.other',
                             'admin.active.block.other',
                             'admin.active.changeRole.self',
                             'admin.active.changeRole.other',
                             'admin.active.remove.self',
                             'admin.active.remove.other',
                             'admin.active.updateSettings.self',
                             'admin.closed.updateSettings.self',
                             'admin.active.joinRequest.other',
                             'member.requested.remove.self',
                             'member.invited.activate.self',
                             'member.active.updateSettings.self',
                             'member.closed.updateSettings.self',
                             'not-a-member.not-a-member.joinRequest.self'
                            ];
        
        // Story not in correct state for requested action
        $check_condition = $member_role . '.' . $member_status . '.' . $action . '.' . $action_on_member;

        if (!in_array($check_condition, $conditions_array)) {
            $this->response_payload['add-on-data'] = ['check_condition' => $check_condition, 
                                                        'conditions_array' => $conditions_array];
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic_errors'] = 'Group Member resource Status / Action Mismatch';
            return false;
        }

        if ($target_member_role == 'owner' && $action != 'updateSettings') {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic_errors'] = 'Not allowed to perform action on owner profile';
            return false;
        }
        
        return true;
    
    }

}


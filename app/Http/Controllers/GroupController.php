<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;

use App\Models\Group;
use App\Models\GroupMember;

class GroupController extends API_Controller
{
    use \App\Traits\FriendHelpers;
    use \App\Traits\ImageHelpers;

    private $group;
    private $group_id;
    private $_my_authority = null;

    // ENTRY POINTS

    public function close(Request $request, $group_id) {
        $this->group_id = $group_id;
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Group Activated');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function create(Request $request) {
        $this->v_object_function_authority_check = false;
        $this->setupValidationRules($request, 'create');
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Group Created');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function mygroups(Request $request) { 
        $this->v_object_function_authority_check = false;
        $this->_process_control($request, __FUNCTION__, $this->rest_200_ok, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function open(Request $request, $group_id) {
        $this->group_id = $group_id;
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Group Deactivated');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function retrieve(Request $request, $group_id) { 
        $this->group_id = $group_id;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function search(Request $request) { 
        $this->v_object_function_authority_check = false;
        $this->_process_control($request, __FUNCTION__, $this->rest_200_ok, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function update(Request $request, $group_id) { 
        $this->group_id = $group_id;
        $this->setupValidationRules($request, 'update');
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Group Updated');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    // PROCESSING LOGIC
    
    protected function p_close(Request $request) {
        $this->group->status = 'closed';
        $this->group->update();
        $this->response_payload = $this->returnGroup($this->group_id);
        return true;
    }

    protected function p_create(Request $request) {

        $this->group = new Group;
        $this->group->name = $request->name;
        $this->group->description = $request->description;
        $this->group->visibility = $request->visibility;
        $this->group->allow_to_add_events = 'no-one';
        $this->group->request_to_join_rule = 'approval';
        $this->group->rules = [];
        $this->group->owner_id = Auth::id();
        $this->group->status = 'active';
        $this->group->photo = null;
        $this->group->photo_history = null;
        $this->group->save();

        //Create Group Owner Membership Record
        $group_member = new GroupMember;
        $group_member->group_id = $this->group->id;
        $group_member->user_id = Auth::id();
        $group_member->role = 'owner';
        $group_member->status = 'active';
        $group_member->save();

        $this->response_payload = $this->returnGroup($this->group_id);

        return true;
    }

    protected function p_delete(Request $request) {
        $this->group->delete();
        return true;
    }

    protected function p_mygroups(Request $request) {

        $myGroup_ids = GroupMember::select('group_id')->where('user_id' ,'=', Auth::id())->where('status', '!=', 'blocked')
                                             ->pluck('group_id')->toArray();

        $this->response_payload ['groups'] = 
            Group::with(array('owner', 'myMember' => function($query) { $query->where('user_id', '=', Auth::id()); } ))
                    ->withCount(array('members' => function($query2) { $query2->where('status', '=', 'active'); } ))
                    ->whereIn('id', $myGroup_ids)
                    ->orderBy('name')
                    ->get();

        return true;
    }

    protected function p_open(Request $request) {
        $this->group->status = 'active';
        $this->group->update();
        $this->response_payload = $this->returnGroup($this->group_id);
        return true;
    }

    protected function p_retrieve(Request $request) {

        if ($this->_my_authority == 'member') {
            $this->response_payload = 
                Group::with(array('members' => function($query) { $query->where('status', '=', 'active'); }, 'members.user') )
                    ->find($this->group_id);
        } else {
            $this->response_payload = 
                Group::with(array('members', 'members.user'))->find($this->group_id);
        }

        $this->response_payload ['_my_authority'] = $this->_my_authority;

        return true;
    }

    protected function p_search(Request $request) {

        $myGroup_ids = GroupMember::select('group_id')->where('user_id' ,'=', Auth::id())->pluck('group_id')->toArray();
        $myFriends = $this->getMyFriendIds(['accepted']);

        $get_groups = 
            Group::with('owner')
                ->withCount(array('members' => function($query2) { $query2->where('status', '=', 'active'); } ))
                ->whereNotIn('id', $myGroup_ids)
                ->where('visibility', '=', 'public')
                ->where('status', '=', 'active')
                ->where('name', 'LIKE', '%'.$request->query('search_string').'%')
                ->orderBy('name');

        if ($request->query('scope') == 'friend-groups') {
            $get_groups->whereIn('owner_id', $myFriends);
        }

        $this->response_payload ['groups'] = $get_groups->get();

        return true;
    }

    protected function p_update(Request $request) {

        $this->group->name = $request->name;
        $this->group->description = $request->description;
        //$this->group->visibility = $request->visibility;
        $this->group->allow_to_add_events = $request->allow_to_add_events;
        $this->group->request_to_join_rule = $request->request_to_join_rule;
        $this->group->rules = array_filter($request->rules);

        if ($request->hasFile('photo')) {
            $this->group->photo = $this->saveSingleImage('group_image', 'photo', $request);
        }

        $this->group->update();
        
        return $this->p_retrieve($request);
    }

    // VALIDATION LOGIC

    protected function v_4XX_close (Request $request) {
        if ($this->group->status != 'active' ) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'Group is not active'; 
            return false;
        } else {
            return true;
        }
    }

    protected function v_4XX_open(Request $request) {
        if ($this->group->status != 'closed' ) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'Group is not closed'; 
            return false;
        } else {
            return true;
        }
    }

    // INTERNAL LOGIC

    protected function returnGroup($group_id) {

        $group_plus = Group::with(array('owner', 'myMember' => function($query) { $query->where('user_id', '=', Auth::id()); } ))
                           ->withCount(array('members' => function($query2) { $query2->where('status', '=', 'active'); } ))
                           ->find($group_id);

        $group_plus['_my_authority'] = $this->_my_authority;

        return $group_plus;

    }

    protected function setupAttributes(Request $request, $function) {

        $this->group->name = $request->name;
        $this->group->description = $request->description;
        $this->group->visibility = $request->visibility;
        $this->group->allow_to_add_events = $request->allowToAddEvents;
        $this->group->request_to_join_rule = $request->requestToJoinRule;
        $this->group->rules = array_filter($request->rules);

        if ($function == 'create') {
            $this->group->owner_id = Auth::id();
            $this->group->image = null;
            $this->group->status = 'active';
            $this->group->image = $this->saveBase64AsFile('App\Models\Group', $request->imgSrc);
            $this->group->image_history = null;
        }

    }

    protected function setupValidationRules($request, $function) {

        if ($function == 'create') {
            $this->v_422_rules = [ 
                'name' => 'required|min:2|max:100',
                'visibility' => 'required|in:private,friends,public',
                'description' => 'required|min:2|max:1000'
            ];
        } else {
            $this->v_422_rules = [ 
                'name' => 'required|min:2|max:50',
                'visibility' => 'required|in:private,friends,public',
                'request_to_join_rule' => 'required|in:automatic,approval',
                'allow_to_add_events' => 'required|in:no-one,owner,admin,member',
                'description' => 'required|min:2|max:500',
                'rules' => 'nullable|array',
                'rules.*' => 'max:500',
                'photo' => 'required'
            ];
        }

        $this->v_422_messages = [
            'rules.*.max' => 'Cannot be longer than 500 characters',
        ];

    }

    protected function object_function_authority_check($action) {
               
        $this->access_state = null;

        $this->group = Group::find($this->group_id);

        // Valid group resource
        if (!$this->group) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic_errors'] = 'Invalid group resource identifier - ' . $this->group_id;
            return false;
        }

        $member_role = 'not-a-member';
        $member_status = "active";

        $my_group_member = GroupMember::where('group_id', '=', $this->group->id)
                                      ->where('user_id', '=', Auth::id())
                                     ->first();

        if ($my_group_member) {
            if ($my_group_member->status == 'blocked') {
                $this->rest_response = $this->rest_405_methodNotAllowed;
                $this->response_payload['logic_errors'] = 'Group resource Status / Action Mismatch';
                return false;
            }
            $member_role = $my_group_member->role;
            $member_status = $my_group_member->status;
        }

        // member Role . member status . function . group status

        $conditions_array = ['owner.active.open.closed',
                             'owner.active.close.active',
                             'owner.active.delete.closed',
                             'owner.active.retrieve.*',
                             'owner.active.update.*',
                             'admin.active.retrieve.*',
                             'admin.active.update.*',
                             'member.active.retrieve.*',
                             'guest.active.retrieve.*'
                            ];
        
        // Story not in correct state for requested action

        $check_condition_1 = $member_role . '.' . $member_status . '.' . $action . '.' . $this->group->status;
        $check_condition_2 = $member_role . '.' . $member_status . '.' . $action . '.*';

        if (!in_array($check_condition_1, $conditions_array)
            && !in_array($check_condition_2, $conditions_array)) {
            $this->response_payload['add-on-data'] = ['check_condition' => $check_condition_1 . ' - ' . $check_condition_2, 
                                                        'conditions_array' => $conditions_array];
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic_errors'] = 'Group resource Status / Action Mismatch';
            return false;
        }

        $this->_my_authority = $member_role;

        return true;
    
    }

}

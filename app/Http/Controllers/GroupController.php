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
    private $_my_authority;

    // ENTRY POINTS

    public function activate(Request $request, $group_id) {
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

    public function deactivate(Request $request, $group_id) {
        $this->group_id = $group_id;
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Group Deactivated');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function delete(Request $request, $group_id) {
        $this->group_id = $group_id;
        $this->v_4xx_validation = true;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Group Deleted');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function list(Request $request) { 
        $this->v_object_function_authority_check = false;
        $this->_process_control($request, __FUNCTION__, $this->rest_200_ok, null);
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

    public function update_image(Request $request, $group_id) { 
        $this->group_id = $group_id;
        $this->v_422_rules = [ 
            'imgSrc' => 'required'
        ];
        $this->_process_control($request, $group_id, __FUNCTION__, $this->rest_202_accepted, 'Group Image Updated');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    // PROCESSING LOGIC

    protected function p_activate(Request $request) {
        $this->group->status = 'active';
        $this->group->update();
        $this->response_payload = $this->returnGroup($this->group_id);
        return true;
    }

    protected function p_create(Request $request) {

        $this->group = new Group;
        $this->group->name = $request->name;
        $this->group->description = $request->description;
        $this->group->visibility = $request->visibility;
        $this->group->allow_to_add_events = 'none';
        $this->group->request_to_join_rule = 'approval';
        $this->group->rules = [];
        $this->group->owner_id = Auth::id();
        $this->group->image = null;
        $this->group->status = 'active';
        $this->group->image = null;
        $this->group->image_history = null;
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
    
    protected function p_deactivate(Request $request) {
        $this->group->status = 'inactive';
        $this->group->update();
        $this->response_payload = $this->returnGroup($this->group_id);
        return true;
    }

    protected function p_delete(Request $request) {
        $this->group->delete();
        return true;
    }

    protected function p_list(Request $request) {

        $myGroup_ids = GroupMember::select('group_id')->where('user_id' ,'=', Auth::id())->where('status', '!=', 'blocked')
                                             ->pluck('group_id')->toArray();

        $this->response_payload = Group::with(array('owner', 'myMember' => function($query) { $query->where('user_id', '=', Auth::id()); } ))
                                       ->withCount(array('members' => function($query2) { $query2->where('status', '=', 'active'); } ))
                                       ->whereIn('id', $myGroup_ids)
                                       ->orderBy('name')
                                       ->get();
        
        return true;
    }

    protected function p_retrieve(Request $request) {

        $this->response_payload = Group::with(array('members' => function($query) { $query->where('status', '=', 'active'); }, 
                                                              'members.user') )
                                                 ->find($this->group_id);

        return true;
    }

    protected function p_search(Request $request) {

        $myGroup_ids = GroupMember::select('group_id')->where('user_id' ,'=', Auth::id())->where('status', '!=', 'blocked')
                                             ->pluck('group_id')->toArray();
        
        $myFriends = $this->getMyFriendIds(['accepted']);

        $myFriends_group_ids = Group::select('id')
                                    ->whereIn('owner_id', $myFriends)
                                    ->where('visibility', '!=', 'private')
                                    ->where('status', '=', 'active')
                                    ->pluck('id')->toArray();
        
        $friend_groups_am_not_member = array_diff($myFriends_group_ids, $myGroup_ids);

        $this->response_payload = Group::with('owner')
                                       ->withCount(array('members' => function($query2) { $query2->where('status', '=', 'active'); } ))
                                       ->whereIn('id', $friend_groups_am_not_member)
                                       ->where('visibility', '=', 'friends')
                                       ->orWhere('visibility', '=', 'public')
                                       ->whereNotIn('id', $myGroup_ids)
                                       ->orderBy('name')
                                       ->get();
        
        
       $this->response_payload['_my_authority'] = $this->my_authority;

        return true;
    }

    protected function p_update(Request $request) {
        $this->setupAttributes($request, 'update');
        $this->group->update();
        $this->response_payload = $this->returnGroup($this->group_id);
        return true;
    }

    protected function p_update_image(Request $request) {
        $save_image = $this->group->image;
        $this->group->image = $this->saveBase64AsFile('App\Models\Group', $request->imgSrc);
        $this->group->image_history = $this->imageHistory($save_image, $this->group->image_history);
        $this->group->update();
        $this->response_payload['result'] ['new_image'] = $this->group->image;

        $this->response_payload = $this->returnGroup($this->group_id);
        return true;
    }

    // VALIDATION LOGIC

    protected function v_4XX_open(Request $request) {
        if ($this->group->status != 'inactive' ) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'Group is not inactive'; 
            return false;
        } else {
            return true;
        }
    }

    protected function v_4XX_deactivate (Request $request) {
        if ($this->group->status != 'active' ) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic-errors'] = 'Group is not active'; 
            return false;
        } else {
            return true;
        }
    }

    protected function v_4XX_delete (Request $request) {

        // Needs work 

        return true;
    }

    // INTERNAL LOGIC

    protected function returnGroup($group_id) {

        $group_plus = Group::with(array('owner', 'my_member' => function($query) { $query->where('user_id', '=', Auth::id()); } ))
                           ->withCount(array('members' => function($query2) { $query2->where('status', '=', 'active'); } ))
                           ->find($group_id);

        $group_plus['_my_authority'] = $this->my_authority;

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
                'requestToJoinRule' => 'required|in:auto,approval',
                'allowToAddEvents' => 'required|in:none,owner,admin,member',
                'description' => 'required|min:2|max:500',
                'rules' => 'nullable|array',
                'rules.*' => 'max:255'
                //'imgSrc' => 'required',
                //'groupMembers' => 'nullable|array',
                //'groupMembers.*' => 'exists:users,id'
            ];
        }

        $this->v_422_messages = [
            'rules.*.max' => 'Cannot be longer than 255 characters',
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

        // member Role . function . group status . visibility

        $conditions_array = ['owner.*.*.*',
                             'admin.update.*.*',
                             'admin.retrieve.*.*',
                             'admin.deactivate.*.*',
                             'admin.activate.*.*',
                             'member.retrieve.*.*',
                             'guest.retrieve.*.*'
                            ];
        
        // Story not in correct state for requested action

        $check_condition_1 = $user_role . '.' . $action . '.' . $this->group->status . '.' . $this->group->visibility;
        $check_condition_2 = $user_role . '.' . $action . '.' . $this->group->status . '.*';
        $check_condition_3 = $user_role . '.' . $action . '.*.*';
        $check_condition_4 = $user_role . '.*.*.*';

        if (!in_array($check_condition_1, $conditions_array)
            && !in_array($check_condition_2, $conditions_array)
            && !in_array($check_condition_3, $conditions_array)
            && !in_array($check_condition_4, $conditions_array)) {
            $this->response_payload['add-on-data'] = ['check_condition' => $check_condition_1 . ' - ' . $check_condition_2 . ' - ' . $check_condition_3 . ' - ' . $check_condition_4, 
                                                        'conditions_array' => $conditions_array];
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic_errors'] = 'Group resource Status / Action Mismatch';
            return false;
        }

        $this->my_authority = $user_role;

        return true;
    
    }

}

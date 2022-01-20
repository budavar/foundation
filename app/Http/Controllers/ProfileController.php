<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

use Illuminate\Support\Facades\Log;

use App\Models\User;

class ProfileController extends API_Controller
{
    use \App\Traits\ImageHelpers;

    /**public function create(Request $request) {
        $this->v_object_function_authority_check = false;

        $this->v_422_rules = [  
            'name' => 'required|min:2|max:30|regex:/^[a-zA-Z][a-zA-Z ]+$/u', 
            'email' => 'required|email|unique:users', 
            'password' => ['required',
                            Password::min(10)
                                    ->letters()
                                    ->mixedCase()
                                    ->numbers()
                                    ->symbols()
                                    ->uncompromised()],
            'confirmpassword' => 'required|same:password'
        ];

        $this->_process_control($request, null, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }*/

    /**public function changePassword(Request $request) {
        $this->v_object_function_authority_check = false;
        $this->v_422_rules = [  
            //'currentPassword' => 'current_password', 
            'newPassword' => ['required',
                              Password::min(10)
                                        ->letters()
                                        ->mixedCase()
                                        ->numbers()
                                        ->symbols()
                                        ->uncompromised()],
            'confirmNewPassword' => 'required|same:newPassword', 
        ];

        $this->_process_control($request, null, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }*/

    public function updateMyAvatar(Request $request) {
        $this->v_object_function_authority_check = false;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Profile Avatar/Photo updated');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function updateMyProfile(Request $request) {
        $this->v_object_function_authority_check = false;

        $this->v_422_rules = [
            'name' => 'required|min:2|max:100|regex:/^[a-zA-Z][a-zA-Z .-]+$/u'
            //'timezone' => 'required|in:gmt,cet,eet,sgt,est'
        ];

        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Profile details updated');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    /**protected function p_create($request) {

        $user = new User;
        $user->name = $request->name; 
        $user->email = $request->email;
        $user->avatar = null;
        $user->type = 'user';
        $user->timezone = 'gmt';
        $user->password = Hash::make($request->password);
        $user->save();

        $this->response_payload['result'] = $user;

        return true;
    }*/

    /**protected function p_changePassword($request) {

        $user = User::find(auth::id());
        $user->password = Hash::make($request->newPassword);
        $user->update();

        $this->response_payload['status_details'] = 'password_changed';

        return true;
    }*/

    protected function p_updateMyAvatar($request) {

        $user = User::find(auth::id());
        $user->avatar = $this->saveSingleImage('profile_avatar', 'avatar_image', $request);
        $user->update();
        $this->response_payload['result'] = $user->avatar;

        return true;
    }

    protected function p_updateMyProfile($request) {

        $user = User::find(auth::id());
        $user->name = $request->name;
        //$user->timezone = $request->timezone;
        $user->update();
        $this->response_payload['result'] = $user;

        return true;
    }

}

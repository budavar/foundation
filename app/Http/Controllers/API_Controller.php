<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use App\HelperClasses\ActivityNotification;

use Validator;

class API_Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public $rest_200_ok = 200;
    public $rest_201_created = 201;
    public $rest_202_accepted = 202;
    public $rest_204_noContent = 204;

    public $rest_400_badRequest = 400;
    public $rest_401_unauthorised = 401;
    public $rest_403_forbidden = 403;
    public $rest_404_notFound = 404;
    public $rest_405_methodNotAllowed = 405;
    public $rest_406_notAcceptable = 406;
    public $rest_409_resourceStateConflict = 409;
    public $rest_415_unsupportedMediaType = 415;
    public $rest_422_unprocessableData = 422;
    public $rest_423_locked = 423;

    public $rest_500_systemError = 500;
    public $rest_501_invalidMethod = 501;

    public $rest_response = null;
    public $response_payload = null;

    protected $v_object_function_authority_check = true;
    protected $v_422_rules = [];
    protected $v_422_messages = [];
    protected $v_422_custom_rules = false;
    protected $v_4xx_validation = false;
    protected $p_2xx_status = 200;
    protected $p_2xx_status_details = null;
    protected $p_processing_data = [];

    protected $p_commit = true;

    protected $p_route_array = [];
    protected $p_route_object = null;
    protected $p_route_action = null;
    protected $p_route_model = null;

    protected $p_activity;

    protected function _process_control($request, $method, $rest_response_code, $rest_response_status) 
    {
        $this->p_route_array = explode('.', $request->route()->getName());
        $this->p_route_object = $this->p_route_array[0];
        $this->p_route_action = $this->p_route_array[1];
        $this->p_route_model = 'App\\Models\\' . ucfirst($this->p_route_object);

        $this->p_activity = new ActivityNotification($request->route()->getName());
    
        if ($this->v_object_function_authority_check) {
            if (!$this->object_function_authority_check($method)) {
                Log::info('======= FAILED OBJECT CHECK ======');
                return false;
            }
        }

        if ($this->v_422_rules || $this->v_422_custom_rules) {
            $validator = Validator::make($request->all(), $this->v_422_rules, $this->v_422_messages);
            if ($this->v_422_custom_rules) {  
                $validator->after(function ($validator) use ($method, $request){
                    $function = 'v_422_' . $method;
                    $errors = $this->$function($request);
                    if ($errors) {
                        foreach ($errors as $key => $error) {
                            $validator->errors()->add($key, $error);
                        }
                    }
                });
            }        
            if ($validator->fails()) { 
                $this->rest_response = $this->rest_422_unprocessableData;
                $this->response_payload['data_errors'] = $validator->errors();   
                return false;
            } 
        }

        if ($this->v_4xx_validation) {
            $function = 'v_4XX_' . $method;
            if (!$this->$function($request)) {
                if ($this->rest_response == null) {
                    $this->rest_response = $this->rest_406_notAcceptable;
                    $this->response_payload['logic-errors'] = 'unreported_issue';   
                }
                return false;
            }
        }

        DB::beginTransaction();
        try {

            $function = 'p_' . $method;
            if ($this->$function($request)) {

                $this->p_activity->publish();

                if ($this->p_commit) {              
                    DB::commit();  
                } else {
                    DB::rollback();
                }
                $this->rest_response = $rest_response_code;
                $this->response_payload['status_details'] = $rest_response_status;
            } else {
                if ($this->rest_response == null) {
                    $this->rest_response = $this->rest_409_resourceStateConflict;
                }
                DB::rollback();
            }
        
        } catch(\Exception $e) 
        {
            DB::rollback();
            Log::info('===========================================');
            Log::info($e);
            Log::info('===========================================');
            $this->rest_response = $this->rest_500_systemError;
            $this->response_payload['status_details'] = ['request' => $e->getMessage()];
            $this->response_payload['system_errors'] = $e; 
        };

        return true;
    }
}
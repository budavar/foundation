<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Models\Notification;

class NotificationController extends API_Controller
{
    private $notification_id;

    public function delete(Request $request, $notification_id) {
        $this->notification_id = $notification_id;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Notification deleted');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function markAllAsRead(Request $request) {
        $this->v_object_function_authority_check = false;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'All notifications marked as read');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function markAsRead(Request $request, $notification_id) {
        $this->notification_id = $notification_id;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, 'Notification marked as read');
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    public function list(Request $request) {
        $this->v_object_function_authority_check = false;
        $this->_process_control($request, __FUNCTION__, $this->rest_202_accepted, null);
        return Response()->json($this->response_payload, $this->rest_response); 
    }

    // PROCESSING LOGIC
  
    protected function p_delete(Request $request) {
        $this->notification->delete();
        $this->response_payload = $this->notification;
        return true;
    }

    protected function p_markAllAsRead(Request $request) {
        $rows = Notification::where('to_user_id', '=', Auth::id())->where('status', '=', 'unread')->update(['status' => 'read']);
        $this->response_payload ['rows_updated'] = $this->rows;
        return true;
    }

    protected function p_markAsRead(Request $request) {
        $this->notification->status = 'read';
        $this->notification->update();
        $this->response_payload = $this->notification;
        return true;
    }

    protected function p_list(Request $request) {
        sleep(1);
        $num_rows_per_fetch = 5;

        $start_row = 0;
        switch ($request->query('pagination')) {
            case 'init':
                $page = 0;
                $start_row = 0;
                break;
            case 'more':
                $page = $request->query('current_fetch');
                $start_row = ($page * $num_rows_per_fetch) + 1;
                break;
        };

        $notifications = Notification::with('action_user')
                                     ->where('to_user_id', '=', Auth::id())
                                     ->orderBy('created_at', 'desc')
                                     ->limit($num_rows_per_fetch + 1 )
                                     ->offset($start_row)
                                     ->get();

        $this->response_payload['current_fetch'] = $page + 1;
        $this->response_payload['more_rows'] = $notifications->count() > $num_rows_per_fetch ? true : false;
        $this->response_payload['notifications'] = $notifications->splice(0,$num_rows_per_fetch);

        return true;
    }

    protected function object_function_authority_check($action) {

        $this->notification = Notification::find($this->notification_id);

        // Valid group resource
        if (!$this->notification) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic_errors'] = 'Invalid notification resource identifier - ' . $this->notification_id;
            return false;
        }

        if ($this->notification->to_user_id != Auth::id()) {
            $this->rest_response = $this->rest_405_methodNotAllowed;
            $this->response_payload['logic_errors'] = 'You can only manage your own Notifications - ' . $this->notification_id;
            return false;
        }

        return true;
    }
}

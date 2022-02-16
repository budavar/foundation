<?php

namespace App\HelperClasses;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use App\Models\Activity;
use App\Models\Notification;
use App\Models\GroupMember;

class ActivityNotification
{
    private $activity;
    private $bypass_activity = false;
    private $controls;

    private $visibility;
    private $scoping_entity_type;
    private $scoping_entity_id;
    private $primary_entity_type;
    private $primary_entity_id;
    private $meta = [];

    private $n_main_user_type = null;
    private $n_user_list_type = null;
    private $n_to_main_user = null;
    private $n_to_user_list = null;
    private $n_to_user_list_key = null;
    private $n_main_user_deep_link = null;
    private $n_user_list_deep_link = null;

    public function __construct($action) {
        $this->setUp($action);
    }

    private function setUp($action) {
        $route_name_segments = explode('.', $action);
        $controls = config('route_meta_data.' . implode('-', $route_name_segments), config('route_meta_data._default'));
        $this->controls = json_decode(json_encode($controls));
        $this->activity = new Activity;
        $this->activity->type = $this->controls->activity->type;
        $this->visibility = $this->controls->activity->dft_visibility;
        $this->activity->publish_in_newsfeed = $this->controls->activity->publish_in_newsfeed;
        $this->activity->by_user_id = Auth::id();
        return;
    }

    public function __set($property, $value) {
        $meta_check = substr($property, 0, 5);
        if (property_exists($this, $property) && $meta_check == 'meta_') {
            $meta_attr = substr($property, 5);
            $this->$meta[$meta_attr] = $value;
        }
        if (property_exists($this, $property) && $meta_check != 'meta_') {
          $this->$property = $value;
        }
        return $this;
    }

    public function override($action) {
        $this->setUp($action);
    }

    public function bypass() {
        $this->bypass_activity = true;
    }

    public function publish() {

        if ($this->bypass_activity) {
            return;
        }

        if ($this->controls->activity->log === false) {
            return;
        }

        $this->activity->visibility = $this->visibility;
        $this->activity->scoping_entity_type = $this->scoping_entity_type;
        $this->activity->scoping_entity_id = $this->scoping_entity_id;
        $this->activity->primary_entity_type = $this->primary_entity_type;
        $this->activity->primary_entity_id = $this->primary_entity_id;
        $this->activity->meta = $this->meta == [] ? null : $this->meta;
        $this->activity->save();

        if ($this->controls->notification->log === false) {
            return;
        }

        if ($this->n_to_main_user != null) {
            $notification = new Notification;
            $notification->type = $this->n_main_user_type === null ? $this->controls->notification->type_main : $this->n_main_user_type;
            $notification->activity_id = $this->activity->id;
            $notification->to_user_id = $this->n_to_main_user;
            $notification->from_user_id = Auth::id();
            $notification->deeplink_url = $this->n_main_user_deep_link;
            $notification->read = false;
            $notification->save();
        }

        switch($this->n_to_user_list) {
            case null:
                return;
            case 'group-members':
                $this->n_to_user_list = $this->getGroupMembers();
                break;
            default:
                if (gettype($this->n_to_user_list) === 'string') {
                    $this->n_to_user_list = [$this->n_to_user_list];
                }
                break;
        }

        foreach($this->n_to_user_list as $user) {
            $notification = new Notification;
            $notification->type = $this->n_user_list_type === null ? $this->controls->notification->type_list : $this->n_user_list_type;
            $notification->activity_id = $this->activity->id;
            $notification->to_user_id = $user;
            $notification->from_user_id = Auth::id();
            $notification->deeplink_url = $this->n_user_list_deep_link;
            $notification->read = false;
            $notification->save();
        }
    }

    public function getGroupMembers() {

        $ids = GroupMember::select('user_id')->where('group_id' ,'=', $this->primary_entity_id)
                                             ->pluck('user_id')->toArray();

        return array_diff( $ids, [Auth::id(), $this->n_to_main_user] );
    }
}
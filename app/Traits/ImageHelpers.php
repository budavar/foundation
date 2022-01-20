<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Image;
use Illuminate\Support\Facades\Log;

trait ImageHelpers
{

    private $image_types = [
      'profile_avatar' => ['image_category' => 'profile', 'folder' => 'profile_images']
    ];

    private function getBase64Image($image_name) {
        return 'data:image/' . substr($image_name, strpos($image_name, ".") + 1)  . ';base64, ' . base64_encode(Storage::get($image_name));
    }

    private function removeImage($image_name) {
        Storage::delete($image_name, 'public');
        return;
    }

    private function saveSingleImage($image_class, $input_name, $request) {

        $path = $request->file($input_name)->store($this->image_types[$image_class] ['folder'], 'public');
        Log::info($path);

        $image = new Image;
        $image->user_id = Auth::id();
        $image->category = $this->image_types[$image_class] ['image_category'];
        $image->name = $path;
        $image->save();

        return $path;
    }

    private function imageHistory($old_image, $history) {

        if ($history == null) {
            return $old_image;
        } else {
            $images = explode($history, ',');
            if (count($images) < 5) {
                return $old_image . ',' . $history;
            } else {
              return $old_image . ',' 
                      . $images[0] . ','
                      . $images[1] . ','
                      . $images[2] . ','
                      . $images[3];
            }
        }
    }
}
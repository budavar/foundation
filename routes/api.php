<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;
//use App\Http\Controllers\AuthController;
//use App\Http\Controllers\UserController;
//use App\Http\Controllers\TokenController;
//use App\Http\Controllers\AvatarController;
//use App\Http\Controllers\MessageController;

//use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post('/sanctum/token', TokenController::class);

Route::middleware(['auth:sanctum'])->group(function () {

  Route::get('/users/auth', AuthController::class);
  Route::get('/users/{user}', [UserController::class, 'show']);
  Route::get('/users', [UserController::class, 'index']);

  Route::post('/users/auth/avatar', [AvatarController::class, 'store']);

  Route::post('/messages', [MessageController::class, 'store']);
  Route::get('/messages', [MessageController::class, 'index']);

  Route::put('/profiles/my-profile', [ProfileController::class, 'updateMyProfile'])->name('profile.update');
  Route::post('/profiles/my-avatar', [ProfileController::class, 'updateMyAvatar'])->name('profile.update-avatar');

  // FRIENDS
  Route::get('friends', [FriendController::class, 'list'])->name('friend.list');
  Route::get('friends/search', [FriendController::class, 'search'])->name('friend.search');
  Route::post('friends', [FriendController::class, 'sendFriendRequest'])->name('friend.sendRequest');
  Route::put('friends/accept/{id}', [FriendController::class, 'accept'])->name('friend.accept');
  Route::put('friends/block/{id}', [FriendController::class, 'block'])->name('friend.block');
  Route::delete('friends/delete/{id}', [FriendController::class, 'delete'])->name('friend.delete');

  // PEOPLE
  Route::get('people/my-friends', [PeopleController::class, 'myFriends'])->name('people.myFriends');
  Route::get('people/search', [PeopleController::class, 'search'])->name('people.search');

  // GROUPS
  Route::get('groups/search', [GroupController::class, 'search'])->name('group.search');
  Route::get('groups/{group_id}', [GroupController::class, 'retrieve'])->name('group.retrieve');
  Route::get('groups', [GroupController::class, 'mygroups'])->name('group.list');
  Route::post('groups/{group_id}', [GroupController::class, 'update'])->name('group.update');
  Route::post('groups', [GroupController::class, 'create'])->name('group.create');

  // GROUP MEMBERS
  Route::post('groups/{group_id}/new-member', [GroupMemberController::class, 'joinRequest'])->name('group.joinRequest');
  Route::delete('group-members/{group_member_id}', [GroupMemberController::class, 'remove'])->name('groupMember.remove');
  Route::put('group-members/{group_member_id}/activate', [GroupMemberController::class, 'activate'])->name('groupMember.activate');
  Route::put('group-members/{group_member_id}/block', [GroupMemberController::class, 'block'])->name('groupMember.block');
  Route::put('group-members/{group_member_id}/change-member-role', [GroupMemberController::class, 'changeRole'])->name('groupMember.chnageRole');


  //Route::put('groups/{group_id}/updateimage', [GroupController::class, 'update_image'])->name('group.update_image');
  //Route::put('groups/{group_id}', [GroupController::class, 'update'])->name('group.update');
  //Route::put('groups/{group_id}/open', [GroupController::class, 'open'])->name('group.open');
  //Route::put('groups/{group_id}/close', [GroupController::class, 'close'])->name('group.close');
  //Route::post('groups/{group_id}/like', [socialController::class, 'like'])->name('group.like');
  //Route::post('groups/{group_id}/unlike', [socialController::class, 'unlike'])->name('group.unlike');




});

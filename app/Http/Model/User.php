<?php

namespace App\Http\Model;


use Laravel\Passport\HasApiTokens;

use Illuminate\Notifications\Notifiable;

use Illuminate\Foundation\Auth\User as Authenticatable;

use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class User extends Authenticatable

{

  use HasApiTokens, Notifiable,HasRoles;

  protected $table = 'users';
  /**

  * The attributes that are mass assignable.

  *

  * @var array

  */

  protected $fillable = [

    'name', 'email', 'password','user_type','client_id'

  ];


  /**

  * The attributes that should be hidden for arrays.

  *

  * @var array

  */

  protected $hidden = [

    'password'

  ];
  /**
  * Get the user detail
  */
  public function getUserDetail()
  {
    return $this->hasOne('App\Http\Model\UserDetail', 'user_id', 'id');
  }
  /**
  * Get user type
  */
  public function getUserType()
  {
    return $this->belongsTo('App\Http\Model\Role', 'user_type', 'id');
  }

}

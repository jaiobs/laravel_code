<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class UsersWallet extends Model
{
  /**
  * The table associated with the model.
  *
  * @var string
  */
  protected $table = 'users_wallet';
  protected $primaryKey = 'wallet_id';

  protected $fillable = [

    'reference_id','user_id','payment_type','account_number','paid_amount','challan_copy','credit_status'
    , 'branch_name','payment_challan_date','created_by', 'updated_by','credited_at'

  ];
  /**
  * Get user
  */
  public function getWalletUserData()
  {
    return $this->belongsTo('App\Http\Model\User', 'user_id', 'id');
  }

  /**
  * Get the user detail
  */
  public function getWalletUserDetail()
  {
    return $this->belongsTo('App\Http\Model\UserDetail', 'user_id', 'user_id');
  }
}

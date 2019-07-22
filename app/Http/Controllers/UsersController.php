<?php


namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\Utility;
use App\Repositories\Admin\UserRepository;

class UsersController extends Controller

{

  use Utility;
  public function __construct(UserRepository $userRepository) {

    $this->userRepository = $userRepository;
  }

  /**
  * Get all users
  */
  public function getAllCustomers(){
    $post = $this->getData($_REQUEST);
    return $this->userRepository->getAllCustomers($post);
  }

  /**
  * Change user status
  */
  public function changeUserStatus(){
    $post = $this->getData($_REQUEST);
    return $this->userRepository->changeUserStatus($post);
  }

  /**
  * Approve user
  */
  public function approveCustomer(){
    $post = $this->getData($_REQUEST);
    return $this->userRepository->approveCustomer($post);
  }

  /**
  * Delete customer
  */
  public function deleteCustomer(){
    $post = $this->getData($_REQUEST);
    return $this->userRepository->deleteCustomer($post);
  }
  /**
  * get customers state and city list
  */
  public function getCustomerStateCity(){
    return $this->userRepository->getCustomerStateCity();
  }
  /**
  * create/edit sub admin
  */
  public function createSubAdmin(){
    $post = $this->getData($_REQUEST);
    $validator = Validator::make($post, [
      'name' => 'required'
      ,'mobile_number' => 'required|unique:user_details,mobile_number,'.$post['id'].',user_id,is_deleted,0'
      ,'email' => 'required|unique:users,email,'.$post['id'].',id,is_deleted,0'
      ,'user_type'=>'required'
    ]);

    if ($validator->fails()) {
      //email and mobile number unique validation
      return $this->getReponse(FALSE, 7, $validator->errors()->keys());
    }
    return $this->userRepository->createSubAdmin($post);
  }
  /**
  * get all sub admins
  */
  public function getAllSubAdmins(){
    $post = $this->getData($_REQUEST);
    return $this->userRepository->getAllSubAdmins($post);
  }
  /**
  * get all user roles
  */
  public function getSubAdminUserRoles(){
    $post = $this->getData($_REQUEST);
    return $this->userRepository->getSubAdminUserRoles($post);
  }
  /**
  * get subadmin data
  */
  public function getSubAdminData(){
    $post = $this->getData($_REQUEST);
    return $this->userRepository->getSubAdminData($post);
  }
}

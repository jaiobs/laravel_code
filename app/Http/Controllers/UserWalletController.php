<?php


namespace App\Http\Controllers\User;


use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\Utility;
use App\Repositories\User\UserWalletRepository;

class UserWalletController extends Controller

{

  use Utility;
  public function __construct(UserWalletRepository $userWalletRepository) {

    $this->userWalletRepository = $userWalletRepository;
  }

  /**

  * Save user payment

  *

  * @return \Illuminate\Http\Response

  */

  public function savePayment(){
    $post = $this->getData($_REQUEST);

    $validator = Validator::make(json_decode($post['paymentData'],true), [
      'account_number' => 'required'
      ,'paid_amount' => 'required'
      ,'branch_name' => 'required'
      ,'payment_challan_date' => 'required'
    ]);

    if ($validator->fails()) {
      return $this->getReponse(FALSE, 7, $validator->messages());
    }

    return $this->userWalletRepository->savePayment($post);
  }
  /**
  * get customer Banks
  */
  public function getCustomerBanks(){
    return $this->userWalletRepository->getCustomerBanks();
  }
  /**
  * User wallet list
  */
  public function getUserPaymentList(){
    $post = $this->getData($_REQUEST);
    return $this->userWalletRepository->getUserPaymentList($post);
  }
  /**
  * encrypt payment data
  */
  public function encryptPaymentData(){
    $post = $this->getData($_REQUEST);
    return $this->userWalletRepository->encryptPaymentData($post);
  }
  /**
  * receive payment response
  */
  public function paymentResponse(){
    $post = $this->getData($_REQUEST);
    return $this->userWalletRepository->paymentResponse($post);
  }
  /**
  * get branch name
  */
  public function getBranchName(){
    $post = $this->getData($_REQUEST);
    return $this->userWalletRepository->getBranchName($post);
  }/**
  * get Account Number
  */
  public function getAccountNumber(){
    $post = $this->getData($_REQUEST);
    return $this->userWalletRepository->getAccountNumber($post);
  }
  
}

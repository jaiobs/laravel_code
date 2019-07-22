<?php

namespace App\Repositories\User;
use App\Contracts\User\UserWalletInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use App\Http\Model\User;
use App\Http\Model\UsersWallet;
use App\Http\Model\Bank;
use App\Http\Model\UsersWalletAmount;
use App\Http\Model\UserDetail;
use App\Http\Model\UserAsset;
use App\Http\Traits\Utility;
use App\Http\Model\OnlinePayment;
use App\Notifications\UserApprovalNotification;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;
use Hash;
use Auth;
use File;
use Config;
use DB;
/**
* Decouples bussiness logic from object storage, manipulation and internal logic over Module entity.
*/
class UserWalletRepository implements UserWalletInterface
{
  use Utility;
  /**
  * constructor
  *
  */
  public function __construct(Auth $auth,User $user,UsersWallet $usersWallet,OnlinePayment $onlinePaymentModel
  ,UsersWalletAmount $usersWalletAmount,Bank $BanksModel,UserDetail $userDetailModel) {

    $this->auth     = $auth;
    $this->user     = $user;
    $this->BanksModel=$BanksModel;
    $this->userWallet=$usersWallet;
    $this->onlinePaymentModel=$onlinePaymentModel;
    $this->usersWalletAmount=$usersWalletAmount;
    $this->userDetailModel=$userDetailModel;
  }

  /**
  * get customer Banks
  */
  public function getCustomerBanks(){
    try {
      $BanksList=$this->BanksModel->select('bank_id','bankName')
      ->where(['status'=>0])->get();
      $BanksList=$BanksList->unique('bankName')->values()->all();
      if($BanksList){
        $this->errorLogCreate(class_basename(get_class($this)), 'api.getCustomerBanks' , 'info', 1, '',__LINE__);
        $response = $this->getReponse(TRUE,TRUE,$BanksList);
      }else{
        $this->errorLogCreate(class_basename(get_class($this)), 'api.getCustomerBanks' , 'error', 4, '',__LINE__);
        $response = $this->getReponse(FALSE, 4, FALSE);
      }
      return $response;
    } catch (\Exception $e) {
      $this->errorLogCreate(class_basename(get_class($this)), 'api.getCustomerBanks' , 'error', 6, $e->getTraceAsString(),__LINE__);
      return $response = $this->getReponse(FALSE, 6, $e->getMessage());
    }
  }

  /**
  * get branch name
  */
  public function getBranchName($post){
    try {
      if(!empty($post) && !empty($post['bankName'])){
        $bankName=trim($post['bankName']);
        $branchList=$this->BanksModel->select('bank_id','branchName')
        ->where(['bankName'=>$bankName,'status'=>0])->get();
        $branchList=$branchList->unique('branchName')->values()->all();
        if($branchList){
          $this->errorLogCreate(class_basename(get_class($this)), 'api.getBranchName' , 'info', 1, '',__LINE__);
          $response = $this->getReponse(TRUE,TRUE,$branchList);
        }else{
          $this->errorLogCreate(class_basename(get_class($this)), 'api.getBranchName' , 'error', 4, '',__LINE__);
          $response = $this->getReponse(FALSE, 4, FALSE);
        }
      }else{
        $this->errorLogCreate(class_basename(get_class($this)), 'api.getBranchName' , 'error', 3, '',__LINE__);
        $response = $this->getReponse(FALSE, 3, FALSE);
      }
      return $response;
    } catch (\Exception $e) {
      $this->errorLogCreate(class_basename(get_class($this)), 'api.getBranchName' , 'error', 6, $e->getTraceAsString(),__LINE__);
      return $response = $this->getReponse(FALSE, 6, $e->getMessage());
    }
  }

  /**
  * get Account Number
  */
  public function getAccountNumber($post){
    try {
      if(!empty($post) && !empty($post['branchName'])&& !empty($post['bankName'])){
        $branchName=trim($post['branchName']);
        $bankName=trim($post['bankName']);
        $accountNumberList=$this->BanksModel->select('bank_id','accountNumber')
        ->where(['bankName'=>$bankName,'branchName'=>$branchName,'status'=>0])->get();
        if($accountNumberList){
          $this->errorLogCreate(class_basename(get_class($this)), 'api.getAccountNumber' , 'info', 1, '',__LINE__);
          $response = $this->getReponse(TRUE,TRUE,$accountNumberList);
        }else{
          $this->errorLogCreate(class_basename(get_class($this)), 'api.getAccountNumber' , 'error', 4, '',__LINE__);
          $response = $this->getReponse(FALSE, 4, FALSE);
        }
      }else{
        $this->errorLogCreate(class_basename(get_class($this)), 'api.getAccountNumber' , 'error', 3, '',__LINE__);
        $response = $this->getReponse(FALSE, 3, FALSE);
      }
      return $response;
    } catch (\Exception $e) {
      $this->errorLogCreate(class_basename(get_class($this)), 'api.getAccountNumber' , 'error', 6, $e->getTraceAsString(),__LINE__);
      return $response = $this->getReponse(FALSE, 6, $e->getMessage());
    }
  }

  /**
  * save payment
  */
  public function savePayment($post){
    try {
      if(!empty($post)){
        $paymentData=json_decode($post['paymentData'],true);
        $loggedUserId=Auth::user()->id;
        $wallet=new UsersWallet;
        $paymentData['user_id']=$loggedUserId;
        $paymentData['created_by']=$loggedUserId;
        $paymentData['payment_challan_date']=(Carbon::parse($paymentData['payment_challan_date']));
        if(!empty($post['file'])){
          $imagepath = public_path()."/storage/assets/customer_challan";

          if(!File::exists($imagepath)) {
            $result = File::makeDirectory($imagepath, 0777, true);
          }
          $imagecontent = $post['file'];
          $imageName = time().str_replace(' ', '-', $imagecontent->getClientOriginalName());
          $challanCopy = new UserAsset;

          $challanCopy->file_name = $imageName;
          $challanCopy->file_size = $imagecontent->getSize();
          $challanCopy->file_path = '/storage/assets/customer_challan/'.$imageName;
          $challanCopy->save();
          $imagecontent->move($imagepath, $imageName);
          $paymentData['challan_copy']=$challanCopy->user_asset_id;
        }
        $walletListCount=$this->userWallet->count();
        if($walletListCount){
          $refId=$this->userWallet->orderBy('wallet_id','desc')->first();
          $paymentData['reference_id']=($refId->reference_id)+1;
        }else{
          $paymentData['reference_id']=Config::get('errorcode')['DEFAULT_WALLET_REF_ID'];
        }
        $wallet->fill($paymentData);
        if($wallet->save()){
          $this->errorLogCreate(class_basename(get_class($this)), 'api.savePayment' , 'info', 1, '',__LINE__);
          $response = $this->getReponse(TRUE,'ADD_PAYMENT_SUCCESS_MESSAGE',TRUE );
        }else{
          $this->errorLogCreate(class_basename(get_class($this)), 'api.savePayment' , 'error', 5, '',__LINE__);
          $response = $this->getReponse(FALSE, 5, FALSE);
        }
      }else{
        $this->errorLogCreate(class_basename(get_class($this)), 'api.savePayment' , 'error', 3, '',__LINE__);
        $response = $this->getReponse(FALSE, 3, FALSE);
      }
      return $response;
    } catch (\Exception $e) {
      $this->errorLogCreate(class_basename(get_class($this)), 'api.savePayment' , 'error', 6, $e->getTraceAsString(),__LINE__);
      return $response = $this->getReponse(FALSE, 6, $e->getMessage());
    }
  }

  /**
  * User wallet list
  */
  public function getUserPaymentList($post){
    try {
      $loggedUserId=Auth::user()->id;
      $skip=$post['pageNumber']*$post['pageSize'];
      $limit=$post['pageSize']; 
      $paymentList=DB::table('users_wallet')
      ->leftjoin('banks','banks.bank_id','=','users_wallet.account_number')
      ->where(['users_wallet.user_id'=>$loggedUserId,'banks.status'=>0])
      ->select('users_wallet.wallet_id','users_wallet.reference_id','users_wallet.paid_amount','users_wallet.payment_challan_date','users_wallet.credit_status','banks.accountNumber as account_number','banks.branchName as branch_name','banks.bankName as bank_name')
      ->skip($skip)->take($limit)->orderBy('users_wallet.wallet_id', 'desc')->get();
      $paymentCount=$this->userWallet->where(['user_id'=>$loggedUserId])->count();
      $payment['paymentList']=$paymentList;
      $payment['totalCount']=$paymentCount;
      if($payment){
        $this->errorLogCreate(class_basename(get_class($this)), 'api.getUserPaymentList' , 'info', 1, '',__LINE__);
        $response = $this->getReponse(TRUE, TRUE, $payment);
      }else{
        $this->errorLogCreate(class_basename(get_class($this)), 'api.getUserPaymentList' , 'error', 4, '',__LINE__);
        $response = $this->getReponse(FALSE, 4, FALSE);
      }
      return $response;
    } catch (\Exception $e) {
      $this->errorLogCreate(class_basename(get_class($this)), 'api.getUserPaymentList' , 'error', 6, $e->getTraceAsString(),__LINE__);
      return $response = $this->getReponse(FALSE, 6, $e->getMessage());
    }
  }

  /**
  * encrypt payment data
  */
  public function encryptPaymentData($post){
    $merchant_data='';
    $working_key=env('CCAVENUE_WORKING_KEY');//Shared by CCAVENUES
    foreach ($post as $key => $value){
      $merchant_data.=$key.'='.$value.'&';
    }
    $encrypted_data=$this->encrypt($merchant_data,$working_key); // Method for encrypting the data.
    return $encrypted_data;
  }

  /**
  * payment response
  */
  public function paymentResponse($post){
    $workingKey=env('CCAVENUE_WORKING_KEY');	//Working Key should be provided here.
    $encResponse=$post["encResp"];			//This is the response sent by the CCAvenue Server
    $rcvdString=$this->decrypt($encResponse,$workingKey);		//Crypto Decryption used as per the specified working key.
    $order_status="";
    $decryptValues=explode('&', $rcvdString);
    $dataSize=sizeof($decryptValues);
    $paymentStatus=explode('=',$decryptValues[3]);

    $payment=new OnlinePayment;
    for($i = 0; $i < $dataSize; $i++)
    {
      $information=explode('=',$decryptValues[$i]);
      $data=$information[1];
      switch($i){
        case 1:
        $payment->tracking_id=$data;
        break;
        case 2:
        $payment->bank_ref_number=$data;
        break;
        case 3:
        $payment->status=$data;
        break;
        case 4:
        $payment->failure_message=$data;
        break;
        case 5:
        $payment->payment_mode=$data;
        break;
        case 6:
        $payment->card_name=$data;
        break;
        case 10:
        $amountPaid=$data;
        break;
        case 27:
        $payment->user_id=$data;
        break;
        default:
        break;
      }
    }
    $payment->save();
    if($paymentStatus[1]=='Success'){
      $postData['payment_type']=Config::get('errorcode')['PAYMENT_MODE'][1]['paymentModeId'];
      $postData['user_id']=$payment->user_id;
      $postData['created_by']=$payment->user_id;
      $postData['payment_challan_date']=date('Y-m-d H:i:s');
      $postData['credited_at']=date('Y-m-d H:i:s');
      $postData['credit_status']=1;
      $postData['account_number']=Config::get('errorcode')['ACCOUNT_NUMBERS'][0]['accountId'];//change
      $postData['branch_name']='Kk Nagar';//change
      $postData['paid_amount']=$amountPaid;
      $wallet=new UsersWallet;
      $walletListCount=$this->userWallet->count();
      if($walletListCount){
        $refId=$this->userWallet->orderBy('wallet_id','desc')->first();
        $postData['reference_id']=($refId->reference_id)+1;
      }else{
        $postData['reference_id']=Config::get('errorcode')['DEFAULT_WALLET_REF_ID'];
      }
      $wallet->fill($postData);
      $wallet->save();
      $payment->wallet_id=  $wallet->wallet_id;
      $payment->save();
      $amountInWallet=$this->usersWalletAmount->where(['user_id'=>$wallet->user_id])->first();
      if(empty($amountInWallet)){
        $amountInWallet=new UsersWalletAmount;
      }
      $amountInWallet->user_id=$wallet->user_id;
      $amountInWallet->amount_in_wallet+=$wallet->paid_amount;

      if($amountInWallet->save()){
        $creditMessage=Config::get('errorcode')['WALLET_CREDIT_SMS'];
        $creditMessage=str_replace('{{CURRENTBALANCE}}',$amountInWallet->amount_in_wallet,$creditMessage);
        $creditMessage=str_replace('{{CREDITEDAMOUNT}}',$payment->paid_amount,$creditMessage);
        $this->sendSms($amountInWallet->getUserDetail->mobile_number, $creditMessage);
      }
    }
    $this->errorLogCreate(class_basename(get_class($this)), 'api.paymentResponse' , 'info', 1, '',__LINE__);
    $response = $this->getReponse(TRUE, 1, TRUE);
    return Redirect::to(env('FRONT_END_URL').'users/paymentredirect/'.$payment->status.'/'.$payment->tracking_id);
  }
}
?>

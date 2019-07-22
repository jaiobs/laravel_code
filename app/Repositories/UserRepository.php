<?php

namespace App\Repositories\Admin;
use App\Contracts\Admin\UserInterface;
use Illuminate\Http\Request;
use App\Http\Model\User;
use App\Http\Model\UserDetail;
use App\Http\Model\UserAsset;
use App\Http\Model\Orders;
use App\Http\Model\OrderStatus;
use App\Http\Traits\Utility;
use App\Http\Traits\ZohoInvoice;
use App\Http\Model\State;
use App\Http\Model\Role;
use App\Http\Model\ZohoCustomerId;
use Hash;
use Auth;
use Config;
use File;
use DB;

/**
* Decouples bussiness logic from object storage, manipulation and internal logic over Module entity.
*/
class UserRepository implements UserInterface
{
	use Utility;
	use ZohoInvoice;
	/**
	* constructor
	*
	*/
	public function __construct(Auth $auth,User $user,UserDetail $userDetail,UserAsset $userAsset
	,State $stateModel,Role $rolesModel,ZohoCustomerId $zohoCustomerModel,Orders $ordersModel,OrderStatus $orderStatusModel) {

		$this->auth     = $auth;
		$this->user     = $user;
		$this->userDetail=$userDetail;
		$this->userAsset=$userAsset;
		$this->stateModel=$stateModel;
		$this->rolesModel=$rolesModel;
		$this->zohoCustomerModel=$zohoCustomerModel;
		$this->orderStatusModel=$orderStatusModel;
		$this->ordersModel=$ordersModel;
	}

	/**
	* Get all users
	*/
	public function getAllCustomers($post){
		try {
			$skip=$post['pageNumber']*$post['pageSize'];
			$limit=$post['pageSize'];

			$subquery=DB::table('users')
			->leftjoin('user_details', 'user_details.user_id', '=', 'users.id')
			->where(['users.is_deleted'=>0,'users.user_type'=>Config::get('errorcode')['CUSTOMER_USER_ID']]);

			if(!empty($post['filterState'])){
				$subquery=$subquery->where(['user_details.state_id'=>$post['filterState']]);
			}
			if(!empty($post['filterCity'])){
				$subquery=$subquery->where(['user_details.city'=>$post['filterCity']]);
			}
			if(!empty($post['searchUser'])){
				$searchText=$post['searchUser'];
				$subquery=$subquery->where(function ($query) use ($searchText) {
					$query->where('users.client_id','like','%' .$searchText. '%')
					->orWhere('users.email','like','%' .$searchText. '%')
					->orWhere('users.name','like','%' .$searchText. '%')
					->orWhere('user_details.company_name','like','%' .$searchText. '%')
					->orWhere('user_details.mobile_number','like','%' .$searchText. '%');
				});
			}

			$data['totalCount']=$subquery->count();

			$userData=$subquery->select('users.id','users.name','users.client_id','users.email'
			,'users.is_approved','users.is_active','users.is_deleted','users.user_type','user_details.company_name'
			,'user_details.mobile_number')->skip($skip)->take($limit)->orderBy('users.id','desc')->get();
			$data['userList']=$userData;
			if($data){
				$this->errorLogCreate(class_basename(get_class($this)), 'api.getAllCustomers' , 'info', 1, '',__LINE__);
				$response = $this->getReponse(TRUE, 1, $data);
			}else{
				$this->errorLogCreate(class_basename(get_class($this)), 'api.getAllCustomers' , 'error', 4, '',__LINE__);
				$response = $this->getReponse(FALSE, 4, FALSE);
			}
			return $response;
		} catch (\Exception $e) {
			$this->errorLogCreate(class_basename(get_class($this)), 'api.getAllCustomers' , 'error', 6, $e->getTraceAsString(),__LINE__);
			return $response = $this->getReponse(FALSE, 6, $e->getMessage());
		}
	}
	/**
	* Change user status
	*/
	public function changeUserStatus($post){
		try {
			if(!empty($post)&& !empty($post['userId'])){
				$user=$this->user->find($post['userId']);
				if($user){
					if($user->is_active){
						$statusUpdate=	$this->user->where(['id'=>$post['userId']])->update(['is_active'=>0]);
						$successMessage='USER_DEACTIVATED';
					}else{
						$statusUpdate=	$this->user->where(['id'=>$post['userId']])->update(['is_active'=>1]);
						$successMessage='USER_ACTIVATED';
					}
					$this->errorLogCreate(class_basename(get_class($this)), 'api.changeUserStatus' , 'info', 1, '',__LINE__);
					$response = $this->getReponse(TRUE, $successMessage, TRUE);
				}else{
					$this->errorLogCreate(class_basename(get_class($this)), 'api.changeUserStatus' , 'error', 4, '',__LINE__);
					$response = $this->getReponse(FALSE, 4, FALSE);
				}
			}else{
				$this->errorLogCreate(class_basename(get_class($this)), 'api.changeUserStatus' , 'error', 3, '',__LINE__);
				$response = $this->getReponse(FALSE, 3, FALSE);
			}
			return $response;
		} catch (\Exception $e) {
			$this->errorLogCreate(class_basename(get_class($this)), 'api.changeUserStatus' , 'error', 6, $e->getTraceAsString(),__LINE__);
			return $response = $this->getReponse(FALSE, 6, $e->getMessage());
		}
	}

	/**
	* Approve user
	*/
	public function approveCustomer($post){
		try {
			if(!empty($post)&& !empty($post['userId'])){
				$user=$this->user->find($post['userId']);
				if($user){
					$password=str_random(8);
					$hashPassword = Hash::make($password);
					$statusUpdate =	$this->user->where(['id'=>$post['userId'],'is_approved'=>0])->update(['is_approved'=>1,'password'=>$hashPassword,'is_active'=>1]);
					if($statusUpdate){
						$userDetail=$this->userDetail->where(['user_id'=>$user->id])->first();
						$zohoCustomer=$this->createInvoiceContact($user,$userDetail);
						if(!empty($zohoCustomer['contact'])){
							$zoho=new ZohoCustomerId;
							$zoho->user_id=$user->id;
							$zoho->zoho_customer_id=$zohoCustomer['contact']['contact_id'];
							$zoho->save();
						}
						$subject='Your account has been approved';
						$this->sendEmail('userApprovalEmailTemplate', ['password' =>$password,'email'=>$user->email,'name'=>$user->name
						,'client_id'=>$user->client_id
						,'loginUrl'=>env('FRONT_END_URL').'users/login'], env('MAIL_FROM'), $user->email, $subject);
						$approveMessage=Config::get('errorcode')['APPROVE_SMS'];
						$approveMessage=str_replace('{{CLIENTID}}', $user->client_id,$approveMessage);
						$approveMessage=str_replace('{{PASSWORD}}', $password,$approveMessage);
						$this->sendSms($user->getUserDetail->mobile_number, $approveMessage);
					}
					$this->errorLogCreate(class_basename(get_class($this)), 'api.approveCustomer' , 'info', 1, '',__LINE__);
					$response = $this->getReponse(TRUE, 'USER_APPROVE_MESSAGE', TRUE);

				}else{
					$this->errorLogCreate(class_basename(get_class($this)), 'api.approveCustomer' , 'error', 4, '',__LINE__);
					$response = $this->getReponse(FALSE, 4, FALSE);
				}
			}else{
				$this->errorLogCreate(class_basename(get_class($this)), 'api.approveCustomer' , 'error', 3, '',__LINE__);
				$response = $this->getReponse(FALSE, 3, FALSE);
			}
			return $response;
		} catch (\Exception $e) {
			$this->errorLogCreate(class_basename(get_class($this)), 'api.approveCustomer' , 'error', 6, $e->getTraceAsString(),__LINE__);
			return $response = $this->getReponse(FALSE, 6, $e->getMessage());
		}
	}
		/**
	* Delete customer
	*/
	public function deleteCustomer($post){
		try {
			if(!empty($post) && !empty($post['userId'])){
				$orderData=$this->ordersModel
				->select('order_id','order_status_id')
				->where(['user_id'=>$post['userId'],'status'=>0])
				->whereIn('order_status_id', [2,6,7])
				->get();
				$userOrders=$orderData->count();
				if(!($userOrders))
				{
					$userData=$this->user->where('id',$post['userId'])->update(['is_deleted' => Config::get('errorcode')['CUSTOMER_DELETED']]);
					$this->userDetail->where('user_id',$post['userId'])->update(['is_deleted' => Config::get('errorcode')['CUSTOMER_DELETED']]);
					$userType=$this->user->find($post['userId'])->user_type;
					if($userType==Config::get('errorcode')['CUSTOMER_USER_ID']){
						$successMessage='CUSTOMER_DELETE_MESSAGE';
					}else{
						$successMessage='SUBADMIN_DELETE_MESSAGE';
					}
					if($userData){
						$this->errorLogCreate(class_basename(get_class($this)), 'api.deleteCustomer' , 'info', $successMessage, '',__LINE__);
						$response = $this->getReponse(TRUE, $successMessage, TRUE);
					}else{
						$this->errorLogCreate(class_basename(get_class($this)), 'api.deleteCustomer' , 'error', 4, '',__LINE__);
						$response = $this->getReponse(FALSE, 4, FALSE);
					}
				}else{
					$this->errorLogCreate(class_basename(get_class($this)), 'api.deleteCustomer' , 'error', 11, '',__LINE__);
					$response = $this->getReponse(FALSE, 11, FALSE);
				}											
				}else
				{
					$this->errorLogCreate(class_basename(get_class($this)), 'api.deleteCustomer' , 'error', 3, '',__LINE__);
					$response = $this->getReponse(FALSE, 3, FALSE);
				}
			return $response;
		} catch (\Exception $e) {
			$this->errorLogCreate(class_basename(get_class($this)), 'api.deleteCustomer' , 'error', 6, $e->getTraceAsString(),__LINE__);
			return $response = $this->getReponse(FALSE, 6, $e->getMessage());
		}
	}
	/**
	* get customers state and city list
	*/
	public function getCustomerStateCity(){
		try {
			$usersList=$this->user->where('is_deleted',0)
			->where(['user_type'=>Config::get('errorcode')['CUSTOMER_USER_ID']])//exclude admin user
			->orderBy('id','desc')->get();
			$states=collect();
			$cities=collect();
			foreach ($usersList as $key => $value) {
				$userDetail=$value->getUserDetail;
				if(!empty($userDetail->state_id)){
					$userState=$userDetail->getStateName;
					$stateList=collect();
					$stateId=$userState->state_id;
					$stateName=$userState->state;
					$stateList->put('stateId',$stateId);
					$stateList->put('stateName',$stateName);
					$states->push($stateList);
				}
				$cityList=collect();
				if(!empty($value->getUserDetail->city)){
					$cityName=ucwords(strtolower($value->getUserDetail->city));
					$cityList->put('city',trim($cityName));
					$cities->push($cityList);
				}
			}
			$uniqueStates=$states->unique('stateId');
			$list['stateList']=$uniqueStates->values()->all();
			$uniqueCities=$cities->unique('city');
			$list['cityList']=$uniqueCities->values()->all();
			if($list){
				$this->errorLogCreate(class_basename(get_class($this)), 'api.getCustomerStateCity' , 'info', 1, '',__LINE__);
				$response = $this->getReponse(TRUE, TRUE, $list);
			}else{
				$this->errorLogCreate(class_basename(get_class($this)), 'api.getCustomerStateCity' , 'error', 4, '',__LINE__);
				$response = $this->getReponse(FALSE, 4, FALSE);
			}
			return $response;
		} catch (\Exception $e) {
			$this->errorLogCreate(class_basename(get_class($this)), 'api.getCustomerStateCity' , 'error', 6, $e->getTraceAsString(),__LINE__);
			return $response = $this->getReponse(FALSE, 6, $e->getMessage());
		}

	}
	/**
	* create/edit subadmin
	*/
	public function createSubAdmin($post){
		try {
			if(!empty($post)){
				if(!empty($post['id'])){
					$userModel=$this->user->find($post['id']);
					$userModel['name']=$post['name'];
					$userModel['user_type']=$post['user_type'];
					$userModel->save();
					$successMessage='SUBADMIN_UPDATED_MESSAGE';
				}else{
					$post['is_approved']=1;
					$post['is_active']=1;
					$password=str_random(8);
					$hashPassword = Hash::make($password);
					$post['password']=$hashPassword;
					$userModel = $this->user->create($post);
					$userId=$userModel->id;
					$userDetail['user_id']=$userId;
					$userDetail['mobile_number']=$post['mobile_number'];
					$userDetails=$this->userDetail->create($userDetail);
					$subject='Your account has been created';
					$this->sendEmail('createSubadminEmailTemplate', ['password' =>$password,'email'=>$userModel->email
					,'name'=>$userModel->name,'loginUrl'=>env('FRONT_END_URL').'admin/login']
					, env('MAIL_FROM'), $userModel->email, $subject);
					$successMessage='SUBADMIN_CREATED_MESSAGE';
				}

				if($userModel){
					$this->errorLogCreate(class_basename(get_class($this)), 'api.createSubAdmin' , 'info', 1, $userModel,__LINE__);
					$response = $this->getReponse(TRUE,$successMessage, TRUE);
				}else{
					$this->errorLogCreate(class_basename(get_class($this)), 'api.createSubAdmin' , 'error', 5, $userModel,__LINE__);
					$response = $this->getReponse(FALSE, 5, FALSE);
				}
			}else{
				$this->errorLogCreate(class_basename(get_class($this)), 'api.createSubAdmin' , 'error', 3, '',__LINE__);
				$response = $this->getReponse(FALSE, 3, FALSE);
			}
			return $response;
		} catch (\Exception $e) {
			$this->errorLogCreate(class_basename(get_class($this)), 'api.createSubAdmin' , 'error', 6, $e->getTraceAsString(),__LINE__);
			return $response = $this->getReponse(FALSE, 6, $e->getMessage());
		}

	}
	/**
	* get all sub admins
	*/
	public function getAllSubAdmins($post){
		try {
			if(!empty($post)){
				$skip=$post['pageNumber']*$post['pageSize'];
				$limit=$post['pageSize'];

				$subquery=DB::table('users')
				->leftjoin('user_details', 'users.id', '=', 'user_details.user_id')
				->leftjoin('roles', 'users.user_type', '=', 'roles.id')
				->where('users.user_type','!=',Config::get('errorcode')['CUSTOMER_USER_ID'])
				->where('users.user_type','!=',Config::get('errorcode')['ADMIN_USER_ID'])
				->where('users.is_deleted',0);

				if(!empty($post['searchText'])){
					$searchText=$post['searchText'];
					$subquery=$subquery->where(function ($query) use ($searchText) {
						$query->where('users.name','like','%' .$searchText. '%')
						->orWhere('users.email','like','%' .$searchText. '%')
						->orWhere('user_details.mobile_number','like','%' .$searchText. '%')
						->orWhere('roles.name','like','%' .$searchText. '%');
					});
				}
				$data['totalCount']=$subquery->count();

				$userData=$subquery->select('users.id','users.name','users.email','user_details.mobile_number','roles.name as user_role')
				->skip($skip)->take($limit)->orderBy('id','desc')->get();
				$data['subadminList']=$userData;
				if($data){
					$this->errorLogCreate(class_basename(get_class($this)), 'api.getAllSubAdmins' , 'info', 1, '',__LINE__);
					$response = $this->getReponse(TRUE, 1, $data);
				}else{
					$this->errorLogCreate(class_basename(get_class($this)), 'api.getAllSubAdmins' , 'error', 4, '',__LINE__);
					$response = $this->getReponse(FALSE, 4, FALSE);
				}
			}else{
				$this->errorLogCreate(class_basename(get_class($this)), 'api.getAllSubAdmins' , 'error', 3, '',__LINE__);
				$response = $this->getReponse(FALSE, 3, FALSE);
			}

			return $response;
		} catch (\Exception $e) {
			$this->errorLogCreate(class_basename(get_class($this)), 'api.getAllSubAdmins' , 'error', 6, $e->getTraceAsString(),__LINE__);
			return $response = $this->getReponse(FALSE, 6, $e->getMessage());
		}
	}
	/**
	* get all user roles
	*/
	public function getSubAdminUserRoles(){
		try {
			$userRoles=$this->rolesModel->select('id','name')
			->where('id','!=',Config::get('errorcode')['CUSTOMER_USER_ID'])
			->where('id','!=',Config::get('errorcode')['ADMIN_USER_ID'])->get();
			if($userRoles){
				$this->errorLogCreate(class_basename(get_class($this)), 'api.getSubAdminUserRoles' , 'info', 1, '',__LINE__);
				$response = $this->getReponse(TRUE, TRUE, $userRoles);
			}else{
				$this->errorLogCreate(class_basename(get_class($this)), 'api.getSubAdminUserRoles' , 'error', 3, '',__LINE__);
				$response = $this->getReponse(FALSE, 3, FALSE);
			}
			return $response;
		} catch (\Exception $e) {
			$this->errorLogCreate(class_basename(get_class($this)), 'api.getSubAdminUserRoles' , 'error', 6, $e->getTraceAsString(),__LINE__);
			return $response = $this->getReponse(FALSE, 6, $e->getMessage());
		}

	}
	/**
	* get subadmin data
	*/
	public function getSubAdminData($post){
		try {
			if(!empty($post) && !empty($post['userId'])){
				$data=$this->user->find($post['userId']);
				$data['mobile_number']=$data->getUserDetail->mobile_number;
				unset($data['getUserDetail']);
				if($data){
					$this->errorLogCreate(class_basename(get_class($this)), 'api.getSubAdminData' , 'info', 1, '',__LINE__);
					$response = $this->getReponse(TRUE, 1, $data);
				}else{
					$this->errorLogCreate(class_basename(get_class($this)), 'api.getSubAdminData' , 'error', 4, '',__LINE__);
					$response = $this->getReponse(FALSE, 4, FALSE);
				}
			}else{
				$this->errorLogCreate(class_basename(get_class($this)), 'api.getSubAdminData' , 'error', 3, '',__LINE__);
				$response = $this->getReponse(FALSE, 3, FALSE);
			}

			return $response;
		} catch (\Exception $e) {
			$this->errorLogCreate(class_basename(get_class($this)), 'api.getSubAdminData' , 'error', 6, $e->getTraceAsString(),__LINE__);
			return $response = $this->getReponse(FALSE, 6, $e->getMessage());
		}
	}
}
?>

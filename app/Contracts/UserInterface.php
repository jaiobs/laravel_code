<?php

namespace App\Contracts\Admin;

interface UserInterface
{
	function getAllCustomers($post);
	function approveCustomer($post);
	function changeUserStatus($post);
	function deleteCustomer($post);
	function getCustomerStateCity();
	function createSubAdmin($post);
	function getAllSubAdmins($post);
	function getSubAdminUserRoles();
	function getSubAdminData($post);
}

<?php

namespace App\Contracts\User;

interface UserWalletInterface
{
  function savePayment($post);
  function getCustomerBanks();
  function getBranchName($post);
  function getAccountNumber($post);
  function getUserPaymentList($post);
  function encryptPaymentData($post);
  function paymentResponse($post);
}

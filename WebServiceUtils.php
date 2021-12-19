<?php

$DDDD = dirname(__DIR__);
//var_dump($DDDD."/dmbe/Rest/RestTest.class.php");
//die;
require_once $DDDD."/dmbe/Rest/RestTest.class.php";
require_once $DDDD."/dmbe/Rest/RestDmbeApi.class.php";
require_once $DDDD."/dmbe/Rest/RestApiUsersAdmin.class.php";
require_once $DDDD."/dmbe/Rest/RestApiOnBoardingAdmin.class.php";
require_once $DDDD."/dmbe/dmbeapi/OnbordingDevice.class.php";
require_once $DDDD."/dmbe/userdeviceapi/UserDevice.class.php";

class WebServiceUtils {

    const ADMIN_CONCENT_REDIRECTURL = "https://dm.customers.audiocodesaas.com/sa/";

    public static function getOnboardingDevice($DeviceId) {
      return OnbordingDevice::getDevice($DeviceId);
    }

    public static function getOnboardingDevicesByMac($DeviceId) {
      return OnbordingDevice::getDevicesByMac($DeviceId);
    }

    public static function getPairedDevice($DeviceId) {
      return UserDevice::getDevice($DeviceId);
    }


    public static function getPairedDevicesByMac($mac,$DeviceId) {
      return UserDevice::getDevicesByMac($mac,$DeviceId);
    }

    ///dmEdge/api/142F9BE8-008B-4E60-890D-3F078FE9ACA3/healthCheck
    public static function healthCheck() {
      $client = RestDmbeApi::healthCheck();
      $error = "";
      $res = RestDmbeApi::getInstance()->getResult($client, $error);
      if($res['http_code'] == 200){
        $response = $client->response;
        if(empty($response)){
          $res['data'] = null;
        }else{
          $all =  json_decode($response, true);
          $res['data'] = $all;
        }
      }
      return isset($res['data']) ? $res['data'] : "";
      //return WebServiceUtils::getDeviceByField(ProvDeviceOptions::DeviceId,$DeviceId);
    }

    


    public static function findDevice($DeviceId,$onboardingFirst = false) {
     
      if(empty($DeviceId))
        return array();

      if($onboardingFirst)
      { 
        //need to search on Onboarding!!!
        $provDevice = WebServiceUtils::getOnboardingDevice($DeviceId);
        if(empty($provDevice)) //not found on Onboarding, find on paired
          $provDevice = WebServiceUtils::getPairedDevice($DeviceId);
        }
        else
        {
          //When DM search in the DB and see that in the OB container the state is paired - it will delete the entry from the DB and will load the data from the user container.
          if($provDevice[BaseDevicePropEnum::DeviceStatus] == DeviceStatusEnum::Paired)
          {
            $res = RestDmbeApi::getInstance()->deleteDevice($DeviceId);
            write_spot_log("onboarding device state is paired - delete it ".json_encode($res),EasyLogger::DEBUGING);
            return WebServiceUtils::getPairedDevice($DeviceId);
          }
          
        }  
      }else
      {
        //need to search on paired!!!
        $provDevice = WebServiceUtils::getPairedDevice($DeviceId);
        if(empty($provDevice)) //not found on pair, find on onboarding
          $provDevice = WebServiceUtils::getOnboardingDevice($DeviceId);
      }
              
      return $provDevice;
    }



    public static function findDevicesByMac($mac,$DeviceId) {
     
      if(empty($mac))
        return array();

      //need to search on Onboarding!!!
      $provOnboardingDevices = WebServiceUtils::getOnboardingDevicesByMac($mac,$DeviceId);
      $provPairedDevices = WebServiceUtils::getPairedDevicesByMac($mac,$DeviceId);

      $allDevices = array_merge($provOnboardingDevices,$provPairedDevices);

      foreach($allDevices as $key => $provDevice)
      {
        //for eachWhen DM search in the DB and see that in the OB container the state is paired - it will delete the entry from the DB and will load the data from the user container.
        if($provDevice[BaseDevicePropEnum::DeviceStatus] == DeviceStatusEnum::Paired)
        {
          $res = RestDmbeApi::getInstance()->deleteDevice($provDevice[BaseDevicePropEnum::DeviceId]);
          write_spot_log("ATA - onboarding device state is paired - delete it ".json_encode($res),EasyLogger::DEBUGING);
          unset($allDevices[$key]);
        }
        
      }  
              
      return $allDevices;
    }

    public static function getPairedInfoBySdhDeviceId($SdhDeviceId) {
      return RestDmbeApi::getPairedInfoBySdhDeviceId($SdhDeviceId);
    }

    

    public static function pairDevice($token, $code,$SipPassword) {
      return RestDmbeApi::getInstance($token)->pairDevice( $code,$SipPassword);
    }


    public static function getAdminConsentURL($token) {
      return RestDmbeApi::getAdminConsentURL($token);
    }

    public static function createDevice($device) {

        $arr = array();

        $arr[BaseDevicePropEnum::MacAddress] = $device->getMongoMacAddress();
        $arr[BaseDevicePropEnum::PairCode] = $device->getPairCode();
        $arr[BaseDevicePropEnum::DeviceStatus] = DeviceStatusEnum::OnBoarding;
        $arr[BaseDevicePropEnum::DeviceId] = $device->getDeviceId();
        $arr[BaseDevicePropEnum::SdhDeviceId] = $device->getSdhDeviceId();
        $arr[BaseDevicePropEnum::TenantId] = $device->getTenantId();
        $arr[BaseDevicePropEnum::SipAuthName] = $device->getSIPUserName();
        $arr[BaseDevicePropEnum::SipPassword] =$device->getSIPPassword();
        $arr[BaseDevicePropEnum::SipDisplayName] = $device->getDisplayName();
        $arr[BaseDevicePropEnum::DeviceType] = $device->getModel();
        $arr[BaseDevicePropEnum::SwVersion] = $device->getSwVersion();
        $arr[BaseDevicePropEnum::HttpsGlobalIp] = $device->getDeviceIpAddr();
        $arr[BaseDevicePropEnum::HttpUserAgent] = $device->getUserAgent();
        $arr[BaseDevicePropEnum::AnalogDevicePort] = $device->getAnalogDevicePort();
        $arr[BaseDevicePropEnum::Vendor] =  $device->getMake();

        $arr[BaseDevicePropEnum::IPEI] =  $device->getIPEI();
                   
        $arr[BaseDevicePropEnum::Aor] = $device->getAOR();
        $arr[BaseDevicePropEnum::IsMwiSupported] = $device->isMwiSupported();

        $data =  OnbordingDevice::add($arr);

        return $data;
    }

    public static function createDeviceFromArr($arr) {

      unset($arr[BaseDevicePropEnum::PairUrl]);
      unset($arr[BaseDevicePropEnum::PairCode]);
      unset($arr[BaseDevicePropEnum::PairTime]);
      unset($arr[BaseDevicePropEnum::SipContact]);
      unset($arr[BaseDevicePropEnum::SipContact]);
      unset($arr[BaseDevicePropEnum::SipUserAgent]);
      unset($arr[BaseDevicePropEnum::SipHeaders]);
      unset($arr[BaseDevicePropEnum::RegistrationExpirationUtc]);
      unset($arr[BaseDevicePropEnum::SbcApiAddress]);
      unset($arr[BaseDevicePropEnum::SbcApiPort]);
      unset($arr[BaseDevicePropEnum::LastRegisterRequestUtcTime]);
      unset($arr[BaseDevicePropEnum::LastConfigReturnTimeUtc]);
      unset($arr[BaseDevicePropEnum::SbcTeamsAddress]);
      unset($arr[BaseDevicePropEnum::SbcTeamsPort]);
      unset($arr[BaseDevicePropEnum::ShouldNotifyOnReg]);
      unset($arr[BaseDevicePropEnum::DmAction]);
      $arr[BaseDevicePropEnum::DeviceStatus] = DeviceStatusEnum::OnBoarding;

      
      $data =  OnbordingDevice::add($arr);

      return $data;
  }

  public static function updateOnboarding($DeviceId,$data){
    return RestDmbeApi::getInstance()->put_onboarding($DeviceId,$data);
  }

  public static function updatePaired($DeviceId,$data){
    return RestDmbeApi::getInstance()->put_paired($DeviceId,$data);
  }

    /*public static function updateDevice($DeviceId,$field,$value) {
      $provDevice = WebServiceUtils::getOnboardingDevice($DeviceId);
      if(!empty($provDevice))
      {
        $provDevice[$field] = $value;
        return OnbordingDevice::add($provDevice);
      }
      //can not find the device
      return false;
    }*/

    public static function deleteDevice($DeviceId, $token = "") {
        $device = OnbordingDevice::getDevice($DeviceId);
        if(!empty($device))
          return RestDmbeApi::getInstance($token)->deleteDevice($DeviceId, $token);

        return RestDmbeApi::getInstance($token)->deleteUserDevice($DeviceId, $token);
    }

    public static function getAllOnBoarding($token,$params) {
      return RestApiOnBoardingAdmin::getInstance($token)->search($params);
    }

    public static function unpaired($DeviceId,$token = "") {
      return RestDmbeApi::getInstance($token)->unpair($DeviceId);
    }

    public static function appInsightsEvents($events){
      return RestDmbeApi::getInstance()->appInsightsEvents($events);
    }

    public static function appInsightsEvent($eventName, $params = array()){
      $event = self::buildEvent($eventName, $params);
      return RestDmbeApi::getInstance()->appInsightsEvents(array($event));
    }

    public static function appInsightsLog($message, $logLevel = "Error"){
      global $WRITE_ERR_APP_LOGS;
      $WRITE_ERR_APP_LOGS = false;
      $res = RestDmbeApi::getInstance()->appInsightsLog($message, $logLevel);
      $WRITE_ERR_APP_LOGS = true;
      return $res;
    }
    

    public static function clearAction($DeviceId, $dmAction, $token = "")
    {
      return RestDmbeApi::getInstance($token)->clearAction($DeviceId,$dmAction);
    }

    public static function getAllUsers($token,$params) {
      return RestApiUsersAdmin::getInstance($token)->search($params);
    }

    public static function getAll($token) {
      return RestApiUsersAdmin::getInstance($token)->search([]);
    }

    public static function buildEvent($eventName, $params = array())
    {
      $event = array();
      $event["eventName"] = $eventName;
      $event["eventProperties"] = array();
      foreach ($params as $ind => $value) {
        if(!empty($value))
          $event["eventProperties"][$ind] = $value;
      }
      
      return $event;
    }
  


}




 ?>

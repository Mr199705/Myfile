<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://ljk.cc All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: itkk <it_kk@qq.com>
// +----------------------------------------------------------------------
namespace app\shop\model;
use think\Model;
use think\Db;
class UserSmsCode extends Model{
    protected $table = 'ljk_user_smscode';
     /**
     * 根据手机号码获取code信息
     * @param $mobile
	 * @param $type: 验证码类型
					1-手机号验证
					2-提现
     */
	public function getCode($mobile,$type,$is_use=0)
    {
	   $map['mobile']=$mobile;
	   $map['type']=$type;
	   $map['is_use']=$is_use;
	   $code=$this->where($map)->order('id desc')->field('id,code,exp,create_time')->find();
	   return $code;
	}
	
	     /**
     * 根据手机号码获取code信息
     * @param $mobile
	 * @param $type: 验证码类型
					1-注册
					2-登录
     */
    public function setCode($mobile,$type,$input = []){
        $CheckCode= rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9);
        $data['code']=$CheckCode;
        $data['mobile']=$mobile;
        $data['type']=intval($type);
        $data['create_time']=time();
        $data['ip']=get_client_ip();
        $data['user_agent']= input('server.HTTP_USER_AGENT');
        $data['gid'] = isset($input['gid']) ? (int)$input['gid'] : 0;
        $data['uid'] = isset($input['uid']) ? (int)$input['uid'] : 0;
        $this->insert($data);
        return $CheckCode;
    }
	
    public function getSmsTemplate($type)
    {
        switch ($type) {
           case 1: // 注册
                $temp['template']="SMS_44365441";
                $temp['product']="";
                break;
           case 2:// 登录
                $temp['template']="SMS_44365443";
                $temp['product']="";
               break;
           case 3: //验证手机号
                $temp['template']="SMS_44365438";
                $temp['product']="验证手机号";
              break;
           case 4: //绑定提现账号
                $temp['template']="SMS_44365445";
                $temp['product']="绑定提现账号";
                break;
           case 5: //申请提现
                $temp['template']="SMS_44365445";
                $temp['product']="申请提现";
                break;
           case 6: //确认付款
                $temp['template']="SMS_44365445";
                $temp['product']="确认付款";
                break;
           case 7: //支付密码
                $temp['template']="SMS_44365438";
                $temp['product']="支付密码";
              break;
           case 8: //登录密码
                $temp['template']="SMS_44365438";
                $temp['product']="登录密码";
              break;
           case 9: //激活账号
                $temp['template']="SMS_44365445";
                $temp['product']="激活账号";
                break;
           case 10: //充值续费
                $temp['template']="SMS_44365445";
                $temp['product']="充值续费";
               break;
       }
        return $temp;
    }
    /**
     * 根据用户id获取角色信息
     * @param $id
     */
    public function checkCode($code,$mobile,$type)
    {
	   $dbcode=$this->getCode($mobile,$type);
	   $exp=$dbcode['create_time']+$dbcode['exp'];
	   if($dbcode['code']&&$dbcode['code']==$code){
		  $result =  $this->save(['is_use' =>1], ['id' => $dbcode['id']]);
		   if($exp<time()){
			  $c['code']=-3; 
			  $c['msg']='手机短信验证码已过期,请重新获取'; 
			  return $c; 
			   }
		  $c['code']=1; 
		  $c['msg']='手机短信验证码验证通过'; 
	   }
	   else{ 
	   	  $c['code']=-4; 
		  $c['msg']='手机短信验证码错误,请重新输入'; 
 		}
	   return $c; 
	}
    
}
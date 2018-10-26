<?php
namespace app\shop\controller;
use think\Controller;
use think\wechatsdk\Api;
use think\Db;
use think\Request;
class Base extends Controller{
    protected $gid;
    protected $uid;
    protected $rankid;
    protected $jsName;
    protected $requestFunc;
    protected $sign;
    protected $requestUrl;
    protected $cUrl = '';
    protected $rows = 10;
    protected $initData = [];
    protected $guid;//当前登录账号编号
    protected $groupShop = [];
    protected $umca = [];
    public function __construct(\think\Request $request = null) {
        parent::__construct($request);
        $this->jsName = strtolower(Request::instance()->controller());
        $this->requestFunc = strtolower(Request::instance()->controller()).ucfirst(Request::instance()->action());
        $this->sign = strtolower(Request::instance()->controller()).ucfirst(Request::instance()->action());
        $this->requestUrl = url();
        $this->cUrl = substr($this->requestUrl, 0, strrpos($this->requestUrl,'/'));
        session( 'gid', 133);
        session( 'uid', 10001);
        session( 'shopid', 100);
        $usermember = Db::name('user_member')->where([ 'gid' => 133 ,'uid' => 10001 ])->find();
        session('usermember', $usermember);
        $this->gid =133;
        $this->uid =71944;
        $this->shopid = 109;
        $whereGs['gid'] = $this->gid;
        $whereGs['url'] = 'http://'. $_SERVER['HTTP_HOST']; 
        $Shopinfo = Db::name('group_shop')->field('id,gid,url,name,weixin,appid,appsecret,access_token,fxcheck,fxstatus,fxs_fc,v,hyk')->where($whereGs)->find();
        $this->groupShop = $Shopinfo;
        //$this->preAct();
        if($this->uid !== 0){
            $whereU['uid'] = $this->uid;
            $whereU['gid'] = $this->gid;
            $this->rankid = Db::name('user_member')->where($whereU)->value('rankid');
        }else{
            $this->rankid = 0;
        }
        if($this->groupShop['hyk'] == 1){
            if($this->gid == -1){
                //只有等级为0或等级 68的时候才进行这个操作
                if($this->rankid == 0 || $this->rankid == 68){
                    $this->umca();
                    if($this->umca['useable'] > 0 && !!$this->umca['status']){
                        $this->rankid = 68;//只要有余额 就享受会员价
                    }else{
                        $this->rankid = 0;
                    }
                }
            }else if($this->gid == 205){
                //只有等级为0或等级 333的时候才进行这个操作
                if($this->rankid == 0 || $this->rankid == 333){
                    $this->umca();
                    if($this->umca['useable'] > 0  && !!$this->umca['status']){
                        $this->rankid = 333;//只要有余额 就享受会员价
                    }else{
                        $this->rankid = 0;
                    }
                }
            }
        }
        $this->assign('groupShop',$this->groupShop);
        $this->assign('initData',$this->initData);
        $this->assign('controller',strtolower(Request::instance()->controller()));
    }
    //user mcard account 用户会员卡账户信息！
    protected function umca(){
        $map = array();
        $map['gid']  = $this->gid;
        $map['uid']  = $this->uid;
        //  查询数据
        $cardum = Db::name('mcard_um')->where($map)->field('id,status,useable,disable')->find();
        $this->umca = $cardum;
    }
    public function preAct(){
        //设置移动端访问标识，
        session('is_mobile',1);
        $this->checkLogin();
        $this->initWxconfig();
        $this->regCheck();
    }
    private function regCheck(){
        if($this->uid == 0){
            $mustCheck = [
                'user' => true,
                'shopcart' => true,
                'payment' => true,
                'settlement' => true,
                'address' => true,
                'order' => true
            ];
            $permission = true;
            $c = strtolower(Request::instance()->controller());
            $a = strtolower(Request::instance()->action());
            if(isset($mustCheck[$c])){
                //控制器内所有方法 均不允许 非注册用户访问
                if($mustCheck[$c] === true){
                    $permission = false;
                }else if(in_array($a,$mustCheck[$c])){
                    $permission = false;
                }
            }
            if($permission === false){
                if(request()->isAjax()){
                    echo json_encode(['code' => -2, 'msg' => '请先登录后再进行该操作！']);
                    exit();
                }else{
                    $this->redirect(url('/shop/login/login'));
                    exit();
                }
            }else{
                return true;
            }
        }else{
            return true;
        }
    }
    private function checkLogin(){
        $memberInfo = session('usermember');
        if(
            $this->groupShop 
            && 
            ($this->groupShop['id'] == 96 || $this->groupShop['id'] == 109)
            && 
            (
                empty($memberInfo)
                ||
                $this->uid == 0
                || 
                !$memberInfo['mobile']
                || 
                !$memberInfo['trust']
                || 
                !$memberInfo['is_check_mobile']
            )
                
        ){
            //宇蓝商贸的
            if(request()->isAjax()){
                echo json_encode(['code' => -2, 'msg' => '请先登录后再进行该操作！']);
                exit();
            }else{
                echo $this->fetch('/common/warning');
                exit();
            }
        }
        $controller = strtolower(request()->controller());
        $module = strtolower(request()->module());
        $action = strtolower(request()->action());
        if(request()->isAjax() && empty($memberInfo)){
            echo json_encode(['code' => -5,'msg'=>'登陆超时，请重新登录商城！']);
            exit();
        }
        $input = input();
        if(empty($memberInfo)){
            //获取 url 参数
            $input['m'] = strtolower(\think\Request::instance()->module());
            $input['c']  = strtolower(\think\Request::instance()->controller());
            $input['a']  = strtolower(\think\Request::instance()->action());
            $this->redirect(url("/shop/login/index",$input));
        }
    }
    private function initWxconfig(){
        $wxconfig = session('wxconfig');
        if(!$wxconfig){
            $wxconfig = $this->wxConfig();
            $wxconfig['t'] = time();
        }else if((time() - $wxconfig['t']) >= 7200){
            $wxconfig = $this->wxConfig();
            $wxconfig['t'] = time();
        }
        session('wxconfig',$wxconfig);
        $this->assign('wxconfig',$wxconfig);
    }
    protected function wxConfig(){
    	$wxToken = Db::name('wx_token')->where('gid',session('gid'))->find();    	
    	if($wxToken){
            $c = [
                'appId' =>  $wxToken['appid'],
                'appSecret'	=> $wxToken['appsecret']
            ];
    	}else{
            $c = [
                'appId' =>  Config('weixin_config.appId'),
                'appSecret'	=> Config('weixin_config.appSecret')
            ];
    	}
    	$wxApi = new Api($c);
    	//$url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    	$url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    	//echo $url;
    	$config = $wxApi->get_jsapi_config($url);
    	return $config;
    }
    //获取公众号token
    protected function getwx_access_token($gid=0,$isupdate=0){
        if(!$gid) $gid=session('gid');
        $wxToken = Db::name('wx_token')->where('gid',$gid)->field('appid,appsecret,access_token,ctime,exptime')->find();
        if(!empty($wxToken)){
            if($isupdate==1||$wxToken['access_token']==''||time()-$wxToken['ctime']-600 > $wxToken['exptime']){
                $c = [
                'appId' =>  $wxToken['appid'],
                'appSecret'	=> $wxToken['appsecret']
                ];
                $wxApi = new Api($c);
                $token = $wxApi->new_access_token($wxToken['appid'],$wxToken['appsecret']);
                $access_token = $wxTokendata['access_token'] = $token->access_token;
                $wxTokendata['exptime'] = $token->expires_in;
                $wxTokendata['ctime'] = time();
                Db::name('wx_token')->where('appid',$wxToken['appid'])->update($wxTokendata);
            }else{
                $access_token = $wxToken['access_token'];	    		
            }	
        }
    	return $access_token?$access_token:'';
    }
    	//FIXME::  返回固定json 状态码,数据,错误信息
    protected function reJson( $code, $data, $msg = '' ){
        $reData = [];
        $reData['code'] = $code;
        $reData['data'] = $data;
        $reData['msg'] = $msg;
        return json( $reData )->send();
    }
    protected function getUserId(){
        return [
            'gid' => $this->gid,
            'uid' => $this->uid,
            'shopid' => $this->groupShop['id']
        ];
    }
}


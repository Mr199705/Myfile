<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\shop\controller;
use think\Db;
class Bindwx extends \think\Controller{
    private $gid = 0;
    private $shopid = 0;
    private $groupInfo = [];
    public function logout(){
        session('gid',null);
        session('groupInfo',null);
        session('user_weixin',null);
    }
    public function index(){
        if(!is_weixin()){
            $err = ['code' => -1, 'msg' => '请在微信端打开本页面！' ];
            $this->assign('err',$err);
            return $this->fetch('/bindwx/tip');
        }
        //验证签名
        $input = input();
        $sign = new \app\common\controller\Sign();
        if($sign->validateSign($input) === false){
            $err = ['code' => -1, 'msg' => '该链接无效1！' ];
            $this->assign('err',$err);
            return $this->fetch('/bindwx/tip');
        }
        $inputGid = isset($input['gid']) ? (int)$input['gid'] : false;
        $inputShopid = isset($input['shopid']) ? (int)$input['shopid'] : false;
        $inputUid = isset($input['uid']) ? (int)$input['uid'] : false;
        if(!$inputGid || !$inputShopid || !$inputUid){
            $err = ['code' => -1, 'msg' => '该链接无效2！' ];
            $this->assign('err',$err);
            return $this->fetch('/bindwx/tip');
        }
        $wx = session('user_weixin');
        $re_url=$this->get_url();
        session('re_url',$re_url);
        $this->groupInfo = session('groupinfo');
        if(!$this->groupInfo){
            $url = str_replace('.m.', '.', $_SERVER['HTTP_HOST']);
            $whereG['url|sysurl'] =  $_SERVER['REQUEST_SCHEME'] . '://' .$url;
            $this->groupInfo = Db::name('group')->where($whereG)->find();
        }
        if(!$this->groupInfo){
            $err = ['code' => -1, 'msg' => '链接无效3！' ];
            $this->assign('err',$err);
            return $this->fetch('/bindwx/tip');
        }
        session('groupinfo', $this->groupInfo);
        $this->assign('groupInfo',$this->groupInfo);
        $this->gid = $this->groupInfo['gid'];
        if($this->gid != $inputGid){
            $err = ['code' => -1, 'msg' => '链接无效6！' ];
            $this->assign('err',$err);
            return $this->fetch('/bindwx/tip');
        }
        session('gid', $this->gid);
        $groupShop = session('groupshop');
        if(!$groupShop){
            $url = str_replace('.m.', '.', $_SERVER['HTTP_HOST']);
            $whereGs['url'] =  $_SERVER['REQUEST_SCHEME'] . '://' .$url;
            $whereGs['gid'] = $this->gid;
            $groupShop = Db::name('group_shop')->field('id,weixin')->where($whereGs)->find();
            session('groupshop',$groupShop);
        }
        if(!$groupShop || $groupShop['id'] != $inputShopid){
            $err = ['code' => -1, 'msg' => '链接无效4！' ];
            $this->assign('err',$err);
            return $this->fetch('/bindwx/tip');
        }
        $this->shopid = $groupShop['id'];
        //验证客户资料的真实性
        $whereU['uid'] = $inputUid;
        $whereU['gid'] = $this->gid;
        if(!Db::name('user_member')->where($whereU)->count()){
            $err = ['code' => -1, 'msg' => '无效的链接5！' ];
            $this->assign('err',$err);
            return $this->fetch('/bindwx/tip');
        }
        //验证微信资料是否已经绑定
        $whereUo['uid'] = $inputUid;
        $whereUo['shopid'] = $this->shopid;
        $whereUo['gid'] = $this->gid;
        if(Db::name('user_open')->where($whereUo)->count()){
            $err = ['code' => -1, 'msg' => '您的账号已绑定微信信息，请不要重复绑定！' ];
            $this->assign('err',$err);
            return $this->fetch('/bindwx/tip');
        }
        if(!$wx){
            $url= $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/shop/qrhb/getwx';
            $this->redirect($url);
        }else{
            //验证微信是否已被绑定过
            $whereUo = [];
            $whereUo['weixin'] = $wx['openid'];
            $whereUo['shopid'] = $this->shopid;
            $whereUo['gid'] = $this->gid;
            if(Db::name('user_open')->where($whereUo)->count()){
                $err = ['code' => -1, 'msg' => '您的微信信息账号已绑定过账号，请不要重复绑定！' ];
                $this->assign('err',$err);
                return $this->fetch('/bindwx/tip');
            }else{
                //进行绑定操作
                $uo['gid'] = $this->gid;
                $uo['uid'] = $inputUid;
                $uo['shopid'] = $this->shopid;
                $uu['weixin'] = $uo['weixin'] = $wx['openid'];
                $uo['tousername'] = $groupShop['weixin'] ? $groupShop['weixin'] : '';
                $uo['realname'] = isset($wx['realname']) ? $wx['realname'] : '';
                $uu['nickname'] = $uo['nickname'] = $wx['nickname'];
                $uo['mobile'] = isset($wx['mobile']) ? $wx['mobile'] : '';
                $uo['sex'] = isset($wx['sex']) ? $wx['sex'] : 0;
                $uo['city'] = isset($wx['city']) ? $wx['city'] : '';
                $uo['country'] = isset($wx['country']) ? $wx['country'] : '';
                $uo['province'] = isset($wx['province']) ? $wx['province'] : '';
                $uo['headimgurl'] = isset($wx['headimgurl']) ? $wx['headimgurl'] : '';
                $uo['language'] = isset($wx['language']) ? $wx['language'] : '';
                $uu['trust'] = 1;
                $whereUu['gid'] = $this->gid;
                $whereUu['uid'] = $inputUid;
                Db::startTrans();
                try{
                    Db::name('user_open')->insert($uo);
                    Db::name('user_member')->where($whereUu)->update($uu);
                    Db::commit();
                    $err = ['code' => 0, 'msg' => '您的微信信息已绑定成功，请关闭该页面！' ];
                    $this->assign('err',$err);
                    return $this->fetch('/bindwx/tip');
                }catch(\think\Exception $e){
                    Db::rollback();
                    $err = ['code' => -1, 'msg' => '系统繁忙，请稍后再试！' . $e->getMessage()];
                    $this->assign('err',$err);
                    return $this->fetch('/bindwx/tip');
                }
            }
        }
    }
    public function getwx(){
        if(is_weixin()){
            $this->gid = session('gid');
            if(!$this->gid){
                $url = str_replace('.m.', '.', $_SERVER['HTTP_HOST']);
                $whereG['url|sysurl'] =  $_SERVER['REQUEST_SCHEME'] . '://' .$url;
                $this->gid = Db::name('group')->where($whereG)->value('gid');
            }
            if(!$this->gid){
                echo '链接无效！';
                exit();
            }
            session('gid',$this->gid);
            $config = Db::name('wx_token')->where('gid',session('gid'))->find();
            $code = $config['code'] = input('code');
            if(!$code){
                $redirect_uri = urlencode($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/shop/bindwx/getwx');
                $codeurl='https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$config['appid'].'&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_userinfo&state=123#wechat_redirect';
                $this->redirect($codeurl);
            }else{
                $weixin = $this->weixinInfo($config);
                session('user_weixin',$weixin);
                $url2 = session('re_url');
                if($url2){
                    $this->redirect($url2);
                }
            }
    	}else{
    	    //显示错误页面
            echo '请在微信端打开本页面！';
            exit();
    	}
    }
    private function weixinInfo($config = []){
    	$url="https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$config['appid']."&secret=".$config['appsecret']."&code=".$config['code']."&grant_type=authorization_code";
    	$access_token = file_get_contents($url);
    	$access_token = json_decode($access_token, true);
        $data= $access_token;
    	$url='https://api.weixin.qq.com/sns/userinfo?access_token='.$data['access_token'].'&openid='.$data['openid'].'&lang=zh_CN';
    	$userinfo =file_get_contents($url);
    	$userinfo=json_decode($userinfo, true);
        $weixin = array_merge($access_token,$userinfo);
        return $weixin;
    }
    public function get_url() {
    	$sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
    	$php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
    	$path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    	$relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
    	return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    }
}


<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\shop\controller;
use think\Controller;
use app\m\model\Group;
use think\wechatsdk\Api;
use think\Db;
use app\shop\model\UserSmsCode;
use app\common\model\SendSms;
class Login extends Controller{
    private $gid = 0;
    private $groupShop = [];
    public function __construct(\think\Request $request = null) {
        parent::__construct($request);
    }
    public function index(){
    	$re_url=$this->get_url();
        //获取用户的openid，自动登录系统
        $url = str_replace('.m.', '.', $_SERVER['HTTP_HOST']);
        if(!session('gid')){
            $groupinfo = Group::get(['url|sysurl' => 'http://' .$url]);
            $this->gid = $groupinfo && $groupinfo['gid'] ? $groupinfo['gid'] : 0;
            session('gid',$this->gid);
        }else{
            $this->gid = session('gid');
        }
        session('user_openid','ouCe5jgKYugGBkm1ZNZUnAeY94X4');
    	session('re_url',$re_url);
    	if(!session('user_openid')){
            $url='http://'.$_SERVER['HTTP_HOST'].'/shop/login/access';
            $this->redirect($url);
    	}
        $input = input();
        $inputParam = [];
        $inputUrl = [];
        foreach($input as $k=>$v){
            if($k === 'm'){
                $inputUrl['m'] = $v;
            }else if($k === 'c'){
                $inputUrl['c'] = $v;
            }else if($k === 'a'){
                $inputUrl['a'] = $v;
            }else{
                $inputParam[$k] = $v;
            }
        }
        if(empty($inputUrl)){
            $inputUrl['m'] = strtolower(\think\Request::instance()->module());
            $inputUrl['c'] = strtolower(\think\Request::instance()->controller());
            $inputUrl['a'] = strtolower(\think\Request::instance()->action());
        }
        $u = $_SERVER['HTTP_HOST'];
        if(!$this->gid){
            $whereGs['url'] = [['EQ', 'http://'. $u], ['EQ', 'https://'. $u] , 'OR'];
            $groupShop = Db::name('group_shop')
                    ->field('id,gid,url,name,weixin,appid,appsecret,access_token,fxcheck,fxstatus,fxs_fc,v,hyk')
                    ->where($whereGs)
                    ->find();
            if(empty($groupShop)){
                $this->assign('line','a');
                return $this->fetch('/common/forbidden');
            }else{
                $whereG['gid'] = $groupShop['gid'];
                $this->gid = Db::name('group')->where($whereG)->value('gid');
                if(!$this->gid){
                    $this->assign('line','a');
                    return $this->fetch('/common/forbidden');
                }else{
                    session('gid',$this->gid);
                }
            }
        }else{
            $whereGs['gid'] = $this->gid;
            $whereGs['url'] = [['EQ', 'http://'. $u], ['EQ', 'https://'. $u] , 'OR'];
            $groupShop = Db::name('group_shop')
                ->where($whereGs)
                ->field('id,gid,url,name,weixin,appid,appsecret,access_token,fxcheck,fxstatus,fxs_fc,v,hyk')
                ->find();
        }
        if(!!$this->gid){
            session('shopinfo',$groupShop);
            //获取当前组的微信公众号信息
            //将groupShop作为参数去获取微信openid
            if(!empty($groupShop)){
               // $openId = $this->userOpenid($groupShop);
                $openId = session('user_openid');
            }else{
                //这里就需要手动登录系统或者直接跳到商城首页去
                redirect(url("{$inputUrl['m']}/{$inputUrl['c']}/{$inputUrl['a']}"));
                //return $this->fetch('/shop/login/index');
                return false;
            }
            //快速创建客户信息！
            $whereUo['gid'] = $this->gid;
           // $whereUm['shopid'] = [['EQ',$shopId],['EQ',0],'OR'];
            $whereUo['weixin'] = $openId;
            $uid = Db::name('user_open')->where($whereUo)->value('uid');
            if($uid){
                $whereUm['uid'] = $uid;
                $whereUm['gid'] = $this->gid;
                $userMember = Db::name('user_member')->where($whereUm)->find();
            }else{
                $userMember = [];
            }
            if(!empty($userMember)){
                //判定用户是否存在上级
                $params = [];
                $user = [];
                $params['uid'] = $user['uid'] = $userMember['uid'];
                $params['gid'] = $userMember['gid'];
                if(!$userMember['unique_sign']){
                    $user['unique_sign'] = mkUniqueSign($this->gid,$params);
                }
                try{
                    Db::name('user_member')->update($user);
                }catch(\think\Exception $e){
                    exit();
                }
                $sessData['uid'] = $userMember['uid'];
                $sessData['gid'] = $userMember['gid'];
                $sessData['rankid'] = $userMember['rankid'];
                $sessData['guid'] = $userMember['guid'];
                $sessData['unique_sign'] = $userMember['unique_sign'] ? $userMember['unique_sign'] : $user['unique_sign'];
                $sessData['realname'] = $userMember['realname'];
                $sessData['mobile'] = $userMember['mobile'];
                $sessData['trust'] = $userMember['trust'];
                $sessData['is_check_mobile'] = $userMember['is_check_mobile'];
            }else{
                $userMember = [];
                $userO['gid'] = $user['gid'] = $this->gid;
                $userO['weixin'] = $user['weixin'] = $openId;
                $userO['shopid'] = $user['shopid'] = $groupShop ? $groupShop['id'] : 0;
                $userO['tousername'] = $groupShop ? $groupShop['weixin'] : '';
                //获取客户标识信息
                $fx = false;
                if(isset($inputParam['fxpsign']) && !!($fxpsign = trim($inputParam['fxpsign']))){
                    $fx = true;
                    //获取上级信息
                    $whereUmp['gid'] = $this->gid;
                    $whereUmp['unique_sign'] = $fxpsign;
                    $pUser = Db::name('user_member')->field('uid,guid,gspid,is_fx')->where($whereUmp)->find();//分销客户归属为上级客户的跟进人
                    if($pUser){
                        $user['fxpid'] = $pUser['uid'];
                    }else{
                        $user['fxpid'] = 0;
                    }
                    if($pUser['is_fx']){
                    	$user['gspid'] = $pUser ? $pUser['uid'] : 0;
                    }else{
                        $user['gspid'] = $pUser ? $pUser['gspid'] : 0;
                    }
                    $user['guid'] = $pUser ? $pUser['guid'] : 0;
                }
                //公司身份标识
                if(isset($inputParam['gssign']) && !!($gssign = trim($inputParam['gssign']))&&!$user['gspid'] ){
                    $fx = true;
                    //获取上级信息
                    $whereUmp1['gid'] = $this->gid;
                    $whereUmp1['unique_sign'] = $gssign;
                    $pUser1 = Db::name('user_member')->field('uid,guid')->where($whereUmp1)->find();//分销客户归属为上级客户的跟进人
                    if($pUser1){
                        $user['gsid'] = $pUser1['uid'];
                    }else{
                        $user['gsid'] = 0;
                    }
                }
                //业务员身份标识
                if(isset($inputParam['gmsign']) && !!($gmpsign = trim($inputParam['gmsign']))&&!$user['guid'] ){
                    $fx = true;
                    $whereGm['gid'] = $this->gid;
                    $whereGm['unique_sign'] = $gmpsign;
                    $gm = Db::name('group_member')->field('uid')->where($whereGm)->find();//分销客户归属为上级客户的跟进人
                    $user['guid'] = $gm ? $gm['uid'] : 0;
                }
                try{
                    $userinfo=$this->getwxuser($openId);
                    $user['realname'] = $userinfo['nickname'];
                    $user['regtime'] = time();
                    $user['bd'] = '0000-00-00';
                    $user['birthday'] = '0000-00-00';
                    $user['frozen_money'] = '0';
                    $user['wexin'] = $openId;
                    $user['x'] = '0';
                    $user['y'] = '0';
                    $user['bdate'] = '0';
                    $user['odate'] = '0';
                    $user['hdate'] = '0';
                    $userU = [];
                    $params = [];
                    if($fx === true){
                        $userinfo['uid'] = $userU['uid'] = $user['uid'] = Db::name('user_member')->insertGetId($user);
                        $params['uid'] = $user['uid'];
                        $params['gid'] = $user['gid'];
                        $userU['unique_sign'] = $user['unique_sign']= mkUniqueSign($this->gid,$params);
                        Db::name('user_member')->update($userU);
                    }else{
                        $userinfo['uid'] = $userU['uid'] = $user['uid'] = 0;
                    }
                    $userinfo['gid']=$this->gid;
                    $userinfo['openid'] = $openId;
                    $userinfo['shopid'] = $groupShop['id'];
                    $userinfo['weixin'] = $groupShop['weixin'];
                    $this->uwx($userinfo);
                }catch(\think\Exception $e){
                    $this->assign('line','b' . $e->getMessage());
                    return $this->fetch('/common/forbidden');
                }
                $sessData['uid'] = $user['uid'];
                $sessData['gid'] = $user['gid'];
                $sessData['guid'] = $user['guid'];
                $sessData['trust'] = isset($user['trust'])? $user['trust'] : 0;
                $sessData['mobile'] = isset($user['mobile'])? $user['mobile'] : '';
                $sessData['is_check_mobile'] = isset($user['is_check_mobile'])? $user['is_check_mobile'] : 0;
                $sessData['unique_sign'] = $user['unique_sign'];
                $sessData['realname'] = isset($user['realname']) ? $user['realname'] : '';
            }
            $this->sessionSet('usermember',$sessData);
            $inputParam['fxpsign']=$userMember['unique_sign']?$userMember['unique_sign']:$user['unique_sign'];
            if($inputUrl['c']=='login' && $inputUrl['a']=='index'){
                $inputUrl['c']='user';
                $this->redirect(url("{$inputUrl['m']}/{$inputUrl['c']}/{$inputUrl['a']}",$inputParam));
            }else{
                $this->redirect(url("{$inputUrl['m']}/{$inputUrl['c']}/{$inputUrl['a']}",$inputParam));
            }             
            exit();
        }else{
            $this->assign('line','c');
            return $this->fetch('/common/forbidden');
        }
    }
    //短信验证码
    public function login(){
        if(!session('gid')){
            $url = str_replace('.m.', '.', $_SERVER['HTTP_HOST']);
            $this->gid = Db::name('group')->where(['url|sysurl' => 'http://' .$url])->value('gid');
            session('gid',$this->gid);
        }else{
            $this->gid = session('gid');
        }
        $u = $_SERVER['HTTP_HOST'];
        if(!$this->gid){
            if(!session('groupShop')){
                $whereGs['url'] = [['EQ', 'http://'. $u], ['EQ', 'https://'. $u] , 'OR'];
                $this->groupShop = Db::name('group_shop')
                    ->field('id,gid,url,name,weixin,appid,appsecret,access_token,fxcheck,fxstatus,fxs_fc,v,hyk')
                    ->where($whereGs)
                    ->find();
            }else{
                $this->groupShop = session('groupShop');
            }
            if(empty($this->groupShop)){
                echo '商城不存在！';
                exit();
            }else{
                $whereG['gid'] = $groupShop['gid'];
                $this->gid = Db::name('group')->where($whereG)->value('gid');
                if(!$this->gid){
                    $this->assign('line','a');
                    return $this->fetch('/common/forbidden');
                }else{
                    session('gid',$this->gid);
                }
            }
        }else{
            if(!session('groupShop')){
            $whereGs['gid'] = $this->gid;
            $whereGs['url'] = [['EQ', 'http://'. $u], ['EQ', 'https://'. $u] , 'OR'];
            $this->groupShop = Db::name('group_shop')
                ->where($whereGs)
                ->field('id,gid,url,name,weixin,appid,appsecret,access_token,fxcheck,fxstatus,fxs_fc,v,hyk')
                ->find();
            }else{            
                $this->groupShop = session('groupShop');
            }
        }
        if(!$this->gid || empty($this->groupShop)){
            echo '商城不存在！';
            exit();
        }else{
            session('gid',$this->gid);
            session('groupShop',$this->groupShop);
        }
        $memberInfo = session('usermember');
        if(!empty($memberInfo)){
            if($memberInfo['uid'] != 0){
                //已经登录的用户
                if(
                    $this->groupShop 
                    && 
                    ($this->groupShop['id'] == 96 || $this->groupShop['id'] == 109)
                    && 
                    (
                        empty($memberInfo)
                        ||
                        !$memberInfo['mobile']
                        || 
                        !$memberInfo['trust']
                        || 
                        !$memberInfo['is_check_mobile']
                    )
                ){

                }else{
                    if(request()->isAjax() && request()->isPost()){
                        return json(['code' => -3,'msg' => '您已登录，请勿再次登录！']);
                    }else{
                        $this->redirect('/shop/index/index');
                    }
                }
            }
        }
        if(!session('user_openid')){
            $re_url=$this->get_url();
            session('re_url',$re_url);
            $url='http://'.$_SERVER['HTTP_HOST'].'/shop/login/access';
            $this->redirect($url);
        }
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $action = '';
            if(isset($input['action'])){
                $action  = $input['action'];
            }
            switch($action){
                case 'regloginsms':return $this->regloginsms($input);
                case 'reglogin':return $this->reglogin($input);
                default : return json(['code' => -1,'msg' => '无效的操作！']);
            }
        }else{
            $this->initWxconfig();
            return $this->fetch('/login/index');
        }
    }
    private function regloginsms($input = []){
        $mobile = isset($input['mobile']) ? trim($input['mobile']) : false;
        if(preg_match("/^1[3456789]\d{9}$/", $mobile)){
            //如果没有设置 $codetype
            $whereUm['gid'] = $this->gid;
            $whereUm['mobile'] = $mobile;
            $um = Db::name('user_member')->where($whereUm)->field('uid,guid')->find();
            if(!empty($um)){
                $codetype = 2;//登录
            }else{
                $um['uid'] = 0;
                $um['guid'] = 0;
                $codetype = 1;//注册
            }
            //验证 10分钟内 是否超过3条短信
            $whereUs['gid'] = $this->gid;
            $whereUs['uid'] = $um['uid'];
            $whereUs['mobile'] = $mobile;
            $whereUs['create_time'] = ['BETWEEN',[time()-600,time()]];
            $whereUs['type'] = $codetype;
            if(Db::name('user_smscode')->where($whereUs)->count() >= 3){
                return json(['code' => -1, 'data' => '', 'msg' => '请勿频繁操作！']);
            }
            $whereUs['create_time'] = ['BETWEEN',[time()-3600,time()]];
            if(Db::name('user_smscode')->where($whereUs)->count() >= 5){
                return json(['code' => -1, 'data' => '', 'msg' => '请勿频繁操作！']);
            }
            $whereUs['create_time'] = ['BETWEEN',[time()-24 * 3600,time()]];
            if(Db::name('user_smscode')->where($whereUs)->count() >= 10){
                return json(['code' => -1, 'data' => '', 'msg' => '请勿频繁操作！']);
            }
            $input['mobile'] = $mobile;
            $input['codetype'] = $codetype;
            $input['uid'] = $um['uid'];
            $input['guid'] = $um['guid'];
            return $this->sendsms($input);
        }else{
            return json(['code' => -1, 'data' => '', 'msg' => '手机号码格式不正确！']);
        }
        //验证这个号码在 10分钟内发了几条短信，如果已经发了3条 那么 就不能再发送了
        return $this->sendsms($input);
    }
    private function reglogin($input = []){
        $mobile = isset($input['mobile']) ? trim($input['mobile']) : '';
        $smscode = isset($input['code']) ? trim($input['code']) : '';
        $lat = isset($input['lat']) ? trim($input['lat']) : 0;
        $lng = isset($input['lng']) ? trim($input['lng']) : 0;
        if(!!$lat && !!$lng){
            $bd = getbaidugps($lat,$lng,true);
            $x = $bd[0];
            $y = $bd[1];
        }else{
            $x = 0;
            $y = 0;
        }
        if(!trim($smscode)){
            return json(['code' => -1,'msg' => '请填写验证码！']);
        }
        if(preg_match("/^1[3456789]\d{9}$/", $mobile)){
            //如果没有设置 $codetype
            $whereUm['gid'] = $this->gid;
            $whereUm['mobile'] = $mobile;
            $um = Db::name('user_member')->where($whereUm)->field('uid,gid,bdate,odate,x,y,mobile,rankid,phone,contact,regtime,regip,clicknum,lastvisit,lastip,tpid,visitid,trust,is_check_mobile,realname,nickname,address,unique_sign,guid,user_money,frozen_money,pay_points,rank_points')->find();
            $whereUs['gid'] = $this->gid;
            $whereUs['mobile'] = $mobile;
            $whereUs['status'] = 1;
            $whereUs['is_use'] = 0;
            if(empty($um)){
                $whereUs['uid'] = 0;
                $whereUs['type'] = 1;
                $actName = '注册';
            }else{
                $whereUs['uid'] = $um['uid'];
                $whereUs['type'] = 2;
                $actName = '登录';
            }
            $sms = Db::name('user_smscode')->field('id,code,create_time,exp')->where($whereUs)->order(['id' => 'DESC'])->find();
            if(empty($sms) || ($sms['code'] != $smscode)){
                return json(['code' => -1,'msg' => '验证码错误！']);
            }
            if(($sms['create_time'] + $sms['exp']) < time()){
                return json(['code' => -1,'msg' => '该验证码已失效，请重新获取验证码！']);
            }
            //验证码重置
            $whereUs['id'] = $sms['id'];
            Db::name('user_smscode')->where($whereUs)->update(['is_use' => 1]);
            $openId = session('user_openid');
            $userinfo = $this->getwxuser($openId);
            if(!empty($um)){
                $whereUo = [];
                $whereUo['gid'] = $this->gid;
                $whereUo['uid'] = $um['uid'];
                $umo = Db::name('user_open')->where($whereUo)->field('id,weixin,uid')->find();
                if(!empty($umo)){
                    if($openId === $umo['weixin']){
                        //当前登录的 weixin 和 绑定的微信 一致
                        //一致 那就 去更新 微信的 信息
                        $input['um'] = $um;
                        $input['x'] = $x;
                        $input['y'] = $y;
                        $userinfo['id'] = $umo['id'];
                        $input['userinfo'] = $userinfo;
                        $input['openid'] = $openId;
                        $input['mobile'] = $mobile;
                        $res = $this->upUser($input,true);
                        return $res;
                    }else{
//                      //不相同，那么直接登录成功！
                        $sessData['uid'] = $um['uid'];
                        $sessData['gid'] = $um['gid'];
                        $sessData['guid'] = $um['guid'];
                        $sessData['unique_sign'] = $um['unique_sign'];
                        $sessData['realname'] = $um['realname'] ? $um['realname'] : ($um['nickname'] ? $um['nickname'] : '');
                        $this->sessionSet('usermember',$sessData);
                        return json(['code' => 0,'msg' =>  '登录成功，即将跳转到商城首页']);
                    }
                }else{
                    //当前微信以前是否创建过了客户资料，如果创建了
                    //进行账户的合并(以及记录信息 ) //合并账户  //合并 订单 //合并 跟进记录 //合并 订单商品 //合并 购物车  
                    $whereUo = [];
                    $whereUo['gid'] = $this->gid;
                    $whereUo['weixin'] = $openId;
                    $uo = Db::name('user_open')->where($whereUo)->field('id,weixin,uid')->find();
                    if(!empty($uo)){
                        //当前登录的微信信息已经存在于系统当中了
                        //1.还没有绑定客户资料
                        if($uo['uid']){
                            $whereUm = [];
                            $whereUm['gid'] = $this->gid;
                            $whereUm['uid'] = $uo['uid'];
                            $umx = Db::name('user_member')->field('uid,gid,bdate,odate,x,y,mobile,rankid,phone,contact,regtime,regip,clicknum,lastvisit,lastip,tpid,visitid,trust,is_check_mobile,realname,nickname,address,unique_sign,guid,user_money,frozen_money,pay_points,rank_points')->where($whereUm)->find();
                        }else{
                            $umx = false;
                        }
                        if(!empty($umx)){
                            //去合并两个账号的资料
                            $res1 = $this->mergeUser($um, $umx);
                            if($res1['code'] === -1){
                                return json($res1);
                            }
                            $uid = $res1['uid'];
                            if($uid == $umx['uid']){
                                $um = $umx;
                            }
                            $input['um'] = $um;
                            $input['x'] = $x;
                            $input['y'] = $y;
                            $userinfo['id'] = $uo['id'];
                            $input['userinfo'] = $userinfo;
                            $input['openid'] = $openId;
                            $input['mobile'] = $mobile;
                            $res = $this->upUser($input,true);
                            return $res;
                        }else{
                            $input['um'] = $um;
                            $input['x'] = $x;
                            $input['y'] = $y;
                            $userinfo['id'] = $uo['id'];
                            $input['userinfo'] = $userinfo;
                            $input['openid'] = $openId;
                            $input['mobile'] = $mobile;
                            $res = $this->upUser($input,true);
                            return $res;
                        }
                    }else{
                        //当前登录的微信信息不存在与系统当中
                        $input['um'] = $um;
                        $input['x'] = $x;
                        $input['y'] = $y;
                        $input['userinfo'] = $userinfo;
                        $input['openid'] = $openId;
                        $input['mobile'] = $mobile;
                        $res = $this->upUser($input,true);
                        return $res;
                    }
                }
            }else{
                $whereUo = [];
                $whereUo['gid'] = $this->gid;
                $whereUo['weixin'] = $openId;
                $uo = Db::name('user_open')->where($whereUo)->field('id,nickname,sex,headimgurl,weixin,uid')->find();
                if(!empty($uo)){
                    //当前登录的微信信息已经存在于系统当中了
                    //1.还没有绑定客户资料
                    if($uo['uid']){
                        $whereUm = [];
                        $whereUm['gid'] = $this->gid;
                        $whereUm['uid'] = $uo['uid'];
                        $umx = Db::name('user_member')->field('uid,gid,bdate,odate,x,y,mobile,rankid,phone,contact,regtime,regip,clicknum,lastvisit,lastip,tpid,visitid,trust,is_check_mobile,realname,nickname,address,unique_sign,guid,user_money,frozen_money,pay_points,rank_points')->where($whereUm)->find();
                    }else{
                        $umx = false;
                    }
                    if(!empty($umx)){
                        $input['um'] = $umx;
                        $input['x'] = $x;
                        $input['y'] = $y;
                        $userinfo['id'] = $uo['id'];
                        $input['userinfo'] = $userinfo;
                        $input['openid'] = $openId;
                        $input['mobile'] = $mobile;
                        unset($input['code']);
                        $res = $this->upUser($input,true);
                        return $res;
                    }else{
                        if(!$userinfo || !isset($userinfo['nickname']) || !$userinfo['nickname']){
                            $userinfo['nickname'] = $uo['nickname'] ? $uo['nickname'] : '';
                        }
                        if(!$userinfo || !isset($userinfo['sex']) || !$userinfo['sex']){
                            $userinfo['sex'] = $uo['sex'] ? $uo['sex'] : 1;
                        }
                        if(!$userinfo || !isset($userinfo['headimgurl']) || !$userinfo['headimgurl']){
                            $userinfo['headimgurl'] = $uo['headimgurl'] ? $uo['headimgurl'] : '';
                        }
                        if(!$userinfo || !isset($userinfo['country']) || !$userinfo['country']){
                            $userinfo['country'] = $uo['country'] ? $uo['country'] : '';
                        }
                        if(!$userinfo || !isset($userinfo['province']) || !$userinfo['province']){
                            $userinfo['province'] = $uo['province'] ? $uo['province'] : '';
                        }
                        if(!$userinfo || !isset($userinfo['city']) || !$userinfo['city']){
                            $userinfo['city'] = $uo['city'] ? $uo['city'] : '';
                        }
                        $input['x'] = $x;
                        $input['y'] = $y;
                        $userinfo['id'] = $uo['id'];
                        $input['userinfo'] = $userinfo;
                        $input['openid'] = $openId;
                        $input['mobile'] = $mobile;
                        $res = $this->addUser($input,true);
                        return $res;
                    }
                }else{
                    //客户微信以及客户的资料都不存在
                    $input['x'] = $x;
                    $input['y'] = $y;
                    $input['userinfo'] = $userinfo;
                    $input['openid'] = $openId;
                    $input['mobile'] = $mobile;
                    $res = $this->addUser($input);
                    return $res;
                }
            }
        }else{
            return json(['code' => -1,'msg' => '手机号码不正确！']);
        }
    }
    private function mergeUser($um1 = [],$um2 = []){
        /* 
         * 1.合并会员卡账户
         * 2.合并跟进记录
         * 3.合并订单信息
         * 4.合并购物车
         * 5.合并订单商品
         * 6.合并客户等级
         * 7.合并客户类型
         * 8.合并来自
        */
        //根据订单商品记录数 以及 跟进记录数 来确定 保留哪一个客户的信息 ，保留数据量多的客户数据
        Db::startTrans();
        try{
            $uid1 = $um1['uid'];
            $uid2 = $um2['uid'];
            $um[$uid1] = $um1;
            $um[$uid2] = $um2;
            $whereO1['gid'] = $this->gid;
            $whereO1['uid'] = $uid1;
            $oc1 = Db::name('order')->where($whereO1)->count();
            $whereO2['gid'] = $this->gid;
            $whereO2['uid'] = $uid2;
            $oc2 = Db::name('order')->where($whereO2)->count();
            if($oc1 >= $oc2){
                $uid = $uid1;
                $xuid = $uid2;
            }else{
                $uid = $uid2;
                $xuid = $uid1;
            }
            //获取账户资料
            $whereMum['gid'] = $this->gid;
            $whereMum['uid'] = $uid;
            $whereMumx['gid'] = $this->gid;
            $whereMumx['uid'] = $xuid;
            $mum = Db::name('mcard_um')->where($whereMum)->find();
            $mumx = Db::name('mcard_um')->where($whereMumx)->find();
            if(!empty($mum) && !empty($mumx)){
                $whereUmum['gid'] = $this->gid;
                $whereUmum['uid'] = $uid;
                $whereUmum['id'] = $mum['id'];
                //直接进行账户合并
                $ip = get_client_ip();
                $ua = input('server.HTTP_USER_AGENT');
                $t = time();
                $upum = [
                    'useable' => $mum['useable'] + $mumx['useable'],
                    'disable' => $mum['disable'] + $mumx['disable']
                ];
                $upumx = [
                    'useable' => 0,
                    'disable' => 0,
                    'status' => -1
                ];
                $ump[] = [
                    'uinc' => $mumx['useable'],
                    'dinc' => $mumx['disable'],
                    'useable' => $mum['useable'] + $mumx['useable'],
                    'disable' => $mum['disable'] + $mumx['disable'],
                    'uid' => $uid,
                    'gid' => $mum['gid'],
                    'auid' => $uid,
                    'atype' => 1,
                    'type' => 9,
                    'desc' => '【账户合并】',
                    'ua' => $ua,
                    'ctime' => $t,
                    'ip' => $ip
                ];
                $ump[] = [
                    'uinc' => 0 - $mumx['useable'],
                    'dinc' => 0 - $mumx['disable'],
                    'useable' => 0,
                    'disable' => 0,
                    'uid' => $xuid,
                    'gid' => $mum['gid'],
                    'auid' => $xuid,
                    'atype' => 1,
                    'type' => 9,
                    'desc' => '【账户合并】该会员卡账户已失效！无法进行任何操作！',
                    'ua' => $ua,
                    'ctime' => $t,
                    'ip' => $ip
                ];
                $whereUmumx['id'] = $mumx['id'];
                $whereUmumx['uid'] = $xuid;
                Db::name('mcard_um')->where($whereUmum)->update($upum);
                Db::name('mcard_um')->where($whereUmumx)->update($upumx);
                Db::name('mcard_umpost')->insertAll($ump);
            }else if(!empty($mumx)){
                $whereUmum['gid'] = $this->gid;
                $whereUmum['uid'] = $uid;
                $whereUmum['id'] = $mum['id'];
                //直接进行账户合并
                $ip = get_client_ip();
                $ua = input('server.HTTP_USER_AGENT');
                $t = time();
                $aum = [
                    'gid' => $this->gid,
                    'guid' => $um[$uid]['guid'],
                    'uid' => $uid,
                    'ctime' => $t,
                    'useable' => $mumx['useable'],
                    'disable' => $mumx['disable'],
                    'status' => 1,
                ];
                $upumx = [
                    'useable' => 0,
                    'disable' => 0,
                    'status' => -1
                ];
                $ump[] = [
                    'uinc' => $mumx['useable'],
                    'dinc' => $mumx['disable'],
                    'useable' => $mum['useable'] + $mumx['useable'],
                    'disable' => $mum['disable'] + $mumx['disable'],
                    'uid' => $uid,
                    'gid' => $mum['gid'],
                    'auid' => $uid,
                    'atype' => 1,
                    'type' => 9,
                    'desc' => '【账户合并】',
                    'ua' => $ua,
                    'ctime' => $t,
                    'ip' => $ip
                ];
                $ump[] = [
                    'uinc' => 0 - $mumx['useable'],
                    'dinc' => 0 - $mumx['disable'],
                    'useable' => 0,
                    'disable' => 0,
                    'uid' => $xuid,
                    'gid' => $mum['gid'],
                    'auid' => $xuid,
                    'atype' => 1,
                    'type' => 9,
                    'desc' => '【账户合并】该会员卡账户已失效！无法进行任何操作！',
                    'ua' => $ua,
                    'ctime' => $t,
                    'ip' => $ip
                ];
                $whereUmumx['id'] = $mumx['id'];
                $whereUmumx['uid'] = $xuid;
                Db::name('mcard_um')->insert($aum);
                Db::name('mcard_um')->where($whereUmumx)->update($upumx);
                Db::name('mcard_umpost')->insertAll($ump);
            }
            //更新绑定的微信 uid
            $whereUo['gid'] = $this->gid;
            $whereUo['uid'] = $uid;
            $uo = Db::name('user_open')->where($whereUo)->find();
            $whereUox['gid'] = $this->gid;
            $whereUox['uid'] = $xuid;
            if(empty($uo)){
                $whereUuo['gid'] = $this->gid;
                $whereUuo['uid'] = $xuid;
                Db::name('user_open')->where($whereUuo)->update(['uid' => $uid]);
            }
            //客户跟进合并
            $whereUup['gid'] = $this->gid;
            $whereUup['uid'] = $xuid;
            Db::name('user_post')->where($whereUup)->update(['uid' => $uid]);
            $whereUo['gid'] = $this->gid;
            $whereUo['uid'] = $xuid;
            Db::name('order')->where($whereUo)->update(['uid' => $uid]);
            $whereUop['gid'] = $this->gid;
            $whereUop['uid'] = $xuid;
            Db::name('order_post')->where($whereUop)->update(['uid' => $uid]);
            $whereUog['gid'] = $this->gid;
            $whereUog['uid'] = $xuid;
            Db::name('order_goods')->where($whereUog)->update(['uid' => $uid]);
            $whereUc['gid'] = $this->gid;
            $whereUc['user_id'] = $xuid;
            Db::name('cart')->where($whereUc)->update(['user_id' => $uid]);
            $whereUro['gid'] = $this->gid;
            $whereUro['uid'] = $xuid;
            Db::name('rgorder')->where($whereUro)->update(['uid' => $uid]);
            $whereUrop['gid'] = $this->gid;
            $whereUrop['uid'] = $xuid;
            Db::name('rgorder_post')->where($whereUrop)->update(['uid' => $uid]);
            $whereUa['gid'] = $this->gid;
            $whereUa['uid'] = $xuid;
            Db::name('user_address')->where($whereUa)->update(['uid' => $uid]);
            $whereUom['gid'] = $this->gid;
            $whereUom['uid'] = $xuid;
            Db::name('order_member')->where($whereUom)->update(['uid' => $uid]);
            $whereUul['gid'] = $this->gid;
            $whereUul['uid'] = $xuid;
            Db::name('user_log')->where($whereUul)->update(['uid' => $uid]);
            $uUser = [];
            foreach($um[$uid] as $k => $v){
                if(!$v){
                    $uUser[$k] = $um[$xuid][$k];
                }else{
                    $ak = ['pay_points','rank_points','user_money','frozen_money'];
                    if(in_array($k, $ak)){
                        if($um[$xuid][$k] > 0){
                            $uUser[$k] += $um[$xuid][$k];
                        }
                    }
                }
            }
            if(!empty($uUser)){
                $whereUum['gid'] = $this->gid;
                $whereUum['uid'] = $uid;
                Db::name('user_member')->where($whereUum)->update($uUser);
            }
            $whereDum['gid'] = $this->gid;
            $whereDum['uid'] = $xuid;
            Db::name('user_member')->where($whereDum)->delete();
            Db::commit();
            return ['code' => 0,'msg' => '客户资料已合并！','uid' => $uid];
        }catch(\think\Exception $e){
            Db::rollBack();
            return ['code' => -1,'msg' => '系统繁忙' . $e->getMessage() . __LINE__];
        }
    }
    private function upUser($input = [],$upwx = true){
        $um = $input['um'];
        $userinfo = $input['userinfo'];
        $openId = $input['openid'];
        $x = $input['x'];
        $y = $input['y'];
        $mobile = isset($input['mobile']) ? trim($input['mobile']) : '';
        $uid = $um['uid'];
        $user['weixin'] = $openId;
        $user['mobile'] = $mobile;
        if($mobile){
            $user['trust'] = 1;
            $user['is_check_mobile'] = 1;
        }else{
            $user['trust'] = $um['trust'];
            $user['is_check_mobile'] = $um['is_check_mobile'];
        }
        $user['shopid'] = $this->groupShop['id'];
        $user['gender'] = $userinfo['sex'];
        $user['realname'] = $userinfo['nickname'];
        $user['nickname'] = $userinfo['nickname'];
        $um['x'] ? null : $user['x'] = $x;
        $um['y'] ? null : $user['y'] = $y;
        $um['address'] ? null : $user['address'] = $userinfo['country'] . $userinfo['province'] . $userinfo['city'] ;
        $um['unique_sign'] ? null : $user['unique_sign'] = mkUniqueSign($this->gid,['uid'=>$uid,'gid'=>$this->gid]);
        $user['lastvisit'] = time();
        $user['lastip'] = get_client_ip();
        if(!empty($user)){
            $whereUum['gid'] = $this->gid;
            $whereUum['mobile'] = $um['mobile'];
            $whereUum['uid'] = $um['uid'];
            Db::name('user_member')->where($whereUum)->update($user);
            $user['uid'] = $uid;
            $user['gid'] = $this->gid;
        }
        if($upwx === true){
            $userinfo['uid'] = $uid;
            $userinfo['gid']= $this->gid;
            $userinfo['openid'] = $openId;
            $userinfo['shopid'] = $this->groupShop['id'];
            $userinfo['weixin'] = $this->groupShop['weixin'];
            $this->uwx($userinfo);
        }
        $sessData['uid'] = $user['uid'];
        $sessData['gid'] = $user['gid'];
        $sessData['guid'] = $user['guid'];
        $sessData['unique_sign'] = $user['unique_sign'];
        $sessData['realname'] = isset($user['realname']) ? $user['realname'] : '';
        $sessData['trust'] = $user['trust'];
        $sessData['is_check_mobile'] = $user['is_check_mobile'];
        $sessData['mobile'] = $user['mobile'];
        $this->sessionSet('usermember',$sessData);
        return json(['code' => 0,'msg' =>  '登录成功，即将跳转到商城首页！']);
    }
    private function addUser($input = []){
        $x = $input['x'];
        $y = $input['y'];
        $mobile = isset($input['mobile']) ? trim($input['mobile']) : '';
        $userinfo = $input['userinfo'];
        $openId = $input['openid'];
        $ip = get_client_ip();
        $user['gid'] = $this->gid;
        $user['guid'] = 0;
        $user['realname'] = $userinfo['nickname'] ? $userinfo['nickname'] : '';
        $user['nickname'] = $userinfo['nickname'] ? $userinfo['nickname'] : '';
        $user['bd'] = '0000-00-00';
        $user['birthday'] = '0000-00-00';
        $user['frozen_money'] = '0';
        $user['x'] = $x ? $x : 0;
        $user['y'] = $y ? $y : 0;
        $user['bdate'] = '0';
        $user['odate'] = '0';
        $user['hdate'] = '0';
        if($mobile){
            $user['trust'] = 1;
            $user['is_check_mobile'] = 1;
        }else{
            $user['trust'] = 0;
            $user['is_check_mobile'] = 0;
        }
        $user['lastvisit'] = time();
        $user['lastip'] = $ip;
        $user['regtime'] = time();
        $user['regip'] = $ip;
        $user['taobao'] = '';
        $user['email'] = '';
        $user['qq'] = '';
        $user['shopid'] = $this->groupShop['id'];
        $user['weixin'] = $openId;
        $user['alipay'] = '';
        $user['summary'] = '';
        $user['yhzh'] = '';
        $user['mobile'] = $mobile; 
        $user['gender'] = $userinfo['sex']; 
        $user['address'] = ($userinfo['country'] ? $userinfo['country'] : '') . ($userinfo['province'] ? $userinfo['city'] : ''). ($userinfo['city'] ? $userinfo['city'] : '');
        Db::startTrans();
        try{
            $user['uid'] = $uid = Db::name('user_member')->insertGetId($user);
            $whereUmm['gid'] = $this->gid;
            $whereUmm['uid'] = $uid;
            $whereUmm['mobile'] = $mobile;
            $whereUmm['trust'] = 1;
            $whereUmm['shopid'] = $this->groupShop['id'];
            Db::name('user_member')->where($whereUmm)->update(['unique_sign' => mkUniqueSign($this->gid,['uid'=>$uid,'gid'=>$this->gid])]);
            Db::commit();
        }catch(\think\Exception $e){
            Db::rollBack();
            return ['code' => -1,'msg' => '系统繁忙，请稍后再试！' . $e->getMessage()];
        }
        $userinfo['uid'] = $uid;
        $userinfo['gid']= $this->gid;
        $userinfo['openid'] = $openId;
        $userinfo['shopid'] = $this->groupShop['id'];
        $userinfo['weixin'] = $this->groupShop['weixin'];
        $this->uwx($userinfo);
        $sessData['uid'] = $user['uid'];
        $sessData['gid'] = $user['gid'];
        $sessData['guid'] = $user['guid'];
        $sessData['unique_sign'] = $user['unique_sign'];
        $sessData['realname'] = isset($user['realname']) ? $user['realname'] : '';
        $sessData['trust'] = $user['trust'];
        $sessData['is_check_mobile'] = $user['is_check_mobile'];
        $sessData['mobile'] = $user['mobile'];
        $this->sessionSet('usermember',$sessData);
        return json(['code' => 0,'msg' => '注册成功，即将跳转到商城首页！']);
    }
    private function uwx($userinfo){
        $wxuserid = isset($userinfo['id']) ? (int)$userinfo['id'] : false;
        unset($userinfo['id']);
        if(!$wxuserid){
            $map['weixin'] = $userinfo['openid'];
            $map['gid'] = $this->gid;
            $wxuserid = (int)Db::name('user_open')->where($map)->value('id');
        }
        $usero['uid'] = $userinfo['uid'];
        $usero['gid'] = $this->gid;
        $usero['shopid'] = $userinfo['shopid'];
        $usero['nickname'] = $userinfo['nickname'];
        $usero['sex'] = $userinfo['sex'];
        $usero['province'] = $userinfo['province'];
        $usero['city'] = $userinfo['city'];
        $usero['country'] = $userinfo['country'];
        $usero['headimgurl'] = $userinfo['headimgurl'];
        $usero['unionid'] = isset($userinfo['unionid']) ? $userinfo['unionid'] : '';
        $usero['weixin'] = $userinfo['openid'];
        $usero['tousername'] = $userinfo['weixin'];
    	if(!$wxuserid){
            Db::name('user_open')->insert($usero);
    	}else{
            $map['id'] = $wxuserid;
            Db::name('user_open')->where($map)->update($usero);
    	}
    }
    private function sendsms($input = []){
        $url = str_replace('.m.', '.', $_SERVER['HTTP_HOST']);
        //$url = $_SERVER['HTTP_HOST'];
        if(!session('gid')){
            $gid = Db::name('group')->where(['url|sysurl' => 'http://' .$url])->value('gid');
            session('gid',$gid);
        }else{
            $gid = session('gid');
        }
        $mobile = isset($input['mobile']) ? trim($input['mobile']) : false;
        $code = isset($input['code']) ? $input['code'] : false;
        $codetype = isset($input['codetype']) ? $input['codetype'] : false;
        $uid = isset($input['uid']) ? (int)$input['uid'] : 0;
        $guid = isset($input['guid']) ? (int)$input['guid'] : 0;
        if(preg_match("/^1[3456789]\d{9}$/", $mobile)){
            $UserSmsCode = new UserSmsCode;
            if(!$code){
                $smscode = $UserSmsCode->setCode($mobile,$codetype,['uid' => $uid,'gid' => $gid]);
            }else{
                $smscode = $code;
            }
            $st = $UserSmsCode->getSmsTemplate($codetype);
            if($st['template']){
                $Send = new SendSms;
                if($gid){
                    $messagenum =Db::name('group_app')->where('gid',$gid)->where('appid',9)->value('num');
                    if($messagenum < 1){
                        return json(['code' =>-1, 'data' => '', 'msg' => '短信数量不足请充值']);
                    }else{
                        $res = $Send->sms([
                            'param'  => ['code'=>$smscode,'product'=>$st['product']],
                            'mobile'  => input('post.mobile/s','','trim,strip_tags'),
                            'template'  => $st['template'],
                        ],'身份验证');
                        if($res !== true){
                            return json(['code' => -1, 'data' => '', 'msg' => '验证码发送失败']);
                        }else{
                            $addMessages['dateline'] = "0:".$mobile;
                            $addMessages['desc'] = "验证手机号码";
                            $addMessages['returnto'] = 1;
                            $addMessages['type'] = 2;
                            $addMessages['fromid'] = $uid;
                            $addMessages['adate'] = time();
                            $addMessages['gid'] = $gid;
                            $addMessages['guid'] = $guid;
                            try{
                                Db::name('message')->insert($addMessages);
                                Db::name('group_app')->where('gid',$gid)->where('appid',9)->setInc('num',-1);
                                return json(['code' =>0, 'msg' => '验证码已经发送到您的手机，请注意查收！']);
                            }catch(\think\Exception $e){
                                return json(['code' =>1, 'data' => '', 'msg' => '验证码发送失败' . $e->getMessage()]);
                            }
                        }
                    }
                }
            }else{
                return json(['code' => -1, 'data' => '', 'msg' => '手机号码错误']);
            }
        }else{
            return json(['code' => -1, 'data' => '', 'msg' => '手机号码格式不正确！']);
        }
    }
    private function sessionSet($prefix = [],$data = []){
        foreach($data as $k => $v){
            if($prefix = trim($prefix)){
                session($prefix . '.' . $k, $v);
            }
        }
        return true;
    }
    private function userOpenid($config = []){
    	$url="https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $config['appid']."&secret=".$config['appsecret']."&code=".$config['code']."&grant_type=authorization_code";
    	$access_token = file_get_contents($url);
    	$access_token = json_decode($access_token, true);
    	session('user_wx_access_token',$access_token);
        return $access_token;
    }
    public function logout(){
        session('usermember',null);
        session('re_url',null);
        session('user_openid',null);
        session('gid',null);
        session('shopinfo',null);
        $this->redirect(url('/shop/login/login'));
    }
    public function access(){
    	if(is_weixin()){
    		if(!session('gid')){
                    $url = str_replace('.m.', '.', $_SERVER['HTTP_HOST']);
                    //$url = $_SERVER['HTTP_HOST'];
                    $groupinfo = Group::get(['url|sysurl' => 'http://' .$url])->getData();
                    session('gid',$groupinfo['gid']);
    		}
    		$config = Db::name('wx_token')->where('gid',session('gid'))->find();
    		$code = $config['code'] = input('code');
    		$url=input('url');
    		if(!$code){
                    $url=urlencode($url);
                    $redirect_uri='http%3A%2F%2F'.$_SERVER['HTTP_HOST'].'%2Fshop%2Flogin%2Faccess';
                    /*if(session('gid')==201){
                            echo $redirect_uri;
                            exit();
                    }*/
                    //$codeurl='https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$config['appid'].'&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_base&state=123#wechat_redirect';
                    $codeurl='https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$config['appid'].'&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_userinfo&state=123#wechat_redirect';
                    $this->redirect($codeurl);
    		}else{
                    $access_token = $this->userOpenid($config);
                    session('user_openid',$access_token['openid']);
                    $url2=session('re_url');
                    if($url2){
                        $this->redirect($url2);
                    }
    		}
    	}else{
    		
    	}
    }
    private function getwxuser(){
        $wxUserInfo = session('wxuserinfo');
        if(!empty($wxUserInfo)){
            $userinfo = $wxUserInfo;
        }else{
            $data = session('user_wx_access_token');
            $url='https://api.weixin.qq.com/sns/userinfo?access_token='.$data['access_token'].'&openid='.$data['openid'].'&lang=zh_CN';
            $userinfo = file_get_contents($url);
            $userinfo = json_decode($userinfo, true);
            session('wxuserinfo',$userinfo);
            
        }
        return $userinfo;
    }
    private function adduseropen($userinfo){
    	$map['weixin']=$userinfo['openid'];
    	$map['gid']=session('gid');
    	$wxuserid=Db::name('user_open')->where($map)->value('id');
    	if(!$wxuserid){
            $usero['uid'] = $userinfo['uid'];
            $usero['gid'] = $userinfo['gid'];
            $usero['shopid'] = $userinfo['shopid'];
            $usero['nickname'] = $userinfo['nickname'];
            $usero['sex'] = $userinfo['sex'];
            $usero['province'] = $userinfo['province'];
            $usero['city'] = $userinfo['city'];
            $usero['country'] = $userinfo['country'];
            $usero['headimgurl'] = $userinfo['headimgurl'];
            if($userinfo['privilege']) $usero['privilege'] = $userinfo['privilege'];
            if($userinfo['unionid']) $usero['unionid'] = $userinfo['unionid'];
            $usero['weixin'] = $userinfo['openid'];
            $usero['tousername'] = $userinfo['weixin'];
            Db::name('user_open')->insert($usero);
    	}else{
            $usero['uid'] = $userinfo['uid'];
            $usero['gid'] = $userinfo['gid'];
            $usero['shopid'] = $userinfo['shopid'];
            $usero['nickname'] = $userinfo['nickname'];
            $usero['sex'] = $userinfo['sex'];
            $usero['province'] = $userinfo['province'];
            $usero['city'] = $userinfo['city'];
            $usero['country'] = $userinfo['country'];
            $usero['headimgurl'] = $userinfo['headimgurl'];
            if($userinfo['privilege']) $usero['privilege'] = $userinfo['privilege'];
            if($userinfo['unionid']) $usero['unionid'] = $userinfo['unionid'];
            $usero['weixin'] = $userinfo['openid'];
            $usero['tousername'] = $userinfo['weixin'];
            Db::name('user_open')->where($map)->update($usero);
    	}
    	//print_r($usero);
    	//return $userinfo;
    }
    /**
     * 获取当前页面完整URL地址
     */
    public function get_url() {
    	$sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
    	$php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
    	$path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    	$relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
    	return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
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
}


<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\shop\controller;
use app\shop\controller\Base;
use think\Db;
use think\Request;
use app\shop\model\UserSmsCode;
class User extends Base{
    public function __construct(Request $request = null) {
        parent::__construct($request);
        $this->initData = [
            'sign'=>$this->sign,
            'requestFunc'=>$this->requestFunc,
            'requestUrl'=>$this->requestUrl,
            'cUrl'=>$this->cUrl,
            'jsName'=>$this->jsName,
        ];
        $this->assign('initData',$this->initData);
    }
    public function index(){
        $map = array();
        $map['gid']  = $this->gid;
        $map['uid']  = $this->uid;
        if(!!$this->uid){
            $userinfo = Db::name('user_member')
                ->where( $map )
                ->field('uid,gid,nickname,realname,birthday,gender,mobile,user_money,frozen_money,is_check_mobile,unique_sign,is_fx')
                ->find();
            $userinfo['icon']=Db::name('user_open')->where( $map )->value('headimgurl');
            $userinfo['tgurl']='http://'.$_SERVER['HTTP_HOST'].'/shop/user/index/fxpsign/'.$userinfo['unique_sign'].'.html';
            $this->assign('userinfo',$userinfo);
            //  渲染输出
            return view('/ljk/userhome');
        }
        $userinfo = Db::name('user_member')
            ->where( $map )
            ->field('uid,gid,nickname,realname,birthday,gender,mobile,user_money,frozen_money,is_check_mobile,unique_sign,is_fx')
            ->find();
        $userinfo['icon']=Db::name('user_open')->where( $map )->value('headimgurl');
        $userinfo['tgurl']='http://'.$_SERVER['HTTP_HOST'].'/shop/user/index/fxpsign/'.$userinfo['unique_sign'].'.html';
        $this->assign('userinfo',$userinfo);
        return view('/ljk/userhome');
    }
    public function address(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $action = [];
            if(isset($input['action']) && $action = trim($input['action'])){
                unset($input['action']);
            }
            switch($action){
                case 'getareas':return $this->getAreas($input);
                case 'getoneaddress':return $this->getOneAddress($input);
                case 'editaddress':return $this->editAddress($input);
                case 'addresslist':return $this->addressList($input);
                case 'setdefaultaddress':return $this->setDefaultAddress($input);
            }
        }
    }
    public function getAreas($input = [],$jsign = 1){
        if(!empty($input)){
            $whereA['level'] = isset($input['level']) ? trim($input['level']) : 1;
            $whereA['parent_code'] = isset($input['parent_code']) ? trim($input['parent_code']) : '';
        }else{
            $whereA['level'] = 1;
            $whereA['parent_code'] = '';
        }
        $areas = Db::name('area')->where($whereA)->select();
        //查询一下下个level是否还有选项
        if(empty($areas)){
            $res = ['code' => -1,'msg' => '数据加载成功','data' => $areas];
        }else{
            $res = ['code' => 0,'msg' => '数据加载成功','data' => $areas];
        }
        if($jsign === 1){
            return json($res);
        }else{
            return $res;
        }
    }
    //  TODO:: 主页信息
    public function index_json()
    {
    	//  当前用户账号
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	
    	//  查询数据
    	$result = Db::name('user_member')->find( $map );
    	if( empty($result) ){
    		return $this->reJson( 1, 0, '没有账号信息' );
    	}
    	$result['icon']=Db::name('user_open')->where( $map )->value('headimgurl');
    	$result['tgurl']='http://'.$_SERVER['HTTP_HOST'].'/shop/user/index/fxpsign/'.$result['unique_sign'].'.html';
    	//  替换掉市辖区
    	$result['areaname'] = str_replace( [ '市市辖区', '市县' ], '市', $result['areaname'] );
    	//
    	return $this->reJson( 10, $result);
    }
    
    //  TODO:: 我的可用余额
    public function money(){
    	//  当前用户账号
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	//  查询数据
    	$moneyInfo = Db::name('user_member')->where($map)->field('gid,user_money,frozen_money')->find();
    	//  资金提现记录
    	$moneyInfo['withdrawals'] = Db::name('user_withdrawals')->where($map)
    	->order( 'id DESC' )
    	->limit( 0, 5)
    	->select();
    	$whereGs['gid'] =$moneyInfo['gid'];
    	//  $whereGs['url'] = [['EQ', 'http://'. $_SERVER['HTTP_HOST']], ['EQ', 'https://'. $_SERVER['HTTP_HOST']] , 'OR'];
    	$whereGs['url'] = 'http://'. $_SERVER['HTTP_HOST'];
    	$Shopinfo = Db::name('group_shop')->where($whereGs)->find();
    	$this->assign('groupShop',$Shopinfo);
    	$this->assign('moneyInfo',$moneyInfo);
    	//  渲染输出
    	return view('/ljk/money');
    }
        //  TODO:: 我的可用余额
    public function card(){
    	//  当前用户账号
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $action = '';
            if(isset($input['action'])){
                $action = $input['action'];
                unset($input['action']);
            }
            switch($action){
                case 'cardrecord':return $this->cardRecord($input);
                case 'cardcharge':return $this->cardCharge($input);
            }
        }else{
            if(empty($this->umca)){
                $this->umca = $this->umca();
            }
            $this->assign('cardum',$this->umca);
            //  渲染输出
            return view('/ljk/card');
        }
    }
    private function cardCharge($input = []){
        $uid = $this->uid;
        $mcard = isset($input['mcard']) ? (array)$input['mcard']: [];
        if(!$uid){
            $msg = '非法操作！';
            return json(['code' => -1,'msg' => $msg]);
        }
        //过滤无效的卡输入，重复的卡输入，只保留第一次输入的卡号
        $mcards = [];
        $sns  = [];
        foreach($mcard as $x){
            $pwd = isset($x['pwd']) ? $x['pwd'] : false;
            $sn = isset($x['sn']) ? $x['sn'] : false;
            if($pwd && $sn){
                if(!isset($mcards[$sn])){
                    $mcards[$sn] = $pwd;
                    $sns[] = $sn;
                }
            }
        }
        if(empty($sns)){
            $msg = '必须填写卡号及密码！';
            return json(['code' => -1,'msg' => $msg]);
        }
        //继续过滤符合条件的sn
        $whereMc['gid'] = $this->gid;
        $whereMc['sn'] = ['IN',$sns];
        $mcardsx = Db::name('mcard')->where($whereMc)->column('sn,id,pwd,uid,facevalue,balance,status,expiry,expirys,expirye,salt');
        if(empty($mcardsx)){
            $msg = '未查询到输入的会员卡信息！';
            return json(['code' => -1,'msg' => $msg]);
        }
        //验证并继续过滤
        $tips = [];
        $vv = [];
        foreach($mcardsx as $k=>$v){
            if(!!$v['uid'] && $v['uid'] != $uid){
                $tips[] = '会员卡：' . $k . '已被占用！';
            }else if($v['balance'] == 0){
                $tips[] = '会员卡：' . $k . '已使用完毕！';
            }else if($v['status'] == 0){
                $tips[] = '会员卡：' . $k . '已被禁用！';
            }else if($v['expiry'] == 1){
                if($v['expirys'] > time()){
                    //未到使用期
                    $tips[] = '会员卡：' . $k . '未到使用期！';
                }else if($v['expirye'] < time()){
                    $tips[] = '会员卡：' . $k . '已过使用期！';
                }else{
                    //开始验证密码
                    $p = $mcards[$k];
                    //生成密码 并 比对
                    $pwd = pwd($p,$v['salt']);
                    if($pwd['pwd'] !== $v['pwd']){
                        $tips[] = '会员卡：' . $k . '密码错误！';
                    }else{
                        $vv[] = $v;
                    }
                }
            }else{
                //开始验证密码
                $p = $mcards[$k];
                //生成密码 并 比对
                $pwd = pwd($p,$v['salt']);
                if($pwd['pwd'] !== $v['pwd']){
                    $tips[] = '会员卡：' . $k . '密码错误！';
                }else{
                    $vv[] = $v;
                }
            }
        }
        if(empty($vv)){
            return json(['code' => -1,'msg' => implode('<br />', $tips)]);
        }
        $res = $this->corecum($vv);
        $msg = $res['msg'];
        if($res['code'] === 0){
            return json(['code' => -1,'msg' => $msg]);
        }else{
            return json(['code' => -1,'msg' => $res['msg']]);
        }
        if(!empty($tips)){
            $tipss = implode('<br />', $tips);
            $msg .= '<br />' . $tipss;
        }
        return json(['code' => 0,'msg' => $msg]);
    }
    private function corecum($mcards = []){
        $uid = $this->uid;
        if($uid === false){
            return ['code' => -1,'msg' => '请选择需要进行充值的会员！'];
        }
        if(empty($mcards)){
            return ['code' => -1,'msg' => '请选择会员卡！'];
        }
        $whereUm['gid'] = $this->gid;
        $whereUm['uid'] = $uid;
        $um = Db::name('user_member')->field('gid,guid,uid')->where($whereUm)->find();
        if(!$um){
            return ['code' => -1,'msg' => '不存在此客户信息，无法充值！'];
        }
        //查询该用户是否已经拥有会员卡账户
        $whereMum['gid'] = $this->gid;
        $whereMum['uid'] = $uid;
        $mum = Db::name('mcard_um')
                ->field('id,useable,disable,status')
                ->where($whereMum)
                ->find();
        if(!empty($mum)){
            if($mum['status'] == 0 ){
                return ['code' => -1,'msg' => '该会员的会员卡账户已被禁用，如需充值，请前往会员账户启用后再进行操作！'];
            }else{
                $whereMum['id'] = $mum['id'];
            }
        }else{
            $mum['status'] = 1;
            $mum['gid'] = $this->gid;
            $mum['uid'] = $uid;
            $mum['useable'] = 0;
            $mum['disable'] = 0;
            $mum['ctime'] = time();
        }
        $mum['guid'] = $um['guid'];
        $mump['gid'] = $this->gid;
        $mump['uid'] = $uid;
        $mump['auid'] = $this->uid;
        $mump['atype'] = 1;
        $mump['type'] = 2;//充值
        $mump['ctime'] = $t = time();
        $mump['ua'] = input('server.HTTP_USER_AGENT');
        $mump['ip'] = get_client_ip();
        $mump['uinc'] = 0;
        $mump['dinc'] = 0;
        $mcpsx = [];
        $mcids = [];
        $mcUpdata['uid'] = $uid;
        $mcUpdata['balance'] = 0;
        for($i = 0, $l = count($mcards); $i < $l; $i++){
            if(!!$mcards[$i]['uid'] && $mcards[$i]['uid'] != $uid){
                continue;
            }
            $mcp = [];
            $mcp['gid'] = $this->gid;
            $mcp['mcardid'] = $mcids[] = $mcards[$i]['id'];
            $mcp['atype'] = 1;
            $mcp['inc'] = -$mcards[$i]['balance'];
            $mcp['balance'] = 0;
            $mcp['auid'] = $this->uid;
            $mcp['type'] = 2;
            $mcp['ctime'] = $t;
            $mcp['desc'] = '【充值】';
            $mcpsx[] = $mcp;
            $mum['useable'] +=  $mcards[$i]['balance'];
            $mump['uinc'] +=  $mcards[$i]['balance'];
        }
        $whereMc['id'] = ['IN', $mcids];
        $mump['useable'] = $mum['useable'];
        $mump['disable'] = $mum['disable'];
        Db::startTrans();
        try{
            if(isset($mum['id']) && !!$mum['id']){
                $mumid = $mum['id'];
                unset($mum['id']);
                Db::name('mcard_um')->where($whereMum)->update($mum);
            }else{
                $mumid = Db::name('mcard_um')->insertGetId($mum);
            }
            $mumpid = Db::name('mcard_umpost')->insertGetId($mump);
            $mcps = array_map(function ($mcp) use($mumpid){
                $mcp['mumpid'] = $mumpid;
                return $mcp;
            },$mcpsx);
            Db::name('mcard_post')->insertAll($mcps);
            Db::name('mcard')->where($whereMc)->update($mcUpdata);
            Db::commit();
            return ['code' => 1,'msg' => '充值成功！','umc'=>['useable' => $mum['useable'],'disable' => $mum['disable']]];
        }catch(\think\Exception $e){
            Db::rollBack();
            $arrtips['msg'] = '系统繁忙！' . $e->getMessage();
            $arrtips['autoclose_sign'] = true;
            $arrtips['close'] = array('sign'=>true);
            return $this->tips($arrtips);
        }
    }
    private function cardRecord($input = []){
        $p = isset($input['p']) && (intval($input['p']) > 0) ?  intval($input['p'])  : 1;
        $limit = isset($input['limit']) && (intval(trim($input['limit'])) > 0) ?  intval(trim($input['limit'])) : 10;
        $type = isset($input['type']) ? intval($input['type']) : 0;
        switch($type){
            case 2:$whereMum['type'] = 2;break;
            case 6:$whereMum['type'] = 6;break;
            default :null;
        }
        $whereMum['gid'] = $this->gid;
        $whereMum['uid'] = $this->uid;
        if($p === 1){
            $total = Db::name('mcard_umpost')
                ->where($whereMum)
                ->count();
            if($total === 0){
                return json(['code'=>0,'msg'=>'没有更多数据！']);
            }
        }
        $umPost = Db::name('mcard_umpost')
            ->where($whereMum)
            ->field('id,ctime,type,uinc,useable,dinc,disable')
            ->page($p,$limit)
            ->order(['id' => 'DESC'])
            ->select();
        //返回数据
        if(isset($total)){
            return json(['code' => 0,'msg' => '数据加载成功！','data' => $umPost,'total' => $total]);
        }else{
            if(empty($umPost)){
                return json(['code' => 1,'msg' => '数据加载成功！','data' => $umPost]);
            }else{
                return json(['code' => 0,'msg' => '数据加载成功！','data' => $umPost]);
            }
        }
    }
    //  TODO:: 我的可用余额和资金操作
    public function money_json(){
    	//  当前用户账号
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	//  查询数据
    	$result = Db::name('user_member')->where($map)->find();
    	if( empty($result) ){
    		return $this->reJson( 1, 0, '没有账号信息' );
    	}
    	//  资金提现记录
    	$txData = Db::name('user_withdrawals')->where($map)
    	->order( 'datetime DESC' )
    	->limit( 0, 5)
    	->select();
    	$result['withdrawals'] = $txData;
    	// 返回数据
    	return $this->reJson( 10, $result);
    }
    
    //  TODO:: 余额明细
    public function money_detail_json(){
    	//  当前用户账号
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	//  页数
    	$page = input('page');
    	//  资金提现记录
    	$detailData = Db::name('user_withdrawals')->where($map)
    	->order( 'datetime DESC' )
    	->page( $page, 5)
    	->select();
    	return $this->reJson( 10, $detailData);
    }
    
    //  TODO:: 用户个人信息显示
    public function info(){
    	// 显示个人信息
    	//  当前用户账号
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	$userinfo = Db::name('user_member')
    	->where( $map )
    	->field('nickname,realname,birthday,gender,mobile,is_check_mobile')
    	->find();
    	$userinfo['icon']=Db::name('user_open')->where( $map )->value('headimgurl');
    	$this->assign('userinfo',$userinfo);
    	return view('/ljk/user_info');
    }
    
    //  TODO:: 返回固定json 状态码,数据,提示信息
    //0 -1 -2 -3 错误状态码
    //1 2 3 正确状态码
    public function reJson( $code, $data, $msg = '' ){
    	$reJson = array();
    	$reJson['code'] = $code;
    	$reJson['data'] = $data;
    	$reJson['msg'] = $msg;
    	return json( $reJson );
    }
    
    //  TODO:: 新增默认用户信息
    public function addInfo(){
    	//  当前用户账号
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	if( empty( $map['gid'] ) || empty( $map['uid'] ) ){
    		return $this->reJson( 1, 0, '请登陆账号' );
    	}
    	// 返回所有地址
    	Db::name('user_member')->insert( $map );
    	//  返回用户个人信息
    	return $this->getInfo;
    }
    
    //  TODO:: 获取用户个人信息显示
    public function getInfo(){
    	//  当前用户账号
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	
    	// 返回所有地址
    	$result = Db::name('user_member')
    	->where( $map )
    	->field('nickname,realname,birthday,gender,mobile,is_check_mobile')
    	->find();
    	$result['icon']=Db::name('user_open')->where( $map )->value('headimgurl');
    	if( !$result ){  //  没有个人信息
    		//  整合数据
    		$reJson = array();
    		$reJson['code'] = 1;
    		return json( $reJson );
    	}
    	//  返回数据
    	return $this->reJson( 10, $result );
    }
    
    //  TODO:: 用户信息编辑
    public function updateInfo(){
    	//  当前用户账号
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	if( empty( $map['gid'] ) || empty( $map['uid'] ) ){
    		return $this->reJson( 1, 0, '请登陆账号' );
    	}
    	//  取出数据
    	$province   = explode( ',', input('province'));
    	$city       = explode( ',', input('city'));
    	$area       = explode( ',', input('area'));
    	$data = array();
    	/*$data['fxpid']      = 3000;
    	 $data['is_distribut'] = 0;  //  是否多级分销
    	$data['type']       = 2;    //  账号类型
    	$data['is_lock']    = 1;    //  是否锁定账号
    	$data['trust']      = 1;    //  是否认证
    	*/
    	$data['nickname']   = trim( input('nickname') );    //  客户姓名
    	$data['nickname'] = str_replace(' ', '', $data['nickname']);   //  去掉空格
    	$data['realname']   = trim( input('realname') );    //  真实姓名
    	$data['realname'] = str_replace(' ', '', $data['realname']);   //  去掉空格
    	$data['birthday']   = input('birthday');    //  生日
    	$data['gender']     = input('gender');  //  性别
    	//$data['areaids']    = $province[0] . ',' . $city[0] . ',' . $area[0];    //  地址ID
    	//$data['areaname']   = $province[1] . $city[1] . $area[1];
    	//$data['address']    = input('address'); //  地址
    	//$data['address'] = str_replace( ' ', '', $data['address']);   //  去掉空格
    	//$data['email']      = input('email'); //  邮箱
    	//$data['email'] = str_replace( ' ', '', $data['email']);   //  去掉空格
    	//  头像上传
    	if( !is_null( input('icon') ) ){
    		//  更改头像
    		//$data['icon']      = input('icon'); //  头像
    	}
    
    	//  验证数据正确性
    	$rule = [
    	//['nickname', 'require|min:6|max:18', '昵称不能为空|昵称不能短于1个字|昵称不能超过6个字'],
    	['realname', 'require|min:6|max:30', '|真实姓名不能短于2个字|真实姓名不能超过10个字'],
    	//['gid', 'require', '请登陆后，组ID不能为空'],
    	//['uid', 'require', '请登陆后，客户ID不能为空'],
    	//['address', 'require|min:6|max:60', '请输入详细地址|地址最少2个字|地址最多20个字'],
    	//['birthday', 'require', '生日不能为空'],
    	//['areaids', 'require', '请选择省市区'],
    	//['areaname', 'require', '省市区获取失败'],
    	//['phone', '/^1[3456789]\d{9}$/', '电话号码填写错误'],
    	//['email', 'require', '邮箱不能为空'],
    	];
        $l = mb_strlen($data['realname'],'utf-8');
        $errmsg = false;
        if($l === 0){
            $errmsg = '真实姓名不能为空';
        }else if($l < 2){
            $errmsg = '真实姓名不能短于2个字';
        }else if($l > 30){
            $errmsg = '真实姓名不能超过10个字';
        }
        if(!!$errmsg){
            return $this->reJson( 2, 0, $errmsg);
        }
//    	$result = $this->validate( $data, $rule );
//    	if( $result !== true ){
//    		return $this->reJson( 2, 0, $result );
//    	}
    	//  更新用户个人信息
    	$result = Db::name('user_member')->where( $map )->update( $data );
    	//  更新成功,返回完整个人信息
    	return $this->getInfo();
    }
    
    /*
     *      TODO:: 用户积分
    *
    * */
    public function integral(){
    	$map = $this->getUserId();
    	unset( $map['shopid'] );    //  记录表不包含shopid
    	$reData = Db::name('user_member')
    	->where( $map )
    	->field(['pay_points','rank_points'])
    	->find();
    	$this->assign('points', $reData);
    	return $this->fetch('/ljk/integral');
    }
    //  TODO:: 获取更多积分明细
    private function integralList( $input = '' ){
    	if( !($map = $this->getUserId()) ){
    		return $this->reJson( -1, 0, '请登录！');
    	}
    	unset( $map['shopid'] );    //  记录表不包含shopid
    	$map['mid_type'] = 7;   //  积分记录
    	$page = isset($input['page']) ? $input['page'] : 1;
    	$user_log = Db::name('user_log')
    	->where( $map )
    	->field(['frozen_change', 'change_time'])
    	->order(['change_time DESC'])
    	->page( $page, 8)
    	->select();
    	//  时间变换
    	foreach ( $user_log as $k => $v ){
    		$user_log[$k]['frozen_change']  = intval( $v['frozen_change'] );
    		$user_log[$k]['change_time']    = date('Y-m-d H:i:s', $v['change_time'] );
    	}
    	return $this->reJson( 1, $user_log );
    }
    
    //  TODO:: 手机号码验证页面
    public function checkPhone(){
    	//  查询当前手机号码
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	$userinfo = Db::name('user_member')
    	->where( $map )
    	->field('mobile,is_check_mobile')
    	->find();
    	$this->assign('userinfo',$userinfo);
    	return $this->fetch('/ljk/user_phone');
    }
    
    //  TODO:: 手机号更新保存
    public function updatePhone(){
    	//  当前用户账号
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	$phone = trim( input('phone') );
    	$phoneCode = trim( input('phoneCode') );
    	$UserSmsCode=new UserSmsCode;
    	$yzcode=$UserSmsCode->checkCode($phoneCode,$phone,3);
    	if($yzcode['code']==1){
    		$data['is_check_mobile'] = 1;
    		$data['mobile'] = $phone;
    		Db::name('user_member')->where( $map )->update( $data );
    	}
    	return $this->reJson($yzcode['code'], 0,$yzcode['msg']);
    }
    
    //  TODO:: 短信验证码获取
    public function getPhoneCode(){
    	//  手机号
    	$phone = input('phone');
    	//  验证手机号码
    	$rule = [
    	['phone', '/^1[3456789]\d{9}$/', '电话号码填写错误'],
    	];
    	$result = $this->validate(['phone' => $phone], $rule );
    	if( $result !== true ){
    		return $this->reJson( 2, 0, $result );
    	}
    	//  模拟随机数
    	$phoneCode = rand( 1000, 9999 );
    	\think\Session::set('phoneCode', $phoneCode);
    	return $this->reJson( 10, '验证码:' . $phoneCode, 0 );
    }
    
    //  TODO:: 头像上传
    public function upIcon(){
    	//  当前用户账号
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	if( empty( $map['gid'] ) || empty( $map['uid'] ) ){
    		return $this->reJson( 1, 0, '请登陆账号' );
    	}
    	//  获取头像
    	$file = $this->request->file('file');
    	if( empty( $file ) ){
    		return $this->reJson( 1, 0, '请选择头像文件' );
    	}
    	//  移动文件
    	$fileInfo = $file->move(ROOT_PATH . 'public' . DS . 'uploads\\img');
    	if( $fileInfo ){
    		//  判断文件大小
    		if( $fileInfo->getSize() > 2097152 ){
    			$filePathName = $fileInfo->getPath() . '\\' . $fileInfo->getFilename();
    			unlink( $filePathName );
    			return $this->reJson( 1, 0 , '文件大小不能超过200k' );
    		}
    		// 成功上传后 获取上传信息
    		return $this->reJson( 10, $fileInfo->getSaveName() , '上传测试' . $fileInfo->getSaveName() );
    	}else{
    		// 上传失败获取错误信息
    		return $this->reJson( 2, 0, '上传错误：' . $fileInfo->getError() );
    	}
    }
    
    //  TODO:: 客户经理页面
    public function manager(){
    	$map = $this->getUserId();
    	unset($map['shopid']);
    	$reData = Db::name('user_member')
    	->where( $map )
    	->value('guid');
    	if( isset( $reData ) ){
    		$reData = Db::name('group_member')
    		->where( [ 'uid' => $reData ] )
    		->field(['realname','img','mobile'])
    		->find();
    		$this->assign( 'manInfo', $reData );
    	}
    	return $this->fetch('/ljk/manager');
    }
    
    //  TODO:: 全部订单
    public function orderList(){
    	return $this->fetch('/ljk/OrderList');
    }
    
    //  TODO:: 申请提现
    public function withdrawals(){
    	//  当前用户账号
    	$map = array();
    	$map['gid']  = $this->gid;
    	$map['uid']  = $this->uid;
    	//  获取输入
    	$tx_money = input('tx_money');
    	$tx_money = isset( $tx_money ) ? trim( $tx_money ) : 0;
    	$tx_money = str_replace( ' ', '', $tx_money);   //  去掉空格
    	//  验证数据
    	$rule = [
    	['tx_money', '/^\d+(\.\d{1,2})?$/', '提现金额最底0.01元，最高5000元。'],
    	];
    	$result = $this->validate( [ 'tx_money' => $tx_money], $rule );
    	if( $result !== true ){
    		return $this->reJson( 3, 0, $result );
    	}
    	if( $tx_money > 5000 ){
    		return $this->reJson( 4, 0, '提现金额不能超过5000元！' );
    	}
    	//  判断当前用户余额
    	$reData = Db::name('user_member')->where($map)->field('weixin,user_money,frozen_money')->find();
    	if( $tx_money > $reData['user_money'] ){
    		return $this->reJson( -1, 0, '账户可用余额不足，最多可提现金额：' . $reData['user_money'] );
    	}
    	//  TODO:: 操作 余额->冻结余额,创建申请提现
    	// 启动事务
    	Db::startTrans();
    	try{
    		//  1,添加申请提现
    		$inData = array();
    		$inData['uid']      = $this->uid;
    		$inData['gid']      = $this->gid;
    		$inData['shopid']   = $this->shopid;
    		$inData['sn']   =  'TX'.date('YmdHis',time()).mt_rand(100000, 999999);
    		$inData['amount'] = $tx_money;
    		$inData['pay_type']   = 'weixin';  
    		$inData['pay_no']   = $reData['weixin']; //提现账号
    		$inData['pay_state']   = 0; //0待支付1支付中2支付成功3支付失败4支付退回
    		$inData['user_agent']   =  input('server.HTTP_USER_AGENT');    
    		$inData['ip']   = get_client_ip();    
    		$inData['create_time'] = time();
    		
    		$inId = Db::name('user_withdrawals')->insertGetId( $inData );
    		//  2,减少余额，增加冻结余额
    		$upData = array();
    		$upData['user_money']   = $reData['user_money'] - $tx_money;
    		$upData['frozen_money'] = $reData['frozen_money'] + $tx_money;
    		Db::name('user_member')->where($map)->update( $upData );
    		//  3,操作记录
    		$logData = array();
    		$logData['uid'] = $map['uid'];
    		$logData['gid'] = $map['gid'];
    		$logData['mid_type'] = 5;       //  提现操作
    		$logData['mid'] = $inId;        //  申请提现表id
    		$logData['before_user_money']   = $reData['user_money'];      //  操作前 余额
    		$logData['before_frozen_money'] = $reData['frozen_money'];    //  操作前 冻结余额
    		$logData['user_money']    = $upData['user_money'];       //  操作后 余额
    		$logData['frozen_money']  = $upData['frozen_money'];     //  操作后 冻结余额
    		$logData['change_time']     = time();
    		$changeDesc = array(
    				'user' => - $tx_money,
    				'frozen' => $tx_money
    		);
    		$logData['change_desc']     = '【申请提现】'.$this->userLogChangeDesc($changeDesc);//'提现:可用余额:减少' . $tx_money . '元:冻结余额:增加' . $tx_money . '元';
    		$logData['frozen_change']   = $tx_money;
    		$logData['ip']              = $this->request->ip();
    		$logData['user_agent']      = $this->request->header('user-agent');
    		Db::name('user_log')->insert($logData);
    		// 提交事务
    		Db::commit();
    		return $this->reJson( 1, 0, '申请提现成功，等待审核。');
    	} catch (\Exception $e) {
    		// 回滚事务
    		Db::rollback();
    		return $this->reJson( 6, 0, '申请提现错误' . $e);
    	}
    	//  返回信息
    	return $this->money_json();
    }
    
    /*
     *      TODO:: 资金明细
    *
    * */
    public function moneyDetail(){
    	if(request()->isAjax() && request()->isPost()){
    		$input = input('post.');
    		$action = [];
    		if(isset($input['action']) && $action = trim($input['action'])){
    			unset($input['action']);
    		}
    		switch($action){
    			case 'allmoneydetail':return $this->allMoneyDetail($input);
    			case 'txmoneydetail':return $this->txMoneyDetail($input);
    			case 'czmoneydetail':return $this->czMoneyDetail($input);
    			case 'integrallist':return $this->integralList($input);     //  积分更多明细
    			case 'withdrawals' :return $this->withdrawals($input);
    		}
    	}
    }
    //  TODO:: 提现和充值资金明细
    private function allMoneyDetail( $input = '' ){
    	$map = $this->getUserId();
    	unset($map['shopid']);
    	//  页数
    	$page = isset($input['page']) ? $input['page'] : 1;
    	//  资金提现记录
    	$detailData = Db::name('user_log')
    	->where($map)
    	->field(['change_time','user_money','frozen_money','change_desc'])
    	->order( 'id DESC' )
    	->page( $page,10)
    	->select();
    	foreach ($detailData as $key=>$detail){
    		$detailData[$key]['change_time']=date('Y-m-d H:i',$detail['change_time']);
    	}
    	return $this->reJson( 1, $detailData);
    }
    //  TODO:: 提现资金明细
    private function txMoneyDetail( $input = '' ){
    	$map = $this->getUserId();
    	//  页数
    	$page = isset($input['page']) ? $input['page'] : 1;
    	//  资金提现记录
    	$detailData = Db::name('user_withdrawals')
    	->where($map)
    	->field(['create_time','status','amount','verify_state','pay_state'])
    	->order( 'id DESC' )
    	->page( $page,10)
    	->select();
    	foreach ($detailData as $key=>$detail){
    		$detailData[$key]['create_time']=date('Y-m-d H:i',$detail['create_time']);
    	}
    	return $this->reJson( 1, $detailData);
    }
    //  TODO:: 提现资金明细
    private function czMoneyDetail( $input = '' ){
    	$map = $this->getUserId();
    	//  页数
    	$page = isset($input['page']) ? $input['page'] : 1;
    	//  资金提现记录
    	$detailData = Db::name('user_withdrawals')
    	->where($map)
    	->field(['create_time','status','user_money','frozen_money'])
    	->order( 'id DESC' )
    	->page( $page,10)
    	->select();
    	return $this->reJson( 1, $detailData);
    }
    
    public function applyfx(){
    	if(request()->isAjax() && request()->isPost()){
    		$uid = $this->uid;
    		$gid = $this->gid;
    		if(!$uid || !$gid){
    			return json(['code' => -1,'msg' => '非法操作。']);
    		}
    		$input = input('post.');
    		$validate = validate('Fxuser');
    		$res = $validate->check($input);
    		if($res !== true){
    			$msg = $validate->getError();
    			return json(['code'=>-1,'msg'=>$msg]);
    		}else{
    			//验证手机号码
    			$phone = trim($input['mobile']);
    			$phoneCode = trim($input['verifycode']);
    			$UserSmsCode=new UserSmsCode;
    			$yzcode=$UserSmsCode->checkCode($phoneCode,$phone,3);
    			//                $yzcode['code'] = 1;
    			if($yzcode['code']==1){
    				//确定这个手机号码是否被其他人占用
    				$whereUm['gid'] = $gid;
    				$whereUm['uid'] = ['NEQ',$uid];
    				$whereUm['mobile'] = trim($input['mobile']);
    				$whereUm['is_check_mobile'] = 1;
    				$c = Db::name('user_member')->where($whereUm)->count();
    				if(!!$c){
    					return json(['code' => -1,'msg' => '该号码已被占用，请使用其他号码操作！']);
    				}else{
    					$groupShop = session('shopinfo');
    					if(!$groupShop){
    						return json(['code' => -1,'msg' => '非法操作！']);
    					}else{
    						$whereGs['gid'] = $this->gid;
    						$whereGs['id'] = $groupShop['id'];
    						$newGroupShop = Db::name('group_shop')->where($whereGs)->find();
    						if($newGroupShop['fxstatus'] == 0){
    							session('groupShop',$newGroupShop);
    							return json(['code' => -3,'msg' => '申请失败，该商城无法使用分销功能，页面即将刷新！']);
    						}
    					}
    					$whereUdum['gid'] = $this->gid;
    					$whereUdum['uid'] = $uid;
    					$isFx = Db::name('user_member')->where($whereUdum)->value('is_fx');
    					//验证当前是否已经申请通过或是否已经申请过了
    					$data['realname'] = trim($input['realname']);
    					$data['mobile'] = trim($input['mobile']);
    					$data['contact'] = trim($input['contact']);
    					$data['address'] = trim($input['address']);
    					$data['fx_apply_time'] = time();
    					$data['is_check_mobile'] = 1;
    					$data['is_fx'] = 1;
    					//根据输入的邀请码获取上级客户信息
    					//                        $whereUpum['gid'] = $this->gid;
    					//                        $whereUpum['unique_sign'] = $input['yqcode'];
    					try{
    						if($isFx != 2 || $isFx != 1){
    							Db::name('user_member')->where($whereUdum)->update($data);
    							$rmsg = '申请成功，请等待管理员审核，页面即将刷新！';
    						}else if($isFx == 2){
    							Db::name('user_member')->where($whereUdum)->update($data);
    							$rmsg = '您已是分销会员，请勿重复申请！系统已更新您提交的其他信息，页面即将刷新！';
    						}else{
    							$rmsg = '您已提交过申请，请等待管理员审核，页面即将刷新！';
    						}
    						return json(['code' => 0,'msg' => $rmsg]);
    					} catch (\think\Exception $e){
    						return json(['code' => -1,'msg' => '系统繁忙，请稍后再试！']);
    					}
    				}
    			}else{
    				return json(['code' => -1,'msg' => '手机验证码填写不正确！']);
    			}
    		}
    	}else{
    		return json(['code' => -1,'msg' => '非法操作！']);
    	}
    }
    
    //  TODO:: 日志记录描述
    private function userLogChangeDesc($inc = []){
    	$desc = '';
    	foreach($inc as $k => $v){
    		switch($k){
    			case 'user':$name = '可用资金';$unit = '元';break;
    			case 'frozen':$name = '冻结资金';$unit = '元';break;
    			case 'pay':$name = '消费积分';$unit = '分';break;
    			case 'rank':$name = '会员积分';$unit = '分';break;
    			default:continue;
    		}
    		if($v < 0){
    			$desc .= $name . ':' . '减少' . abs($v) . $unit . ' ';
    		}else if($v > 0){
    			$desc .= $name . ':' . '增加' . abs($v) . $unit . ' ';
    		}
    	}
    	return trim($desc);
    }
    private function getOneAddress($input = []){
        
    }
    private function editAddress($input = []){
        
    }
    private function addressList($input = []){
        
    }
    private function setDefaultAddress($input = []){
        
    }
}

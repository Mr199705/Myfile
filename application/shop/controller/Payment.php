<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://ljk.cc All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: itkk <it_kk@qq.com>
// +----------------------------------------------------------------------
namespace app\shop\controller;
use think\wechatsdk\Api;
use think\Db;
use app\shop\controller\WeixinMsg;
use app\common\controller\Sign;
class Payment extends Base{
    public function __construct(\think\Request $request = null) {
        parent::__construct($request);
    	$groupShop = $this->groupShop;
        $this->gid = $groupShop['gid'];
        session('gid',$groupShop['gid']);
        $this->weixin_appId_appSecret=array(
            'appId' =>  $groupShop['appid'],
            'appSecret'	=> $groupShop['appsecret']
        );
        $map['gid']=$groupShop['gid'];
        $map['shopid']=$groupShop['id'];
        $payconfig = Db::name('payment')->where($map)->value('pay_config');
        if(is_string($payconfig)){
            $store = unserialize($payconfig);
            foreach ($store as $key=>$value){
                $config[$value['name']] = $value['value'];
            }
            $this->weixin_appId_Pay=array(
                'appId' =>  $config['wxappid'],
                'appSecret'	=> $config['wxappsecret'],
                'mchId'	=> $config['tenpay_account'],
                'key'	=> $config['tenpay_key']
            );
        }
        if(empty($this->umca)){
            $this->umca();
        }
    }
    public function payerror(){
    	return $this->fetch('/default/payment/error');
    }
    public function paycenter(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $oid = isset($input['oid']) ? intval($input['oid']) : false;
            if(!$oid){
                return json(['code' => -1,'msg' => '非法操作！']);
            }
            $whereO['gid'] = $this->gid;
            $whereO['oid'] = $oid;
            $whereO['uid'] = $this->uid;
            $whereO['confirm'] = ['NEQ',-1];
            $whereO['trade'] = ['NEQ',1];
            $ord = Db::name('order')
                ->field('oid,number,guid,dguid,uid,gid,pay,total')
                ->where($whereO)
                ->find();
            if(empty($ord)){
                return json(['code' => -1,'msg' => '订单不存在！']);
            }
            if($ord['pay'] == 1){
                return json(['code' => 1,'msg' => '订单已支付，请勿重复支付！']);
            }
            $paytype = $ord['paytype'];
            if(!$this->groupShop['hyk'] || empty($this->umca) || $this->umca['useable'] <= 0 || !$this->umca['status']){
                return json(['code' => 3,'msg' => '会员卡余额不足，即将跳转到微信支付页面！']);
            }
            if($this->gid == 133 || $this->gid == 205){
                $whereOp['gid'] = $this->gid;
                $whereOp['uid'] = $this->uid;
                $whereOp['oid'] = $oid;
                $hp = Db::name('order_pay')->where($whereOp)->sum('pay') + 0;
                $np = $ord['total'] - $hp;
                if($np > 0){
                    //此时才需要支付
                    if($this->umca['useable'] < $np){
                        $res = $this->mcardPay($ord,$np,$hp);
                        if($res['code'] === 0){
                            return json(['code' => 2,'msg' => '会员卡账户扣款成功，即将跳转至微信支付页面支付余额：¥'. sprintf('%0.2f',($np - $this->umca['useable'])) .'！']);
                        }else{
                            return json(['code' => -1,'msg' => '系统繁忙！' . $res['msg']]);
                        }
                    }else{
                        $res = $this->mcardPay($ord,$np,$hp);
                        if($res['code'] === 0){
                            return json(['code' => 0,'msg' => '会员卡账户扣款成功，订单已成功支付！']);
                        }else{
                            return json(['code' => -1,'msg' => '系统繁忙！' . $res['msg']]);
                        }
                    }
                }else{
                    $whereO['oid'] = $oid;
                    Db::name('order')->where($whereO)->update(['confirm' => 1,'pay' => 1]);
                    return json(['code' => 1,'msg' => '订单已支付完成，请勿重复支付！']);
                }
            }else if($paytype == 1){
                return json(['code' => 4,'msg' => '当前订单为微信支付，即将调到微信支付页！']);
            }else if($paytype == 4){
                $whereOp['gid'] = $this->gid;
                $whereOp['uid'] = $this->uid;
                $whereOp['oid'] = $oid;
                $hp = Db::name('order_pay')->where($whereOp)->sum('pay') + 0;
                $np = $ord['total'] - $hp;
                if($this->umca['useable'] < $np){
                    return json(['code' => 3,'msg' => '会员卡余额不足，即将跳转到微信支付页面！']);
                }else{
                    //开始进行会员卡支付
                    $res = $this->mcardPay($ord,$np,$hp);
                    if($res['code'] === 0){
                        return json(['code' => 0,'msg' => '会员卡账户扣款成功，订单已成功支付！']);
                    }else{
                        return json(['code' => -1,'msg' => '系统繁忙！' . $res['msg']]);
                    }
                }
            }
        }else{
            $input = input();
            $oid = isset($input['oid']) ? intval($input['oid']) : false;
            if(!$oid){
                return $this->fetch('/default/payment/mcard');
            }
            $whereO['gid'] = $this->gid;
            $whereO['oid'] = $oid;
            $whereO['uid'] = $this->uid;
            $whereO['confirm'] = ['NEQ',-1];
            $whereO['trade'] = ['NEQ',1];
            $ord = Db::name('order')
                ->field('oid,number,uid,gid,pay,total')
                ->where($whereO)
                ->find();
            //获取订单已支付的总金额信息
            if(empty($ord)){
                return $this->fetch('/default/payment/paycenter');
            }
            if($ord['pay'] == 1){
                $this->assign('pay',1);
                $this->assign('oid',$oid);
                return $this->fetch('/default/payment/paycenter');
            }
            //获取这个订单的支付方式 
            $paytype = $ord['paytype'];
            if(!$this->groupShop['hyk'] || empty($this->umca) || $this->umca['useable'] <= 0  || !$this->umca['status']){
                $this->redirect(url('/shop/payment/?oid='. $oid));
            }
            //直接跳转到微信，如果是载禾农业，那么优先还是去扣除会员卡余额
            if($this->gid == 133 || $this->gid == 205){
                $whereOp['gid'] = $this->gid;
                $whereOp['uid'] = $this->uid;
                $whereOp['oid'] = $oid;
                $hp = Db::name('order_pay')->where($whereOp)->sum('pay') + 0;
                $np = $ord['total'] - $hp;
                if($np > 0){
                    //此时才需要支付
                    if($np > $this->umca['useable']){
                        //查询这个订单是否已经请求过微信支付，如果已经请求过，直接使用微信进行支付
                        $whereOwr['out_trade_no'] = $ord['number'];
                        $whereOwr['prepay_id'] = ['NEQ',''];
                        if(Db::name('weipay_request')->where($whereOwr)->count()){
                            $this->redirect(url('/shop/payment/?oid='. $oid)); //继续去使用微信支付
                        }
                    }
                    $this->assign('hp',$hp);
                    $this->assign('np',$np);
                    $this->assign('ord',$ord);
                    $this->assign('oid',$oid);
                    $this->assign('umca',$this->umca);
                    return $this->fetch('/default/payment/paycenter');
                }else{
                    $whereO['oid'] = $oid;
                    Db::name('order')->where($whereO)->update(['confirm' => 1,'pay' => 1]);
                    $this->assign('pay',1);
                    $this->assign('oid',$oid);
                    return $this->fetch('/default/payment/paycenter');
                }
            }else if($paytype == 1){
                $this->redirect(url('/shop/payment/?oid=' . $oid));
            }else if($paytype == 4){
                $whereOp['gid'] = $this->gid;
                $whereOp['uid'] = $this->uid;
                $whereOp['oid'] = $oid;
                $hp = Db::name('order_pay')->where($whereOp)->sum('pay') + 0;
                $np = $ord['total'] - $hp;
                if($this->umca['useable'] < $np){
                    $this->redirect(url('/shop/payment/?oid=' . $oid));
                }else{
                    $this->assign('hp',$hp);
                    $this->assign('np',$np);
                    $this->assign('ord',$ord);
                    $this->assign('oid',$oid);
                    $this->assign('umca',$this->umca);
                    return $this->fetch('/default/payment/paycenter');
                }
            }
        }
    }
    private function mcardPay($ord = [],$m = 0,$hp = 0){
        if($this->umca['useable'] < $m){
            $m = $this->umca['useable'];
        }
        $opdata['oid'] = $ord['oid'];
        $opdata['gid'] = $ord['gid'];
        $opdata['uid'] = $ord['uid'];
        $opdata['guid'] = $ord['guid'];
        $opdata['total'] = $ord['total'];
        $opdata['notpay'] = $ord['total'] - ($hp + $m);
        $opdata['haspay'] = $hp + $m;
        $opdata['pay'] = $m;
        $opdata['paytime'] = time();
        $opdata['sn'] = date('YmdHis',time()).mt_rand(100000, 999999);
        $opdata['paytype'] = 4; //0-现金 1-微信 2-支付宝 3-银行转账 4-会员卡
        $postdata['oid'] = $ord['oid'];
        $postdata['gid'] = $ord['gid'];
        $postdata['uid'] = $ord['uid'];
        $postdata['content'] = '会员卡付款，支付金额：' . $m;
        $postdata['adate'] = time();
        $postdata['confirm'] = 1;//订单已付款 既确认
        $uorder = [
            'where' => [
                'gid' => $this->gid,
                'oid' => $ord['oid'],
                'uid' => $this->uid],
            'up' => [
                'confirm' => 1
            ]
        ];
        $useable = $this->umca['useable'] - $m;
        $umum = [
            'where' => [
                'gid' => $this->gid,
                'uid' => $this->uid,
                'id' => $this->umca['id']
            ],
            'up' => [
                'useable' => $useable
            ]
        ];
        $mump = [
            'gid' => $this->gid,
            'uid' => $this->uid,
            'auid' => $this->uid,
            'atype' => 1,
            'ctime' => time(),
            'ua' => input('server.HTTP_USER_AGENT'),
            'ip' => get_client_ip(),
            'useable' => $this->umca['useable'] - $m,
            'disable' => $this->umca['disable'],
            'uinc' => 0 - $m,
            'dinc' => 0,
            'type' => 6,
            'sn' => date('YmdHis',time()).mt_rand(100000, 999999),
            'oid' => $ord['oid'],
            'osn' => $ord['number'],
            'desc' => '【商城消费扣款】'
        ];
        if($opdata['notpay'] <= 0){
            $postdata['pay'] = 1;
            $uorder['up']['pay'] = 1;
        }
        Db::startTrans();
        try{
            Db::name('order')->where($uorder['where'])->update($uorder['up']);
            Db::name('mcard_um')->where($umum['where'])->update($umum['up']);
            Db::name('mcard_umpost')->insert($mump);
            Db::name('order_pay')->insert($opdata);
            Db::name('order_post')->insert($postdata);
            Db::commit();
            return ['code' => 0,'msg' => '扣款成功！'];
        }catch(\think\Exception $e){
            Db::rollBack();
            return ['code' => -1,'msg' => $e->getMessage()];
        }
    }
    //微信支付订单weixinpay
    public function index(){
    	$oid = input('oid');
    	if($oid){
            $map['oid'] = $oid;
            $map['gid'] = $this->gid;
            $orderinfo=Db::name('order')->where($map)->field('oid,uid,number,pay,total')->find();
            if($orderinfo['pay']==1){
                $params['out_trade_no']=$orderinfo['number'];
                $params['oid']=$orderinfo['oid'];
                $this->assign('params',$params);
                $this->assign('total_fee',$orderinfo['total']);
                $this->assign('is_pay',1);
                return $this->fetch('/default/payment/payment');
            }
            //汇总历史支付 总额
            $whereOp['gid'] = $this->gid;
            $whereOp['oid'] = $oid;
            $whereOp['uid'] = $orderinfo['uid'];
            $pt = Db::name('order_pay')->where($whereOp)->sum('pay') + 0;
            if($pt >= $orderinfo['total']){
                //说明已经支付完毕了
                Db::name('order')->where($map)->update(['pay' => 1]);
                $params['out_trade_no']=$orderinfo['number'];
                $params['oid'] = $orderinfo['oid'];
                $this->assign('params',$params);
                $this->assign('total_fee',$orderinfo['total']);
                $this->assign('is_pay',1);
                return $this->fetch('/default/payment/payment');
            }else{
                $total_fee = sprintf('%0.2f',$orderinfo['total'] - $pt) + 0;
            }
            $openid=Db::name('user_member')->where('uid',$orderinfo['uid'])->value('weixin');
            if($orderinfo && $total_fee > 0){
                //获取订单是否已经请求过微信支付
                $config['body'] = '购买商品';
                $config['out_trade_no'] = $orderinfo['number'];
                $config['total_fee'] = $total_fee * 100;
                $config['detail'] = '';
                $pay = Db::name('weipay_request')->where($config)->find();
                if($this->groupShop['hyk'] == 1){
                    if($this->gid == 205 || $this->gid == 133){
                        if($this->umca['useable'] > 0 &&  !!$this->umca['status']){
                            if($total_fee > $this->umca['useable']){
                                //查询这个订单是否已经请求过微信支付，如果已经请求过，直接使用微信进行支付
                                if(empty($pay) || $pay['prepay_id'] === ''){
                                    $this->redirect(url('/shop/payment/paycenter/oid/' . $oid));
                                }
                            }else{
                                $this->redirect(url('/shop/payment/paycenter/oid/' . $oid));
                            }
                        }
                    }
                }
                $api = new Api($this->weixin_appId_Pay);
                if(!$pay['prepay_id']){
                    $config['notify_url']='http://'.$_SERVER['HTTP_HOST'].'/shop/payment/weixin_notify_url.html';
                    $config['trade_type']='JSAPI';
                    $config['openid']=$openid;
                    $config['spbill_create_ip'] = get_client_ip();
                    $pay = $api->wxPayUnifiedOrder($config);
                    $pay['out_trade_no']=$config['out_trade_no'];
                    if($pay['prepay_id']){
                        $res = array_merge($config,$pay);
                        Db::name('weipay_request')->insert($res);
                    }
                }
                // print_r($pay);exit;
                $jsApiParameters=$api->getWxPayJsApiParameters($pay['prepay_id']);
                $this->assign('title','购买商品');
                $params['out_trade_no']=$config['out_trade_no'];
                $params['paytype']='weipay';
                $sign = new Sign;
                $params = array_merge($params,$sign->mkSign($params));
                $this->assign('params',$params);
                $this->assign('pay',$pay);
                $this->assign('total_fee',$total_fee);
                $this->assign('jsApiParameters',$jsApiParameters);
                return $this->fetch('/default/payment/payment');
            }else{
                echo '订单不存在';
            }
    	}else{
            echo '订单不存在';
    	}
    }
    //处理微信支付异步通知
    public function weixin_notify_url(){
    	$api = new Api($this->weixin_appId_Pay);
    	$msg = $api->progressWxPayNotify();
    	if($msg[0]){
            $res=$msg[1];
            $sign = new Sign();
            $res['sign1']=$res['sign'];
            unset($res['sign']);
            $res = array_merge($res,$sign->mkSign($res));
            $result=$this->check_weixin_order_api($res);
            if($result['code'] == 1){
                //更改订单状态 
                //paytype=weipay&out_trade_no=2017030815285298697&timestamp=1488958133&sign=9A188FE6D2B196FD1E02C9BCADC3F313
                $res1['paytype']='weipay';
                $res1['out_trade_no']=$res['out_trade_no'];
                $res1= array_merge($res1,$sign->mkSign($res1));
                $payRes = $this->updateOrderStatus($res1);
                //执行本地订单支付流程
                //$weixinmsg= new WeixinMsg;
                //通知管理员
                //$weixinmsg->msg('rechargemsgtzgly','',$res);
                $fp = fopen("../paylog/".$res['transaction_id'].".json", "w");
                fwrite($fp,json_encode($res).json_encode($result).json_encode($payRes->getData()));
                fclose($fp);
            }
    	}
    	return xml($msg[2]);
    }
    //本地订单支付状态修改
    private function updateOrderStatus($inputData){
    	$sign = new Sign();
    	if($sign->validateSign($inputData)){
            $payOrderInfo = Db::name('order')
                ->field('oid,uid,guid,dguid,gid,pay,total')
                ->where('number',$inputData['out_trade_no'])
                ->find();
            //充值订单，对手续费订单的操作
            if($payOrderInfo['pay']==0){
                $paydata['pay'] = 1;
                $paydata['confirm'] = 1;
                $whereOp['gid'] = $this->gid;
                $whereOp['oid'] = $payOrderInfo['oid'];
                $whereOp['uid'] = $payOrderInfo['uid'];
                $pt = Db::name('order_pay')->where($whereOp)->sum('pay') + 0;
                $pay = $payOrderInfo['total'] - $pt;
                if($inputData['paytype']=='weipay') $paydata['paytype'] = 1;
                $paydata['pay_time'] = time();
                Db::name('order')->where('oid',$payOrderInfo['oid'])->update($paydata);
                $opdata['oid'] = $payOrderInfo['oid'];
                $opdata['gid'] = $payOrderInfo['gid'];
                $opdata['uid'] = $payOrderInfo['uid'];
                $opdata['total'] = $pay;
                $opdata['notpay'] = 0;
                $opdata['haspay'] = $payOrderInfo['total'];
                $opdata['pay'] = $pay;
                $opdata['paytime'] = time();
                $opdata['sn'] = date('YmdHis',time()).mt_rand(100000, 999999);
                $opdata['paytype']=1; //0-现金 1-微信 2-支付宝 3-银行转账 4-会员卡
                Db::name('order_pay')->insert($opdata);
                $postdata['oid']=$payOrderInfo['oid'];
                $postdata['gid']=$payOrderInfo['gid'];
                $postdata['uid']=$payOrderInfo['uid'];
                $postdata['content']='微信付款，支付金额：' . sprintf('%0.2f',$pay);
                $postdata['adate']=time();
                $postdata['pay'] = 1;
                $postdata['confirm'] = 1;
                Db::name('order_post')->insert($postdata);
                $this->fc($payOrderInfo['oid']);
                return json(['code'=>1,'oid'=>$payOrderInfo['oid'],'msg'=>'支付成功！']);
            }else{
                return json(['code'=>1,'oid'=>$payOrderInfo['oid'],'msg'=>'支付成功！']);
            }
    	}else{
            return json(['code'=>-1,'msg'=>'签名验证失败！']);
    	}
    }
    //操作分成数据
    private  function fc($oid){
    	//查询这个baojia的分销规则
    	//$oid=70914;
    	$where['gid'] = session('gid');
    	$where['oid'] = $oid;
    	$where['is_fc'] = 0; //未分成
    	$mkorder=Db::name('order_marketing')->where($where)->find();
    	$userInfo=Db::name('user_member')->where('uid',$mkorder['uid'])->find();
        if($mkorder&&$userInfo['fxpid']){
            $order=Db::name('order')->where('oid',$mkorder['oid'])->field('number,total')->find();
            $xNum=Db::name('order_goods')->where('oid',$mkorder['oid'])->sum('num');
            /*
            if(isset($input['unitid']) && ($unitId = intval(trim($input['unitid']))) && ($unitId != $ogInfo['unitid'])){
                    $whereU['id'] = ['IN',[$unitId,$ogInfo['unitid']]];
                    $whereU['gid'] = $this->gid;
                    $whereU['unitgid'] = $ogInfo['unitgid'];
                    $units = Db::name('unit')->where($whereU)->column('id,coefficient,uname,title utitle');
                    if(!isset($units[$unitId])){
                            return json(['code' => -1,'msg' => '非法操作！']);
                    }else{
                            $ogInfo['unitid'] = $unitId;
                            $ogInfo['unit'] = $units[$unitId]['uname'];
                            $ogInfo['unitg'] = $units[$unitId]['utitle'];
                            $ogInfo['price'] = round($ogInfo['price'] * $units[$unitId]['coefficient'] / $units[$ogInfo['unitid']]['coefficient'], 2);
                            $ogInfo['amount'] = $ogInfo['price'] * $ogInfo['num'];
                            $ogInfo['adate'] = time();
                            $storageNum =  $ogInfo['num'] * $units[$unitId]['coefficient'];
                            $xNum = round($ogInfo['num'] * $units[$unitId]['coefficient'] / $units[$ogInfo['unitid']]['coefficient']); //这个是转换成报价单位后的数量
                    }
            }else{
                    //总价计算系数设为1
                    $xNum = $ogInfo['num'];
                    $whereU['id'] = $ogInfo['unitid'];
                    //$whereU['gid'] = $this->gid;
                    $whereU['unitgid'] = $ogInfo['unitgid'];
                    $unit = Db::name('unit')->field('title,coefficient')->where($whereU)->find();
                    $ogInfo['unitg'] = $unit['title'];
                    $ogInfo['amount'] = $ogInfo['price'] * $ogInfo['num'];
                    $ogInfo['adate'] = time();
                    $storageNum =  $ogInfo['num'] * $unit['coefficient'];
            }
            */
            $mkInfo=Db::name('baojia_marketing')->where('id',$mkorder['mkid'])->find();
            $whereBfr['baojia_id'] = $mkorder['baojia_id'];
            $whereBfr['gid'] = session('gid');
            $whereBfr['rule_type'] = $mkInfo['fx_into_type'];
            $whereBfr['status'] = 1;
            $whereBfr['mkid'] = $mkorder['mkid'];
            $fxRules = Db::name('baojia_fxrule')->where($whereBfr)->column('level,rule');
            $level = $mkInfo['fx_level'];
            $ruleType = $mkInfo['fx_into_type'];
            if(!empty($fxRules) && $level){
                $userAccount = [];//这里用于存储分销客户
                $userLog = [];//这里用于存储分销客户账户变化记录
                $whereUmp['gid'] = session('gid');
                //   $whereUmp['is_fx'] = 1;//分销商身份证
                $l = 1;
                $fcje=0; //分成总金额
                $fxpid = $userInfo['fxpid'];
                $groupShop = session('shopinfo');
                $shopId = isset($groupShop['id']) ? $groupShop['id'] : 0;
                $whereGs['id'] = $shopId;
                $whereGs['gid'] = session('gid');
                $newGroupShop = Db::name('group_shop')->where($whereGs)->find();
                $fxsfc=1;//一级分销商参与一级分成
                if($newGroupShop['fxstatus'] === 1){
                    do{
                        //获取用户fxpid信息
                        if($l > $level){
                            break;
                        }else{
                            if($userInfo['is_fx']==2&&$newGroupShop['fxs_fc']==1&&$fxsfc==1){
                            	$u = $userInfo;
                            	$fxsfc=0;
                            }else{
                            $whereUmp['uid'] = $fxpid;
                            $whereUmp['shopid'] = $shopId;
                            if($newGroupShop['fxcheck'] === 1){
                                $whereUmp['is_fx'] = 2;
                            }
                                $u = Db::name('user_member')->field('uid,gid,guid,rank_points,pay_points,fxpid,gspid,frozen_money,user_money')->where($whereUmp)->find();
                            }
                            if(empty($u)){
                                break;
                            }else{
                                //获取当前这个等级对应的规则
                                $r = $fxRules[$l];
                                if(!$r){
                                    $l++;
                                    continue;
                                }
                                //根据规则去计算分成信息
                                $x = [];
                                if($ruleType === 1){
                                    $x['where'] = [
                                    'uid' => $u['uid'],
                                    'gid' => $u['gid'],
                                    'guid' => $u['guid']
                                    ];
                                    $inc['frozen'] = $xNum * $r;
                                    $x['update'] = [
                                    'frozen_money' => $u['frozen_money'] +  $inc['frozen']
                                    ];
                                }else if($ruleType === 2){
                                    $x['where'] = [
                                    'uid' => $u['uid'],
                                    'gid' => $u['gid'],
                                    'guid' => $u['guid']
                                    ];
                                    $inc['frozen'] = $order['amount'] * $r * 0.01;
                                    $x['update'] = [
                                    'frozen_money' => $u['frozen_money'] +  $inc['frozen']
                                    ];
                                }else{
                                    $l++;
                                    continue;
                                }
                                $changeDesc = '【推广奖励】'.$this->userLogChangeDesc($inc);
                                $userLog[] = [
                                'uid' => $u['uid'],
                                'gid' => $u['gid'],
                                'oid' => $mkorder['oid'], 
                                'mid_type' => 2,//推广奖励
                                'change_type' => 2, //增加
                                'mid' => $order['number'],
                                'before_user_money' => $u['user_money'],
                                'pay_points' => $u['pay_points'],
                                'rank_points' => $u['rank_points'],
                                'before_frozen_money' => $u['frozen_money'],
                                'frozen_money' => $u['frozen_money'] + $inc['frozen'],
                                'user_money' => $u['user_money'],
                                'frozen_change' => $inc['frozen'],
                                'change_desc' => $changeDesc,
                                'ip' => get_client_ip(),
                                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                                'change_time' => time()
                                ];
                                $userAccount[] = $x;
                                $fxpid = $u['fxpid'];
                                $fcje+=$inc['frozen'];
                                $l++;
                            }
                        }
                    }while($fxpid);
                }
            }
            if(!empty($userLog)){
                Db::startTrans();
                try{
                    $PostData = [
                        'desc'=>'分成到冻金额'.input('desc'),
                        'om_id'=>$mkorder['id'],
                        'type'=>1,
                        'gid'=>session('gid'),
                        'guid'=>0,
                        'fc_state'=>1,
                        'amount'=>$fcje,
                        'trade'=>0,
                        'create_time'=>time(),
                        'ip' => get_client_ip(),
                        'user_agent' => $_SERVER['HTTP_USER_AGENT']
                    ];
                    Db::name('order_marketing_post')->insert($PostData);
                    Db::name('user_log')->insertAll($userLog);
                    Db::name('order_marketing')->where($where)->update(['is_fc'=>1]);
                    for($i = 0,$l = count($userAccount); $i < $l; $i++){
                        Db::name('user_member')->where($userAccount[$i]['where'])->update($userAccount[$i]['update']);
                    }
                    Db::commit();
                    // return json(['code' => 0,'oid' => $oid,'paytype' => $order['paytype'],'msg' => '您已成功提交订单数据，请前往订单中心查看详细信息，页面即将刷新！']);
                }catch(\think\Exception $e){
                    return json(['code' => -1,'msg' => '系统繁忙！'.$e->getMessage()]);
                    Db::rollBack();
                }
            }
        }else{
            
        }
    }
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
    public function snsapi_base(){
        header('Content-type: text/html; charset=utf-8');
        // api模块 - 包含各种系统主动发起的功能
        $api = new Api($this->weixin_appId_appSecret);
        list($err, $user_info) = $api->get_userinfo_by_authorize('snsapi_base');
        if ($user_info !== null) {
            $userinfo=json_decode(json_encode($user_info),true);
            return $userinfo;
        }else{
            return false;
        }
    }
    public function snsapi_userinfo(){
        header('Content-type: text/html; charset=utf-8');
        // api模块 - 包含各种系统主动发起的功能
        $api = new Api($this->weixin_appId_appSecret);
        list($err, $user_info) = $api->get_userinfo_by_authorize('snsapi_userinfo');
        if ($user_info !== null) {
            $userinfo=json_decode(json_encode($user_info),true);
            $userinfo['privilege']=json_encode($userinfo['privilege']);
            $openid=$userinfo['openid'];
            unset($userinfo['openid']);
            $id=db('ypb_open')->where('weixin',$openid)->update($userinfo);
            session('weixin_openid',$openid);
            $this->redirect(url('login/jihuo'));
        } else {	
            // echo '授权失败！';		
            $authorize_url = $api->get_authorize_url('snsapi_userinfo','https://www.epinbao.cc/e/weixin/snsapi_userinfo.html');
            $this->redirect($authorize_url);
        }
    }
    public function setypb(){
        header('Content-type: text/html; charset=utf-8');
        $url=input('param.url');
        $userinfobase=$this->snsapi_base();
        if($userinfobase){
            $weixin=db('ypb_open')->where('weixin',$userinfobase['openid'])->find();
                if($weixin['id']){
                        if(!$weixin['nickname']){
                        //$authorize_url = $api->get_authorize_url('snsapi_userinfo','https://www.epinbao.cc/e/weixin/snsapi_userinfo.html');
                        //$this->redirect($authorize_url);
                         $api = new Api($this->weixin_appId_appSecret);
                         $userinfo=$api->get_user_info($userinfobase['openid']);
                        $userinfo=json_decode(json_encode($userinfo),true);
                         $userinfo=$userinfo[1];
                         $userinfo['tagid_list']=json_encode($userinfo['tagid_list']);
                         unset($userinfo['openid']);
                         $id=db('ypb_open')->where('weixin',$userinfobase['openid'])->update($userinfo);
                                }
                        if($weixin['eid']){
                                   $userinfo=db('ypb')->find($weixin['eid']);
                                        if($userinfo['mobile']){
                                        session('mobile', $userinfo['mobile']);
                                        session('id', $userinfo['id']);
                                        session('usertype', $etype);
                                        session('userinfo', $userinfo);
                                        session('login_error_num',0);
                                        //更新登录状态
                                        $param = [
                                                'last_login_ip' => get_client_ip(),
                                                'last_login_time' => time()
                                        ];
                                        db('ypb_user')->where('eid',$userinfo['id'])->update($param);
                                        db('ypb_user')->where('eid',$userinfo['id'])->setInc('login');
                                        if($url) $this->redirect($url);
                                        else $this->redirect('index/index');
                                        }
                        }
                }else{
                        $api = new Api($this->weixin_appId_appSecret);
                        $data['weixin']=$userinfobase['openid'];
                        $data['tousername']='gh_39aee64d5fc6';
                        $id=db('ypb_open')->insert($data);
                        $userinfo=$api->get_user_info($userinfobase['openid']);
                    $userinfo=json_decode(json_encode($userinfo),true);
                        $userinfo=$userinfo[1];
                        $userinfo['tagid_list']=json_encode($userinfo['tagid_list']);
                        unset($userinfo['openid']);
                        $id=db('ypb_open')->where('weixin',$userinfobase['openid'])->update($userinfo);
                }
                session('weixin_openid',$userinfobase['openid']);
                echo session('weixin_openid');
                $this->redirect(url('login/jihuo'));
        }else{
            echo '授权失败！';
        }
    }
    public function cc(){      
            $weixinmsg= new WeixinMsg;
            $touser='osrhdv8BQAVDc7n92-BiG3UUqV-A';
             $msg=array(
                    'number'=>'414005671',
                    'adCharge'=>'30元',
                    'cashBalance'=>'100元',
                    );
            $code=$weixinmsg->msg('withdrawmsg',$touser,$msg);
            print_r($code);
            ///$cc='{"appid":"wxf0c511a63ec4117e","bank_type":"CFT","cash_fee":"100","fee_type":"CNY","is_subscribe":"Y","mch_id":"1393117602","nonce_str":"nx0EqKnpbNelRgmhAvSdLt7Dhns7CqaV","openid":"osrhdv8BQAVDc7n92-BiG3UUqV-A","out_trade_no":"2017031516472506619","result_code":"SUCCESS","return_code":"SUCCESS","time_end":"20170315164731","total_fee":"100","trade_type":"JSAPI","transaction_id":"4006542001201703153459743879","sign1":"5B2E26E82A92BC1D5EEE4717DAAC2584","sign":"682A01C542000D04AFB66AC0C6B88132","timestamp":1489567652}';
            //print_r(json_decode($cc,true));
            //$code=$weixinmsg->rechargemsgtzgly(json_decode($cc,true));
            //print_r($code);
                    //$url='https://open.weixin.qq.com/connect/oauth2/authorize?appid=wxf0c511a63ec4117e&redirect_uri=https%3A%2F%2Fwww.epinbao.cc%2Fe%2Fweixin%2Fsetypb.html'.$tzurl.$cookieurl.'&response_type=code&scope=snsapi_userinfo&state=#wechat_redirect';
            /*$api = new Api($this->weixin_appId_Pay);
            $t=$api->get_Templatelist();
            foreach($t['template_list'] as $tt){
                    $id=db('weixin_template')->where('template_id',$tt['tempalte_id'])->value('id');
                    if(!$id) { echo db('weixin_template')->insert($tt);}
                    }*/

    }
    public function wx_notify_url(){
            $api = new Api($this->weixin_appId_Pay);
            $msg=$api->progressWxPayNotify();
            if($msg[0]){
                    $this->update_weixin_order($msg[1]);
            } 
            $this->assign('paymsg_cash_fee',$msg[1]['cash_fee']);
            $this->assign('paymsg_status',$msg[2]);
    return $this->fetch('/paymsg');
            }
    //查询微信订单支付状态
    public function check_weixin_order(){
            $input=input("param.");
            $result=$this->check_weixin_order_api($input);
            if($result['code'] == 1){
                    //更改订单状态 写日志记录 写充值记录 更新易品保账户
                $sign = new Sign();
                    unset($input['sign']);
                    unset($input['timestamp']);
                    $input= array_merge($input,$sign->mkSign($input));
                    //$payRes = json_decode($orderapi->payOrderApi($input));//执行本地订单支付流程
                    $payRes = $this->updateOrderStatus($input);//执行本地订单支付流程
                    $payRes =$payRes->getData();
                    if($payRes['code']){
                            return json(['code'=>1,'oid'=>$payRes['oid'],'msg'=>'支付成功！']);
                    }else{
                            return json(['code'=>-1,'msg'=>'支付失败！']);
                    }
     }else{
            return json(['code'=>-1,'msg'=>$result['msg']]);
                }
            return json($result);
    }
    //查询微信订单支付状态
    public function get_weixin_order_api(){
            $input=input("param.");
            $sign = new Sign();
            $result = $sign->validateSign($input);
            if($result){
                    $api = new Api($this->weixin_appId_Pay);
                    $res=$api->getPayOrder($input['out_trade_no']);
                    if($res['result_code']=="SUCCESS"){
                            //$res['sign1']=$res['sign'];
                            //unset($res['sign']);
                            //$res= array_merge($res,$sign->mkSign($res));
                            //$this->update_weixin_order($res);
                            //$weixinmsg= new WeixinMsg;
                            //微信通知客户充值
                            //$weixinmsg->msg('rechargemsg',$res['openid'],$res);
                            if($res['trade_state']=='SUCCESS'){
                                    //更新支付状态
                                    return  json(['code'=>1,'data'=>$res,'msg'=>"支付成功"]) ;
                            }
                            else{
                                    return  json(['code'=>-1,'data'=>$res,'msg'=>$res['trade_state_desc']]);
                            }
                    }else{
                            return  json(['code'=>$res['err_code'],'msg'=>$res['err_code_des ']]);
                    }
            }else{
                    return json(['code'=>'-1','msg'=>'签名失败']);
            }
    }
    //查询微信订单支付状态
    public function check_weixin_order_api($input){
        $sign = new Sign();
        $result = $sign->validateSign($input);
        if($result){
                    $api = new Api($this->weixin_appId_Pay);
                    $res=$api->getPayOrder($input['out_trade_no']);
                    if($res['result_code']=="SUCCESS"){
                            $res['sign1']=$res['sign'];
                            unset($res['sign']);
                            $res= array_merge($res,$sign->mkSign($res));
                            $this->update_weixin_order($res);
                            //微信通知客户充值
                            //$weixinmsg= new WeixinMsg;
                            //$weixinmsg->msg('rechargemsg',$res['openid'],$res);
                            if($res['trade_state']=='SUCCESS'){
                                    //更新支付状态
                                    return  ['code'=>1,'msg'=>"支付成功"] ;
                            }
                            else{
                                    return  ['code'=>-1,'trade_state'=>$res['trade_state'],'msg'=>$res['trade_state_desc']];
                                    }
                    }else{
                            return  ['code'=>$res['err_code'],'msg'=>$res['err_code_des ']];
                            }
              }else{
        return ['code'=>'-1','msg'=>'签名失败']; 
    }
    }		
    private function update_weixin_order($res){
            $sign = new Sign();
    $result = $sign->validateSign($res);
    if($result){
                    $res['attach']=json_encode($res['attach']);
                    if($res['trade_state']=="SUCCESS") $res['trade_state_desc']='支付成功';
                    $out_trade_no=$res['out_trade_no'];
                    unset($res['out_trade_no']);
                    unset($res['timestamp']);
                    $res['sign']=$res['sign1'];
                    unset($res['sign1']);
                    db('weipay_request')->where('out_trade_no',$out_trade_no)->update($res);
              }else{
        return ['code'=>'-1','msg'=>'签名失败']; 
    }
    }
    //微信支付订单
    public function wxpayapi($data=[]){
             if(!empty($data) && is_array($data)){
          $input = $data;
     }else if(request()->isPost()){
          $input=input("param.");
     }else{
          return json(['code'=>'-1','msg'=>'非法操作']); 
          }

            //去验证签名是否合法
      $sign = new Sign();
      $result = $sign->validateSign($input);
              if($result==1){
                    if($input["trade_type"]=="JSAPI"){
                            //用户openid 微信内支付
                            $config['openid']=$input["openid"];
                            if(!$input["notify_url"]) $input["notify_url"]='https://www.epinbao.cc/e/weixin/weixin_notify_url.html';
                            $config['notify_url']=$input["notify_url"];
                            //$config['spbill_create_ip'] = get_client_ip();
                    }
                    if($input["trade_type"]=="NATIVE"){
                            //商品ID 扫码支付
                            $config['product_id']=$input["product_id"];
                            $config["notify_url"]='https://www.epinbao.cc/e/weixin/weixin_notify_url.html';
                            //$config['spbill_create_ip'] = '120.26.222.1';
                    }
                    $config['spbill_create_ip'] = get_client_ip();
                    $config['body']=$input["body"];
                    if($input["detail"]) $config['detail']=$input["detail"];
                    if($input["attach"]) $config['attach']=$input["attach"];
                    if($input["fee_type"]) $config['fee_type']=$input["fee_type"];
                    if($input["time_start"]) $config['time_start']=$input["time_start"];
                    if($input["time_expire"]) $config['time_expire']=$input["time_expire"];
                    if($input["goods_tag"]) $config['goods_tag']=$input["goods_tag"];
                    if($input["limit_pay"]) $config['limit_pay']=$input["limit_pay"];
                    $config['out_trade_no']=$input["out_trade_no"];
                    $config['total_fee']=$input["total_fee"]*100;
                    //$config['total_fee']=1;
                    $config['trade_type']=$input["trade_type"];
                    if(!$input["no_credit"]) $config['limit_pay'] = 'no_credit';
                    $api = new Api($this->weixin_appId_Pay);
                    $pay=$api->wxPayUnifiedOrder($config);
                    if($pay['prepay_id']){
                        $res = array_merge($config,$pay);
                            db('weipay_request')->insert($res);
                    }
                    //return json($config);
                    return json($pay);
                    }else{
        return json(['code'=>'-1','msg'=>'签名失败']); 
            }
    }
    //充值续费微信内支付
    public function xfweixinpay(){
            $query_str = input('param.query');
            $code = input('param.code');
            $query = explode('&',base64_decode(urldecode($query_str)));
            $inputData = [];
            for($i=0;$i<count($query);$i++){
                    $q = explode('=',$query[$i]);
                    if(!!$q && !!$q[0]){
                            $inputData[$q[0]] = $q[1];
                    }
            }
            if(!empty($inputData)){
                    $sign = new Sign;
                    if($sign->validateSign($inputData)){
                            if(!session('openuserinfo')){
                            $api = new Api($this->weixin_appId_appSecret);
                            list($err, $user_info) = $api->get_userinfo_by_authorize('snsapi_base');
                            }else{
                                    $user_info=session('openuserinfo');
                            }
                            if ($user_info !== null) {
                                    session('openuserinfo',$user_info);
                                    $userinfo=json_decode(json_encode($user_info),true);
                                    $openid = $userinfo['openid'];
                                    $number = $inputData['number'];
                                    $orderinfo = Db::name('ypb_order')->where('number',$number)->find();
                                    if(!!$orderinfo){
                                            $total_fee = $orderinfo['total'];
                                            if($total_fee>0){
                                                    $config['body']='充值续费';
                                                    $config['out_trade_no']=$orderinfo['number'];
                                                    $config['total_fee']=$total_fee*100;
                                                    //$config['total_fee']=1;
                                                    $config['notify_url']='https://www.epinbao.cc/e/weixin/weixin_notify_url.html';
                                                    $config['trade_type']='JSAPI';
                                                    $config['openid']=$openid;
                                                    //$config['limit_pay'] = 'no_credit'; 
                                                    $config['spbill_create_ip'] = get_client_ip();
                                                    $api = new Api($this->weixin_appId_Pay);
                                                    $pay=$api->wxPayUnifiedOrder($config);
                                                    $pay['out_trade_no']=$config['out_trade_no'];
                                                    if($pay['prepay_id']){
                                                            $res = array_merge($config,$pay);
                                                            db('weipay_request')->insert($res);
                                                    }
                                                    if(session('jsApiParameters'.$orderinfo['number'])){
                                                            $jsApiParameters=session('jsApiParameters'.$orderinfo['number']);
                                                    }else{
                                                            $jsApiParameters=$api->getWxPayJsApiParameters($pay['prepay_id']);
                                                            session('jsApiParameters'.$orderinfo['number'],$jsApiParameters);
                                                    }
                                                    $params['out_trade_no']=$config['out_trade_no'];
                                                    $params['paytype']='weipay';
                                                    $params = array_merge($params,$sign->mkSign($params));
                                                    $backData['number'] = $orderinfo['number'];
                                                    $backData['type'] = 2;
                                                    $backData = array_merge($backData,$sign->mkSign($backData));
                                                    $backstr = '';
                                                    foreach($backData as $k=>$v){
                                                            $backstr .= $k . '=' . $v . '&';
                                                    }
                                                    $backstr = rtrim($backstr,'&');
                                                    $this->assign('params',$params);
                                                    $this->assign('backstr',  urlencode(base64_encode($backstr)));
                                                    $this->assign('preData',$inputData);
                                                    $this->assign('total_fee',$total_fee);
                                                    $this->assign('jsApiParameters',$jsApiParameters);
                                                    return $this->fetch('/w');
                                            }else{
                                                    return json(['code'=>-1,'msg'=>'系统繁忙，请稍候再试！']);
                                            }
                                    }else{
                                            return json(['code'=>-1,'msg'=>'系统繁忙，请稍候再试！']);
                                    }
                            }else {
                                    return json(['code'=>-1,'msg'=>'系统繁忙，请稍候再试！']);
                            }
                    }else{
                            return json(['code'=>-1,'msg'=>'签名验证失败！']);
                    }
            }else{
                    return json(['code'=>-1,'msg'=>'系统繁忙，请稍候再试！']);
            }
            return;
    }
    //微信企业支付订单
    public function wxqypayapi(){
// 		 if(request()->isPost()){
// 		  $input=input("param.");
// 		//去验证签名是否合法
//           $sign = new Sign();
//           $result = $sign->validateSign($input);
// 	  	  if($result==1){
// 			$api = new Api($this->weixin_appId_Pay);
// 			$config['partner_trade_no']=$input["out_trade_no"];
// 			$config['amount']=100;
// 			$config['spbill_create_ip']=get_client_ip();
// 			$config['openid']='osrhdv8BQAVDc7n92-BiG3UUqV-A';
// 			$config['desc']='佣金';
// 			$config['re_user_name']='张红奎';
// 			$pay=$api->wxPaymkttransfers($config);
// 			return json($pay);
// 			}else{
//             return json(['code'=>'-1','msg'=>'签名失败']); 
//         	}
// 		}else{
//             return json(['code'=>'-1','msg'=>'非法操作']); 
//         }
    }
}
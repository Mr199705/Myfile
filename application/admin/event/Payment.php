<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://ljk.cc All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: itkk <it_kk@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\event;
use think\Db;
class Payment extends \think\Controller{
    private $umca =  [];
    private $uid = 0;
    private $auid = 0;
    private $atype = 0;
    private $guid = 0;
    private $gid = 0;
    public function __construct(\think\Request $request = null) {
        parent::__construct($request);
    }
    public function paycenter($input = []){
        if(!empty($input)){
            $this->uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
            $this->auid = isset($input['auid']) ? intval($input['auid']) : 0;
            $this->atype = isset($input['atype']) ? intval($input['atype']) : 0;
            $this->guid = isset($input['guid']) ? intval($input['guid']) : 0;
            $this->gid = isset($input['gid']) ? intval($input['gid']) : 0;
            $oid = isset($input['oid']) ? intval($input['oid']) : false;
            if(!$oid || !$this->uid){
                return (['code' => -1,'msg' => '非法操作！']);
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
                return (['code' => -1,'msg' => '订单不存在！']);
            }
            if($ord['pay'] == 1){
                return (['code' => -1,'msg' => '订单已支付，请勿重复支付！']);
            }
            $whereMum['gid'] = $this->gid;
            $whereMum['uid'] = $this->uid;
            $whereMum['status'] = 1;
            $this->umca = Db::name('mcard_um')->where($whereMum)->find();
            if(empty($this->umca)){
                return (['code' => -1, 'msg' => '未支付！']);
            }
            $whereOp['gid'] = $this->gid;
            $whereOp['uid'] = $this->uid;
            $whereOp['oid'] = $oid;
            $hp = Db::name('order_pay')->where($whereOp)->sum('pay') + 0;
            $np = $ord['total'] - $hp;
            if($this->umca['useable'] < $np){
                return (['code' => -1,'msg' => '会员卡账户余额不足！']);
            }else{
                //开始进行会员卡支付
                $res = $this->mcardPay($ord,$np,$hp);
                if($res['code'] === 0){
                    return (['code' => 0,'msg' => '会员卡账户扣款成功，订单已成功支付！']);
                }else{
                    return (['code' => -1,'msg' => '系统繁忙！' . $res['msg']]);
                }
            }
        }else{
            return (['code' => -1,'msg' => '非法操作！']);
        }
    }
    private function mcardPay($ord = [],$m = 0,$hp = 0){
        if($this->umca['useable'] < $m){
            $m = $this->umca['useable'];
        }
        $opdata['oid'] = $ord['oid'];
        $opdata['gid'] = $ord['gid'];
        $opdata['uid'] = $ord['uid'];
        $opdata['guid'] = $this->guid ? $this->guid : $ord['guid'];
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
        $this->guid ? $postdata['guid'] = $this->guid : null;
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
            'auid' => ($this->auid ? $this->auid : $this->uid),
            'atype' => ($this->atype ? $this->atype : 0),
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
}
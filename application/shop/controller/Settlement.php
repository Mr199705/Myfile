<?php
namespace app\shop\controller;
use think\Db;
class Settlement extends Base{
    public function index(){
        //  获取默认收货地址信息,没有则提示用户前往设置
        $whereA = [];
        $whereA['uid'] = isset($this->uid) ? $this->uid : 0;
        $whereA['gid'] = isset($this->gid) ? $this->gid : 0;
        $whereA['status'] = 1;
        $whereA['delete'] = 0;
        $addrs = Db::name('user_address')
            ->field('id,consignee,areaname,mobile,address')
            ->where($whereA)
            ->order(['isdefault' => 'DESC'])
            ->select();
        for($i = 0, $l = count($addrs); $i < $l; $i++){
            $addr = $addrs[$i];
            if(!!$addr['areaname']){
                $addr['areaname'] = str_replace( [ '市市辖区', '市县' ], '市', $addr['areaname'] );
            }
            $addrs[$i] = $addr;
        }
        //预定送货日期
        $preDate = [];
        $week = ['天','一','二','三','四','五','六','天'];
        for( $fi = 0; $fi < 7; $fi++  ){
            $lotime = strtotime("+1 days", time() + ( $fi * 86400 ) );
            $dayInfo = [];
            $dayInfo['day'] = date( 'm月d日', $lotime );
            $dayInfo['week'] = $week[date( 'N', $lotime )];
            //明天
            if( date("Y-m-d",strtotime("+1 day")) == date( 'Y-m-d', $lotime ) ){
                $dayInfo['week'] .= ' 明天';
            }else if( date("Y-m-d",strtotime("+2 day")) == date( 'Y-m-d', $lotime ) ){
                $dayInfo['week'] .= ' 后天';
            }
            $preDate[] = $dayInfo;
        }
        //获取已选中的购物车商品
        $whereC['user_id'] = $this->uid;
        $whereC['gid'] = $this->gid;
        $whereC['is_real'] = 2;
        $whereC['type'] = 1;
        $whereC['checked'] = 1;
        $cartList = Db::name('cart')
            ->where($whereC)
            ->field('id,goods_name,goods_id,bjid,checked,goods_number,unit,unitid,unitgid,unitg,priceid,avs,skuid,user_id,goods_price,market_price')
            ->order('id','DESC')
            ->select();
        $amount = 0;
        foreach ($cartList as $k => $v ){
            $amount += $v['goods_price'] * $v['goods_number'];
            if(!!$v['bjid']){
                $whereBj['gid'] = $this->gid;
                $whereBj['id'] = $v['bjid'];
                if(!!$v['goods_id']){
                    $whereBj['goods_id'] = $v['goods_id'];
                }else{
                    unset($whereBj['goods_id']);
                }
                if(!isset($b[$v['bjid']])){
                    $b[$v['bjid']] = Db::name('baojia')//获取sku中的预览图
                        ->field('goodsThumb,goodsImg')
                        ->where($whereBj)
                        ->find();
                    if(!$itemInfo['goodsThumb'] && !$itemInfo['goodsImg']){
                        $b[$v['bjid']] = Db::name('goods')//获取sku中的预览图
                        ->field('goods_thumb goodsThumb,goods_img goodsImg')
                        ->where(['goods_id' => $v['goods_id']])
                        ->find();
                    }
                }
                if(!!$b[$v['bjid']]['goodsThumb']){
                    $cartList[$k]['goodsThumb'] = mkgoodsimgurl(['url' => $b[$v['bjid']]['goodsThumb']]);
                }else if(!!$b[$v['bjid']]['goodsImg']){
                    $cartList[$k]['goodsThumb'] = mkgoodsimgurl(['url' => $b[$v['bjid']]['goodsImg']]);
                }else{
                    $cartList[$k]['goodsThumb'] = '';
                }
            }else if(!!$v['goods_id']){
                if(!isset($g[$v['goods_id']])){
                    $g[$v['goods_id']] = Db::name('goods')//获取sku中的预览图
                    ->field('goods_thumb goodsThumb,goods_img goodsImg')
                    ->where(['goods_id' => $v['goods_id']])
                    ->find();
                }
                if(!!$g[$v['goods_id']]['goodsThumb']){
                    $cartList[$k]['goodsThumb'] = mkgoodsimgurl(['url' => $g[$v['goods_id']]['goodsThumb']]);
                }else if(!!$g[$v['goods_id']]['goodsImg']){
                    $cartList[$k]['goodsThumb'] = mkgoodsimgurl(['url' => $g[$v['goods_id']]['goodsImg']]);
                }else{
                    $cartList[$k]['goodsThumb'] = '';
                }
            }else{
                unset($cartList[$k]);
            }
        }
        //  模版输出
        //获取会员卡账户 
        //  查询数据
        if($this->groupShop['hyk'] === 1){
            $whereMum['gid']  = $this->gid;
            $whereMum['uid']  = $this->uid;
            $whereMum['status'] = 1;
            $umu = Db::name('mcard_um')->where($whereMum)->value('useable');
        }else{
            $umu = -1;
        }
        $this->assign('umu', floor(floatval($umu) * 100) / 100);
        $this->assign('amount',$amount);
        $this->assign('cartList',$cartList);
        $this->assign('preDate', $preDate);
        $this->assign('addrs', $addrs);
        return view('/ljk/settlement');
    }
    //	TODO:: 支付页面
    public function succ(){
    	return '提交成功,请支付！';
    }
}
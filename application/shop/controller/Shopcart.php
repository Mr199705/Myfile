<?php
namespace app\shop\controller;
use think\Db;
class Shopcart extends Base{
    public function __construct(\think\Request $request = null) {
        parent::__construct($request);
    }
    public function index(){
        return view('/ljk/cart/index');
    }
    //  TODO:: Shopcart异步数据
    public function cartFun(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $action = [];
            if(isset($input['action']) && $action = trim($input['action'])){
                unset($input['action']);
            }
            switch($action){
                case 'showlist':return $this->getList($input);
                case 'add':return $this->add($input);
                case 'del':return $this->del($input);
                case 'setitemnumber':return $this->setItemNumber($input);
                case 'getnumber':return $this->getNumber($input);
                case 'checkitem':return $this->checkItem($input);
            }
        }
    }
    //  TODO:: 购物车列表 
    private function getList( $input = '' ){
        $whereC = [];
        $whereC['user_id'] = $this->uid;
        $whereC['gid'] = $this->gid;
        $whereC['is_real'] = 2;
        $whereC['type'] = 1;
        $p = isset($input['p']) && (intval($input['p']) > 0) ?  intval($input['p'])  : 1;
        $limit = isset($input['limit']) && (intval(trim($input['limit'])) > 0) ?  intval(trim($input['limit'])) : 10; 
        if($p === 1){
            $total = Db::name('cart')
                ->where($whereC)
                ->count();
            if($total === 0){
                return json(['code'=>0,'msg'=>'没有更多数据！']);
            }
        }
        $cartList = Db::name('cart')
            ->where($whereC)
            ->field('id,goods_name,goods_id,bjid,checked,goods_number,unit,unitid,unitgid,unitg,priceid,avs,skuid,user_id,goods_price,market_price')
            ->page($p,$limit)
            ->order('id','DESC')
            ->select();
        //  获取商品图片
        $b = [];
        $g = [];
        foreach ($cartList as $k => $v ){
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
        //返回数据
        if(isset($total)){
            return json(['code' => 1,'msg' => '数据加载成功！','data' => $cartList,'total' => $total]);
        }else{
            if(empty($cartList)){
                return json(['code' => 0,'msg' => '数据加载成功！','data' => $cartList]);
            }else{
                return json(['code' => 1,'msg' => '数据加载成功！','data' => $cartList]);
            }
        }
    }
    //  TODO:: 添加到购物车
    private function add( $input = [] ){
        $ptype = 0;
        if(isset($input['ptype'])){
            $ptype = $input['ptype'];
            unset($input['ptype']);
        }
        switch($ptype){
            case 1:$res = $this->oldAddCart($input);break;//老版本
            case 2:;//新版本
            case 3:$res = $this->newAddCart($input);break;//新版本
            default:$res = ['code' => -1,'msg' => '非法操作'];
        }
        return json($res);
    }
    private function oldAddCart($input = []){
        //获取当前商品
        $bjid = isset($input['bjid']) ? (int)$input['bjid'] : 0;
        $num = isset($input['num']) ? (int)$input['num'] : 0;
        $unitid = isset($input['unitid']) ? (int)$input['unitid'] : 0;
        $cartid = isset($input['cartid']) ? (int)$input['cartid'] : 0;
        if(!$bjid || !$unitid){
            return ['code' => -1,'msg' => '非法操作'];
        }
        if($num <= 0){
            return ['code' => -1,'msg' => '请填写正确的数量！'];
        }
        $whereBj['id'] = $bjid;
        $whereBj['gid'] = $this->gid;
        $whereBj['unitgid'] = ['NEQ',0];
        $whereBj['sell_status'] = 1;
        $bj = Db::name('baojia')->field('id,goods_id,goodsName,sn,unitgid,unitid,tuanprice,retailprice')->where($whereBj)->find();
        if(empty($bj)){
            return ['code' => -1,'msg' => '商品已下架1！'];
        }
        $goods = Db::name('goods')->field('goods_name goodsName,goods_sn sn')->where(['goods_id'=>$bj['goods_id']])->find();
        if(!empty($goods)){
            if(!$bj['goodsName']){
                $bj['goodsName'] = $goods['goodsName'];
            }
            if(!$bj['sn']){
                $bj['sn'] = $goods['sn'];
            }
        }
        $unitgid = $bj['unitgid'];
        $whereUg['gid'] = $this->gid;
        $whereUg['id'] = $unitgid;
        $whereUg['status'] = 1;
        $ug = Db::name('unit_group')->field('id,title')->where($whereUg)->find();
        if(empty($ug)){
            return ['code' => -1,'msg' => '商品已下架2！'];
        }
        $whereU['gid'] = $this->gid;
        $whereU['unitgid'] = $unitgid;
        $whereU['status'] = 1;
        $units = Db::name('unit')->where($whereU)->order(['coefficient' => 'DESC'])->column('id,uname,coefficient');
        if(empty($units) || !isset($units[$unitid])){
            return ['code' => -1,'msg' => '商品已下架3！'];
        }
        $whereBs['gid'] = $this->gid;
        $whereBs['baojia_id'] = $bjid;
        $whereBs['unitgid'] = $unitgid;
        $skuIds = Db::name('baojia_sku')->where($whereBs)->column('id');
        if(!empty($skuIds)){
            $whereBsp['gid'] = $this->gid;
            $whereBsp['bjid'] = $bjid;
            $whereBsp['skuid'] = ['IN',$skuIds];
            $bsp = Db::name('baojia_skuprice')->where($whereBsp)->find();
            if(!empty($bsp)){
                return ['code' => -1,'msg' => '商品已下架4！'];
            }
        }
        if(!!$this->rankid){
            //判断等级是否存在
            $whereUr['gid'] = $this->gid;
            $whereUr['id'] = $this->rankid;
            $whereUr['status'] = 1;
            $rc = Db::name('user_rank')->where($whereUr)->count();
            if($rc){
                $whereBjr['gid'] = $this->gid;
                $whereBjr['rank_id'] = $this->rankid;
                $whereBjr['goods_id'] = $bj['goods_id'];
                $whereBjr['unitgid'] = $unitgid;
                $whereBjr['tuanprice'] = ['NEQ',0];
                $bjr = Db::name('baojia_rank')->field('id,unitid,tuanprice,retailprice')->where($whereBjr)->find();
                if(!empty($bjr)){
                    $bj['unitid'] = $bjr['unitid'];
                    $bj['tuanprice'] = $bjr['tuanprice'];
                }
            }
        }
        //准备数据
        //同样的bjid 同样的tuanprice 同样的单位 unitid 同样的人 同样的商品类型
        //获取当前的价格信息
        if($bj['unitid'] !== $unitid){
            //价格同步
            $bj['retailprice'] = ceil($bj['retailprice'] * $units[$unitid]['coefficient'] / $units[$bj['unitid']]['coefficient'] * 100) / 100;
            $bj['tuanprice'] = ceil($bj['tuanprice'] * $units[$unitid]['coefficient'] / $units[$bj['unitid']]['coefficient'] * 100) / 100;
        }
        if(!!$cartid){
            $whereC['gid'] = $this->gid;
            $whereC['user_id'] = $this->uid;
            $whereC['id'] = $cartid;
            $whereC['unitid'] = $unitid;
            $whereC['type'] = 1;//销售类型
            $whereC['vtype'] = 0;//销售类型
            $whereC['status'] = 1;
            $whereC['is_real'] = 2;
            $uCart['goods_number'] = $num;
            $uCart['unit'] = $units[$unitid]['uname'];
            $uCart['unitg'] = $ug['title'];
            $uCart['goods_name'] = $bj['goodsName'];
            $uCart['goods_sn'] = $bj['sn'];
            $uCart['session_id'] = session_id();
            Db::name('cart')->where($whereC)->update($uCart);
        }else{
            $whereC['gid'] = $this->gid;
            $whereC['user_id'] = $this->uid;
            $whereC['unitid'] = $unitid;
            $whereC['goods_price'] = $bj['tuanprice'];
            $whereC['type'] = 1;//销售类型
            $whereC['vtype'] = 0;//销售类型
            $whereC['status'] = 1;
            $whereC['is_real'] = 2;
            $whereC['bjid'] = $bjid;
            $cart = Db::name('cart')->field('id,goods_number')->where($whereC)->find();
            if(!empty($cart)){
                //更新一条记录
                $uCart['goods_number'] = $cart['goods_number'] + $num;
                $uCart['unit'] = $units[$unitid]['uname'];
                $uCart['unitg'] = $ug['title'];
                $uCart['goods_name'] = $bj['goodsName'];
                $uCart['goods_sn'] = $bj['sn'];
                $uCart['session_id'] = session_id();
                $whereC['id'] = $cart['id'];
                Db::name('cart')->where($whereC)->update($uCart);
                $cn = 0;
            }else{
                //新增一条记录
                $iCart['gid'] = $this->gid;
                $iCart['vtype'] = 0;
                $iCart['type'] = 1;
                $iCart['is_real'] = 2;
                $iCart['user_id'] = $this->uid;
                $iCart['unitid'] = $unitid;
                $iCart['unitg'] = $ug['title'];
                $iCart['unitgid'] = $unitgid;
                $iCart['unit'] = $units[$unitid]['uname'];
                $iCart['goods_name'] = $bj['goodsName'];
                $iCart['goods_sn'] = $bj['sn'];
                $iCart['bjid'] = $bjid;
                $iCart['goods_number'] = $num;
                $iCart['goods_price'] = $bj['tuanprice'];
                $iCart['market_price'] = $bj['retailprice'];
                $iCart['session_id'] = session_id();
                $iCart['goods_id'] = $bj['goods_id'];
                Db::name('cart')->insert($iCart);
                $cn = 1;
            }
        }
        return ['code' => 0,'msg' => '添加购物车成功！','cartnuminc' => $cn];
    }
    private function newAddCart($input = []){
        $bjid = isset($input['bjid']) ? (int)$input['bjid'] : 0;
        $num = isset($input['num']) ? (int)$input['num'] : 0;
        $skuid = isset($input['skuid']) ? (int)$input['skuid'] : 0;
        $priceid = isset($input['priceid']) ? (int)$input['priceid'] : 0;
        $cartid = isset($input['cartid']) ? (int)$input['cartid'] : 0;
        if(!$bjid || !$skuid || !$priceid){
            return ['code' => -1,'msg' => '非法操作'];
        }
        if($num <= 0){
            return ['code' => -1,'msg' => '请填写正确的数量！'];
        }
        $whereBj['id'] = $bjid;
        $whereBj['gid'] = $this->gid;
        $whereBj['unitgid'] = ['NEQ',0];
        $whereBj['sell_status'] = 1;
        $bj = Db::name('baojia')->field('id,goods_id,goodsName,sn,unitgid,unitid,tuanprice,retailprice')->where($whereBj)->find();
        if(empty($bj)){
            return ['code' => -1,'msg' => '商品已下架22！'];
        }
        $unitgid = $bj['unitgid'];
        $whereUg['gid'] = $this->gid;
        $whereUg['id'] = $unitgid;
        $whereUg['status'] = 1;
        $ug = Db::name('unit_group')->field('id,title')->where($whereUg)->find();
        if(empty($ug)){
            return ['code' => -1,'msg' => '商品已下架333！'];
        }
        $whereU['gid'] = $this->gid;
        $whereU['unitgid'] = $unitgid;
        $whereU['status'] = 1;
        $units = Db::name('unit')->where($whereU)->order(['coefficient' => 'DESC'])->column('id,uname,coefficient');
        if(empty($units)){
            return ['code' => -1,'msg' => '商品已下架444！'];
        }
        $whereBs['id'] = $skuid;
        $whereBs['baojia_id'] = $bjid;
        $whereBs['sell_status'] = 1;
        $whereBs['status'] = 1;
        $whereBs['gid'] = $this->gid;
        $sku = Db::name('baojia_sku')->field('id,unitgid,storage_num,attr_ids,attr_value_ids,attrs_id,attrs_value,tuanprice,retailprice')->where($whereBs)->find();
        if(empty($sku)){
            return ['code' => -1,'msg' => '商品已下架555！'];
        }
        $whereBsp['gid'] = $this->gid;
        $whereBsp['bjid'] = $bjid;
        /**兼容载禾需求**/
        $order = '';
        if(!!$this->rankid){
            if($this->gid==133){
                if($this->rankid !== 68){
                    $whereBjsp['rankid'] = ['IN',[$this->rankid,68]];
                    $order = 'field(rankid,' . $this->rankid . ') DESC';
                }else{
                    $whereBjsp['rankid'] = $this->rankid;
                    $order = 'rankid DESC';
                }
            }else if($this->gid == 205){
                if($this->rankid !== 333){
                    $whereBjsp['rankid'] = ['IN',[$this->rankid,333]];
                    $order = 'field(rankid,' . $this->rankid . ') DESC';
                }else{
                    $whereBjsp['rankid'] = 333;
                    $order = 'rankid DESC';
                }
            }else{
                $whereBjsp['rankid'] = ['IN',[0,$this->rankid]];
                $order = 'rankid DESC';
            }
        }else{
            if($this->gid==133){
                $whereBjsp['rankid'] = 68;
                $order = 'rankid DESC';
            }else if($this->gid == 205){
                $whereBjsp['rankid'] = 333;
                $order = 'rankid DESC';
            }else{
                $whereBjsp['rankid'] = 0;
                $order = 'rankid DESC';
            }
        }
        /**兼容载禾需求**/
        $whereBsp['sell_status'] = 1;
        $whereBsp['unitgid'] = $unitgid;
        $whereBsp['id'] = $priceid;
        $price = Db::name('baojia_skuprice')->field('id,rankid,unitid,tuanprice,retailprice,buypoint,costpoint')->where($whereBsp)->order($order)->find();
        if(empty($price)){
            return ['code' => -1,'msg' => '商品已下架666！'];
        }
        $unitid = $price['unitid'];
        if(!isset($units[$price['unitid']])){
            return ['code' => -1,'msg' => '商品已下架！'];
        }
        if(!!$this->rankid){
            $whereUr['gid'] = $this->gid;
            $whereUr['id'] = $this->rankid;
            $whereUr['status'] = 1;
            $rc = Db::name('user_rank')->where($whereUr)->count();
            if($price['rankid'] == $this->rankid && !$rc){
                //这个等级的报价删除了！
                return ['code' => -1,'msg' => '系统错误，请刷新后再试！'];
            }
        }else{
            if($this->gid == 133){
                $price['tuanprice'] = $price['retailprice'];
            }else if($this->gid == 205){
                $price['tuanprice'] = $price['retailprice'];
            }
        }
        //获取历史 关联该商品的
        $enum = $num * $units[$unitid]['coefficient'];
        if($enum > $sku['storage_num']){
            return ['code' => -1,'msg' => '库存不足，无法添加！'];
        }
        $whereC['gid'] = $this->gid;
        $whereC['user_id'] = $this->uid;
        $whereC['bjid'] = $bjid;
        $whereC['skuid'] = $skuid;
        $whereC['unitgid'] = $unitgid;
        $whereC['vtype'] = 1;
        $whereC['is_real'] = 2;
        $hcarts = Db::name('cart')->field('id,unitid,goods_number')->where($whereC)->select();
        if($cartid){
            for($i = 0, $l = count($hcarts); $i < $l; $i++){
                $hcart = $hcarts[$i];
                if($hcart['id'] == $cartid){
                    continue;
                }
                $enum += $hcart['goods_number'] * $units[$hcart['unitid']]['coefficient'];
            }
            if($enum > $sku['storage_num']){
                return ['code' => -1,'msg' => '库存不足，无法添加！'];
            }
            $whereC['type'] = 1;
            $whereC['unitid'] = $unitid;
            $whereC['priceid'] = $priceid;
            $whereC['id'] = $cartid;
            $uCart['goods_number'] = $num;
            $uCart['unit'] = $units[$unitid]['uname'];
            $uCart['unitg'] = $ug['title'];
            $uCart['goods_name'] = $bj['goodsName'];
            $uCart['goods_sn'] = $bj['sn'];
            $uCart['avs'] = $sku['attrs_value'];
            $uCart['session_id'] = session_id();
            Db::name('cart')->where($whereC)->update($uCart);
            $cn = 0;
        }else{
            for($i = 0, $l = count($hcarts); $i < $l; $i++){
                $hcart = $hcarts[$i];
                $enum += $hcart['goods_number'] * $units[$hcart['unitid']]['coefficient'];
            }
            if($enum > $sku['storage_num']){
                return ['code' => -1,'msg' => '库存不足，无法添加！'];
            }
            $whereC['type'] = 1;
            $whereC['unitid'] = $unitid;
            $whereC['priceid'] = $priceid;
            $cart = Db::name('cart')->field('id,goods_number')->where($whereC)->find();
            if(!empty($cart)){
                //更新一条记录
                $uCart['goods_number'] = $cart['goods_number'] + $num;
                $uCart['unit'] = $units[$unitid]['uname'];
                $uCart['unitg'] = $ug['title'];
                $uCart['goods_name'] = $bj['goodsName'];
                $uCart['goods_sn'] = $bj['sn'];
                $uCart['goods_price'] = $price['tuanprice'];
                $uCart['market_price'] = $price['retailprice'];
                $uCart['avs'] = $sku['attrs_value'];
                $uCart['session_id'] = session_id();
                $whereC['id'] = $cart['id'];
                Db::name('cart')->where($whereC)->update($uCart);
                $cn = 0;
            }else{
                //新增一条记录
                $iCart['vtype'] = 1; 
                $iCart['gid'] = $this->gid;
                $iCart['user_id'] = $this->uid;
                $iCart['unitid'] = $unitid;
                $iCart['unitg'] = $ug['title'];
                $iCart['unitgid'] = $unitgid;
                $iCart['unit'] = $units[$unitid]['uname'];
                $iCart['goods_name'] = $bj['goodsName'];
                $iCart['goods_sn'] = $bj['sn'];
                $iCart['bjid'] = $bjid;
                $iCart['type'] = 1;
                $iCart['is_real'] = 2;
                $iCart['goods_number'] = $num;
                $iCart['goods_price'] =  $price['tuanprice'];
                $iCart['market_price'] = $price['retailprice'];
                $iCart['session_id'] = session_id();
                $iCart['goods_id'] = $bj['goods_id'];
                $iCart['skuid'] = $skuid;
                $iCart['priceid'] = $priceid;
                $iCart['avs'] = $sku['attrs_value'];
    //            return ['code' => -1,'msg' => $iCart['priceid'] .'|'. __LINE__];
                Db::name('cart')->insert($iCart);
                $cn = 1;
            }
        }
        return ['code' => 0,'msg' => '添加购物车成功！','cartnuminc' => $cn];
    }
    //  TODO:: 从购物车中删除
    private function del( $input = '' ){
        $whereC = [];
        $whereC['user_id']  = $this->uid;
        $whereC['gid']      = $this->gid;
        $whereC['is_real']      = 2;
        $whereC['id']       = isset($input['id']) ? $input['id'] : 0;
        try {
            Db::name('cart')->where($whereC)->delete();
            return json(['code' => 0,'msg' => '删除成功！']);
        } catch (\Exception $e) {
            return json(['code' => -1,'msg' => '系统繁忙！']);
        }
    }
    private function checkItem( $input = '' ){
        $whereC = [];
        $whereC['user_id']  = $this->uid;
        $whereC['gid']      = $this->gid;
        $whereC['is_real']      = 2;
        $ids = isset($input['id']) ? (array)$input['id'] : false;
        if($ids === false){
            return json(['code' => -1,'msg' => '购物车为空！']);
        }
        $whereC['id'] = ['IN',$ids];
        $check = isset($input['i']) ? ((int)$input['i'] ===-1 ? 1 : 0) : 1;
        try {
            Db::name('cart')->where($whereC)->update(['checked'=>$check]);
            return json(['code' => 0,'msg' => '操作成功！']);
        } catch (\Exception $e) {
            return json(['code' => -1,'msg' => '系统繁忙！' . $e->getMessage()]);
        }
    }
    //  TODO:: 编辑购物车中宝贝数量
    private function setItemNumber( $input = '' ){
        $whereC = [];
        $whereC['id'] = isset($input['id']) ? $input['id'] : 1;
        $whereC['user_id'] = $this->uid;
        $whereC['gid'] = $this->gid;
        $whereC['is_real'] = 2;
        $number = isset($input['number']) ? $input['number'] : 1;
        $cart = Db::name('cart')->where($whereC)->find();
        if(!!$cart['priceid']){
            $input['bjid'] = $cart['bjid'];
            $input['num'] = $number;
            $input['priceid'] = $cart['priceid'];
            $input['cartid'] = $cart['id'];
            $input['skuid'] = $cart['skuid'];
            $res = $this->newAddCart($input);
        }else{
            $input['bjid'] = $cart['bjid'];
            $input['num'] = $number;
            $input['unitid'] = $cart['unitid'];
            $input['cartid'] = $cart['id'];
            $res = $this->oldAddCart($input);
        }
        if($res['code'] === 0){
            return json(['code'=>0,'msg'=>'已更新！']);
        }else{
            return json(['code'=>-1,'msg'=>$res['msg']]);
        }
    }
    //  TODO::  获取购物车条目数量
    private function getNumber(){
        //  购物车商品数量
        $whereC = [];
        $whereC['user_id']  = $this->uid;
        $whereC['gid']      = $this->gid;
        $whereC['status'] = 1;
        $whereC['type'] = 1;
        $whereC['is_real'] = 2;
        try {
            $num = Db::name('cart')->where($whereC)->count();
        } catch (\Exception $e) {
            return $this->reJson( 0, 0, '获取失败');
        }
        return $this->reJson( 1, $num, '获取成功');
    }
}
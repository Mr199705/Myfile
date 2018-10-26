<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Oagroup
 *
 * @author Administrator
 */
namespace app\admin\controller;
use think\Db;
use app\common\controller\Sign;
use app\common\controller\Lss;
use app\common\model\Message;
class Orderrn extends Base{
    private $rankid = 0;
    public function __construct(\think\Request $request = null) {
        parent::__construct($request);
        $this->initData = [
            'sign'			=> $this->sign,
            'requestFunc'	=> $this->requestFunc,
            'requestUrl'	=> $this->requestUrl,
            'cUrl'			=> $this->cUrl,
            'jsName'		=> $this->jsName,
        ];
        $this->assign('initData',$this->initData);
    }
    //FIXME::  返回固定json 状态码,数据,错误信息
    protected function reJson( $code, $data, $msg = '' ){
    	$reJson = array();
    	$reJson['code'] = $code;
    	$reJson['data'] = $data;
    	$reJson['msg'] = $msg;
    	return json( $reJson )->send();
    }
    //  TODO::  给用户下订单
    public function index(){
    	if(request()->isPost()){
            $input = input('post.');
            if(isset($input['action'])){
                $action = $input['action'];
            }else{
                $action = $input['action'] = input('action');
            }
            unset($input['action']);
            switch($action){
                case 'showgoodslist':return $this->getGoodsList($input);
                case 'usercartlist':return $this->getCartList($input);
                case 'addcartitem':return $this->addCartItem($input);
                case 'removecartitem':return $this->removeCartItem($input);
                case 'checkcartitem':return $this->checkCartItem($input);
                case 'createorder':return $this->createOrder($input);
                case 'setcartitemnumber':return $this->setCartItemNumber($input);
                case 'ordergoodsadd': return $this->orderGoodsAdd($input);
                case 'initpreorder': return $this->initPreOrder($input);
                case 'uam': return $this->uam($input);
                case 'addorder':return $this->addOrder($input);
                default:return null ;
            }
        }else{
            //  用户信息
            $input = input();
            $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
            if(!$uid){
                $this->assign('message','请选择需要下单的客户！');
                return $this->fetch('/common/forbidden');
            }
            $whereUm['gid'] = $this->gid;
            $whereUm['uid'] = $uid;
            $userInfo = Db::name('user_member')->where($whereUm)->find();
            $cartNum = $this->getCartNum(['uid' => $uid]);
            if(empty($userInfo)){
                $this->assign('message','客户不存在，无法进行下单操作！');
                return $this->fetch('/common/forbidden');
            }
            session('uid',$uid);
            $this->assign('cartNum',$cartNum);
            $this->assign( 'category', $this->getCategoryList());
            $this->assign( 'userInfo', $userInfo);
            return $this->fetch('/orderrn/index');
        }
    }
    private function getCategoryList(){
        $cate = Db::name('category')->where([ 'gid' => $this->gid, 'status' => 0 ])->select();
        $category = [];
        foreach ( $cate as $ind => $val ){
            if( $val['pid'] == 0 ){
                $citem = $val;
                foreach ( $cate as $ind2 => $val2 ){
                    if( $val['id'] == $val2['pid'] ){
                        $citem['c'][] = $val2;
                    }
                }
                $category[] = $citem;
            }
        }
        return $category;
    }
    private function getGoodsList($input = []){
        // 排序
        $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
        $whereUm['gid'] = $this->gid;
        $whereUm['uid'] = $uid;
        $this->rankid = Db::name('user_member')->where($whereUm)->value('rankid');
        $p = isset($input['p']) && (intval($input['p']) > 0) ?  intval($input['p'])  : 1;
        $limit = isset($input['limit']) && (intval(trim($input['limit'])) > 0) ?  intval(trim($input['limit'])) : 10; 
        $limits = ($p - 1) * $limit;
        $s = (int)$input['sort'];
        switch($s){
            case 1:$sort = 'bj.price';$sortT = 'ASC';break;
            case 2:$sort = 'bj.price';$sortT = 'DESC';break;
            case 3:$sort = 'bj.scount';$sortT = 'ASC';break;
            case 4:$sort = 'bj.scount';$sortT = 'DESC';break;
            case 5:$sort = 'bj.ccount';$sortT = 'ASC';break;
            case 6:$sort = 'bj.ccount';$sortT = 'DESC';break;
            default:$sort = 'bj.id';$sortT = 'DESC';
        }
        $whereB = [];
        $whereB['bj.gid'] = $this->gid;
        $whereB['bj.sell_status'] = 1;
        $whereB['bj.status'] = 1;
        $whereB['bj.unitgid'] = ['NEQ',0];
        $cid = isset($input['cid']) ? $input['cid'] : 0;
        $bjidss = '';
        if($cid){
            $whereC['gid'] = $this->gid;
            $whereC['id'] = $cid;
            $whereC['is_show'] = 1;
            $whereC['pid'] = 0;
            $c = Db::name('category')->where($whereC)->count();
            if($c){
                //说明是一级分类
                unset($whereC['id']);
                $whereC['gid'] = $this->gid;
                $whereC['is_show'] = 1;
                $whereC['pid'] = $cid;
                $ccid = Db::name('category')->where($whereC)->column('id');
                array_push($ccid,$cid);
                $cid = ['IN',$ccid];
            }
            $whereBc['categoryid'] = $cid;
            $whereBc['gid'] = $this->gid;
            $bjids = Db::name('baojia_cat')->where($whereBc)->column('bid');
            if(empty($bjids)){
                return json(['code'=>0,'total' => 0, 'p' => $p, 'msg'=>'没有更多数据！']);
            }else{
                $whereB['bj.id'] = ['IN',$bjids];
                $bjidss = 'AND bj.id IN(' . implode(',',$bjids) . ')';
            }
        }
        $w = isset($input['w']) ? trim($input['w']) : '';
        $like = '';
        if($w){
            $whereB['bj.goodsName'] = ['LIKE' , '%' . $w . '%' ];
            $like = "AND bj.goodsName LIKE '%{$w}%' ";
        }
        $field = 'bj.id,bj.goodsName,bj.unitid,bj.goods_id,bj.unitgid,bj.tuanprice,bj.goodsThumb,bj.unit,bj.goodsImg,bj.retailprice';
        $dbc = config('database');
        $dbPrefix = $dbc['prefix'];
        $order = '';
        $rs = '';
        if(!!$this->rankid){
            if($this->gid == -1){
                if($this->rankid !== 68){
                    $rs = "AND bjsp.rankid IN({$this->rankid},68)";
                    $whereBjsp['rankid'] = ['IN',[$this->rankid,68]];
                    $order = 'field(rankid,' . $this->rankid . ') DESC';
                }else{
                    $rs = "AND bjsp.rankid={$this->rankid}";
                    $whereBjsp['rankid'] = $this->rankid;
                    $order = 'rankid DESC';
                }
            }else if($this->gid == 205){
                if($this->rankid !== 333){
                    $rs = "AND bjsp.rankid IN({$this->rankid},333)";
                    $whereBjsp['rankid'] = ['IN',[$this->rankid,333]];
                    $order = 'field(rankid,' . $this->rankid . ') DESC';
                }else{
                    $rs = "AND bjsp.rankid={$this->rankid}";
                    $whereBjsp['rankid'] = $this->rankid;
                    $order = 'rankid DESC';
                }
            }else{
                $rs = "AND bjsp.rankid IN({$this->rankid},0)";
                $whereBjsp['rankid'] = ['IN',[0,$this->rankid]];
                $order = 'rankid DESC';
            }
        }else{
            if($this->gid == -1){
                $rs = "AND bjsp.rankid=68";
                $whereBjsp['rankid'] = 68;
                $order = 'rankid DESC';
            }else if($this->gid == 205){
                $rs = "AND bjsp.rankid=333";
                $whereBjsp['rankid'] = 333;
                $order = 'rankid DESC';
            }else{
                $rs = "AND bjsp.rankid=0";
                $whereBjsp['rankid'] = 0;
                $order = 'rankid DESC';
            }
        }
        if($this->gid == 205 || $this->gid == -1){
            $whereBjspj['bjsp.gid'] = $this->gid;
            $whereBjspj['bjsp.rankid'] = $whereBjsp['rankid'];
            $whereBjspj['bjsp.tuanprice'] = ['GT',0]; 
            if($p === 1){
                $total = Db::name('baojia bj')
                    ->join('baojia_skuprice bjsp','bjsp.bjid=bj.id','LEFT')
                    ->where($whereB)
                    ->where($whereBjspj)
                    ->group('bj.id')
                    ->having('count(bjsp.id) >= 1')
                    ->count();
                if($total === 0){
                    return json(['code'=>0,'total' => 0, 'p' => $p, 'msg'=>'没有更多数据！']);
                }
            }
            $bjx = Db::name('baojia bj')
                ->field($field)
                ->join('baojia_skuprice bjsp','bjsp.bjid=bj.id','LEFT')
                ->where($whereB)
                ->where($whereBjspj)
                ->group('bj.id')
                ->having('count(bjsp.id) >= 1')
                ->order( [$sort=>$sortT] )
                ->page($p,$limit)
                ->select();
        }else{
            $sql = <<<SQL
                SELECT 
                    {$field}
                FROM
                    {$dbPrefix}baojia bj
                WHERE
                    `bj`.`gid` = {$this->gid}
                AND `bj`.`status` = 1
                AND `bj`.`sell_status` = 1
                AND `bj`.`unitgid` <> 0
                {$bjidss}
                {$like}
                AND 
                    CASE WHEN 
                        (SELECT COUNT(*)
                            FROM
                                {$dbPrefix}baojia_skuprice bjsp
                            WHERE
                                bjsp.gid={$this->gid}
                            AND
                                bjsp.tuanprice>0
                            AND 
                                bjsp.bjid=bj.id
                                {$rs}
                        ) >= 1
                            THEN 1 ELSE  `bj`.`tuanprice` > 0 END
                ORDER BY
                    {$sort}
                {$sortT}
                LIMIT
                    {$limits},{$limit}
SQL;
            if($p === 1){
                $sqlc = <<<SQL
                    SELECT 
                        count(*) t
                    FROM
                        {$dbPrefix}baojia bj
                    WHERE
                        `bj`.`gid` = {$this->gid}
                    AND `bj`.`status` = 1
                    AND `bj`.`sell_status` = 1
                    AND `bj`.`unitgid` <> 0
                    {$bjidss}
                    {$like}
                    AND 
                        CASE WHEN 
                            (SELECT COUNT(*)
                                FROM
                                    {$dbPrefix}baojia_skuprice bjsp
                                WHERE
                                    bjsp.gid={$this->gid}
                                AND
                                    bjsp.tuanprice>0
                                AND 
                                    bjsp.bjid=bj.id
                                    {$rs}
                            ) >= 1
                        THEN 
                            1 
                        ELSE
                            `bj`.`tuanprice` > 0
                        END

SQL;
                $t = Db::connect()->query($sqlc);
                $total = $t[0]['t'];
                if($total === 0){
                    return json(['code' => 0, 'total' => 0, 'p'=>$p, 'msg' => '没有更多数据！']);
                }
            }
            $bjx = Db::connect()->query($sql);
        }
        $unitg = [];
        foreach($bjx as $k => $bj){
            $bjid = $bj['id'];
            if(!trim($bj['goodsThumb']) && !trim($bj['goodsImg']) || !trim($bj['goodsName'])){
                $whereG['goods_id'] = $bj['goods_id'];
                $goods = Db::name('goods')->field('goods_thumb,goods_name,goods_img')->where($whereG)->find();
            }
            if(!!trim($bj['goodsThumb'])){
                $goodsThumb = $bj['goodsThumb'];
            }else if(!!trim($bj['goodsImg'])){
                $goodsThumb = $bj['goodsImg'];
            }else{
                if(!empty($goods)){
                    if(!!$goods['goods_thumb']){
                        $goodsThumb = $goods['goods_thumb'];
                    }else if(!!$goods['goods_img']){
                        $goodsThumb = $goods['goods_img'];
                    }
                }
            }
            if(!trim($bj['goodsName'])){
                if(!empty($goods)){
                    if(!!$goods['goods_name']){
                        $bjx[$k]['goodsName'] = $goods['goods_name'];
                    }
                }
            }
            if(!!$goodsThumb){
                $goodsUrl = mkgoodsimgurl(['url'=>$goodsThumb]);
            }else{
                $goodsUrl = '';
            }
            $bjx[$k]['goodsImg'] = $goodsUrl;
            if(!isset($unitg[$bj['unitgid']])){
                $whereU['unitgid'] = $bj['unitgid'];
                $whereU['gid'] = $this->gid;
                $whereU['status'] = 1;
                //单位列表
                $unitg[$bj['unitgid']] = $units = Db::name('unit')->where($whereU)->order(['coefficient' => 'DESC'])->column('id,uname,coefficient');
                if(empty($unitg[$bj['unitgid']])){
                    unset($bjx[$k]);
                    continue;
                }
            }else{
                if(empty($unitg[$bj['unitgid']])){
                    unset($bjx[$k]);
                    continue;
                }else{
                    $units = $unitg[$bj['unitgid']];
                }
            }
            //sku信息
            $whereBjs['gid'] = $this->gid;
            $whereBjs['baojia_id'] = $bjid;
            $whereBjs['sell_status'] = 1;
            $whereBjs['type'] = 1;
            $whereBjs['status'] = 1;
            $whereBjs['unitgid'] = $bj['unitgid'];
            $bjss = Db::name('baojia_sku')->field('id,attr_ids,baojia_id,attr_value_ids,attrs_id,attrs_value,storage_num,unitgid,detail,imgsid,thumb')->order(['storage_num' => 'DESC'])->where($whereBjs)->select();
            $sunits = [];
            if(!empty($bjss)){
                if(count($bjss) === 1){
                    $bjs = $bjss[0];
                    if(trim($bjs['attr_ids'],',') === ''){
                        $bjx[$k]['ptype'] = 2;
                        //无属性新报价
                        $whereBjsp['skuid'] = $bjs['id'];
                        $whereBjsp['bjid'] = $bjid;
                        $whereBjsp['status'] = 1;
                        $whereBjsp['sell_status'] = 1;
                        $whereBjsp['unitgid'] = $bj['unitgid'];
                        $whereBjsp['tuanprice'] = ['NEQ',0];
                        $objsps = Db::name('baojia_skuprice')->field('id,unitid,sale_num,skuid,rankid,unitgid,tuanprice,retailprice,buypoint,costpoint')->where($whereBjsp)->order($order)->select();
                        if(empty($objsps)){
                            return json(['code' => -1, 'msg' => '该商品已下架1！']);
                        }else{
                            $sunits = [];
                            $bjsps = [];
                            for($i = 0, $l = count($objsps); $i < $l; $i++){
                                $objsp = $objsps[$i];
                                if(!isset($sunits[$objsp['unitid']])){
                                    $sunits[$objsp['unitid']] = $units[$objsp['unitid']];
                                }
                                if(!isset($bjsps[$objsp['unitid']])){
                                    $bjsps[$objsp['unitid']] = $objsp;
                                }
                            }
                            $bjx[$k]['skusl'] = 1;
                            $bjx[$k]['skus'][$bjs['id']] = [
                                'sku' => $bjs,
                                'units' => $sunits,
                                'prices' => $bjsps,
                                'sunitid' => $objsp['unitid']
                            ];
                            continue;
                        }
                    }
                }
                $bjx[$k]['skusl'] = 0;
                for($j = 0, $jl = count($bjss); $j < $jl; $j++){
                    $bjx[$k]['ptype'] = 3;
                    $bjs = $bjss[$j];
                    $whereBjsp['skuid'] = $bjs['id'];
                    $whereBjsp['bjid'] = $bjid;
                    $whereBjsp['status'] = 1;
                    $whereBjsp['sell_status'] = 1;
                    $whereBjsp['unitgid'] = $bj['unitgid'];
                    $whereBjsp['tuanprice'] = ['NEQ',0];
                    $objsps = Db::name('baojia_skuprice')->field('id,unitid,sale_num,skuid,rankid,unitgid,tuanprice,retailprice,buypoint,costpoint')->where($whereBjsp)->order($order)->select(); 
                    if(empty($objsps)){
                        continue;
                    }else{
                        $sunits = [];
                        $bjsps = [];
                        for($i = 0, $ll = count($objsps); $i < $ll; $i++){
                            $objsp = $objsps[$i];
                            if(!isset($sunits[$objsp['unitid']])){
                                $sunits[$objsp['unitid']] = $units[$objsp['unitid']];
                            }
                            if(!isset($bjsps[$objsp['unitid']]) || ($bjsps[$objsp['unitid']]['rankid'] == 0 && $objsp['rankid'] == $this->rankid)){
                                $bjsps[$objsp['unitid']] = $objsp;
                            }
                        }
                    }
                    $bjx[$k]['skusl']++;
                    $bjx[$k]['skus'][$bjs['id']] = [
                        'sku' => $bjs,
                        'units' => $sunits,
                        'prices' => $bjsps,
                        'sunitid' => $objsp['unitid']
                    ];
                }
            }else{
                if(!!$this->rankid){
                    $whereBjr['gid'] = $this->gid;
                    $whereBjr['rank_id'] = $this->rankid;
                    $whereBjr['goods_id'] = $bj['goods_id'];
                    $whereBjr['unitgid'] = $bj['unitgid'];
                    $whereBjr['tuanprice'] = ['NEQ',0];
                    $bjr = Db::name('baojia_rank')->field('id,unitid,tuanprice,retailprice')->where($whereBjr)->find();
                    if(!empty($bjr)){
                        if($bjr['retailprice'] != 0){
                            $bj['retailprice'] = $bjr['retailprice'];
                        }else if($bj['unitid'] !== $bjr['unitid']){
                            $bj['retailprice'] = 0;
                        }
                        $bj['tuanprice'] = $bjr['tuanprice'];
                        $bj['unitid'] = $bjr['unitid'];
                    }
                }
                if($bj['tuanprice'] == 0){
                    continue;
                }else{
                    $bjx[$k]['ptype'] = 1;
                    $bjx[$k]['units'] = $units;
                    $bjx[$k]['tuanprice'] = $bj['tuanprice'];
                    $bjx[$k]['retailprice'] = $bj['retailprice'];
                    $bjx[$k]['unitid'] = $bj['unitid'];
                }
            }
        }
        $bjx = array_values($bjx);
        if(isset($total)){
            return json(['code' => 1, 'p' => $p, 'msg' => '数据加载成功！','data' => $bjx,'total' => $total]);
        }else{
            if(empty($bjx)){
                return json(['code' => 0, 'p' => $p, 'msg' => '数据加载成功！','data' => $bjx]);
            }else{
                return json(['code' => 1, 'p' => $p, 'msg' => '数据加载成功！','data' => $bjx]);
            }
        }
    }
    //	TODO::  显示购物车中的商品,总计价格，收货信息
    //  TODO:: 购物车列表 
    private function getCartList( $input = [] ){
        $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
        $whereC = [];
        $whereC['user_id'] = $uid;
        $whereC['gid'] = $this->gid;
        $whereC['is_real'] = 2;
        $p = isset($input['p']) && (intval($input['p']) > 0) ?  intval($input['p'])  : 1;
        $limit = isset($input['limit']) && (intval(trim($input['limit'])) > 0) ?  intval(trim($input['limit'])) : 10; 
        if($p === 1){
            $total = Db::name('cart')
                ->where($whereC)
                ->count();
            if($total === 0){
                return json(['code'=>0,'msg'=>'没有更多数据！','p' => 1,'total' => 0]);
            }
        }
        $cartList = Db::name('cart')
            ->where($whereC)
            ->field('id,goods_name,type,goods_id,bjid,checked,goods_number,unit,unitid,unitgid,unitg,priceid,avs,skuid,user_id,goods_price,market_price')
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
    private function addCartItem( $input = [] ){
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
        $type = isset($input['type']) ? (int)$input['type'] : 1;
        $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
        if(!$bjid || !$unitid || !$uid){
            return ['code' => -1,'msg' => '非法操作'];
        }
        $whereUm['gid'] = $this->gid;
        $whereUm['uid'] = $uid;
        $this->rankid = Db::name('user_member')->where($whereUm)->value('rankid');
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
            return ['code' => -1,'msg' => '商品已下架！'];
        }
        $whereU['gid'] = $this->gid;
        $whereU['unitgid'] = $unitgid;
        $whereU['status'] = 1;
        $units = Db::name('unit')->where($whereU)->order(['coefficient' => 'DESC'])->column('id,uname,coefficient');
        if(empty($units) || !isset($units[$unitid])){
            return ['code' => -1,'msg' => '商品已下架！'];
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
                return ['code' => -1,'msg' => '商品已下架！'];
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
            $whereC['user_id'] = $uid;
            $whereC['id'] = $cartid;
            $whereC['unitid'] = $unitid;
            //$whereC['type'] = $type;//销售类型
            $whereC['vtype'] = 0;//版本类型
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
            $whereC['user_id'] = $uid;
            $whereC['unitid'] = $unitid;
            $whereC['goods_price'] = $bj['tuanprice'];
            $whereC['type'] = $type;//销售类型
            $whereC['vtype'] = 0;//版本类型
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
                $iCart['type'] = $type;
                $iCart['is_real'] = 2;
                $iCart['user_id'] = $uid;
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
        $type = isset($input['type']) ? (int)$input['type'] : 1;
        $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
        if(!$bjid || !$skuid || !$priceid){
            return ['code' => -1,'msg' => '非法操作'];
        }
        $whereUm['gid'] = $this->gid;
        $whereUm['uid'] = $uid;
        $this->rankid = Db::name('user_member')->where($whereUm)->value('rankid');
        if($num <= 0){
            return ['code' => -1,'msg' => '请填写正确的数量！'];
        }
        $whereBj['id'] = $bjid;
        $whereBj['gid'] = $this->gid;
        $whereBj['unitgid'] = ['NEQ',0];
        $whereBj['sell_status'] = 1;
        $bj = Db::name('baojia')->field('id,goods_id,goodsName,sn,unitgid,unitid,tuanprice,retailprice')->where($whereBj)->find();
        if(empty($bj)){
            return ['code' => -1,'msg' => '商品已下架！'];
        }
        $unitgid = $bj['unitgid'];
        $whereUg['gid'] = $this->gid;
        $whereUg['id'] = $unitgid;
        $whereUg['status'] = 1;
        $ug = Db::name('unit_group')->field('id,title')->where($whereUg)->find();
        if(empty($ug)){
            return ['code' => -1,'msg' => '商品已下架！'];
        }
        $whereU['gid'] = $this->gid;
        $whereU['unitgid'] = $unitgid;
        $whereU['status'] = 1;
        $units = Db::name('unit')->where($whereU)->order(['coefficient' => 'DESC'])->column('id,uname,coefficient');
        if(empty($units)){
            return ['code' => -1,'msg' => '商品已下架！'];
        }
        $whereBs['id'] = $skuid;
        $whereBs['baojia_id'] = $bjid;
        $whereBs['sell_status'] = 1;
        $whereBs['status'] = 1;
        $whereBs['gid'] = $this->gid;
        $sku = Db::name('baojia_sku')->field('id,unitgid,storage_num,attr_ids,attr_value_ids,attrs_id,attrs_value,tuanprice,retailprice')->where($whereBs)->find();
        if(empty($sku)){
            return ['code' => -1,'msg' => '商品已下架！'];
        }
        $whereBsp['gid'] = $this->gid;
        $whereBsp['bjid'] = $bjid;
        /**兼容载禾需求**/
        $order = '';
        if(!!$this->rankid){
            if($this->gid == -1){
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
            if($this->gid == -1){
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
            return ['code' => -1,'msg' => '商品已下架！'];
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
            if($this->gid == -1){
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
        $whereC['user_id'] = $uid;
        $whereC['bjid'] = $bjid;
        $whereC['skuid'] = $skuid;
        $whereC['unitgid'] = $unitgid;
        $whereC['vtype'] = 1;
        $whereC['is_real'] = 2;
        $hcarts = Db::name('cart')->field('id,unitid,goods_number')->where($whereC)->select();
        if(!!$cartid){
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
            //$whereC['type'] = $type;
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
            $whereC['type'] = $type;
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
                $iCart['user_id'] = $uid;
                $iCart['unitid'] = $unitid;
                $iCart['unitg'] = $ug['title'];
                $iCart['unitgid'] = $unitgid;
                $iCart['unit'] = $units[$unitid]['uname'];
                $iCart['goods_name'] = $bj['goodsName'];
                $iCart['goods_sn'] = $bj['sn'];
                $iCart['bjid'] = $bjid;
                $iCart['type'] = $type;
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
    private function removeCartItem( $input = '' ){
        $whereC = [];
        $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
        $whereC['user_id']  = $uid;
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
    private function checkCartItem( $input = '' ){
        $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
        $whereC = [];
        $whereC['user_id']  = $uid;
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
    private function setCartItemNumber( $input = '' ){
        $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
        $whereC = [];
        $whereC['id'] = isset($input['id']) ? $input['id'] : 1;
        $whereC['user_id'] = $uid;
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
    private function getCartNum($input = []){
        $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
        //  购物车商品数量
        $whereC = [];
        $whereC['user_id']  = $uid;
        $whereC['gid']      = $this->gid;
        $whereC['status'] = 1;
        $whereC['is_real'] = 2;
        $num = Db::name('cart')->where($whereC)->count();
        return $num;
        
    }
    private function initPreOrder($input = []){
        //  获取默认收货地址信息,没有则提示用户前往设置
        $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
        $whereUm['uid'] = $uid;
        $whereUm['gid'] = $this->gid;
        $um = Db::name('user_member')->field('uid,rankid,shopid,tpid')->where($whereUm)->find();
        if($um['shopid']){
            $whereGs['gid'] = $this->gid;
            $whereGs['id'] = $um['shopid'];
            $whereGs['status'] = 1;
            $groupShop = Db::name('group_shop')->field('id,hyk')->where($whereGs)->find();
        }else{
            $whereGs['gid'] = $this->gid;
            $whereGs['status'] = 1;
            $groupShop = Db::name('group_shop')->field('id,hyk')->where($whereGs)->find();
        }
        $whereA = [];
        $whereA['uid'] = isset($uid) ? $uid : 0;
        $whereA['gid'] = isset($this->gid) ? $this->gid : 0;
        $whereA['status'] = 1;
        $whereA['delete'] = 0;
        $addrs = Db::name('user_address')
            ->where($whereA)
            ->order(['isdefault' => 'DESC'])
            ->column('id,isdefault,areaids,consignee,areaname,mobile,address');
        if(!empty($addrs)){
            foreach($addrs as $k => $v){
                $addrs[$k]['areaname'] = preg_replace('/(.+)((市辖区)|(县))(.+)/','$1$5',$addrs[$k]['areaname']);
                $addrs[$k]['consignee'] = htmlentities($addrs[$k]['consignee']);
                $addrs[$k]['address'] = htmlentities($addrs[$k]['address']);
            }
        }else{
            $addrs = false;
        }
        //预定送货日期
        //获取今日星期几
        $cw = date('w');
        $w = [1,2,3,4,5,6,7];
        $wcn = ['星期一','星期二','星期三','星期四','星期五','星期六','星期天'];
        $wsl = array_slice($w, array_search($cw, $w) + 1);
        $wd = array_diff($w, $wsl);
        $ws = array_merge($wsl,$wd);
        $week = [];
        for($wsi = 0, $wsl = count($ws); $wsi < $wsl; $wsi++){
            $week[] = [
                'w' => $ws[$wsi],
                'v' => $wcn[$ws[$wsi] - 1]
            ];
        }
        //获取已选中的购物车商品
        $whereC['user_id'] = $uid;
        $whereC['gid'] = $this->gid;
        $whereC['is_real'] = 2;
        $whereC['checked'] = 1;
        $cartList = Db::name('cart')
            ->where($whereC)
            ->field('id,goods_name,goods_id,type,bjid,checked,goods_number,unit,unitid,unitgid,unitg,priceid,avs,skuid,user_id,goods_price,market_price')
            ->order('id','DESC')
            ->select();
        $amount = 0;
        foreach ($cartList as $k => $v ){
            if($v['type'] !== 2){
                $amount += $v['goods_price'] * $v['goods_number'];
            }
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
        //  获取会员卡账户 
        //  查询数据
        if($groupShop && $groupShop['hyk'] === 1){
            $whereMum['gid']  = $this->gid;
            $whereMum['uid']  = $uid;
            $whereMum['status'] = 1;
            $umu = Db::name('mcard_um')->where($whereMum)->value('useable');
        }else{
            $umu = -1;
        }
        return json([
                'umu' => $umu,
                'amount' => $amount,
                'cartList' => $cartList,
                'week' => $week,
                'addrs' => $addrs
            ]);
    }
    private function uam($input = []){
        $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
        $id = isset($input['id']) && intval($input['id']) ? intval($input['id']) : 0;
        if(!$uid){
            return json(['code' => -1,'msg' => '非法操作！']);
        }
        $whereUm['uid'] = $uid;
        $whereUm['gid'] = $this->gid;
        $umc = Db::name('user_member')->where($whereUm)->count();
        if(!$umc){
            return json(['code' => -1,'msg' => '非法操作！']);
        }
        if(isset($input['areas'])){
            $province           = isset($input['areas'][1]) ? explode( ':', $input['areas'][1]) : false;
            $city               = isset($input['areas'][2]) ? explode( ':', $input['areas'][2]) :  false;
            $area               = isset($input['areas'][3]) ? explode( ':', $input['areas'][3]) :  false;
        }else{
            $province = false;
        }
        if(!$province || !$city || !$area){
            return json(['code' => -1,'msg' => '请选择完整的区域！']);
        }
        $data               = [];
        $data['isdefault']  = isset($input['isdefault']) && intval($input['isdefault']) ? intval($input['isdefault']) : 0;
        $data['gid']        = $this->gid;
        $data['uid']        = $uid;
        $data['consignee']  = isset($input['consignee']) && trim($input['consignee']) ? (trim($input['consignee'])) : '';
        $data['areaids']    = $province[0] . ',' . $city[0] . ',' . $area[0];    //  地址ID
        $data['areaname']   = $province[1] . $city[1] . $area[1];
        $data['address']    = isset($input['address']) && trim($input['address']) ? (trim($input['address'])) : '';
        $data['mobile']     = isset($input['mobile']) && trim($input['mobile']) ? (trim($input['mobile'])) : '';
        // 验证规则
        $rule = [
            ['consignee', 'require', '收货人姓名不能为空'],
            ['address', 'require', '请输入详细地址'],
            ['mobile', '/^1[3456789]\d{9}$/', '电话号码填写错误']
        ];
        $result = $this->validate( $data, $rule );
        if( $result !== true ){
            return json(['code' => -1,'msg' => $result]);
        }
        if($data['isdefault'] === 1){
            $whereUa['gid'] = $this->gid;
            $whereUa['uid'] = $uid;
            $whereUa['isdefault'] = 1;
            $whereUa['id'] = ['NEQ',$id];
            Db::name('user_address')->where($whereUa)->update(['isdefault' => 0]);
        }
        if(!!$id){
            unset($whereUa['isdefault']);
            $whereUa['id'] = $id;
            Db::name('user_address')->where($whereUa)->update($data);
            $data['id'] = $id;
        }else{
            $whereUa = [];
            $whereUa['gid'] = $this->gid;
            $whereUa['uid'] = $uid;
            if(Db::name('user_address')->where($whereUa)->count() >= 5){
                return json(['code' => -1,'msg' => '该客户已存在5条收货地址信息，无法继续添加！']);
            }else{
                $data['id'] = Db::name('user_address')->where($whereUa)->insertGetId($data);
            }
        }
        $data['areaname'] = preg_replace('/(.+)((市辖区)|(县))(.+)/','$1$5',$data['areaname']);
        $data['consignee'] = htmlentities($data['consignee']);
        $data['address'] = htmlentities($data['address']);
        return json(['code' => 0, 'msg' => '收货地址信息提交成功！', 'data' => $data]);
    }
    private function addOrder($input = []){
        $addrId = isset($input['addrid']) ? intval($input['addrid']) : 0;
        $pd = isset($input['pdate']) ? intval($input['pdate']) : 0;
        $pt = isset($input['ptime']) ? intval($input['ptime']) : 0;
        $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
        if(!$addrId){
            return json(['code' => -1,'msg' => '请设置好收货地址后再下单！']);
        }
        $whereUa['id'] = $addrId;
        $whereUa['gid'] = $this->gid;
        $whereUa['uid'] = $uid;
        $addr = Db::name('user_address')->where($whereUa)->find();
        if(empty($addr)){
            return json(['code' => -1,'msg' => '请设置好收货地址后再下单！']);
        }
        /**获取购物车项目**/
        $whereC['user_id'] = $uid;
        $whereC['gid'] = $this->gid;
        $whereC['is_real'] = 2;
        //$whereC['type'] = 1;
        $whereC['checked'] = 1;
        $whereC['status'] = 1;
        $cartList = Db::name('cart')
            ->where($whereC)
            ->field('id,goods_name name,type,goods_id goodid,bjid baojia_id,goods_number num,unit,user_id uid,gid,unitid,unitgid,unitg,priceid,avs,skuid sku_id,goods_price price')
            ->order('id','DESC')
            ->select();
        $skuNum = [];
        $units = [];
        $ogs = [];
        $sku = [];
        $skuF = [];
        $upids = [];
        $amount = 0;
        $n = 0;//作限制 单次购买商品 件数不得超过 50件
        $t = time();
        $whereUm['uid'] = $uid;;
        $whereUm['gid'] = $this->gid;
        $userInfo = Db::name('user_member')->field('uid,gid,guid,shopid,mobile')->where($whereUm)->find();
        if(empty($userInfo)){
            return json(['code' => -1,'msg' => '非法操作！']);
        }
        if(empty($cartList)){
            json(['code' => -1,'msg' => '未选择商品！']);
        }
        foreach ($cartList as $k => $v ){
            if(!$v['sku_id']){
                if($v['type'] !== 2){
                    $amount += $v['num'] * $v['price'];
                    $v['amount'] = $v['num'] * $v['price'];
                }else{
                    $v['amount'] = 0;
                }
                $v['guid'] = $userInfo['guid'];
                $v['dguid'] = $this->guid;
                $v['adate'] = $t;
                $v['desc'] = '';
                $upids[] = $v['id'];
                unset($v['id']);
                $ogs[] = $v;
                $n++;
            }else{
                //验证库存信息，去除库存不足的项目
                if(isset($skuF[$v['sku_id']]) && $skuF[$v['sku_id']] === false){
                    continue;
                }else{
                    $skuF[$v['sku_id']] = true;
                }
                if(!isset($units[$v['unitgid']])){
                    $whereU['gid'] = $this->gid;
                    $whereU['unitgid'] = $v['unitgid'];
                    $whereU['status'] = 1;
                    $units[$v['unitgid']] = Db::name('unit')->where($whereU)->order(['coefficient' => 'DESC'])->column('id,uname,coefficient');
                }
                if(!isset($skuNum[$v['sku_id']])){
                    $skuNum[$v['sku_id']] = 0;
                    $whereBs['id'] = $v['sku_id'];
                    $whereBs['baojia_id'] = $v['baojia_id'];
                    $whereBs['sell_status'] = 1;
                    $whereBs['status'] = 1;
                    $whereBs['gid'] = $this->gid;
                    $sku[$v['sku_id']] = Db::name('baojia_sku')->where($whereBs)->value('storage_num');
                }
                if(empty($units[$v['unitgid']]) || $sku[$v['sku_id']] <= 0){
                    $skuF[$v['sku_id']] = false;
                    continue;
                }else{
                    $skuNum[$v['sku_id']] += $v['num'] * $units[$v['unitgid']]['coefficient'];
                    if($skuNum[$v['sku_id']] <= $sku[$v['sku_id']]){
                        if($v['type'] !== 2){
                            $amount += $v['num'] * $v['price'];
                            $v['amount'] = $v['num'] * $v['price'];
                        }else{
                            $v['amount'] = 0;
                        }
                        $v['guid'] = $userInfo['guid'];
                        $v['dguid'] = $this->guid;
                        $v['adate'] = $t;
                        $v['desc'] = '';
                        $upids[] = $v['id'];
                        unset($v['id']);
                        $ogs[] = $v;
                        $n++;
                    }else{
                        $skuF[$v['sku_id']] = false;
                        continue;        
                    }
                }
            }
        }
        if(empty($ogs) || !$amount){
            return json(['code' => -1,'msg' => '下单失败，可能原因：1.商品库存不足！2.未选择商品！']);
        }
        $order['type'] = 1;
        $order['gid'] = $this->gid;
        $order['uid'] = $uid;
        $order['guid'] = $userInfo['guid'];
        $order['dguid'] = $this->guid;
        $order['shopid'] = $userInfo['shopid'];
        $order['address_id'] = $addrId;
        $order['number'] = $number = date('YmdHis',time()).mt_rand(100000, 999999);
        $order['total'] = $amount;
        $order['mobile'] = $userInfo['mobile'];
        $order['downgid'] = $this->gid;
        $order['content'] = isset($input['content']) ? $input['content'] : '';
        $order['paytype'] = $paytype = isset($input['paytype']) ? intval($input['paytype']) : 0;
        $order['adate'] = time();
        $order['ip'] = get_client_ip();
        $order['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $order['pd'] = $pd;
        $order['pt'] = $pt;
        $order['state'] = '';
        $orderMember = [];
        $orderMember['address'] = $addr['areaname'] . $addr['address'];
        $orderMember['gid'] = $this->gid;
        $orderMember['uid'] = $uid;
        $orderMember['realname'] = $addr['consignee'];
        $orderMember['phone'] = $addr['mobile'] ? $addr['mobile'] : ($addr['tel'] ? $addr['tel'] : $userInfo['mobile']);
        $orderMember['x'] = 0;
        $orderMember['y'] = 0;
        $orderPost = [];
        $orderPost['gid'] = $this->gid;
        $orderPost['guid'] = $this->guid;
        $orderPost['content'] = '下订单';
        $orderPost['adate'] = time();
        $orderPost['uid'] = $uid;
        $orderPost['thumb'] = '';
        Db::startTrans();
        try{
            $whereC['gid'] = $this->gid;
            $whereC['user_id'] = $uid;
//            $whereC['type'] = 1;
            $whereC['is_real'] = 2;
            $whereC['id'] = ['IN',$upids];
            $whereC['checked'] = 1;
            $whereC['status'] = 1;
            Db::name('cart')->where($whereC)->delete();
            $oid = Db::name('order')->insertGetId($order);
            $orderPost['oid'] = $oid;
            $orderMember['oid'] = $oid;
            $ogss = array_map(function ($v) use($oid){
                $v['oid'] = $oid;
                return $v;
            },$ogs);
            Db::name('order_post')->insert($orderPost);
            Db::name('order_member')->insert($orderMember);
            Db::name('order_goods')->insertAll($ogss);
            Db::commit();
            $inputp['gid'] = $this->gid;
            $inputp['guid'] = $this->guid;
            $inputp['auid'] = $this->guid;
            $inputp['atype'] = 0;
            $inputp['uid'] = $uid;
            $inputp['oid'] = $oid;
            $payment = controller('Payment','event');
            $res = $payment->paycenter($inputp);
            if($res['code'] === 0){
                $inputL['oid'] = $oid;
                $inputL['gid'] = $this->gid;
                $inputL['uid'] = $uid;
                $inputL['confirm'] = 1;
                $inputL['pay']  = 1;
                $sign = new Sign();
                $s = $sign->mkSign($inputL);
                $inputL['sign'] = $s['sign'];
                $inputL['timestamp'] = $s['timestamp'];
                $lss = new Lss();
                $lss->index($inputL);
            }
            return json(['code' => 0,'msg' => '订单信息:提交成功，支付结果：' . $res['msg'],'order' => ['oid' => $oid,'number' => $number]]);
        }catch(\think\Exception $e){
            Db::rollBack();
            return json(['code' => -1,'msg' => '系统繁忙！' . $e->getMessage()]);
        }
    }
}

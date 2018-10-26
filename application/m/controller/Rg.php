<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\m\controller;
use app\m\controller\Base;
use think\Db;
class Rg extends Base{
    public function __construct(\think\Request $request = null) {
        parent::__construct($request);
    }
    private function mustAjaxPost(){
        if(!request()->isAjax() || !request()->isPost()){
            echo $this->fetch('/home/forbidden');
            exit();
        }
    }
    public function rgList($input = []){
        $whereRgo['gid'] = $this->gid;
        $whereRgoO['dguid'] = $this->guid;
        $whereRgoO['guid'] = $this->guid;
        isset($input['uid']) && intval(trim($input['uid'])) ? $whereRgo['uid'] = intval(trim($input['uid'])) : null;
        isset($input['orderid']) && intval(trim($input['orderid'])) ? $whereRgo['orderid'] = intval(trim($input['orderid'])) : null;
        $at = setInterval($input);
        if($at['ss'] && $at['ee']){
            $whereRgo['ctime'] = ['BETWEEN',[$at['ss'],$at['ee']]];
        }else if($at['ss']){
            $whereRgo['ctime'] = ['EGT',$at['ss']];
        }else if($at['ee']){
            $whereRgo['ctime'] = ['ELT',$at['ee']];
        }
        $p = isset($input['p']) && (intval($input['p']) > 0) ?  intval($input['p'])  : 1;
        $limit = isset($input['limit']) && (intval(trim($input['limit'])) > 0) ?  intval(trim($input['limit'])) : $this->limit; 
        if($p === 1){
            $total = Db::name('rgorder')
                ->where($whereRgo)
                ->where(function ($query) use ($whereRgoO){
                    $query->whereOr($whereRgoO);
                })
                ->count();
            if($total === 0){
                //直接退出
                return json(['code'=>1,'msg'=>'没有更多数据！']);
            }
        }
        $data = Db::name('rgorder')
            ->field('oid,gid,guid,dguid,uid,orderid,sn,ctime,rtype')
            ->where($whereRgo)
            ->where(function ($query) use ($whereRgoO){
                $query->whereOr($whereRgoO);
            })
            ->page($p,$limit)
            ->order('ctime DESC')
            ->select();
        $user = [];
        $order = [];
        $units = [];
        for($i = 0, $l = count($data); $i < $l; $i++){
            $x = $data[$i];
            if(!isset($user[$x['uid']])){
                $whereUm['gid'] = $this->gid;
                $whereUm['uid'] = $x['uid'];
                $user[$x['uid']] = Db::name('user_member')->field('realname,mobile')->where($whereUm)->find();
            }
            if(!isset($order[$x['orderid']])){
                $whereO['gid'] = $this->gid;
                $whereO['oid'] = $x['orderid'];
                $order[$x['orderid']] = Db::name('order')->field('oid,number')->where($whereO)->find();
            }
            //获取退货单商品信息
            $whereRgog['gid'] = $this->gid;
            $whereRgog['roid'] = $x['oid'];
            $ogs = Db::name('rgorder_goods')
                ->field('id,goods_name,unitid,unitgid,num')
                ->where($whereRgog)
                ->select();
            for($j = 0, $k = count($ogs); $j < $k; $j++){
                $y = $ogs[$j];
                if(!isset($units[$y['unitgid']])){
                    $whereU['gid'] = $this->gid;
                    $whereU['unitgid'] = $y['unitgid'];
                    $units[$y['unitgid']] = Db::name('unit')
                        ->field('coefficient,uname')
                        ->where($whereU)
                        ->order('coefficient DESC')
                        ->select();
                }
                $y['u'] = '';
                $n = $y['num'];
                foreach($units[$y['unitgid']] as $v){
                    if($n <= 0){
                        break;
                    }
                    $xx = floor($n / $v['coefficient']);
                    if($xx >= 1){
                        $y['u'] .= $xx . $v['uname'];
                        $n -= ($xx * $v['coefficient']);
                    }else{
                        continue;
                    }
                }
                $ogs[$j] = $y;
            }
            $x['ogs'] = $ogs;
            $x['order'] = $order[$x['orderid']];
            $x['user'] = $user[$x['uid']];
            $x['ctime'] = date('Y/m/d H:i:s',$x['ctime']);
            $data[$i] = $x;
        }
        if(isset($total)){
            return json(['code' => 1,'msg' => '数据加载成功！','data' => $data,'total' => $total]);
        }else{
            if(empty($data)){
                return json(['code' => 0,'msg' => '数据加载成功！','data' => $data]);
            }else{
                return json(['code' => 1,'msg' => '数据加载成功！','data' => $data]);
            }
        }
    }
    public function rgCount($input = []){
        $whereRgo['gid'] = $this->gid;
        $whereRgoO['dguid'] = $this->guid;
        $whereRgoO['guid'] = $this->guid;
        isset($input['uid']) && intval(trim($input['uid'])) ? $whereRgo['uid'] = intval(trim($input['uid'])) : null;
        isset($input['orderid']) && intval(trim($input['orderid'])) ? $whereRgo['orderid'] = intval(trim($input['orderid'])) : null;
        $at = setInterval($input);
        if($at['ss'] && $at['ee']){
            $whereRgo['ctime'] = ['BETWEEN',[$at['ss'],$at['ee']]];
        }else if($at['ss']){
            $whereRgo['ctime'] = ['EGT',$at['ss']];
        }else if($at['ee']){
            $whereRgo['ctime'] = ['ELT',$at['ee']];
        }
        $p = isset($input['p']) && (intval($input['p']) > 0) ?  intval($input['p'])  : 1;
        $limit = isset($input['limit']) && (intval(trim($input['limit'])) > 0) ?  intval(trim($input['limit'])) : $this->limit; 
        $roids = Db::name('rgorder')
            ->where($whereRgo)
            ->column('oid');
        //获取退货单商品信息
        $whereRgog['gid'] = $this->gid;
        $whereRgog['roid'] = ['IN',$roids];
        if($p === 1){
            $total = Db::name('rgorder_goods')
                ->where($whereRgog)
                ->group('goods_id,unitgid')
                ->count();
            if($total === 0){
                //直接退出
                return json(['code'=>1,'msg'=>'没有更多数据！']);
            }
        }
        $ogs = Db::name('rgorder_goods')
            ->field('id,goods_id,goods_name,unitid,unitgid,SUM(num) countnum')
            ->where($whereRgog)
            ->page($p,$limit)
            ->group('goods_id,unitgid')
            ->select();
        $units = [];
        for($j = 0, $k = count($ogs); $j < $k; $j++){
            $y = $ogs[$j];
            if(!isset($units[$y['unitgid']])){
                $whereU['gid'] = $this->gid;
                $whereU['unitgid'] = $y['unitgid'];
                $units[$y['unitgid']] = Db::name('unit')
                    ->field('coefficient,uname')
                    ->where($whereU)
                    ->order('coefficient DESC')
                    ->select();
            }
            $y['u'] = '';
            $n = $y['countnum'];
            foreach($units[$y['unitgid']] as $v){
                if($n <= 0){
                    break;
                }
                $xx = floor($n / $v['coefficient']);
                if($xx >= 1){
                    $y['u'] .= $xx . $v['uname'];
                    $n -= ($xx * $v['coefficient']);
                }else{
                    continue;
                }
            }
            $ogs[$j] = $y;
        }
        if(isset($total)){
            return json(['code' => 1,'msg' => '数据加载成功！','data' => $ogs,'total' => $total]);
        }else{
            if(empty($ogs)){
                return json(['code' => 0,'msg' => '数据加载成功！','data' => $ogs]);
            }else{
                return json(['code' => 1,'msg' => '数据加载成功！','data' => $ogs]);
            }
        }
    }
    public function index(){
        $this->mustAjaxPost();
        $input = input();
        $action  = '';
        if(isset($input['action'])){
            $action = trim($input['action']);
            unset($input['action']);
        }
        switch($action){
            case 'showCount':return $this->rgCount($input);
            case 'showList': return $this->rgList($input);
            default : return $this->rgList($input);
        }
    }
    public function org(){
        $this->mustAjaxPost();
        $input = input('post.');
        $action = '';
        if(isset($input['action'])){
            $action = trim($input['action']);
            unset($input['action']);
        }
        switch($action){
            case 'org':return $this->orgList($input);break;
            case 'doorg':return $this->doOrg($input);break;
        }
    }
    private function orgList($input = [],$jsign = true){
        $oid = isset($input['oid']) ? intval(trim($input['oid'])) : 0;
        $ogids = isset($input['ogids']) ? (array)($input['ogids']) : [];
        if(!$oid){
            $res = ['code' => -1,'msg' => '请选择需要进行退货操作的订单！'];
        }else{
            //订单是否已经无效
            $whereO['gid'] = $this->gid;
            $whereO['oid'] = $oid;
            $whereO['confirm'] = ['NEQ',-1];
            $orderinfo = Db::name('order')->field('oid,gid,guid,address_id,uid,number,pay,confirm,dispatch,trade,adate,rstatus,total')->where($whereO)->find();
            if(!empty($orderinfo)){
                $whereOg['gid'] = $this->gid;
                $whereOg['oid'] = $oid;
                //获取商品信息
                if(!empty($ogids)){
                    $whereOg['id'] = ['IN',$ogids];
                }
                $ogs = Db::name('order_goods')->field('id,sku_id,baojia_id,goodid,name,oid,num,amount,unitgid,unit,unitid,price')->where($whereOg)->select();
                if($orderinfo['rstatus'] > 0){
                    //说明这个订单已经有过退货记录
                    $whereRgo['gid'] = $this->gid;
                    $whereRgo['orderid'] = $oid;
                    $whereRgo['confirm'] = 1;
                    $rgoIds = Db::name('rgorder')->where($whereRgo)->column('oid');
                    if(!empty($rgoIds)){
                        $whereRgog['gid'] = $this->gid;
                        $whereRgog['roid'] = ['IN',$rgoIds];
                        if(!empty($ogids)){
                            $whereRgog['ogid'] = ['IN',$ogids];
                        }
                        $oldRgogs = Db::name('rgorder_goods')
                            ->field('ogid,roid,unitid,price,amount,unitgid,num')
                            ->where($whereRgog)
                            ->select();
                        $rgogs = [];
                        for($i = 0, $l = count($oldRgogs); $i < $l; $i++){
                            $y = $oldRgogs[$i];
                            if(!isset($rgogs[$y['ogid']])){
                                $rgogs[$y['ogid']] = $y;
                            }else{
                                $rgogs[$y['ogid']]['num'] += $y['num']; 
                                $rgogs[$y['ogid']]['amount'] += $y['amount'];
                            }
                        }
                    }
                }
                if(!empty($ogs)){
                    $pay_status = config('order_pay_status');
                    $order_confirm_status = config('order_confirm_status');
                    $order_dispatch_status = config('order_dispatch_status');
                    $order_trade_status = config('order_trade_status');
                    $orderinfo['confirm_desc'] = $order_confirm_status[$orderinfo['confirm']+1];
                    $orderinfo['pay_desc'] = $pay_status[$orderinfo['pay']];
                    $orderinfo['dispatch_desc'] = $order_dispatch_status[$orderinfo['dispatch']];
                    $orderinfo['trade_desc'] = $order_trade_status[$orderinfo['trade']];
                    $newOgs = [];
                    $units = [];
                    for($i = 0, $l = count($ogs); $i < $l; $i++){
                        $x = $ogs[$i];
                        $thumbImg = false;
                        if($x['sku_id']){
                            $whereBs['gid'] = $this->gid;
                            $whereBs['id'] = $x['sku_id'];
                            $thumbImg = Db::name('baojia_sku')->where($whereBs)->value('thumb');
                        }
                        if(!$thumbImg && $x['baojia_id']){
                            $whereB['gid'] = $this->gid;
                            $whereB['id'] = $x['baojia_id'];
                            $thumbImg = Db::name('baojia')->where($whereB)->value('goodsThumb');
                        }
                        if(!$thumbImg){
                            $whereG['goods_id'] = $x['goodid'];
                            $img = Db::name('goods')->field('goods_thumb,goods_img')->where($whereG)->find();
                            if(!!$img['goods_thumb']){
                                $thumbImg = $img['goods_thumb'];
                            }else{
                                $thumbImg = $img['goods_img'];
                            }
                        }
                        $x['thumb'] =  mkgoodsimgurl(['url'=>$thumbImg]);
                        //获取当前商品的单位信息
                        if(!isset($units[$x['unitgid']])){
                            $whereU = [];
                            $whereU['gid'] = $this->gid;
                            $whereU['unitgid'] = $x['unitgid'];
                            $whereU['status'] = 1;
                            $units[$x['unitgid']] = 
                                Db::name('unit')
                                ->where($whereU)
                                ->column('id,uname,unitgid,coefficient');
                        }
                        $x['units'] = $units[$x['unitgid']];
//                      $x['ul'] = 100 / count($units[$x['unitgid']]);
                        //可用最小数量！
                        if(isset($rgogs[$x['id']])){
                            $x['num'] = $x['num'] * $x['units'][$x['unitid']]['coefficient'] - $rgogs[$x['id']]['num'];
                        }else{
                            $x['num'] = $x['num'] * $x['units'][$x['unitid']]['coefficient'];
                        }
                        if($x['num']  != 0){
                            $newOgs[] = $x;
                        }
                    }
                    $orderinfo['ogs'] = $newOgs;
                    $orderinfo['adate'] = date('Y/m/d H:i:s', $orderinfo['adate']);
                    $res = ['code' => 1,'msg' => '订单商品获取成功！','data' => $orderinfo];
                }else{
                    $res = ['code' => -1,'msg' => '该订单非法，无法进行退货操作！'];
                }
            }else{
                $res = ['code' => -1,'msg' => '订单不存在或订单无效！'];
            }
        }
        if($jsign === true){
            return json($res);
        }else{
            return $res;
        }
    }
    private function doOrg($input = [],$jsign = true){
        $oid = isset($input['oid']) ? intval(trim($input['oid'])) : $oid;
        $rogs = isset($input['ogs']) ? $input['ogs'] : [];
        $rrogs = [];
        if(empty($rogs)){
            $res = ['code' => -1,'msg' => '请输入退货商品数量！'];
        }else{
            $resx = $this->orgList(['oid'=>$oid,'ogids'=>array_keys($rogs)],false);
            if($resx['code'] === 1){
                $data = $resx['data'];
                unset($resx);
                $ogs = $data['ogs'];
                unset($data['ogs']);
                $order = $data;
                unset($data);
                //print_r($order);
                $user = Db::name('user_member')->field('uid,realname,mobile')->where('gid',$this->gid)->where('uid',$order['uid'])->find();
                $contact = null;
                if(isset($order['address_id']) && !!$order['address_id']){
                    $contact = Db::name('user_address')->field('id,consignee,mobile')->where('gid',$this->gid)->where('uid',$order['uid'])->where('id',$order['address_id'])->find();
                }
                if(empty($contact)){
                    $contact['consignee'] = $user['realname'];
                    $contact['mobile'] = $user['mobile'];
                    $contact['id'] = 0;
                }
                $rorder = [
                    'gid' => $this->gid,
                    'orderid' => $order['oid'],
                    'rtype' => 0,
                    'uid' => $order['uid'],
                    'guid' => $order['guid'],
                    'dguid' => $this->guid,
                    'stype' => 0,
                    'type' => 0,
                    'desc' => '【订单退货】',
                    'sn' => 'TH' . date('YmdHis') . str_pad(mt_rand(0,9999),4,'0',STR_PAD_LEFT),
                    'ctime' => time(),
                    'ip' => get_client_ip(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'isins' => 0,
                    'mobile' =>$contact['mobile'],
                    'address_id' => $contact['id'],
                    'confirm' => 1
                ];
                $rorderPost = [
                    'gid' => $this->gid,
                    'uid' => $order['uid'],
                    'guid' => $this->guid,
                    'type' => 0,
                    'status' => 1,
                    'adate' => time(),
                    'imgsid' => '',
                    'content' => "【客户订单退货】：订单号:{$order['number']}<br />客户名称:{$user['realname']}<br />联系人:{$contact['consignee']}<br />联系电话:{$contact['mobile']}"
                ];
                $roid = 0;
                Db::startTrans();
                try{
                    for($i = 0,$l = count($ogs); $i < $l; $i++){
                        $x = $ogs[$i];
                        $units = $x['units'];
                        unset($x['units'],$x['thumb']);
                        $num = $x['num']; //可退货最小单位数量
                        $x['num'] = 0;//退货最小单位数量
                        $x['amount'] = 0; 
                        if(isset($rogs[$x['id']])){
                            foreach($rogs[$x['id']] as $unitid => $n){
                                $x['num'] += $n * $units[$unitid]['coefficient'];
                            }
                            $x['amount'] = $x['num'] * ($x['price'] / $units[$unitid]['coefficient']);
                        }
                        if($x['num'] <= $num){
                            if(!$roid){
                                $rorderPost['roid'] = $roid = Db::name('rgorder')->insertGetId($rorder);
                                Db::name('rgorder_post')->insert($rorderPost);
                            }
                            $rrogs[] = [
                                'gid' => $this->gid,
                                'sku_id' => $x['sku_id'],
                                'goods_id' => $x['goodid'],
                                'baojia_id' => $x['baojia_id'],
                                'goods_name' => $x['name'],
                                'ogid' => $x['id'],
                                'unitgid' => $x['unitgid'],
                                'unitid' => $x['unitid'],
                                'price' => $x['price'],
                                'amount' => $x['amount'],
                                'roid' => $roid,
                                'num' => $x['num']
                            ];
                        }
                    }
                    if(empty($rrogs)){
                        throw new \think\Exception('退货数量错误或超过订单商品数量！');
                    }else{
                        $whereO['oid'] = $order['oid'];
                        $whereO['gid'] = $order['gid'];
                        Db::name('order')->where($whereO)->update(['rstatus' => $order['rstatus'] + 1]);
                        Db::name('rgorder_goods')->insertAll($rrogs);
                        $res = ['code' => 0,'msg' => '订单退货操作成功！'];
                        Db::commit();
                    }
                }catch(\think\Exception $e){
                    $res = ['code' => -1,'msg' => '系统繁忙，请稍后再试！' . $e->getMessage()];
                    Db::rollBack();
                }
            }else{
                $res = $resx;
            }
            if($jsign === true){
                return json($res);
            }else{
                return $res;
            }
        }
    }
    public function urg(){
        $this->mustAjaxPost();
        $input = input('post.');
        $action = '';
        if(isset($input['action'])){
            $action = trim($input['action']);
            unset($input['action']);
        }
        switch($action){
            case 'goodslist':return $this->goodsList($input);
            case 'dourg':return $this->dourg($input);
            case 'goodscat':return $this->getGoodsCat($input);
        }
    }
    private function dourg($input = [],$jsign = true){
        $uid = isset($input['uid']) ? intval(trim($input['uid'])) : 0 ;
        $goodsIds = isset($input['goods_id']) ? (array)($input['goods_id']) : [] ;
        $unitgids = isset($input['unitgid']) ? (array)($input['unitgid']) : [] ;
        $skuIds = isset($input['sku_id']) ? (array)($input['sku_id']) : [] ;
        $numunits = isset($input['numunit']) ? (array)($input['numunit']) : [] ;
        $goodsNames = isset($input['goodsName']) ? (array)($input['goodsName']) : [] ;
        $nums = isset($input['num']) ? (array)($input['num']) : [] ;
        $bjids = isset($input['bjid']) ? (array)($input['bjid']) : [] ;
        $prices = isset($input['price']) ? (array)($input['price']) : [] ;
        $amounts = isset($input['amount']) ? (array)($input['amount']) : [] ;
        if(empty($uid) || empty($goodsIds) || empty($unitgids) || empty($numunits) || empty($nums) || empty($bjids)){
            $res = ['code' => -1,'msg' => '非法操作！'];
        }else{
            $whereUm['uid'] = $uid;
            $whereUm['gid'] = $this->gid;
            $user = Db::name('user_member')->field('uid,guid,contact,realname,mobile')->where($whereUm)->find();
            if(empty($user)){
                $res = ['code' => -1,'msg' => '客户不存在！'];
            }else{
                $rorder = [
                    'gid' => $this->gid,
                    'orderid' => 0,
                    'rtype' => 1,
                    'uid' => $uid,
                    'guid' => $user['guid'],
                    'dguid' => $this->guid,
                    'stype' => 0,
                    'type' => 0,
                    'desc' => '【客户退货】',
                    'sn' => 'TH' . date('YmdHis') . str_pad(mt_rand(0,9999),4,'0',STR_PAD_LEFT),
                    'ctime' => time(),
                    'ip' => get_client_ip(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'isins' => 0,
                    'mobile' =>$user['mobile'],
                    'address_id' => 0,
                    'confirm' => 1
                ];
                $rorderPost = [
                    'gid' => $this->gid,
                    'uid' => $uid,
                    'guid' => $this->guid,
                    'type' => 0,
                    'status' => 1,
                    'adate' => time(),
                    'imgsid' => '',
                    'content' => "【客户退货】：客户名称:{$user['realname']}<br />联系人:{$user['contact']}<br />联系电话:{$user['mobile']}"
                ];
                $roid = 0;
                $rrogs = [];
                Db::startTrans();
                try{
                    $c = [];
                    for($i = 0, $l = count($goodsIds); $i < $l; $i++){
                        $goodsId = $goodsIds[$i];
                        if($nums[$goodsId] != 0){
                            if(!$roid){
                                $rorderPost['roid'] = $roid = Db::name('rgorder')->insertGetId($rorder);
                                Db::name('rgorder_post')->insert($rorderPost);
                            }
                            if(!isset($c[$numunits[$goodsId]])){
                                $whereU['gid'] = $this->gid;
                                $whereU['id'] = $numunits[$goodsId];
                                $whereU['unitgid'] = $unitgids[$goodsId];
                                $c[$numunits[$goodsId]] = Db::name('unit')->where($whereU)->value('coefficient');
                            }
                            $rrogs[] =[
                                'gid' => $this->gid,
                                'sku_id' => isset($skuIds[$goodsId]) ? $skuIds[$goodsId] : 0,
                                'goods_id' => $goodsId,
                                'baojia_id' => $bjids[$goodsId],
                                'goods_name' => $goodsNames[$goodsId],
                                'ogid' => 0,
                                'unitgid' => $unitgids[$goodsId],
                                'unitid' => $numunits[$goodsId],
                                'price' => isset($prices[$goodsId]) ? $prices[$goodsId] : 0,
                                'amount' => isset($amounts[$goodsId]) ? $amounts[$goodsId] : 0,
                                'roid' => $roid,
                                'num' => $nums[$goodsId] * $c[$numunits[$goodsId]]
                            ];
                        }
                    }
                    if(empty($rrogs)){
                        throw new \think\Exception('请至少设置退货商品数量大于0！');
                    }else{
                        Db::name('rgorder_goods')->insertAll($rrogs);
                        Db::commit();
                        $res = ['code' => 0,'msg' => '客户退货操作成功！','uid'=>$uid];
                    }
                }catch(\think\Exception $e){
                    Db::rollBack();
                    $res = ['code' => -1,'msg' => '系统繁忙，请稍后再试！' . $e->getMessage()];
                }
            }
        }
        if($jsign === true){
            return json($res);
        }else{
            return $res;
        }
    }
    private function goodsList($input = []){
        $whereB['gid'] = $this->gid;
        $pid = isset($input['pid']) ? intval(trim($input['pid'])) : 0;
        $uid = isset($input['uid']) && intval(trim($input['uid'])) ? intval(trim($input['uid'])) : 0;
        $cid = isset($input['cid']) && intval(trim($input['cid'])) ? intval(trim($input['cid'])) : 0;
        $goodsName = isset($input['keywords']) && trim($input['keywords']) ? trim($input['keywords']) : '';
        //方便业务员获取当前商品的报价，还需要获取等级报价，所以需要获取客户的等级报价
        if(!!$uid){
            //获取等级
            $whereUm['gid'] = $this->gid;
            $whereUm['uid'] = $uid;
            $rankId = Db::name('user_member')->where($whereUm)->value('rankid');
            if($rankId === null){
                return json(['code'=>-4,'msg'=>'非法操作！']);
            }
        }
        //获取范围内的符合
        if(!!$pid && !$cid){
            //获取所有的子id
            $whereC['gid'] = $this->gid;
            $whereCo['pid'] = $pid;
            $whereCo['id'] = $pid;
            $cids = Db::name('category')
                    ->where($whereC)
                    ->where(function ($query) use($whereCo){
                        $query->whereOr($whereCo);
                    })->column('id');
            if(!empty($cids)){
                $whereBc['gid'] = $this->gid;
                $whereBc['categoryid'] = ['IN',$cids];
                $bids = Db::name('baojia_cat')->where($whereBc)->column('bid');
                if(empty($bids)){
                    return json(['code'=>1,'total'=>0,'msg'=>'没有数据！']);
                }
            }else{
                return json(['code'=>1,'total'=>0,'msg'=>'没有数据！']);
            }
        }else if(!!$pid && !!$cid){
            //获取所有的子id
            $whereC['gid'] = $this->gid;
            $whereC['pid'] = $pid;
            $whereC['id'] = $cid;
            $cid = Db::name('category')->where($whereC)->whereOr($whereCo)->value('id');
            if(!$cid){
                return json(['code'=>1,'total'=>0,'msg'=>'没有数据！']);
            }else{
                $whereBc['gid'] = $this->gid;
                $whereBc['categoryid'] = $cid;
                $bids = Db::name('baojia_cat')->where($whereBc)->column('bid');
                if(empty($bids)){
                    return json(['code'=>1,'total'=>0,'msg'=>'没有数据！']);
                }
            }
        }
        $whereB['b.gid'] = $this->gid;
        if(isset($bids) && !empty($bids)){
            $whereB['b.id'] = ['IN',$bids];
        }
        if(!!$goodsName){
            $whereG['g.goods_name'] = ['LIKE','%' . $goodsName . '%'];
        }
        $p = isset($input['p']) && (intval($input['p']) > 0) ?  intval($input['p'])  : 1;
        $limit = isset($input['limit']) && (intval(trim($input['limit'])) > 0) ?  intval(trim($input['limit'])) : $this->limit; 
        if(isset($whereG)){
            if($p === 1){
                $total = Db::name('baojia b')
                ->join('goods g','b.goods_id = g.goods_id')
                ->where($whereB)
                ->where($whereG)
                ->count();
                if($total === 0){
                    return json(['code'=>1,'msg'=>'没有更多数据！']);
                }
            }
            $bjGoods = Db::name('baojia b')
                ->field('b.id,b.goods_id,b.unit,b.goodsName,b.goodsImg,b.goodsThumb,b.gid,b.unitid,b.unitgid,b.tuanprice,g.goods_img,g.goods_name')
                ->join('goods g','b.goods_id = g.goods_id')
                ->where($whereB)
                ->where($whereG)
                ->page($p,$limit)
                ->select();
        }else{
            if($p === 1){
                $total = Db::name('baojia b')
                ->join('goods g','b.goods_id = g.goods_id')
                ->where($whereB)
                ->count();
                if($total === 0){
                    return json(['code'=>1,'msg'=>'没有更多数据！']);
                }
            }
            $bjGoods = Db::name('baojia b')
                ->field('b.id,b.goods_id,b.unit,b.goodsName,b.goodsImg,b.goodsThumb,b.gid,b.unitid,b.unitgid,b.tuanprice,g.goods_img,g.goods_name')
                ->join('goods g','b.goods_id = g.goods_id')
                ->where($whereB)
                ->page($p,$limit)
                ->select();
        }
        if(empty($bjGoods)){
            return json(['code'=>0,'msg'=>'没有更多数据！']);
        }else{
            $units = [];
            for($i = 0, $l = count($bjGoods); $i < $l; $i++){
                $x = $bjGoods[$i];
                $goodsImg = '';
                if($x['goodsThumb']){
                    $goodsImg = $x['goodsThumb'];
                }else if($x['goodsImg']){
                    $goodsImg = $x['goodsImg'];
                }else if($x['goods_thumb']){
                    $goodsImg = $x['goods_thumb'];
                }else if($x['goods_img']){
                    $goodsImg = $x['goods_img'];
                }
                $x['goods_img'] = mkgoodsimgurl(['url'=>$goodsImg]);
                if(!!$rankId){
                    $whereBr['gid'] = $this->gid;
                    $whereBr['rank_id'] = $rankId;
                    $whereBr['goods_id'] = $x['goods_id'];
                    $bjr = Db::name('baojia_rank')->field('unitid,unit,tuanprice')->where($whereBr)->find();
                    if(!empty($bjr)){
                        $x['tuanprice'] = $bjr['tuanprice'];
                        $x['unitid'] = $bjr['unitid'];
                        $x['unit'] = $bjr['unit'];
                    }
                }
                if(!isset($units[$x['unitgid']])){
                    $whereU['unitgid'] = $x['unitgid'];
                    $whereU['gid'] = $this->gid;
                    $units[$x['unitgid']] = Db::name('unit')->where($whereU)->column('id,coefficient,uname,unitgid');
                }
                $x['units'] = $units[$x['unitgid']];
                $bjGoods[$i] = $x;
            }
        }
        if(isset($total)){
            return json(['code' => 1,'total' => $total, 'data' => $bjGoods,'msg' => '数据加载成功！']);
        }else{
            return json(['code' => 1,'data' => $bjGoods,'msg' => '数据加载成功！']);
        }
    }
    private function getGoodsCat($input = []){
        $categoryModel = new \app\common\model\Category(['gid'=>$this->gid]);
        $input['type'] = 0;
        $input['isTree'] = true;
        $res = $categoryModel->category($input);
        $res['code']=1;
        return $res;
    }
}

<?php
namespace app\shop\controller;
use think\Db;
use think\Request;
class Order extends Base{
    //put your code here
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
    public function add(){
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
                case 'addorder':return $this->addOrder($input);
            }
        }
    }
    public function index(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $action = '';
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch ($action){
                case 'showlist':return $this->showList($input);
                default:null;
            }
        }else{
            $input = input();
            $st = 0;
            if(isset($input['st'])){
                $st = $input['st'];
                $this->assign('st',$st);
            }
            return $this->fetch('/ljk/order/index');
        }
    }
    private function showList($input = []){
        $limit = $input['limit'];
        $p = intval($input['p']);
        $st = intval($input['st']);
        $p <=0 ? $p = 1 : null;
        $offset = ($p - 1) * $limit;
        switch($st){
            case 0:null;break;
            case 1:
                $whereO['pay'] = 1;
                $whereO['confirm'] = 1;
                break;
            case 2:
                $whereO['pay'] = 0;
                $whereO['confirm'] = ['NEQ', -1];
                $whereO['trade'] = ['NEQ', 1];
                break;
            case 3:
                $whereO['confirm'] = 1;
                $whereO['dispatch'] = 1;
                $whereO['trade'] = ['NEQ', 1];
                break;
            case 4:
                $whereO['confirm'] = 1;
                $whereO['dispatch'] = 2;
                $whereO['trade'] = 1;
                $whereO['pay'] = 1;
                break;
            default:null;
        }
        $whereO['gid'] = $this->gid;
        $whereO['uid'] = $this->uid;
        if($p === 1){
            $total = Db::name('order')->where($whereO)->count();
            if($total === 0){
                $res['code'] = 0;
                $res['msg'] = '没有数据！';
                return json($res);
            }
        }
        $data = Db::name('order')
                ->field('oid,number,uid,pay,dispatch,trade,total,sjguid,express_fee,confirm,adate,confirm')
                ->where($whereO)
                ->limit($offset,$limit)
                ->order('adate DESC')
                ->select();
        if(!!$data){
            $res['code'] = 1;
            $res['msg'] = '加载数据成功！';
            $res['total'] = $total;
            $whereOg['gid'] = $this->gid;
            $payStatus = config('order_pay_status');
            $dispatchStatus = config('order_dispatch_status');
            $psy = [];
            for($i = 0, $l = count($data); $i < $l; $i++){
                if($data[$i]['pay'] == 0){
                    //计算已支付总额
                    $whereOp['gid'] = $this->gid;
                    $whereOp['oid'] = $data[$i]['oid'];
                    $whereOp['uid'] = $this->uid;
                    $data[$i]['haspay'] = $haspay = (float)Db::name('order_pay')->where($whereOp)->sum('pay');
                    $data[$i]['notpay'] = $notpay = $data[$i]['total'] + $data[$i]['express_fee'] - $data[$i]['haspay'];
                }
                $data[$i]['adate'] =  date('Y/m/d H:i:s',$data[$i]['adate']);
                $data[$i]['pay_desc'] = $payStatus[$data[$i]['pay']];
                $data[$i]['dispatch_desc'] = $dispatchStatus[$data[$i]['dispatch']];
                //if($data[$i]['dispatch'] != 0){
                //说明配送激活，调出配送人员信息
//                $whereBo['gid'] = $this->gid;
//                $whereBo['oid'] = $data[$i]['oid'];
//                $whereBo['type'] = 1;
//                $psyid = Db::name('bind_order')->where($whereBo)->value('guid');
                $psyid = $data[$i]['sjguid'];
                if(!!$psyid){
                    if(!isset($psy[$psyid])){
                        $whereGm['gid'] = $this->gid;
                        $whereGm['uid'] = $psyid;
                        $psy[$psyid] = Db::name('group_member')->field('uid,realname,mobile')->where($whereGm)->find();
                    }
                    if(!empty($psy[$psyid])){
                        $data[$i]['psy'] = $psy[$psyid];
                    }
                }
                //}
                //获取订单商品信息
                $whereOg['oid'] = $data[$i]['oid'];
                $goods = Db::name('order_goods')->field('id,goodid,type,sku_id,baojia_id,unitgid,unit,unitid,price,amount,name,avs,num')->where($whereOg)->select();
                $goodsInfo = [];
                $b = [];
                $g = [];
                for($j = 0,$k = count($goods); $j < $k; $j++){
                    $bjId = $goods[$j]['baojia_id'] ? $goods[$j]['baojia_id'] : false;
                    $goodsId = $goods[$j]['goodid'] ? $goods[$j]['goodid'] : false;
                    $x = [
                        'goodsName' => $goods[$j]['name'],
                        'num' => $goods[$j]['num'],
                        'price' => $goods[$j]['price'],
                        'amount' => $goods[$j]['amount'],
                        'unit' => $goods[$j]['unit'],
                        'thumb' => '',
                        'type' => $goods[$j]['type'],
                        'attrs' => $goods[$j]['avs']
                    ];
                    if(!!$bjId){
                        if(!isset($b[$bjId])){
                            $whereB['gid'] = $this->gid;
                            $whereB['id'] = $bjId;
                            $b[$bjId] = Db::name('baojia')->where($whereB)->find();
                        }
                        $x['thumb'] = $b[$bjId]['goodsThumb']?$b[$bjId]['goodsThumb']:$b[$bjId]['goodsImg'];
                        $x['thumb'] = mkgoodsimgurl(['url'=>$x['thumb']]);
                    }else{
                        if(!isset($g[$goodsId])){
                            $whereG['goods_id'] = $goodsId;
                            $g[$goodsId] = Db::name('goods')->where($whereG)->find();
                        }
                        $x['thumb'] = $g[$goodsId]['goods_thumb']?$g[$goodsId]['goods_thumb']:$g[$goodsId]['goods_img'];
                        $x['thumb'] = mkgoodsimgurl(['url'=>$x['thumb']]);
                    }
                    $goodsInfo[] = $x;
                }
                $data[$i]['goodsInfo'] = $goodsInfo;
                $data[$i]['js'] = $k; 
            }
            $res['data'] = $data;
            return json($res);
        }else{
            $res['code'] = 0;
            $res['msg'] = '没有更多数据！';
            $res['data'] = $data;
            return json($res);
        }
    }
    public function detail(){
        if(request()->isAjax() && request()->isPost()){
            /***以后ajax无刷新在说***/
//            $input = input('post.');
//            $action = '';
//            if(isset($input['action'])){
//                $action = trim($input['action']);
//                unset($input['action']);
//            }
//            switch ($action){
//                case 'showdetail':return $this->showDetail($input);
//                default:null;
//            }
        }else{
            $input = input();
            //$oid = isset($input['oid']) ? intval(trim($input['oid'])): 0;
            $action = '';
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch ($action){
                case 'showdetail':$res = $this->showDetail($input,false);$this->assign('res',$res);break;
                default:$res = $this->showDetail($input,false);$this->assign('res',$res);
            }
            return $this->fetch('/ljk/order/detail');
        }
    }
    private function showDetail($input = [],$jsign = true){
        $oid = $input['oid'];
        if(!$oid){
            $res = ['code'=>-1,'msg' => '非法操作！'];
        }else{
            $whereO['gid'] = $this->gid;
            $whereO['uid'] = $this->uid;
            $whereO['oid'] = $oid;
            $order = Db::name('order')
                    ->field('oid,uid,number,pay,dispatch,trade,total,express_fee,confirm,adate,sjguid,content')
                    ->where($whereO)
                    ->find();
            if(!empty($order)){
                $payStatus = config('order_pay_status');
                $dispatchStatus = config('order_dispatch_status');
                $order['adate'] =  date('Y/m/d H:i:s',$order['adate']);
                $order['pay_desc'] = $payStatus[$order['pay']];
                $order['dispatch_desc'] = $dispatchStatus[$order['dispatch']];
                $whereOg['gid'] = $this->gid;
                $whereOg['oid'] = $oid;
                $goods = Db::name('order_goods')
                        ->field('id,goodid,sku_id,baojia_id,unitgid,unit,unitid,price,amount,name,avs,num')
                        ->where($whereOg)
                        ->select();
                if($order['pay'] == 0){
                    //主动拉去微信支付结果
                    $payment = new Payment();
                    $sign = new \app\common\controller\Sign();
                    $param = [
                        'out_trade_no' => $order['number']
                    ];
                    $input = array_merge($param,$sign->mkSign($param));
                    $res = $payment->check_weixin_order($input)->getData();
                    if($res['code'] == 1){
                        $order['pay'] = Db::name('order')
                            ->where($whereO)
                            ->value('pay');
                        $order['pay_desc'] = $payStatus[$order['pay']];
                    }
                    //计算已支付总额
                    $whereOp['gid'] = $this->gid;
                    $whereOp['oid'] = $order['oid'];
                    $whereOp['uid'] = $this->uid;
                    $order['haspay'] = $haspay = (float)Db::name('order_pay')->where($whereOp)->sum('pay');
                    $order['notpay'] = $notpay = $order['total'] + $order['express_fee'] - $order['haspay'];
                }
                $goodsInfo = [];
                $b = [];
                $g = [];
                for($j = 0,$k = count($goods); $j < $k; $j++){
                    //如果有skuId优先使用skuId
                    $bjId = $goods[$j]['baojia_id'] ? $goods[$j]['baojia_id'] : false;
                    $goodsId = $goods[$j]['goodid'] ? $goods[$j]['goodid'] : false;
                    $x = [
                        'goodsName' => $goods[$j]['name'],
                        'num' => $goods[$j]['num'],
                        'price' => $goods[$j]['price'],
                        'amount' => $goods[$j]['amount'],
                        'unit' => $goods[$j]['unit'],
                        'thumb' => '',
                        'attrs' => $goods[$j]['avs']
                    ];
                    if(!!$bjId){
                        if(!isset($b[$bjId])){
                            $whereB['gid'] = $this->gid;
                            $whereB['id'] = $bjId;
                            $b[$bjId] = Db::name('baojia')->where($whereB)->find();
                        }
                        $x['thumb'] = $b[$bjId]['goodsThumb']?$b[$bjId]['goodsThumb']:$b[$bjId]['goodsImg'];
                        $x['thumb'] = mkgoodsimgurl(['url'=>$x['thumb']]);
                    }else{
                        if(!isset($g[$goodsId])){
                            $whereG['goods_id'] = $goodsId;
                            $g[$goodsId] = Db::name('goods')->where($whereG)->find();
                        }
                        $x['thumb'] = $g[$goodsId]['goods_thumb']?$g[$goodsId]['goods_thumb']:$g[$goodsId]['goods_img'];
                        $x['thumb'] = mkgoodsimgurl(['url'=>$x['thumb']]);
                    }
                    $goodsInfo[] = $x;
                }
                $order['goodsInfo'] = $goodsInfo;
                //附加收货人信息
                $whereA['gid'] = $this->gid;
                $whereA['uid'] = $this->uid;
                $whereA['id'] = $order['address_id'];
                $order['address'] = Db::name('user_address')->where($whereA)->find();
                if(!$order['address']){
                    $whereadd['uid'] = $this->uid;
                    $whereadd['oid'] = $oid;
                    $oadd=Db::name('order_member')->where($whereadd)->find();
                    $order['address']['consignee']=$oadd['realname'];
                    $order['address']['mobile']=$oadd['phone'];
                    $order['address']['address']=$oadd['address'];
                }
//                $whereBo['gid'] = $this->gid;
//                $whereBo['oid'] = $oid;
//                $whereBo['type'] = 1;
//                $psyid = Db::name('bind_order')->where($whereBo)->value('guid');
                $psyid  = $order['sjguid'];
                if(!!$psyid){
                    $whereGm['gid'] = $this->gid;
                    $whereGm['uid'] = $psyid;
                    $order['psy'] = Db::name('group_member')->field('uid,realname,mobile')->where($whereGm)->find();
                }
                $res = ['code' => 0,'msg' => '订单存在！','data'=>$order];
            }else{
                $res = ['code' => -1,'msg' => '订单不存在！'];
            }
        }
        if($jsign === true){
            return json($res);
        }else{
            return $res;
        }
    }
    public function addOrder($input = []){
        $addrId = isset($input['addrid']) ? intval($input['addrid']) : 0;
        $pd = isset($input['pdate']) ? trim($input['pdate']) : 0;
        $pt = isset($input['ptime']) ? intval($input['ptime']) : 0;
        $dtype = isset($input['dtype']) ? intval($input['dtype']) : 0;
        if(!$addrId){
            return json(['code' => -1,'msg' => '请设置好收货地址后再下单！']);
        }
        $whereUa['id'] = $addrId;
        $whereUa['gid'] = $this->gid;
        $whereUa['uid'] = $this->uid;
        $addr = Db::name('user_address')->where($whereUa)->find();
        if(empty($addr)){
            return json(['code' => -1,'msg' => '请设置好收货地址后再下单！']);
        }
        /**获取购物车项目**/
        $whereC['user_id'] = $this->uid;
        $whereC['gid'] = $this->gid;
        $whereC['is_real'] = 2;
        $whereC['type'] = 1;
        $whereC['checked'] = 1;
        $whereC['status'] = 1;
        $cartList = Db::name('cart')
            ->where($whereC)
            ->field('id,goods_name name,goods_id goodid,bjid baojia_id,goods_number num,unit,user_id uid,gid,unitid,unitgid,unitg,priceid,avs,skuid sku_id,goods_price price')
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
        $whereUm['uid'] = $this->uid;
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
                $amount += $v['num'] * $v['price'];
                $v['amount'] = $v['num'] * $v['price'];
                $v['guid'] = $userInfo['guid'];
                $v['dguid'] = $userInfo['guid'];
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
                        $amount += $v['num'] * $v['price'];
                        $v['amount'] = $v['num'] * $v['price'];
                        $v['guid'] = $userInfo['guid'];
                        $v['dguid'] = $userInfo['guid'];
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
        $order['type'] = 0;
        $order['gid'] = $this->gid;
        $order['uid'] = $this->uid;
        $order['guid'] = $userInfo['guid'];
        $order['dguid'] = $userInfo['guid'];
        $order['shopid'] = $userInfo['shopid'];
        $order['address_id'] = $addrId;
        $order['number'] = date('YmdHis',time()).mt_rand(100000, 999999);
        $order['total'] = $amount;
        //获取运费配置
        if($dtype === 1){
            $ffee = 0;
        }else{
            $fc = Db::name('freight_config')->where(['gid' => $this->gid,'status' => 1])->find();
            if($fc){
                if($fc['min'] > $amount){
                    $ffee = $fc['ltfee'];
                }else{
                    $ffee = $fc['egtfee'];
                }
            }else{
                $ffee = 0;
            }
        }
        $order['express_fee'] = $ffee;
        $order['mobile'] = $userInfo['mobile'];
        $order['downgid'] = $this->gid;
        $order['content'] = isset($input['content']) ? $input['content'] : '';
        $order['paytype'] = $paytype = isset($input['paytype']) ? intval($input['paytype']) : 0;
        $order['adate'] = time();
        $order['ip'] = get_client_ip();
        $order['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        if(!!$pd){
            $tm = strtotime($pd);
            if($this->gid == 205 || $this->gid == 133){
                $tmdt = strtotime(date('Y-m-d',strtotime('+1days')));//明天的时间戳
                $tmdt5 = strtotime(date('Y-m-d',strtotime('+6days')));//五天后的时间戳
                if($tm < $tmdt){
                    $tm = $tmdt;
                }else if($tm > $tmdt5){
                    $tm = $tmdt5;
                }
            }else{
                $tmdt = strtotime(date('Y-m-d'));//今天的时间戳
                $tmdtm = strtotime(date('Y-m-d',strtotime('+1months'))) + 3600 * 24;//一个月零1天的时间戳
                if($tm < $tmdt){
                    $tm = $tmdt;
                }else if($tm > $tmdtm){
                    $tm = $tmdtm;
                }
            }
        }else{
            if($this->gid == 205 || $this->gid == 133){
                $tm = strtotime(date('Y-m-d',strtotime('+1days')));//默认明天配送
            }else{
                $tm = 0;//默认未预定配送时间
            }
        }
        $order['pd'] = $tm;
        $order['pt'] = $pt;
        $order['state'] = '';
        $orderMember = [];
        $orderMember['address'] = $addr['areaname'] . $addr['address'];
        $orderMember['areaids'] = $addr['areaids'];
        $orderMember['gid'] = $this->gid;
        $orderMember['uid'] = $this->uid;
        $orderMember['realname'] = $addr['consignee'];
        $orderMember['phone'] = $addr['mobile'] ? $addr['mobile'] : ($addr['tel'] ? $addr['tel'] : $userInfo['mobile']);
        $orderMember['x'] = 0;
        $orderMember['y'] = 0;
        $orderPost = [];
        $orderPost['gid'] = $this->gid;
        $orderPost['guid'] = $userInfo['guid'];
        $orderPost['content'] = '下订单';
        $orderPost['adate'] = time();
        $orderPost['uid'] = $this->uid;
        $orderPost['thumb'] = '';
        Db::startTrans();
        try{
            $whereC['gid'] = $this->gid;
            $whereC['user_id'] = $this->uid;
            $whereC['type'] = 1;
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
            return json(['code' => 0,'msg' => '订单信息提交成功！','oid' => $oid,'paytype' => $paytype]);
        }catch(\think\Exception $e){
            Db::rollBack();
            return json(['code' => -1,'msg' => '系统繁忙！' . $e->getMessage()]);
        }
    }
}
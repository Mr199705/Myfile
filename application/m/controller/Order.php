<?php
// +----------------------------------------------------------------------
// | snake
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://ljk.cc All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: itkk <it_kk@qq.com>
// +----------------------------------------------------------------------
namespace app\m\controller;
use app\m\model\OrderModel;
use app\m\model\OrderGoods;
use app\m\model\OrderPost;
use app\m\model\UserMember;
use app\m\model\Group;
use app\common\controller\Sign;
use app\common\model\Message;
use app\api\controller\Push;
use think\Db;
class Order extends Base
{
    private $storageModel;
    public function index(){
        if (request()->isAjax()) {
            $param = input('param.');
            $limit = $param['limit'];
//            $uid = $param['uid'];
            $uid = session('downorderuserid');
            $p = intval($param['p']);
            $offset = ($param['p'] - 1) * $limit;
            $where = [];//生成order查询条件
            $whereOr = [];//生成or查询条件
            $whereOM = [];//生成order_member查询条件
            $whereUM = [];//生成user_member查询条件
            if (!!$uid) {
                $actpromise = false;
                $guid = Db::name('user_member')->where('uid', $uid)->where('gid', $this->gid)->value('guid');
                $whereGM['uid'] = $guid;
                $whereGM['gid'] = $this->gid;
                $superid = Db::name('group_member')->where($whereGM)->value('superid');
                $s1 = trim(trim($superid), ',');
                if (!!$s1) {
                    $s1 = explode(',', $s1);
                    if (in_array($this->guid, $s1)) {
                        $actpromise = true;
                    } else {
                        $actpromise = false;
                    }
                } else {
                    $actpromise = false;
                }
                //说明这个客户的跟进人是这个登录人的下属的客户
                if ($actpromise) {
                    $where['o.guid|o.dguid'] = $guid;
                } else {
                    $where['o.guid|o.dguid'] = $this->guid;
                }
                $where['o.gid'] = $this->gid;
                $where['o.uid'] = $uid;//判断这个客户的上级
            } else {
                if (!session('isadmin')) {
                    $where['o.gid'] = $this->gid;
                    $where['o.guid|o.dguid'] = $this->guid;
                } else {
                    $where['o.gid|o.ppsid'] = $this->gid;
                }
            }
            if (isset($param['confirm']) && strlen($param['confirm']) && in_array($param['confrim'], [-1, 0, 1])) {
                $where['o.confirm'] = $param['confirm'];
            }
            if (isset($param['pay']) && strlen($param['pay']) && in_array($param['pay'], [0, 1])) {
                $where['o.pay'] = $param['pay'];
            }
            $ss = false;
            $ee = false;
            if (isset($param['during']) && in_array(trim($param['during']), ['', '1', '2', '3'])) {
                $during = trim($param['during']);
                if ($during !== '') {
                    $duringType = intval($during);
                    switch ($duringType) {
                        case 1:
                            $ss = strtotime(date('Y-m'));
                            break;//当月
                        case 2:
                            $w = date('N') - 1;
                            $ss = strtotime(date('Y-m-d', strtotime('-' . $w . 'days')));
                            break;//本周
                        case 3:
                            $ss = strtotime(date('Y-m-d'));
                            break;//本日
                        default:
                            null;
                    }
                }
            } else if (
                (isset($param['ss']) && str_replace('T', ' ', trim($param['ss'])))
                ||
                (isset($param['ee']) && str_replace('T', ' ', trim($param['ee'])))
            ) {
                $sss = str_replace('T', ' ', trim($param['ss']));
                $ees = str_replace('T', ' ', trim($param['ee']));
                if (!!$sss && !!$ees) {
                    $ss = strtotime($sss);
                    $ee = strtotime($ees);
                } else if (!!$sss) {
                    $ss = strtotime($sss);
                } else {
                    $ee = strtotime($ees);
                }
            }
            if (!!$ss || !!$ee) {
                if (!!$ss && !!$ee) {
                    if ($ss > $ee) {
                        $where['o.adate'] = ['EGT', $ss];
                    } else if ($ss === $ee) {
                        $where['o.adate'] = $ee;
                    } else {
                        $where['o.adate'] = ['BETWEEN', [$ss, $ee]];
                    }
                } else if (!!$ss) {
                    $where['o.adate'] = ['EGT', $ss];
                } else {
                    $where['o.adate'] = ['ELT', $ee];
                }
            }
            if (isset($param['number']) && !empty($param['number'])) {
                $whereOr['o.number'] = ['like', '%' . $param['number'] . '%'];
            }
            if (isset($param['mphone']) && !empty($param['mphone'])) {
                $whereOM['phone'] = ['like', '%' . $param['mphone'] . '%'];
            }
            if (isset($param['uphone']) && !empty($param['uphone'])) {
                $whereUM['phone'] = ['like', '%' . $param['uphone'] . '%'];
            }
            if (isset($param['maddress']) && !empty($param['maddress'])) {
                $whereOM['address'] = ['like', '%' . $param['maddress'] . '%'];
            }
            if (isset($param['urealname']) && !empty($param['urealname'])) {
                $whereUM['realname'] = ['like', '%' . $param['urealname'] . '%'];
            }
            if (!empty($whereOM)) {
                $oid = Db::name('order_member')->where($whereOM)->column('oid');
                $whereOr['o.oid'] = ['IN', implode(',', $oid)];
            }
            if (!empty($whereUM)) {
                $uid = Db::name('user_member')->field('uid')->where('gid', $this->gid)->where($whereUM)->column('uid');
                $whereOr['o.uid'] = ['IN', implode(',', $uid)];
            }
            $OrderModel = new OrderModel;
            //设置查询条件
            $res = $OrderModel->getOrdersByWhere($where, $whereOr, $offset, $limit);
            if (($this->gid == 133 || $this->gid == 206) && $this->guid == 1113) {
//                print_r($where);
//                print_r($whereOr);
//                print_r($res);
//                exit();
            }
            $orders = [];
            $pay_status = config('order_pay_status');
            $rz_status = config('rz_status');
            $ord_confirm = config('order_confirm_status');
            $ord_dispatch = config('order_dispatch_status');
            $ord_trade = config('order_trade_status');
            foreach ($res as $key => $vo) {
                //对数据进行加工
                $order = $res[$key]->getData();
                $order['adate'] = date('Y-m-d H:i:s', $order['adate']);
                $host = Usermember::get($order['uid']);
                $order['host']['realname'] = $host['realname'];
                $order['host']['mobile'] = $host['mobile'];
                $order['host']['address'] = $host['address'];
                $order['host']['trust'] = $rz_status[$host['trust']];
                $order['confirm_desc'] = $ord_confirm[$vo['confirm'] + 1];
                if ($vo['confirm'] == -1) {
                    $order['pay_desc'] = '';
                    $order['dispatch_desc'] = '';
                    $order['trade_desc'] = '';
                } else {
                    $order['pay_desc'] = $pay_status[$vo['pay']];
                    $order['dispatch_desc'] = $ord_dispatch[$vo['dispatch']];
                    $order['trade_desc'] = $ord_trade[$vo['trade']];
                }
                $orders[] = $order;
            }
            $return['total'] = count($orders);//总数据
            $return['data'] = $orders;
            $return['p'] = intval($param['p']);
            $return['code'] = 1;
            $return['msg'] = '数据加载成功！';
            if (count($orders) == 0) {
                if ($return['p'] === 1) {
                    unset($return['data']);
                }
                $return['code'] = 0;
                $return['msg'] = '没有数据！';
            }
            return json($return);
        }
        return $this->fetch('/order');
    }
    public function daohang()
    {
        $OrderModel = new OrderModel();
        $userModel = new UserMember();
        $GroupModel = new Group();
        $oid = input('param.oid');
        if ($oid) {
            //$oid = $conditions['oid'] = intval($this->getParam('oid'));
            $orderinfo = $OrderModel->getOneOrder($oid);
            // $userinfo=$userModel->getOneUser($this->gid,$orderinfo['uid']);
            $userinfo = $userModel->getOneUser($orderinfo['gid'], $orderinfo['uid']);
            if (!$userinfo['x']) $this->error('客户地理位置未标注，无法使用导航功能，请先标注客户地理位置！');
            //if($_SESSION['lbsinfo']){ $xy=$_SESSION['lbsinfo'];}
            //else{
            $ginfo = $GroupModel->getOneGroup($this->gid);
            $xy['x'] = $ginfo['x'];
            $xy['y'] = $ginfo['y'];
            //	}
            $this->assign('userinfo', $userinfo);
            $this->assign('orderinfo', $orderinfo);
            $this->assign('xy', $xy);
            return $this->fetch('/home/order/daohang');
        }
    }
    /*
    * 订单详情
    */
    public function detail()
    {
        $oid = input('param.id');
        if (!$oid) $oid = session('oid');
        session('oid', $oid);
        $where = [];
        $where['status'] = ['=', 1];
        $where['gid|ppsid'] = $this->gid;
        //$where['guid'] = session('guid');
        $where['oid'] = $oid;
        $OrderModel = new OrderModel;
        $OrderGoodsModel = new OrderGoods;
        $orderinfo = $OrderModel->where($where)->find();
        $sum = 0;
        $whereO['oid'] = $orderinfo['oid'];
        $whereO['status'] = 1;
        $ordergoods = Db::name('order_goods')->where($whereO)->select();
        foreach ($ordergoods as $key => $item) {
            $sum += $item['amount'];
        }
        $OrderModel->where($where)->setField('total', $sum);

        $orderinfo['dhr'] = Db::name('user_member')->where('uid', $orderinfo['uid'])->find();

        $whereGM['uid'] = $orderinfo['guid'];
        $whereGM['gid'] = $this->gid;
        $superid = Db::name('group_member')->where($whereGM)->value('superid');
        $s1 = trim(trim($superid), ',');
        if (!!$s1) {
            $s1 = explode(',', $s1);
            if (in_array($this->guid, $s1)) {
                $subsign = true;
            } else {
                $subsign = false;
            }
        } else {
            $subsign = false;
        }

        if ($orderinfo['guid'] != $this->guid && $orderinfo['dguid'] != $this->guid && !$subsign) {
            if ($orderinfo['dhr']['guid'] != $orderinfo['guid'] || $orderinfo['dhr']['guid'] != $orderinfo['dguid']) {
                $return['code'] = 1;
                $return['msg'] = '没有数据';
                return json($return);
            }
        }
        $map['oid'] = $oid;
        $arr = [];
        $arr2 = [];
        foreach ($ordergoods as $key => $item) {
            $arr['sku_id'] = $item['sku_id'];
            $arr['oid'] = $item['oid'];
            $arr['type'] = $item['type'];
            $arr['gid'] = $item['gid'];
            $arr['goodid'] = $item['goodid'];
            $arr['name'] = $item['name'];
            $arr['unitg'] = $item['unitg'];
            $arr['unit'] = $item['unit'];
            array_push($arr2, $arr);
        }
        //查询去重复
        $temp = [];
        $temp2 = [];
        foreach ($arr2 as $k => $v) {
            $v = join(',', $v); //降维,也可以用implode,将一维数组转换为用逗号连接的字符串
            $temp[$k] = $v;
        }
        $temp = array_unique($temp); //去掉重复的字符串,也就是重复的一维数组
        foreach ($temp as $k => $v) {
            $array = explode(',', $v); //再将拆开的数组重新组装
            $temp2[$k]['sku_id'] = $array[0];
            $temp2[$k]['oid'] = $array[1];
            $temp2[$k]['type'] = $array[2];
            $temp2[$k]['gid'] = $array[3];
            $temp2[$k]['goodid'] = $array[4];
            $temp2[$k]['name'] = $array[5];
            $temp2[$k]['unitg'] = $array[6];
            $temp2[$k]['unit'] = $array[7];
        }
        $map['status'] = 1;
        $map['oid'] = $oid;
        $order_goods_status = config('order_goods_status');
        $selectResult = [];
        $ids = [];
        foreach ($temp2 as $k => $v) {
            $map['sku_id'] = $v['sku_id'];
            $map['oid'] = $v['oid'];
            $map['type'] = $v['type'];
            $map['gid'] = $v['gid'];
            $map['goodid'] = $v['goodid'];
            $map['name'] = $v['name'];
            $map['unitg'] = $v['unitg'];
            $map['unit'] = $v['unit'];
            $nums = 0;
            $amounts = 0;
            $og = Db::name('order_goods')->field('num,amount', true)->where($map)->find();
            $count = Db::name('order_goods')->field('id,num,amount')->where($map)->select();
            foreach ($count as $k) {
                $nums += $k['num'];
                $amounts += $k['amount'];
            }
            $og['num'] = $nums;
            $og['amount'] = $amounts;

            $newog = [
                'num' => $nums,
                'amount' => $amounts
            ];
            Db::name('order_goods')->where($map)->update($newog);
            $selectResult1 = $og;
            array_push($selectResult, $selectResult1);
            array_push($ids, $og['id']);
        }
        //删除 oid=$oid id 不在$ids范围内的商品
        $whereDel['oid'] = ['=', $oid];
        $whereDel['id'] = ['not in', $ids];
        Db::name('order_goods')->where($whereDel)->delete();
        $orderinfo['goodsnum'] = Db::name('order_goods')->where(array('oid' => $oid))->count();
        $pay_status = config('order_pay_status');
        $order_confirm_status = config('order_confirm_status');
        $order_dispatch_status = config('order_dispatch_status');
        $order_trade_status = config('order_trade_status');

        $orderinfo['confirm_desc'] = $order_confirm_status[$orderinfo['confirm'] + 1];
        if ($orderinfo['confirm'] == -1) {
            $orderinfo['pay_desc'] = '';
            $orderinfo['dispatch_desc'] = '';
            $orderinfo['trade_desc'] = '';
        } else {
            $orderinfo['pay_desc'] = $pay_status[$orderinfo['pay']];
            $orderinfo['dispatch_desc'] = $order_dispatch_status[$orderinfo['dispatch']];
            $orderinfo['trade_desc'] = $order_trade_status[$orderinfo['trade']];
        }
        $orderinfo['adate'] = date('Y-m-d H:i', $orderinfo['adate']);
        $orderinfo['shr'] = Db::name('order_member')->where('oid', $orderinfo['oid'])->find();
        //正则匹配得到地址
        $orderinfo['address'] = preg_replace('/(.+)((市辖区)|(县))(.+)/', '$1$5', $orderinfo['shr']['address']);

        $whereOP['gid'] = $this->gid;
        $whereOP['uid'] = $orderinfo['uid'];
        $whereOP['oid'] = $oid;
        $order = Db::name('order')->field('oid,uid,pd,pt,express_fee,dtype,correct')->where($whereOP)->find();
        $order['pd'] = date("Y-m-d", $order['pd']);

        $whereOP['guid'] = $this->guid;
        $orderinfo['postnum'] = Db::name('order_post')->where($whereOP)->count();
        $order_pay = Db::name('order_pay')->where($whereOP)->select();

        $freight_config = Db::name('freight_config')->field('min,ltfee,desc')->where('gid',$this->gid)->find();
        $ltfee = '';
        if ($orderinfo['dtype'] == 0) {
            if ($orderinfo['total'] < $freight_config['min']) {
                $ltfee = $freight_config['ltfee'];
            } else {
                $ltfee = 0;
            }
        } else {
            $ltfee = 0;
        }
        $summ=$orderinfo['total'] + $ltfee + $order['correct'];
        if($summ <= 0 ){
            $summ=0;
        }
        $orderinfo['ordertotal'] = sprintf("%.2f", $summ);
        foreach ($selectResult as $key => $vo) {
//            $unit=$this->exchangeNum($vo['num'],$vo['unitgid']);
            $sku = Db::name('baojia_sku')->field('title,attrs_value')->where(array('gid' => $this->gid, 'id' => $vo['sku_id']))->find();
            $goodsinfo = Db::name('goods')->where('goods_id', $vo['goodid'])->field('goods_thumb,goods_img')->find();
            if ($goodsinfo['goods_thumb']) {
                $goodimg = $goodsinfo['goods_thumb'];
            } else {
                $goodimg = $goodsinfo['goods_img'];
            }
            if ($goodimg) {
                $goodimg = mkgoodsimgurl(['url' => $goodimg]);
            }
//            $selectResult[$key]['unitname']=$unit;
            $selectResult[$key]['goods_img'] = $goodimg;
            $selectResult[$key]['goods_type'] = $order_goods_status[$vo['type']];
            $selectResult[$key]['sku'] = $sku;
        }
        $return['total'] = count($selectResult);  //总数据
        $return['data'] = $orderinfo;
        $return['data']['goods'] = $selectResult;
        $return['data']['order'] = $order;
        $return['data']['order_pay'] = $order_pay;
        $return['data']['freight_config'] = $freight_config;
        $return['code'] = 1;
        $return['msg'] = '数据加载成功！';
        if (count($selectResult) == 0) {
            $return['code'] = 1;
            $return['msg'] = '没有数据！';
        }
        return json($return);
    }
    /**
     * 订单商品
     */
    public function bjCat()
    {
        if (request()->isAjax()) {
            //获取所有的报价类别
            $where['gid'] = $this->gid;
            $where['pid'] = 0;
            $p = Db::table('ljk_category')->field('id,title')->where($where)->select();
            $data = [];
            foreach ($p as $k => $v) {
                $where['pid'] = $v['id'];
                $c = Db::table('ljk_category')->field('id,title')->where($where)->select();
                $data[] = ['p' => $v, 'c' => $c];
            }
            return json(['code' => 1, 'data' => $data]);
        }
    }
    private function goods()
    {
        $goods_id = input('param.id');
        if (!$goods_id) $goods_id = session('goods_id');
        session('goods_id', $goods_id);
        $oid = session('oid');
        $where = [];
        $where['gid'] = $this->gid;
        $where['status'] = ['<>', -1];
        $where['oid'] = $oid;
        $OrderModel = new OrderModel;
        $OrderGoodsModel = new OrderGoods;
        $orderinfo = $OrderModel->where($where)->find();
        $map['oid'] = $oid;
        $map['id'] = $goods_id;

        // $map['status'] = ['<>',-1];;
        $selectResult = $OrderGoodsModel->where($map)->find();

        $order = Db::name('order_goods')->where(array('gid' => $this->gid, 'id' => $goods_id))->find();
        $uid = session('downorderuserid') ? session('downorderuserid') : $order['uid'];
        $rankid = Db::name('user_member')->where('uid', $uid)->value('rankid');
        if ($order['sku_id'] != 0) {
            $sku = Db::name('baojia_skuprice')->field('unitid,unitgid,tuanprice')->where(array('gid' => $this->gid, 'skuid' => $order['sku_id'], 'rankid' => $rankid))->order('id desc')->select();
            $units = [];
            foreach ($sku as $k) {
                $whereu['status'] = 1;
                $whereu['unitgid'] = $k['unitgid'];
                $whereu['id'] = $k['unitid'];
                $whereu['gid'] = $this->gid;
                $unit = Db::name('unit')->where($whereu)->find();
                $unit['tuanprice'] = $k['tuanprice'];
                array_push($units, $unit);
            }
            $orderinfo['unitlist'] = $units;
        } else {
            $whereu['status'] = 1;
            $whereu['unitgid'] = $selectResult['unitgid'];
            $whereu['gid'] = $this->gid;

            $orderinfo['unitlist'] = Db::name('unit')->where($whereu)->select();
        }

        $coefficient = Db::name('unit')->where('id', $selectResult['unitid'])->value('coefficient');
        if ($coefficient) $selectResult['unitprice'] = $selectResult['price'] / $coefficient;
        $order_goods_status = config('order_goods_status');
        $orderinfo['adate'] = date('Y-m-d H:i', $orderinfo['adate']);
        $orderinfo['shr'] = Db::name('order_member')->where('oid', $orderinfo['oid'])->find();
        $orderinfo['postnum'] = Db::name('order_post')->where('oid', $orderinfo['oid'])->count();
        $orderinfo['dhr'] = Db::name('user_member')->where('uid', $orderinfo['uid'])->find();
        $goodsinfo = Db::name('goods')->where('goods_id', $selectResult['goodid'])->field('goods_thumb,goods_img')->find();
        if ($goodsinfo['goods_img']) {
            $goodsinfo['goods_img'] = mkgoodsimgurl(['url' => $goodsinfo['goods_img']]);
        }
        if ($goodsinfo['goods_thumb']) {
            $goodsinfo['goods_thumb'] = mkgoodsimgurl(['url' => $goodsinfo['goods_thumb']]);
        }
        $sku = Db::name('baojia_sku')->where(array('gid' => $this->gid, 'id' => $selectResult['sku_id']))->find();
        if ($sku == null) {
            $goods = Db::name('baojia')->field('tuanprice,unitid,unitgid')
                ->where(array('gid' => $this->gid, 'goods_id' => $selectResult['goodid']))->find();
            $selectResult['goods'] = $goods;
        }

        $selectResult['goods_type'] = $order_goods_status[$selectResult['type']];
        $selectResult['sku'] = $sku;
        $return['total'] = count($selectResult);  //总数据
        $return['data'] = $orderinfo;
        $return['data']['order_goods'] = $selectResult;
        $return['data']['order_goods_status'] = $order_goods_status;
        $return['data']['goods'] = $goodsinfo;
        $return['code'] = 1;
        $return['msg'] = '数据加载成功！';
        if (!$orderinfo) {
            $return['code'] = 0;
            $return['msg'] = '没有数据！';
        }
        return json($return);
    }
    /**
     * 订单详情
     */
    public function post()
    {
        if (request()->isPost()) {
            $param = input('param.');
            $oid = input('param.oid');
            if (!$oid) $oid = session('oid');
            session('oid', $oid);
            $limit = $param['limit'];
            $offset = ($param['p'] - 1) * $limit;
            $where = [];
            $where['oid'] = $oid;
            if (isset($param['searchText']) && !empty($param['searchText'])) {
                $where['number'] = ['like', '%' . $param['searchText'] . '%'];
            }
            $pay_status = config('order_pay_status');
            $order_confirm_status = config('order_confirm_status');
            $order_dispatch_status = config('order_dispatch_status');
            $order_trade_status = config('order_trade_status');
            $OrderPost = new OrderPost;
            $selectResult = $OrderPost->getOrderPostByWhere($where, $offset, $limit);
            $uid = Db::name('order')->where('oid', $oid)->value('uid');
            foreach ($selectResult as $key => $vo) {
                if ($vo['is_down'] == 0 && $vo['wx_serverid'] != '' && $vo['adate'] > time() - 3 * 24 * 60 * 60) {
                    $vo['imgsid'] = $this->ybdownwximg($vo['id'], 'ydxzym' . $vo['id']);
                }
                $selectResult[$key]['adate'] = date('Y-m-d H:i', $vo['adate']);
                $selectResult[$key]['gname'] = Db::name('group_member')->where('uid', $vo['guid'])->value('realname');
                if (!$vo['guid']) $selectResult[$key]['gname'] = Db::name('user_member')->where('uid', $uid)->value('realname');
                $selectResult[$key]['pay'] = $pay_status[$vo['pay']];
                $selectResult[$key]['confirm'] = $order_confirm_status[$vo['confirm'] + 1];
                $selectResult[$key]['dispatch'] = $order_dispatch_status[$vo['dispatch']];
                $selectResult[$key]['trade'] = $order_trade_status[$vo['trade']];
                $pid = $vo['id'];
                $lbs = Db::name('group_memberlbs')->field('business,street,street_number,province,city,district,address')->where('pid', $pid)->where('type', 2)->find();
                if (!empty($lbs)) {
                    $selectResult[$key]['lbs'] = $lbs;
                } else {
                    $selectResult[$key]['lbs'] = null;
                }
                //获取这个跟进记录的图片信息
                if (!!$vo['imgsid']) {
                    $imgsid = explode(',', $vo['imgsid']);
                    $unique = array_unique($imgsid);
                    $imgsurl = [];
                    $imgsurlu = [];
                    foreach ($unique as $key1 => $val2) {
                        $imgsurlu[$val2] = mkurl(Db::name('file')->field('url,savename,savepath,ext')->find($val2));
                        // $imgsurlu[$val2] = mkurl($val2);
                    }
                    for ($i = 0; $i < count($imgsid); $i++) {
                        $imgsurl[] = $imgsurlu[$imgsid[$i]];
                    }
                    $selectResult[$key]['imgsurl'] = $imgsurl;
                }

                /* $selectResult[$key]['adate'] = date('Y-m-d H:i:s', $vo['adate']);
                 $selectResult[$key]['host']['trust'] = $rz_status[$selectResult[$key]['host']['trust']];
                 $operate = [
                     '编辑' => url('user/userEdit', ['id' => $vo['uid']]),
                     '删除' => "javascript:userDel('".$vo['uid']."')"
                 ];
 */
                // $selectResult[$key]['operate'] = showOperate($operate);

            }
            $return['total'] = $OrderPost->getOrderPostCount($where);  //总数据
            $return['data'] = $selectResult;
            $return['p'] = intval($param['p']);
            $return['code'] = 1;
            $return['msg'] = '数据加载成功！';
            if (count($selectResult) == 0) {
                $return['code'] = 0;
                $return['msg'] = '没有数据！';
            }
            return json($return);
        }
    }
    /**
     * 订单配送商
     */
    public function distributor()
    {
        if (request()->isPost()) {
            $oid = input('param.id');
            if (!$oid) $oid = session('oid');
            if ($oid) {
                $where['gid'] = $this->gid;
                $where['oid'] = $oid;
                $OrderModel = new OrderModel;
                $orderinfo = $OrderModel->where($where)->find();
                $distributorInfo = Db::name('group')->where('gid', $orderinfo['ppsid'])->find();
                if ($distributorInfo) {
                    return json(['code' => 1, 'data' => $distributorInfo, 'msg' => "数据加载成功！"]);
                } else {
                    return json(['code' => 0, 'data' => '', 'msg' => "没有配送商！"]);
                }
            } else {
                return json(['code' => 0, 'data' => '', 'msg' => "订单有误或配送商不存在！"]);
            }
        }
    }
    private function exchangeNum($num = 0, $unitgid = 0, $zeroSign = false)
    {
        $exChangeNum = [];
        if (!isset($this->units[$unitgid])) {
            $whereU['gid'] = $this->gid;
            $whereU['unitgid'] = $unitgid;
            $whereU['status'] = 1;//有效单位
            $this->units[$unitgid] = Db::name('unit')->where($whereU)->order(['coefficient' => 'DESC'])->column('id,uname,coefficient');
        }
        $units = $this->units[$unitgid];
        foreach ($units as $u) {
            $c = $u['coefficient'];
            $x = $num / $c;
            if ($x == 0 && $zeroSign === false) {
                return $exChangeNum;
            } else if (floor($x) == 0 && $zeroSign === true) {
                $exChangeNum[] = [
                    'unitid' => $u['id'],
                    'uname' => $u['uname'],
                    'num' => 0,
                    'coefficient' => $c
                ];
            } else if ($x >= 1) {
                $x = floor($x);
                $unum = $x * $c;
                $num -= $unum;
                $exChangeNum[] = [
                    'unitid' => $u['id'],
                    'uname' => $u['uname'],
                    'num' => $x,
                    'coefficient' => $c
                ];
            }
        }
        return $exChangeNum;
    }
    /**
     * 修改订单商品
     */
    public function editgoods()
    {
        if (request()->isPost()) {
            $param = input('post.');
            if (isset($param['action'])) {
                if ($param['action'] == 'showgoods') {
                    return $this->goods();
                }
                if ($param['action'] == 'show') {

                }
            } else {
                $ogId = isset($param['id']) ? intval($param['id']) : 0;
                $param['amount'] = $param['num'] * $param['price'];
                $oid = intval(session('oid'));
                $OrderGoods = new OrderGoods;
                $OrderModel = new OrderModel;
                $orderinfo = $OrderModel->getOneOrderBygid($oid, $this->gid);
                $oldgoods = $OrderGoods->getOneOrderGoods($param['id']);
                //减少库存
                $sku = $OrderGoods->field('sku_id')->where('id', $param['id'])->find();
                $backnum = abs($param['backnum']);
                $ordergoods = '';
                if ($backnum > 0) {
                    $baojiasku = Db::name('baojia_sku')->field('storage_num')
                        ->where(array('gid' => $this->gid, 'id' => $sku['sku_id']))->find();
                    $unit1 = Db::name('unit')->field('id,coefficient')->where(array('gid' => $this->gid, 'id' => $param['unitid']))->find();

                    $ordergoods = Db::name('order_goods')->field('id,uid,oid,name,num,price,amount,unit,unitid,unitg,unitgid')->where(array('id' => $param['id']))->find();
                    $unit2 = Db::name('unit')->field('id,coefficient')->where(array('gid' => $this->gid, 'id' => $ordergoods['unitid']))->find();

                    $sto = 0;
                    $backnum1 = $backnum * $unit1['coefficient'];
                    if ($unit1['id'] == $ordergoods['unitid']) {
                        if ($param['backnum'] >= 0) {
                            //库存增加
                            $sto = $baojiasku['storage_num'] - $backnum1;
                        } else if ($param['backnum'] < 0) {
                            $sto = $baojiasku['storage_num'] + $backnum1;
                        }
                    } else {
                        //单位不一样 系数不一样
                        $coe1 = $ordergoods['num'] * $unit2['coefficient'];//原来的数量 18  3
                        $coe = $param['num'] * $unit1['coefficient'];//传输的数量  3  18
                        $backnum1 = abs($coe1 - $coe);
                        if ($coe1 >= $coe) {
                            //库存增加
                            $sto = $baojiasku['storage_num'] + $backnum1;
                        } else {
                            //库存减少
                            $sto = $baojiasku['storage_num'] - $backnum1;
                        }
                    }
                    if ($sto < 0) {
                        return json(['code' => 10, 'data' => '', 'msg' => "库存不足，无法下单！"]);
                    }
                    Db::name('baojia_sku')->where(array('gid' => $this->gid, 'id' => $sku['sku_id']))->setField('storage_num', $sto);
                } else {
                    unset($param['backnum']);
                }
                $params = [
                    'type' => $param['type'],
                    'unitid' => $param['unitid'],
                    'id' => $param['id'],
                    'unit' => $param['unit'],
                    'price' => $param['price'],
                    'amount' => $param['num'] * $param['price'],
                    'num' => $param['num'],
                    'adate' => time(),
                    'desc' => strip_tags($param['desc']),
                ];
                $content = '编辑订单商品';
                $poststatus = $this->addorderPost($ogId, 'edit', $content, $param['type'], $param['num'], $param['unit'], $param['price'], $param['desc']);
                if ($poststatus) {
                    $flag = $OrderGoods->editOrderGoods($params);
                }
                $this->editordertotal($orderinfo['uid'],$oid);
                $order = '';
                //返还库存之后 只能是未付款的订单 可以删除 修改 商品数量 价格  然后修改 order_pay 记录
                $whereP['gid'] = ['EQ', $this->gid];
                $whereP['guid'] = ['EQ', $this->guid];
                $whereP['uid'] = ['EQ', $orderinfo['uid']];

                $macard_um = Db::name('mcard_um')->where($whereP)->where('status', 1)->find();
                $total_money = $param['num'] * $param['price'];

                if ($macard_um != null) {
                    //有会员卡支付
                    //先扣除 会员卡账户  支付了钱的 都是已付款状态  其他都是未付款
                    $useable = $macard_um['useable'] - $total_money;
                    if ($useable > 0) {
                        //余额足够减
                        $notpay = 0;
                        $paynow = $haspay = $total_money;
                        $status['pay'] = 1;
                    } else if ($useable < 0) {
                        if ($macard_um['useable'] == 0) {
                            $status['pay'] = 0;
                        } else {
                            $status['pay'] = 1;
                        }
                        $notpay = abs($useable);
                        $paynow = $haspay = $macard_um['useable'];
                        $useable = 0;
                    } else {
                        $notpay = abs($useable);
                        $paynow = $haspay = 0;
                        $status['pay'] = 0;
                        $useable = 0;
                    }
                    //扣除后 会员卡余额
                    Db::name('mcard_um')->where($whereP)->setField('useable', $useable);
                } else {
                    //用户没有会员卡 支付
                    $notpay = $total_money;    //未付金额
                    $paynow = $haspay = 0;    //共计支付金额
                    $status['pay'] = 0;
                }
                $danwei2 = '';
                $oidd = Db::name('order')->field('number')->where($whereP)->where(array('oid' => $orderinfo['oid']))->find();
                $pay['total'] = $total_money;     //当前订单总值
                $pay['notpay'] = $notpay;    //未付金额
                $pay['haspay'] = $haspay;    //共计已付金额
                $pay['pay'] = $paynow;       //本次支付金额
                $whereP['sn'] = ['EQ', $oidd['number']];
                $p_status = Db::name('order_pay')->where($whereP)->update($pay);
                if ($flag['code'] == 1 && $p_status) {
                    $fromid = Db::name('order_post')->getLastInsID();
                    if ($fromid) {
                        addgps(session('guid'), $oid, $fromid, 2);
                    }
                    //更新总金额
                    $amounts = $OrderGoods->where('oid', $oid)->select();
                    foreach ($amounts as $amount) {
                        $amounts[] = $amount['amount'];
                    }
//                    $total = array_sum($amounts);
//                    $OrderModel->editOrder(array('oid'=>$oid,'confirm'=>0, 'pay'=>0, 'total'=>$total));
                    if (!!$orderinfo['ddid']) {
                        //重新计算分成信息
                        $separate = controller('fx/Separate', 'controller');
                        $postData = ['oid' => $oid];
                        $sign = new Sign();
                        $postData = array_merge($postData, $sign->mkSign($postData));
                        $separate->insideIndex($postData);
                    }
                    //计算原订单商品最小单位数量
                    $unitgid = $oldgoods['unitgid'];
                    $unitid = $oldgoods['unitid'];
                    $on = $oldgoods['num'];
                    $whereU['gid'] = $this->gid;
                    $whereU['unitgid'] = $unitgid;
                    $whereU['status'] = 1;
                    $minunitId = Db::name('unit')->where($whereU)->order('coefficient ASC')->value('id');
                    $whereU['id'] = $unitid;
                    $u = [];
                    $u[$unitid] = Db::name('unit')->where($whereU)->find();
                    $one = $u[$unitid]['coefficient'] * $on;
                    if (!isset($u[$param['unitid']])) {
                        $whereU['id'] = $param['unitid'];
                        $u[$param['unitid']] = Db::name('unit')->where($whereU)->find();
                    }
                    $nne = $u[$param['unitid']]['coefficient'] * $param['num'];
                    $n = (int)($nne - $one);
                    if ($n !== 0) {
                        //组织商品数据
                        $input['gid'] = $this->gid;
                        $input['apply_uid'] = $this->guid;
                        $input['apply_type'] = 1;
                        $input['oid'] = $orderinfo['oid'];
                        $input['osn'] = $orderinfo['number'];
                        $input['opostid'] = $fromid;

                        $input['goods_name'] = $oldgoods['name'];
                        $input['unitgid'] = $oldgoods['unitgid'];
                        $input['unitid'] = $oldgoods['unitid'];
                        $input['goods_id'] = $oldgoods['goodid'];
                        $input['minunitid'] = $minunitId;

                        if ($n > 0) {
                            //车销出库
                            $input['type'] = 0;
                            $input['typeval'] = 3;
                            $input['target_type'] = 2;
                            $input['target_uid'] = $orderinfo['uid'];
                            $input['from_type'] = 1;
                            $input['from_uid'] = $this->guid;
                            $input['move_in'] = 0;
                            $input['move_out'] = 1;
                            $input['num'] = $n;
                            //获取projectId
//                                $this->ogOutSg($input);
                        } else {
                            //车销退库
                            $n = abs($n);
                            $input['type'] = 1;
                            $input['typeval'] = 4;
                            $input['from_type'] = 2;
                            $input['from_uid'] = $orderinfo['uid'];
                            $input['target_type'] = 1;
                            $input['target_uid'] = $this->guid;
                            $input['move_in'] = 1;
                            $input['move_out'] = 0;
                            $input['num'] = $n;
//                                $this->ogInSg($input);
                        }
                    }
                    return json(['code' => $flag['code'], 'data' => $oid, 'msg' => $flag['msg']]);
                } else {
                    return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
                }
            }
        }
    }
    public function addPost()
    {
        if (!!($oid = request()->post('oid'))) {
            session('oid', $oid);
        }
        $sessName = 'order_oid_' . session('oid');
        $uploadImg = controller('uploadImg', 'event');
        $uploadImg->setSessName($sessName);
        if (($file = request()->file('file'))) {
            if (!empty($file)) {
                $imgsid = $uploadImg->index($file);
                return json([$imgsid]);
            }
        }
        if (request()->isAjax() && input('action') === 'getlocalids') {
            $localids = $uploadImg->getImgsLocalIds();
            return json(['localids' => $localids, 'code' => 1]);
        }
        if (request()->isAjax() && input('action') === 'setlocalids') {
            $localids = input('localids/a');
            $serverids = input('serverids/a');
            $uploadImg->setImgLocalIds($localids, $serverids);
            return 1;
        }
        if (request()->isAjax() && input('action') === 'ybdownwximg') {
            if (input('id')) $this->ybdownwximg(input('id'), $sessName);
        }
        if (request()->isAjax() && input('action') === 'show') {
            $imgs = $uploadImg->getImgs();
            return json(['data' => ['imgs' => $imgs, 'upimgurl' => '_m_order_addpost'], 'code' => 1]);
        }
        if (request()->isAjax() && input('action') === 'add') {
            $post = request()->post();
            $content = $post['content'];
            if (isset($post['imgsid'])) {
                $imgsid = $post['imgsid'];
                $imgsid = str_replace("_", ',', $imgsid);
            } else {
                $imgsid = '';
            }
            $oid = $post['oid'];
            if (!!$oid) {
                session('oid', $oid);
            } else {
                $oid = session('oid');
            }
            //获取该订单的状态信息
            $OrderModel = new OrderModel;
            $status = $OrderModel->field('uid,confirm,status,pay,ppsid,trade,dispatch')->find($oid)->getData();
            $oidPost = [
                'content' => $content,
                'oid' => session('oid'),
                'guid' => session('guid'),
                'gid' => session('gid'),
                'uid' => $status['uid'],
                'imgsid' => $imgsid,
                'wx_serverid' => $uploadImg->getImgsServerIds(),
                'confirm' => $status['confirm'],
                'pay' => $status['pay'],
                'status' => $status['status'],
                'trade' => $status['trade'],
                'psid' => $status['ppsid'],
                'dispatch' => $status['dispatch'],
                'adate' => time(),
            ];
            //这一步对数据进行验证,Validate
            $imgsid = array_count_values(explode(',', $imgsid));
            foreach ($imgsid as $k => $v) {
                Db::table('__FILE__')->where('id', $k)->setInc('cited', $v);//图片引用数据设置
            }
            $uploadImg->clear();
            Db::name('order_post')->insert($oidPost);
            $fromid = Db::name('order_post')->getLastInsID();
            if ($fromid) {
                addgps(session('guid'), $oid, $fromid, 2);  //2订单lbs类型
                return json(['code' => 0, 'id' => $fromid, 'msg' => '订单跟进成功']);
            } else {
                return json(['code' => 1, 'msg' => '订单跟进失败！']);
            }
        }
        if (request()->isAjax() && input('action') === 'upimgs') {
            return $uploadImg->upimgs();
        }
    }
    //异步下载微信图片
    private function ybdownwximg($postid, $sessName)
    {
        if ($postid) {
            $serverIds = Db::name('order_post')->where('gid', session('gid'))->where('id', $postid)->where('is_down', 0)->field('imgsid,uid,wx_serverid')->find();
            if ($serverIds['wx_serverid']) {
                if (!session('uid')) session('uid', $serverIds['uid']);
                $access_token = $this->getwx_access_token();
                if (!$access_token) {
                    return json(['code' => -1, 'msg' => '未设置公众账号信息，无法使用图片上传功能，请联系系统管理员！']);
                }
                $uploadImg = controller('uploadImg', 'event');
                $uploadImg->setSessName($sessName);
                $serverIds['wx_serverid'] = explode(',', $serverIds['wx_serverid']);
                foreach ($serverIds['wx_serverid'] as $sid) {
                    $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token=' . $access_token . '&media_id=' . $sid;
                    $imgsid = $uploadImg->wxupload($url);
                }
                $imgsid = str_replace("_", ',', $imgsid);
                if ($imgsid && $serverIds['imgsid'] != '' && $serverIds['imgsid'] != ',') {
                    $imgsid = explode(',', $serverIds['imgsid'] . ',' . $imgsid);
                    $imgsid = array_unique($imgsid);
                    $imgsid = implode(',', $imgsid);
                }

                Db::name('order_post')->where('gid', session('gid'))->where('id', $postid)->update(['imgsid' => $imgsid, 'is_down' => 1]);
                $imgsidcount = array_count_values(explode(',', $imgsid));
                foreach ($imgsidcount as $k => $v) {
                    Db::table('__FILE__')->where('id', $k)->setInc('cited', $v);//图片引用数据设置
                }
                $uploadImg->clear();
                session('uid', null);
                return $imgsid;
            }
        }
    }
    public function editStatus()
    {
        if (!!($oid = request()->post('oid'))) {
            session('oid', $oid);
        } else {
            $oid = session('oid');
        }
        $OrderModel = new OrderModel;
        $oldStatus = $OrderModel->field('confirm,pay,psid,trade,dispatch')->find($oid)->getData();
        if ($oldStatus['confirm'] == -1 || $oldStatus['trade'] == 1) {
            return json(['code' => 1, 'data' => ['nodata' => 1, 'msg' => '无法对无效或已结束订单进行任何操作']]);
        }
        if (!!$oldStatus['ppsid']) {
            return json(['code' => 1, 'data' => ['nodata' => 1, 'msg' => '该订单已分配给配送商操作，你已无权操作该订单！']]);
        }
        if (request()->isAjax() && input('action') === 'show') {
            //获取当前订单的状态
            return json(
                [
                    'data' => [
                        'nodata' => 0,
                        'msg' => '',
                        'confirm' => ['v' => array_values(config('order_confirm_status')), 'k' => array_keys(config('order_confirm_status')), 'l' => count(config('order_confirm_status'))],
                        'pay' => ['v' => array_values(config('order_pay_status')), 'k' => array_keys(config('order_pay_status')), 'l' => count(config('order_pay_status'))],
                        'dispatch' => ['v' => array_values(config('order_dispatch_status')), 'k' => array_keys(config('order_dispatch_status')), 'l' => count(config('order_dispatch_status'))],
                        'trade' => ['v' => array_values(config('order_trade_status')), 'k' => array_keys(config('order_trade_status')), 'l' => count(config('order_trade_status'))],
                        's' => $oldStatus
                    ],
                    'code' => 1
                ]
            );
        }
        if (request()->isAjax() && input('action') === 'edit') {
            $post = request()->post();
            unset($post['action']);
            unset($oldStatus['psid']);
            $status = $post;
            //第一步先更新订单状态
            if ($status['confirm'] == -1) {
                $status['trade'] = 1;//交易结束
            } else if ($status['pay'] == 1) {
                $status['confirm'] = 1;
            }
            //与原状态相同
            if (isset($status['pay']) && $oldStatus['pay'] == $status['pay']) {
                unset($status['pay']);
            }
            if (isset($status['dispatch']) && $oldStatus['dispatch'] == $status['dispatch']) {
                unset($status['dispatch']);
            }

            $rs = $OrderModel->editOrderStatus($status, $oid);
            if ($rs['code'] === 1) {
                $desc = '';
                foreach ($oldStatus as $k => $v) {
                    if ($oldStatus[$k] != $status[$k]) {
                        //说明这个状态有变化
                        $st = config('order_' . $k . '_status');
                        if ($k == 'confirm') {
                            if (isset($status[$k])) $desc .= $st[$oldStatus[$k] + 1] . '->' . $st[$status[$k] + 1] . ',';
                        } else {
                            if (isset($status[$k])) $desc .= $st[$oldStatus[$k]] . '->' . $st[$status[$k]] . ',';
                        }
                    }
                }
                $desc = trim($desc, ',');
                $status = $OrderModel->field('confirm,status,pay,psid,ddid,trade,dispatch')->find($oid)->getData();
                $oidPost = [
                    'content' => '修改状态:' . $desc,
                    'oid' => session('oid'),
                    'guid' => session('guid'),
                    'gid' => session('gid'),
                    'thumb' => '',
                    'confirm' => $status['confirm'],
                    'pay' => $status['pay'],
                    'status' => $status['status'],
                    'trade' => $status['trade'],
                    'psid' => $status['psid'],
                    'dispatch' => $status['dispatch'],
                    'adate' => time(),
                ];
                $OrderPost = new OrderPost;
                $OrderPost->addPost($oidPost);
                $fromid = $OrderPost->id;
                if (($oldStatus['confirm'] != -1 && $status['confirm'] == -1)) {
                    //进行库存回滚操作
                    $this->cxOrderStorageIn($oid, $fromid);
                }
                if (($oldStatus['pay'] == 0 && $post['pay'] == 1) || ($oldStatus['pay'] == 1 && $post['pay'] == 0)) {
                    //去更新积分信息
                    $this->updateScore($oid);
                }

                //去更新易品保订单信息
                if (!!$status['ddid']) {
                    //设置订单状态信息
                    $separate = controller('fx/Separate', 'controller');
                    $postData = ['oid' => session('oid')];
                    $sign = new Sign();
                    $postData = array_merge($postData, $sign->mkSign($postData));
                    // $res = $separate->insideIndex($postData);
                    // print_r($res);
                    // if($res['code']){
                    //     return json(['code' => 1, 'msg' => '订单状态修改失败']);
                    // }
                }
                return json(['code' => 0, 'oid' => $oid, 'msg' => '订单状态修改成功！']);
            } else {
                return json($res);
            }
        }
        if (request()->isAjax() && input('action') === 'upimgs') {
            return $uploadImg->upimgs();
        }
    }
    private function updateScore($oid)
    {
        //获取订单商品信息
        if ($oid) {
            $where = [];
            $g = [];
            $ml = 0;
            $score = 0;
            $where['gid'] = $gid = $this->gid;
            $where['oid'] = $oid;
            $goodslist = Db::name('order_goods')->where($where)->select();
            $o = Db::name('order')->field('uid,status,confirm,trade,pay,dispatch')->where($where)->find();
            if (!empty($o)) {
                $uid = $o['uid'];
                $status = $o['status'];
                $trade = $o['trade'];
                $confirm = $o['confirm'];
                $pay = $o['pay'];
                $dispatch = $o['dispatch'];
            } else {
                return 0;
            }
            $userInfo = Db::name('user_member')->find($uid);
            $rankid = $userInfo['rankid'];
            foreach ($goodslist as $goods) {
                if ($goods['price'] > 0) {
                    $g['goods_id'] = $goods['goodid'];
                    $g['price'] = $goods['price'];
                    $g['unitid'] = $goods['unitid'];
                    $g['goods_number'] = $goods['num'];
                    $g['rank_id'] = $rankid;
                    $g['gid'] = $gid;
                    $ml += $this->profit($g);
                    $g = [];
                }
            }
            $pay_point = $this->JF(['m' => $ml, 'gid' => $gid, 't' => 2]);
            if ($pay_point) {
                //更新积分信息
                if ($pay) {
                    Db::name('user_member')->where(['uid' => $uid])->setInc('pay_points', $pay_point);
                } else {
                    Db::name('user_member')->where(['uid' => $uid])->setDec('pay_points', $pay_point);
                }
            }
        } else {
            return 0;
        }
    }
    private function profit($goods)
    {
        $whereBJ = [];//报价信息查询条件
        $bj = [];//当前商品报价信息
        if (isset($goods['goods_id']) && !!$goods['goods_id']) {
            $goods_id = $goods['goods_id'];
            $whereBJ['goods_id'] = $goods_id;
        } else {
            return ['code' => 1, 'msg' => '无效的操作'];
        }
        if (isset($goods['unitid']) && !!$goods['unitid']) {
            $unitid = $goods['unitid'];
        } else {
            return ['code' => 1, 'msg' => '无效的操作'];
        }
        if (isset($goods['gid']) && !!$goods['gid']) {
            $whereBJ['gid'] = $goods['gid'];
        } else {
            return ['code' => 1, 'msg' => '无效的操作'];
        }
        if (isset($goods['goods_number']) && !!$goods['goods_number']) {
            $number = intval($goods['goods_number']);
        } else {
            return 0;
        }
        if (isset($goods['price']) && !!$goods['price']) {
            $price = $goods['price'];
        } else {
            return 0;
        }
        if (isset($goods['rank_id']) && !!$goods['rank_id']) {
            $rank_id = $goods['rank_id'];
            $whereBJ['rank_id'] = $goods['rank_id'];
        }
        //获取报价信息
        if (isset($rank_id) && !!$rank_id) {
            //获取商品的报价信息
            $bj = Db::name('baojia_rank')->where($whereBJ)->find();
        }
        if (empty($bj)) {
            unset($whereBJ['rank_id']);
            $bj = Db::name('baojia')->where($whereBJ)->find();
        }
        if (empty($bj)) {
            return 0;
        }
        if ($bj['cbprice'] == 0) {
            return 0;
        }
        $cbUnitid = $bj['cbunitid'];
        $cbPrice = $bj['cbprice'];
        $bju = Db::name('unit')->field('coefficient')->find($unitid);
        $cbu = Db::name('unit')->field('coefficient')->find($cbUnitid);
        $bjc = $bju['coefficient'];
        $cbc = $cbu['coefficient'];
        $bjp = $price / $bjc;
        $cbp = $cbPrice / $cbc;
        return ($bjp - $cbp) * $bjc * $number;
    }
    /*
 * 积分的计算方法
 * 1.利润 m
 * 2.组 gid
 * 3.类型 t
 */
    private function JF($config)
    {
        //初始化各计算要素
        $gid = isset($config['gid']) ? $config['gid'] : 0;
        $m = isset($config['m']) ? $config['m'] : 0; //成交 单价金额
        $t = isset($config['t']) ? $config['t'] : 0;//成交类型
        //调取积分数据库信息
        if (empty($gid) || !$gid) {
            return ['code' => 0, 'msg' => '非法操作！'];
        }
        $c = 0;//系数
        $pInfo = Db::name('point')->where(['gid' => $gid])->find();
        if (empty($pInfo) || !$pInfo['status']) {
            return 0;
        }
        $b = $pInfo['base_ratio'];
        switch ($t) {
            case 0:
                $c = $pInfo['wei_ratio'];
                break;
            case 1:
                $c = $pInfo['tel_ratio'];
                break;
            case 2:
                $c = $pInfo['ywy_ratio'];
                break;
            case 3:
                $c = $pInfo['pc_ratio'];
                break;
            case 4:
                $c = $pInfo['d_ratio'];
                break;
            default:
                $c = 0;
        }
        return round($m * $b * $c * 0.01);
    }
    public function bjGoods()
    {
        if (request()->isAjax()) {
            $var = request()->post();
            if (isset($var['type']) && !empty($var['type'])) {
                switch ($var['type']) {
                    case 0:
                        null;
                        break;
                    case 1:
                        return $this->aogrog();
                        break;
                    default:
                        null;
                }
            }
            if (!isset($var['t'])) {
                $var['t'] = '';
            }
            if (!isset($var['cid'])) {
                $var['cid'] = '';
            }
            if (isset($var['oid']) && !empty($var['oid'])) {
                session('oid', $var['oid']);//更新session oid
            }
            $t = $var['t'];
            $cid = $var['cid'];
            $limit = $var['limit'];
            $p = intval($var['p']);
            $offset = ($p - 1) * $limit;
            $oid = session('oid');
            //通过oid去获取uid
            $u = Db::name('order')->field('uid')->find($oid);
            //判断这个用户存不存在
            if (!empty($u)) {
                $uid = $u['uid'];
                $where = [];
                $whereR = [];
                $whereOr = [];
                $BJ = [];//这里返回报价信息到首页
                //通过uid去获取等级
                if ($cid) {
                    //判定是否有子分类
                    $ccid = Db::name('category')
                        ->where('pid', $cid)
                        ->where('gid', $this->gid)
                        ->column('id');
                    if (!empty($ccid)) {
                        array_push($ccid, $cid);
                        $ccids = implode(',', $ccid);
                        $whereC['categoryid'] = ['IN', $ccids];
                    } else {
                        $whereC['categoryid'] = $cid;
                    }
                    //获取报价分类在这个范围内的报价产品
                    $bid = Db::name('baojia_cat')
                        ->where($whereC)
                        ->where('gid', $this->gid)
                        ->column('bid');
                    if (!empty($bid)) {
                        $where['b.id'] = ['IN', $bid];
                        $wherep['a.id'] = ['IN', $bid];
                        $whereCount['id'] = ['IN', $bid];
                        //如果bid存在，但是报价已经被删除了 说明这个报价分类多余，应该删除，但是如果有文字筛选条件，那么不能确定是否作处理
                    } else {
                        //直接退出了
                        $return['code'] = 0;
                        $return['p'] = $p;
                        $return['msg'] = '没有更多数据！';
                        $return['data'] = null;
                        $return['total'] = 0;
                        return json($return);
                    }
                }
                $where['b.gid'] = $this->gid;
                $wherep['b.gid'] = $this->gid;
                $whereOrG['goods_name'] = $whereOr['g.goods_name'] = ['LIKE', '%' . $t . '%'];
                $whereOrG['goods_sn'] = $whereOr['g.goods_sn'] = ['LIKE', '%' . $t . '%'];
                //获取符合条件的所有goods_id
                if ($t !== '') {
                    $goods_ids1 = Db::name('goods')->whereOr($whereOrG)->column('goods_id');
                    //过滤$goods_ids;
                    if (!empty($goods_ids1)) {
                        //优化$goods_ids
                        //先查询出这个gid下报价商品的所有gid;
                        $bj_goods_ids = Db::name('baojia')->where('gid', $this->gid)->column('goods_id');
                        $goods_ids = [];
                        foreach ($bj_goods_ids as $k => $v) {
                            if (in_array($v, $goods_ids1)) {
                                $goods_ids[] = $v;
                            }
                        }
                    } else {
                        //直接退出了
                        $return = ['code' => 0, 'p' => $p, 'msg' => '没有更多数据！', 'data' => [], 'total' => 0];
                        return json($return);
                    }
                    if (!empty($goods_ids)) {
                        $whereCount['gid'] = $this->gid;
                        $where['b.goods_id'] = $whereCount['goods_id'] = ['IN', $goods_ids];
                        $total = Db::name('baojia')->where($whereCount)->count();
                        $defaultBJ = Db::name('baojia')
                            ->alias('b')
                            ->field('
                                b.id,
                                b.goods_id,
                                b.gid,
                                b.yd_score,
                                b.categoryid,
                                b.retailprice,
                                b.tuanprice,
                                b.cuxiao,
                                b.csdate,
                                b.cedate,
                                b.unit,
                                b.unitid,
                                b.unitgid,
                                g.goods_name,
                                g.goods_desc,
                                g.goods_sn,
                                g.keywords,
                                g.goods_img
                                ')
                            ->join('ljk_goods g', 'b.goods_id=g.goods_id', 'LEFT')
                            ->where($where)
                            ->where(function ($query) use ($whereOr) {
                                $query->whereOr($whereOr);
                            })
                            ->order('goods_id DESC')
                            ->limit($offset, $limit)
                            ->select();
                    } else {
                        $total = 0;
                    }
                } else {
                    $whereCount['gid'] = $this->gid;
//                    $total = Db::name('baojia')->where($whereCount)->count();
                    $defaultBJ1 = Db::name('baojia')
                        ->alias('b')
                        ->field('
                                b.id,
                                b.goods_id,
                                b.gid,
                                b.yd_score,
                                b.categoryid,
                                b.retailprice,
                                b.tuanprice,
                                b.cuxiao,
                                b.csdate,
                                b.cedate,
                                b.unit,
                                b.unitid,
                                b.unitgid,
                                g.goods_name,
                                g.goods_desc,
                                g.goods_sn,
                                g.keywords,
                                g.goods_img
                            ')
                        ->join('goods g', 'b.goods_id=g.goods_id', 'LEFT')
                        ->where($where)
                        ->where('tuanprice', 'neq', 0)
                        ->order('displayorder DESC')
                        ->limit($offset, $limit)
                        ->select();

                    $defaultB2 = Db::name('baojia a')->field('a.*,c.goods_name,c.goods_desc,c.goods_sn,c.keywords,c.goods_img')
                        ->where($wherep)
                        ->where('b.tuanprice', 'neq', 0)
                        ->join('ljk_baojia_skuprice b', 'a.id=b.bjid', 'LEFT')
                        ->join('ljk_goods c', 'a.goods_id=c.goods_id', 'LEFT')
                        ->order('displayorder desc')
                        ->group('a.id')
                        ->limit($offset, $limit)
                        ->select();

                    $defaultBJ = array_merge($defaultBJ1, $defaultB2);
                    $total = count($defaultBJ);
                }
                $rankid = 0;
                $r = Db::name('user_member')->field('rankid')->find($uid);
                if (!empty($r)) {
                    $rankid = $r['rankid'] ? $r['rankid'] : 0;
                }
                if (!empty($defaultBJ)) {
                    //对数据进行加工，有等级报价的修改为等级报价，没有的不更改
                    foreach ($defaultBJ as $k => $v) {
                        //通过等级去获取商品价格,促销信息等内容（没有设置这个等级对应价格那么就显示默认的价格
                        $v['bj_type'] = 1;//这个是默认报价类型
                        //附加箱柜
                        $gunit = Db::name('unit_group')->field('id,uname,title')->find($v['unitgid']);
                        $v['gunit'] = $gunit['title'];
                        if ($rankid) {
                            $w['gid'] = $this->gid;
                            $w['rank_id'] = $rankid;
                            $w['goods_id'] = $v['goods_id'];
                            $w['tuanprice'] = ['neq', 0];

                            $rankbj = Db::name('baojia_rank')->where($w)->find();
                            $baojia_sku = Db::name('baojia_sku')->field('id')->where(array('baojia_id' => $v['id'], 'gid' => $this->gid))->find();
                            $skuprice = Db::name('baojia_skuprice')->field('unitid,tuanprice,retailprice')->where(array('gid' => $this->gid, 'rankid' => $rankid, 'skuid' => $baojia_sku['id']))->find();
                            $unit = Db::name('unit')->field('uname')->where(array('id' => $skuprice['unitid'], 'gid' => $this->gid))->find();
                            if (!empty($rankbj)) {
                                $v['tuanprice'] = $rankbj['tuanprice'];
                                $v['retailprice'] = $rankbj['retailprice'];
                                $v['unit'] = $rankbj['unit'];
                                $v['unitid'] = $rankbj['unitid'];
                            } else if (!empty($baojia_sku)) {
                                $v['tuanprice'] = $skuprice['tuanprice'];
                                $v['retailprice'] = $skuprice['retailprice'];
                                $v['unit'] = $unit['uname'];
                                $v['unitid'] = $skuprice['unitid'];
                            }
                        }
                        if (!!$v['csdate']) {
                            $v['csdate'] = date('Y-m-d H:i:s', $v['csdate']);
                        }
                        if (!!$v['cedate']) {
                            if ($v['cedate'] < time()) {
                                $v['cuxiao'] = '';
                            }
                            $v['cedate'] = date('Y-m-d H:i:s', $v['cedate']);
                        }
                        if (isset($v['goods_thumb'])) {
                            $v['goods_img'] = mkgoodsimgurl(['url' => $v['goods_thumb']]);
                        } else {
                            $v['goods_img'] = mkgoodsimgurl(['url' => $v['goods_img']]);
                        }
                        $BJ[] = $v;
                    }
                    return json(['code' => 1, 'p' => $p, 'total' => $total, 'data' => $BJ]);
                } else {
                    return json(['code' => 0, 'p' => $p, 'total' => $total, 'data' => []]);
                }
            } else {
                return ['code' => 0, 'msg' => '该客户已被禁用或删除，无法为该客户下单，如果一定要下单，请重新添加该客户资料或联系后台管理员！'];
            }
        }
    }
    public function bjInfo()
    {
        if (request()->isAjax()) {
            $var = request()->post();
            if (isset($var['type']) && !empty($var['type']) && in_array($var['type'], ['1', '2'])) {
                $type = $var['type'];
            } else {
                $type = '1';
            }
            if (!isset($var['id']) || empty($var['id'])) {
                return json(['code' => 0, 'msg' => '非法操作！']);
            }
            $uid = session('downorderuserid');
            $rankid = Db::name('user_member')->where('uid', $uid)->value('rankid');
            $id = $var['id'];
            //根据type值来获取对应的报价信息
            if ($type === '1') {
                $bj = Db::table('ljk_baojia')
                    ->alias('b')
                    ->field('
                        b.id,
                        b.goods_id,
                        b.gid,
                        b.yd_score,
                        b.categoryid,
                        b.retailprice,
                        b.tuanprice,
                        b.cuxiao,
                        b.csdate,
                        b.cedate,
                        b.unit bjunit,
                        b.unitid,
                        b.unitgid,
                        g.goods_name,
                        g.goods_desc,
                        g.goods_sn,
                        g.keywords,
                        g.goods_img
                    ')
                    ->join('ljk_goods g', 'b.goods_id=g.goods_id', 'LEFT')
                    ->where('b.id', $id)
                    ->find();
            }
            if ($type === '2') {
                $bj = Db::table('ljk_baojia_rank')
                    ->alias('b')
                    ->field('
                        b.id,
                        b.rank_id,
                        b.goods_id,
                        b.gid,
                        b.categoryid,
                        b.retailprice,
                        b.tuanprice,
                        b.cuxiao,
                        b.csdate,
                        b.cedate,
                        b.unit bjunit,
                        b.unitid,
                        b.unitgid,
                        g.goods_name,
                        g.goods_desc,
                        g.goods_sn,
                        g.keywords,
                        g.goods_img
                    ')
                    ->join('ljk_goods g', 'b.goods_id=g.goods_id', 'LEFT')
                    ->where('b.id', $id)
                    ->find();
                if (!empty($bj)) {
                    //去附加积分信息
                    $whereB['gid'] = $this->gid;
                    $whereB['goods_id'] = $bj['goods_id'];
                    $jf = Db::table('ljk_baojia')->field('yd_score')->where($whereB)->find();
                    $bj['yd_score'] = $jf['yd_score'];
                }
            }
            if (!empty($bj)) {
                //附加箱规信息
                $gunit = Db::name('unit_group')
                    ->field('id,uname,title')
                    ->find($bj['unitgid']);
                $bj['gunit'] = $gunit['title'];
                //附加销售类型信息
                $selltype = Db::name('order_goodstype')->field('id,title,type')->where('status', 1)->select();
                $bj['selltype'] = $selltype;
                //附加oid
                $bj['oid'] = session('oid');
                if (isset($bj['goods_thumb'])) {
                    $bj['goods_img'] = mkgoodsimgurl(['url' => $bj['goods_thumb']]);
                } else {
                    $bj['goods_img'] = mkgoodsimgurl(['url' => $bj['goods_img']]);
                }
                $sku = [];
                if ($bj['id']) {
                    $sku = Db::name('baojia_sku')->field('id,storage_num,attrs_value')->where(array('baojia_id' => $bj['id'], 'gid' => $this->gid))->select();
                    if ($sku) {
                        $unitids = [];
                        foreach ($sku as $k => $v) {
                            $unitlist = [];
                            $unit = [];
                            $price = Db::name('baojia_skuprice')
                                ->field('id,skuid,tuanprice,unitid')
                                ->where(array('skuid' => $v['id'], 'gid' => $this->gid, 'rankid' => $rankid))
                                ->order('unitid asc')
                                ->select();
                            $units = [];
                            foreach ($price as $key => $val) {
                                $unit = Db::name('unit')
                                    ->field('id,uname,coefficient,status,title,unitgid')
                                    ->where(array('gid' => $this->gid, 'id' => $val['unitid']))
                                    ->find();
                                array_push($units, $unit);
                            }
                            $sku[$k]['unitlist'] = $units;
                            $sku[$k]['price'] = $price;
                        }
                        $bj['unit'] = [];
                    } else {
                        $whereu['status'] = 1;
                        $whereu['unitgid'] = $bj['unitgid'];
                        $whereu['gid'] = $this->gid;
                        $bj['unit'] = Db::name('unit')
                            ->field('coefficient,id,status,title,uname,unitgid')
                            ->where($whereu)->order('coefficient asc')
                            ->select();
                    }
                }
                $bj['sku'] = $sku;
                return json(['code' => 1, 'data' => $bj]);
            } else {
                return json(['code' => 1, 'msg' => '报价商品已被后台管理员删除！']);
            }
        }
    }
    public function addordergoods()
    {
        if (request()->isAjax() && (request()->post('action') === 'addordergoods')) {
            $var = request()->post();
            $uid = session('downorderuserid') ? session('downorderuserid') : $var['uid'];
            //首先需要去更新order表
            if (isset($var['oid']) && !empty($var['oid'])) {
                session('oid', $var['oid']);//更新session oid
            }
            $oid = session('oid') ? session('oid') : $var['oid'];
            //更新前需要判断这个订单是否已经结束了
            $whereO['trade'] = 0;
            $whereO['oid'] = $var['oid'];
            $order = Db::name('order')->field('oid,number,uid,car_sale,total,ddid,confirm,status,pay,psid,trade,dispatch')->where($whereO)->find();
            if (empty($order)) {
                return json(['code' => 1, 'msg' => '该订单已结束，无法进行该操作！']);
            }
            //当总金额不为0的时候才去更新ljk_order
            $orderM = new OrderModel;
            $orderM->startTrans();
            if ($var['num'] * $var['price'] != 0) {
                $order['total'] = $order['total'] + $var['num'] * $var['price'];
                $order['adate'] = time();
                if (!$orderM->isUpdate(true)->save($order, ['oid' => $var['oid']])) {
                    return ['code' => 1, 'msg' => '新增订单商品失败！'];
                }
            }
            //更新商品列表ljk_order_goods 当goodid,oid,unitid,type,price都不相同的情况下就新增 不然就更新
            $orderGoods = new OrderGoods;
            $whereOG['oid'] = $var['oid'];
            $whereOG['goodid'] = $var['goodid'];
            if (isset($var['skuid'])) {
                $whereOG['sku_id'] = $var['skuid'];
                /*修改库存*/
                $data['sku_id'] = $var['skuid'];
                $Unitinfo = Db::name('unit')->field('unitgid,uname,title,coefficient')->where('id', $var['unitid'])->where('gid', $this->gid)->find();
                //添加之前修改库存 如果是箱 得到最小单位 减去数量  修改库存
                $sto = Db::name('baojia_sku')->field('storage_num')->where(array('gid' => $this->gid, 'id' => $var['skuid']))->find();
                $number = $sto['storage_num'] - ($Unitinfo['coefficient'] * $var['num']);
                Db::name('baojia_sku')->where(array('gid' => $this->gid, 'id' => $var['skuid']))->setField('storage_num', $number);
                /*修改库存*/
            } else {
                unset($var['skuid']);
            }
            $whereOG['unitid'] = $var['unitid'];
            $whereOG['type'] = $var['type'];
            $whereOG['price'] = $var['price'];
            $ogo = $orderGoods->field('*')->where($whereOG)->find();
            if (!!$ogo) {
                $og = $ogo->getData();
            } else {
                $og = [];
            }
            $orderGoods->startTrans();
            $data['oid'] = $var['oid'];
            $data['uid'] = $uid;
            $data['laiyuan'] = $var['laiyuan'];
            $data['gid'] = $this->gid;
            $data['goodid'] = $var['goodid'];
            $data['name'] = $var['name'];
            $data['desc'] = $var['desc'];
            $data['price'] = $var['price'];
            $data['amount'] = $var['price'] * $var['num'];
            if (!isset($var['unit'])) {
                unset($var['unit']);
            } else {
                $data['unit'] = $var['unit'];
            }
            if (!isset($var['attrs_value'])) {
                unset($var['attrs_value']);
            } else {
                $data['avs'] = $var['attrs_value'];
            }
            $data['unitg'] = $var['unitg'];
            $data['num'] = $var['num'];
            $data['type'] = $var['type'];
            $data['adate'] = time();
            $data['unitid'] = $var['unitid'];
            $data['unitgid'] = $var['unitgid'];
            if (!empty($og)) {
                //需要更新 amount,num,desc
                $data['amount'] = $data['amount'] + $og['amount'];
                $data['num'] = $data['num'] + $og['num'];
                $data['adate'] = time();
                $data['desc'] = $data['desc'] . '<br />' . $og['desc'];
                if (!$orderGoods->isUpdate(true)->save($data, ['id' => $og['id']])) {
                    $orderM->rollback();
                    return ['code' => 1, 'msg' => '新增订单商品失败！'];
                }
            } else {
                if (!$orderGoods->isUpdate(false)->save($data)) {
                    $orderM->rollback();
                    return ['code' => 1, 'msg' => '新增订单商品失败！'];
                }
            }
            //向新增order_post内容
            $ogs = config('order_goods_status');
            if (!isset($var['unit'])) {
                unset($var['unit']);
                $content = '【订单调整】' . '新增订单商品：' . $var['name'] . '，数量：' . $var['num'] . $var['unit'] . '，单价：' . $var['price'] . '，销售类型：' . $ogs[$var['type']];
            } else {
                $content = '【订单调整】' . '新增订单商品：' . $var['name'] . '，数量：' . $var['num'] . $var['unit'] . '，单价：' . $var['price'] . '，销售类型：' . $ogs[$var['type']];
            }
            //新增记录 扣掉金额
            $whereP['gid'] = ['EQ', $this->gid];
            $whereP['guid'] = ['EQ', $this->guid];
            $whereP['uid'] = ['EQ', $uid];
            $whereP['status'] = ['EQ', 1];
            $macard_um = Db::name('mcard_um')->where($whereP)->find();
            $useable = 0;
            $disable = 0;
            $total_money = $var['price'];
            if ($macard_um != null) {
                //有会员卡支付
                //先扣除 会员卡账户
                $useable = $macard_um['useable'] - $total_money;
                if ($useable > 0) {
                    //余额足够减
                    $notpay = 0;
                    $paynow = $haspay = $total_money;
                } else {
                    $notpay = abs($useable);
                    $paynow = $haspay = $macard_um['useable'];
                    $useable = 0;
                }
                //扣除后 会员卡余额
                Db::name('mcard_um')->where($whereP)->setField('useable', $useable);
            } else {
                //用户没有会员卡 支付
                $notpay = $total_money;    //未付金额
                $paynow = $haspay = 0;    //共计支付金额
            }
            //有无会员卡 消费写入用户详细支付记录（首先扣除会员卡余额）  会员卡扣除不足的  其他支付方式支付
            if ($order) {
                $wherem['gid'] = $this->gid;
                $wherem['uid'] = $uid;
                $gm = Db::name('group_member')->field('uid')->where($wherem)->find();
                $um = Db::name('user_member')->field('uid,guid')->where($wherem)->find();
                $post = [];
                $post['gid'] = $this->gid;
                $post['uid'] = $uid;
                if ($gm) {
                    //员工
                    $post['auid'] = $gm['uid'];
                    $post['atype'] = 1;
                } else {
                    $post['auid'] = $um['uid'];
                    $post['atype'] = 0;
                }
                $post['ctime'] = time();
                $post['ua'] = $_SERVER['HTTP_USER_AGENT'];
                $post['ip'] = get_client_ip();
                $post['useable'] = $useable;
                $post['disable'] = $disable;
                if ($macard_um != null) {
                    if ($macard_um['useable'] > $total_money) {
                        $unic = +$total_money;
                    } else if ($macard_um['useable'] < $total_money) {
                        if ($macard_um['useable'] == 0) {
                            $unic = 0;
                        } else {
                            $unic = -$macard_um['useable'];
                        }
                    } else {
                        $unic = -$macard_um['useable'];
                    }
                } else {
                    $unic = 0;
                }
                $post['uinc'] = $unic;
                $post['dinc'] = 0;
                $post['type'] = 6;
                $post['desc'] = '【移动端消费扣款】';
                $post['sn'] = date('YmdHis', time()) . mt_rand(100000, 999999);
                $post['oid'] = $order['oid'];
                $post['osn'] = $order['number'];
                Db::name('mcard_umpost')->insert($post);

                //同时向 order_pay写入支付记录
                $pay = [];
                $pay['gid'] = $this->gid;
                $pay['type'] = 0;
                if ($gm) {
                    //员工
                    $pay['guid'] = $gm['uid'];
                } else {
                    $pay['guid'] = $um['uid'];
                }
                $pay['uid'] = $uid;
                $pay['total'] = $total_money;     //当前订单总值
                $pay['notpay'] = $notpay;    //未付金额
                $pay['haspay'] = $haspay;    //共计已付金额
                $pay['pay'] = $paynow;       //本次支付金额
                $pay['paytime'] = time();
                $pay['ppaytime'] = 0;
                $pay['sn'] = $order['number'];
                $pay['paytype'] = 4;
                $pay['oid'] = $order['oid'];//原订单编号
                Db::name('order_pay')->insert($pay);
                $whereP['oid'] = $oid;
                $whereP['number'] = $order['number'];
            }
            //获取该订单的状态信息
            $oidPost = [
                'content' => $content,
                'oid' => session('oid') ? session('oid') : $var['oid'],
                'guid' => session('guid') ? session('guid') : $this->guid,
                'uid' => $uid,
                'gid' => session('gid') ? session('oid') : $this->gid,
                'thumb' => '',
                'confirm' => $order['confirm'],
                'pay' => $order['pay'],
                'status' => $order['status'],
                'trade' => $order['trade'],
                'psid' => $order['psid'],
                'dispatch' => $order['dispatch'],
                'adate' => time(),
            ];
            //这一步对数据进行验证,Validate
            $OrderPost = new OrderPost;
            $OrderPost->startTrans();
            if ($OrderPost->addPost($oidPost) !== false) {
                $orderGoods->commit();
                $orderM->commit();
                $OrderPost->commit();
                //对订单商品进行出库操作
                //组织商品数据
                $input['gid'] = $this->gid;
                $input['apply_uid'] = $this->guid;
                $input['apply_type'] = 1;
                $input['opostid'] = $OrderPost->id;
                $input['osn'] = $order['number'];
                $input['goods_id'] = $var['goodid'];
                $input['goods_name'] = $var['name'];
                $input['num'] = $var['num'];
                $input['unitid'] = $var['unitid'];
                $input['unitgid'] = $var['unitgid'];
                $input['oid'] = $var['oid'];
                $input['minunitid'] = $var['unitid'];
                $input['comment'] = '订单商品出库:添加订单商品！';
                //车销出库
                $input['type'] = 0;
                $input['typeval'] = 3;
                $input['target_type'] = 2;
                $input['target_uid'] = $order['uid'];
                $input['from_type'] = 1;
                $input['from_uid'] = $this->guid;
                $input['move_in'] = 0;
                $input['move_out'] = 1;
                //获取projectId
//                    $this->ogOutSg($input);
                if (!!$order['ddid']) {
                    //重新计算分成信息
                    $separate = controller('fx/Separate', 'controller');
                    $postData = ['oid' => $var['oid']];
                    $sign = new Sign();
                    $postData = array_merge($postData, $sign->mkSign($postData));
                    $separate->insideIndex($postData);
                    return json(['code' => 0, 'msg' => '新增订单商品成功']);
                } else {
                    return json(['code' => 0, 'msg' => '新增订单商品成功']);
                }
            } else {
                $orderGoods->rollback();
                $orderM->rollback();
                $OrderPost->rollback();
                return json(['code' => 0, 'msg' => '新增订单商品失败']);
            }
        }
    }
    /**
     * 下订单商品列表
     */
    public function addordergoodslist()
    {
        if (request()->isPost()) {
            $param = input('param.');
            if (isset($param['type']) && $param['type'] == 1) {
                return $this->aorog();
            }
            $uid = input('param.id');
            $t = input('param.t');
            $p = intval(trim(input('p')));
            $cid = input('param.cid');
            $where = [];
            $wherep = [];
            if (!$uid) $uid = session('downorderuserid');
            else session('downorderuserid', $uid);
            if ($cid) {
                //判定是否有子id
                $ccid = Db::table('ljk_category')
                    ->where('pid', $cid)
                    ->where('gid', $this->gid)
                    ->column('id');
                if (!empty($ccid)) {
                    array_push($ccid, $cid);
                    $ccids = implode(',', $ccid);
                    $whereC['categoryid'] = ['IN', $ccids];
                } else {
                    $whereC['categoryid'] = $cid;
                }
                $bid = Db::table('ljk_baojia_cat')
                    ->where($whereC)
                    ->where('gid', $this->gid)
                    ->column('bid');
                if (!empty($bid)) {
                    $where['id'] = ['IN', implode(',', $bid)];
                    $wherep['a.id'] = ['IN', implode(',', $bid)];
                } else {
                    //直接退出了
                    $return['code'] = 0;
                    $return['p'] = $p;
                    $return['msg'] = '没有更多数据！';
                    $return['data'] = null;
                    $return['total'] = 0;
                    return json($return);
                }
            }
            if (!empty($t)) {
                $whereOrG = [];
                $whereOrG['goods_sn'] = ['like', '%' . $t . '%'];
                $whereOrG['goods_name'] = ['like', '%' . $t . '%'];
                //获取所有goods_id
                $goods_ids1 = Db::table('ljk_goods')->whereOr($whereOrG)->column('goods_id');
                if (!empty($goods_ids1)) {
                    //优化$goods_ids
                    //先查询出这个gid下报价商品的所有gid;
                    $bj_goods_ids = Db::table('ljk_baojia')->where('gid', $this->gid)->column('goods_id');
                    $goods_ids = [];
                    foreach ($bj_goods_ids as $k => $v) {
                        if (in_array($v, $goods_ids1)) {
                            $goods_ids[] = $v;
                        }
                    }
                    if (!empty($goods_ids)) {
                        $where['goods_id'] = ['IN', implode(',', $goods_ids)];
                        $wherep['a.goods_id'] = ['IN', implode(',', $goods_ids)];
                    } else {
                        //直接退出了
                        $return['code'] = 0;
                        $return['p'] = $p;
                        $return['msg'] = '没有更多数据！';
                        $return['data'] = null;
                        $return['total'] = 0;
                        return json($return);
                    }
                } else {
                    //直接退出了
                    $return['code'] = 0;
                    $return['msg'] = '没有更多数据！';
                    $return['data'] = null;
                    $return['total'] = 0;
                    $return['p'] = $p;
                    return json($return);
                }
            }
            //通过$cid 去获取报价商品是这个分类的报价id
            $limit = intval(trim(input('limit')));
            $offset = ($p - 1) * $limit;

            $rankid = Db::name('user_member')->where('uid', $uid)->value('rankid');
            $where['gid'] = $this->gid;
            $wherep['a.gid'] = $this->gid;
//            $goodslist = Db::name('baojia')->where($where)
//                ->limit($offset, $limit)->order('displayorder desc')
//                ->select();
            $goodslist1 = Db::name('baojia')
                ->where($where)
                ->where('tuanprice', 'neq', 0)
                ->order('displayorder desc')
                ->limit($offset, $limit)
                ->select();

            $goodslist2 = Db::name('baojia a')->field('a.*')
                ->where($wherep)
                ->where('b.tuanprice', 'neq', 0)
                ->join('ljk_baojia_skuprice b', 'a.id=b.bjid', 'LEFT')
                ->order('displayorder desc')
                ->group('a.id')
                ->limit($offset, $limit)
                ->select();

            $goodslist = array_merge($goodslist1, $goodslist2);

            $total = count($goodslist);
            $gl = [];
            foreach ($goodslist as $key => $goods) {
                if (isset($goods['id'])) {
                    if ($rankid) {
                        $w['gid'] = $this->gid;
                        $w['rank_id'] = $rankid;
                        $w['goods_id'] = $goods['goods_id'];
                        $w['tuanprice'] = ['neq', 0];

                        $rankbj = Db::name('baojia_rank')->where($w)->find();
                        $baojia_sku = Db::name('baojia_sku')->field('id')->where(array('baojia_id' => $goods['id'], 'gid' => $this->gid))->find();
                        $skuprice = Db::name('baojia_skuprice')->field('unitid,tuanprice,retailprice')->where(array('gid' => $this->gid, 'rankid' => $rankid, 'skuid' => $baojia_sku['id']))->find();
                        $unit = Db::name('unit')->field('uname')->where(array('id' => $skuprice['unitid'], 'gid' => $this->gid))->find();
                        if (!empty($rankbj)) {
                            $goods['tuanprice'] = $rankbj['tuanprice'];
                            $goods['retailprice'] = $rankbj['retailprice'];
                            $goods['unit'] = $rankbj['unit'];
                            $goods['unitid'] = $rankbj['unitid'];
                        } else if (!empty($baojia_sku)) {
                            $goods['tuanprice'] = $skuprice['tuanprice'];
                            $goods['retailprice'] = $skuprice['retailprice'];
                            $goods['unit'] = $unit['uname'];
                            $goods['unitid'] = $skuprice['unitid'];
                        }
                    }
                    $goods_info = Db::name('goods')->where('goods_id', $goods['goods_id'])->find();
                    if ($goods_info['goods_thumb']) {
                        $goods_info['goods_thumb'] = mkgoodsimgurl(['url' => $goods_info['goods_thumb']]);
                    }
                    if ($goods_info['goods_img']) {
                        $goods_info['goods_img'] = mkgoodsimgurl(['url' => $goods_info['goods_img']]);
                    }
                    $goods['info'] = $goods_info;
                    unset($w);
                    if (!!$goods['csdate']) {
                        $goods['csdate'] = date('Y-m-d H:i:s', $goods['csdate']);
                    }
                    if (!!$goods['cedate']) {
                        if ($goods['cedate'] < time()) {
                            $goods['cuxiao'] = '';
                        }
                        $goods['cedate'] = date('Y-m-d H:i:s', $goods['cedate']);
                    }
                }
                $gl[] = $goods;
            }
            $return = $gl;
            if (count($goodslist) == 0) {
                $return['code'] = 0;
                $return['msg'] = '没有数据';
            } else {
                $return['code'] = 1;
                $return['msg'] = '没有数据';
            }

            $return['total'] = $total;//当前数据
            $return['data'] = $gl;
            $return['p'] = $p;
            $return['msg'] = '数据加载成功！';
            return json($return);
        }
    }
    /**
     * 添加到购物车订单商品列表 商品详情
     */
    public function addgoodslistinfo()
    {
        $goods_id = input('param.id');
        if (!$goods_id) $goods_id = session('addgoods_id');
        session('addgoods_id', $goods_id);
        $where = [];
        $where['gid'] = $this->gid;
        $where['goods_id'] = $goods_id;
        $uid = session('downorderuserid') ? session('downorderuserid') : input('uid');
        $rankid = Db::name('user_member')->where('uid', $uid)->value('rankid');
        $goods_info = [];
        if ($rankid) {
            $goods_info = Db::name('baojia')->field('goodsName,goodsThumb,goodsImg,seller_note,sell_status,categoryid,gid,adate,csdate,cedate,unitgid,unitid,goods_id,id,sn,tuanprice')->where($where)->find();
        }
        if (!!$goods_info['csdate']) {
            $goods_info['csdate'] = date('Y-m-d H:i:s', $goods_info['csdate']);
        }
        if (!!$goods_info['cedate']) {
            if ($goods_info['cedate'] < time()) {
                $goods_info['cuxiao'] = '';
            }
            $goods_info['cedate'] = date('Y-m-d H:i:s', $goods_info['cedate']);
        }
        $sku = [];
        if ($goods_info['id']) {
            $sku = Db::name('baojia_sku')->field('id,storage_num,attrs_value')->where(array('baojia_id' => $goods_info['id'], 'gid' => $this->gid))->select();
            if ($sku) {
                foreach ($sku as $k => $v) {
                    $price = Db::name('baojia_skuprice')
                        ->field('id,skuid,tuanprice,unitid')
                        ->where(array('skuid' => $v['id'], 'gid' => $this->gid, 'rankid' => $rankid))
                        ->order('unitid asc')
                        ->select();
                    $units = [];
                    foreach ($price as $key => $val) {
                        $unit = Db::name('unit')
                            ->field('id,uname,coefficient,status,title,unitgid')
                            ->where(array('gid' => $this->gid, 'id' => $val['unitid']))
                            ->find();
                        array_push($units, $unit);
                    }
                    $sku[$k]['unitlist'] = $units;
                    $sku[$k]['price'] = $price;
                }
                $goods_info['unitlist'] = [];
            } else {
                $whereu['status'] = 1;
                $whereu['unitgid'] = $goods_info['unitgid'];
                $whereu['gid'] = $this->gid;
                $goods_info['unitlist'] = Db::name('unit')
                    ->field('coefficient,id,status,title,uname,unitgid')
                    ->where($whereu)->order('coefficient asc')
                    ->select();
            }
        }
        $coefficient = Db::name('unit')->where('id', $goods_info['unitid'])->value('coefficient');
        if ($coefficient) $goods_info['unitprice'] = $goods_info['tuanprice'] / $coefficient;
        $order_goods_status = config('order_goods_status');
        $goods_info['gunit'] = Db::name('unit_group')->where('id', $goods_info['unitgid'])->value('title');
        $goods = Db::name('goods')->where('goods_id', $goods_info['goods_id'])->find();
        if ($goods['goods_img']) {
            $goods['goods_img'] = mkgoodsimgurl(['url' => $goods['goods_img']]);
        }
        if ($goods['goods_thumb']) {
            $goods['goods_thumb'] = mkgoodsimgurl(['url' => $goods['goods_thumb']]);
        }
        $goods_info['uid'] = $uid;
        $return['total'] = count($goods_info);  //总数据
        $return['data'] = $goods_info;
        $return['data']['order_goods_status'] = $order_goods_status;
        $return['data']['info'] = $goods;
        $return['data']['baojia'] = $sku;
        $return['code'] = 1;
        $return['msg'] = '数据加载成功！';
        if (!$goods_info) {
            $return['code'] = 0;
            $return['msg'] = '没有数据！';
        }
        return json($return);
    }
    public function addcart()
    {
        $uid = input('param.uid');
        if (!$uid) $uid = session('downorderuserid');
        if ($uid) {
            $param = input('param.');
            if (!$param['num']) {
                $param['num'] = 1;
            }
            $rankid = Db::name('user_member')->where('uid', $uid)->value('rankid');
            $w['gid'] = $this->gid;
            $w['rank_id'] = $rankid;
            $w['goods_id'] = $param['goods_id'];
            $goodsBaojiaInfo = Db::name('baojia_rank')->where($w)->find();
            $goodsBaojiaskuInfo = '';
            if (!$goodsBaojiaInfo) {
                $where['gid'] = $this->gid;
                $where['goods_id'] = $param['goods_id'];
                $goodsBaojiaskuInfo = Db::name('baojia_sku')->where($where)->find();
                $goodsBaojiaInfo = Db::name('baojia')->where($where)->find();
            }
            $goodsInfo = Db::name('goods')->where('goods_id', $goodsBaojiaInfo['goods_id'])->find();
            if (isset($param['skuid'])) {
                $carts1 = Db::name('cart')->field('skuid')->where(array('skuid' => $param['skuid']))->find();
                if ($param['skuid'] == $carts1['skuid']) {
                    //验证库存
                    $skus = Db::name('baojia_sku')->field('storage_num')->where(array('id' => $param['skuid'], 'gid' => $this->gid))->find();
                    $goods_number = Db::name('cart')->field('goods_number,unitid')->where(array('gid' => $this->gid, 'goods_id' => $param['goods_id'], 'user_id' => $param['uid'], 'skuid' => $param['skuid']))->select();
                    $nums1 = 0;
                    $nums2 = 0;
                    foreach ($goods_number as $k => $v) {
                        $units = Db::name('unit')->field('coefficient')->where(array('gid' => $this->gid, 'id' => $v['unitid']))->find();
                        $total = intval($v['goods_number'] * $units['coefficient']);
                        $nums1 += $total;
                        $nums2 = intval($param['num'] * $units['coefficient']);
                    }
                    $nums3 = $nums2 + $nums1;
                    if ($nums3 > $skus['storage_num']) {
                        $return['code'] = 2;
                        $return['data'] = 3;
                        $return['uid'] = $uid;
                        $return['msg'] = '库存不足，无法加入购物车！';
                        return json($return);
                    }
                }
            }
            $wherecart['user_id'] = $uid;
            $wherecart['gid'] = $this->gid;
            $wherecart['unitid'] = $param['unitid'];
            if (isset($param['priceid'])) {
                $wherecart['priceid'] = $param['priceid'];
            } else {
                unset($param['priceid']);
            }
            if (isset($param['skuid'])) {
                $wherecart['skuid'] = $param['skuid'];
            } else {
                unset($param['skuid']);
            }
            $wherecart['goods_id'] = $param['goods_id'];
            $wherecart['is_real'] = 2;
            $wherecart['type'] = $param['type'];
            $carts = Db::name('cart')->where($wherecart)->find();
            if ($carts) {
                $goods_number = $carts['goods_number'] + $param['num'];
                $c = Db::name('cart')->where('id', $carts['id'])->setField('goods_number', $goods_number);
            } else {
                if (isset($param['skuid'])) {
                    $cartinfo['goods_id'] = $param['goods_id'];
                    $cartinfo['skuid'] = $param['skuid'];
                } else {
                    unset($param['skuid']);
                    $cartinfo['goods_id'] = $goodsBaojiaInfo['goods_id'] ? $goodsBaojiaInfo['goods_id'] : $goodsBaojiaskuInfo['goods_id'];
                }
                $bjid = Db::name('baojia')->field('id')->where(array('gid' => $this->gid, 'goods_id' => $cartinfo['goods_id']))->find();
                $cartinfo['user_id'] = $uid;
                $cartinfo['bjid'] = $bjid['id'];
                $cartinfo['session_id'] = session_id();
                $cartinfo['goods_sn'] = $goodsInfo['goods_sn'];
                $cartinfo['gid'] = $this->gid;
                $cartinfo['goods_name'] = $goodsInfo['goods_name'];
                $cartinfo['market_price'] = $goodsBaojiaInfo['retailprice'] ? $goodsBaojiaInfo['retailprice'] : $goodsInfo['retailprice'];
                $cartinfo['goods_price'] = $param['price'];
                $cartinfo['goods_number'] = $param['num'];
                if (isset($param['unitid'])) {
                    $cartinfo['unitid'] = $param['unitid'];
                } else {
                    unset($param['unitid']);
                }
                $cartinfo['type'] = $param['type'];
                $Unitinfo = Db::name('unit')->field('unitgid,uname,title,coefficient')->where('id', $param['unitid'])->where('gid', $this->gid)->find();
                $cartinfo['unitgid'] = $Unitinfo['unitgid'];
                $cartinfo['unit'] = $Unitinfo['uname'];
                $cartinfo['unitg'] = $Unitinfo['title'];
                if (isset($param['attrs_value'])) {
                    $cartinfo['avs'] = $param['attrs_value'];
                } else {
                    unset($param['attrs_value']);
                }
                $cartinfo['is_real'] = 2;

                if (isset($param['priceid'])) {
                    $cartinfo['priceid'] = trim($param['priceid']);
                } else {
                    unset($param['priceid']);
                }
                $c = Db::name('cart')->insert($cartinfo);
            }
            $cnum = $this->getCnum();
            if ($c) {
                $return['code'] = 1;
                $return['data'] = $cnum;
                $return['uid'] = $uid;
                $return['msg'] = '加入购物车成功！';
            } else {
                $return['code'] = 2;
                $return['data'] = $cnum;
                $return['uid'] = $uid;
                $return['msg'] = '加入购物车失败！';
            }
        } else {
            $return['code'] = 2;
            $return['msg'] = '请选择要下单的客户！';
        }
        return json($return);
    }
    public function updatecartitem()
    {
        if (request()->isPost()) {
            $uid = session('downorderuserid');
            if ($uid) {
                $id = intval(input('param.sku'));
                $num = input('param.num');
                $map['user_id'] = $uid;
                $map['id'] = $id;
                Db::name('cart')->where($map)->setField('goods_number', $num);
                $cartlist = Db::name('cart')->where('user_id', $uid)->select();
                $z['TotalNum'] = 0;
                $z['TotalPrice'] = 0;
                foreach ($cartlist as $key => $cart) {
//                    $z['TotalNum'] = $z['TotalNum'] + $cart['goods_number'];
                    $z['TotalNum'] = count($cartlist);
                    $z['TotalPrice'] = $z['TotalPrice'] + $cart['goods_price'] * $cart['goods_number'];
                }
                $return['data'] = $z;
                $return['code'] = 1;
                $return['msg'] = '更新成功！';
                return json($return);
            } else {
                $return['code'] = 0;
                $return['msg'] = '更新失败！';
                return json($return);
            }
        }
    }
    /**
     *购物车信息
     */
    public function cartinfo()
    {
        if (request()->isPost()) {
            $param = input('param.');
            $uid = input('param.id');
            $totalPrice = 0;
            $num = 0;
            if (!!$uid) {
                session('downorderuserid', $uid);
            } else {
                $uid = session('downorderuserid');
            }
            $cartnum = Db::name('cart')->where('user_id', $uid)->count();
            $userinfo = Db::name('user_member')->where('uid', $uid)->find();
            $cartlist = '';
            if ($cartnum) {
                $cartlist2 = Db::name('cart')->where('user_id', $uid)->select();
                $num = count($cartlist2);
                $sku = [];
                foreach ($cartlist2 as $key => $goods) {
                    $goods_info = Db::name('goods')->where('goods_id', $goods['goods_id'])->find();
                    if ($goods_info['goods_img']) {
                        $goods_info['goods_img'] = mkgoodsimgurl(['url' => $goods_info['goods_img']]);
                    }
                    if ($goods_info['goods_thumb']) {
                        $goods_info['goods_thumb'] = mkgoodsimgurl(['url' => $goods_info['goods_thumb']]);
                    }
                    $goods['info'] = $goods_info;
//                    $num = $num + $goods['goods_number'];
                    $totalPrice = $totalPrice + $goods['goods_price'] * $goods['goods_number'];
                    if ($goods['skuid'] !== 0) {
                        $sku[$goods['skuid']] = Db::name('baojia_sku')->field('storage_num')->where(array('id' => $goods['skuid'], 'gid' => $this->gid))->select();
                    } else {
                        $sku[$goods['skuid']] = [];
                    }
                    if ($goods['unitid'] !== 0) {
                        $sku[$goods['unitid']] = Db::name('unit')->field('coefficient')->where(array('id' => $goods['unitid'], 'gid' => $this->gid))->select();
                    } else {
                        $sku[$goods['unitid']] = [];
                    }
                    $goods['baojia_sku'] = $sku[$goods['skuid']];
                    $goods['units'] = $sku[$goods['unitid']];
                    $cartlist[] = $goods;
                }
            }
            $whereP['gid'] = $this->gid;
            $whereP['uid'] = $userinfo['uid'];
            $whereP['delete'] = 0;

            $addressinfo = Db::name('user_address')->where($whereP)
                ->where('isdefault', 'eq', 1)
                ->find();
            $addressinfo2 = Db::name('user_address')->where($whereP)
                ->where('isdefault', 'neq', 1)
                ->find();
            $freight_config = Db::name('freight_config')
                ->where(array('gid' => $this->gid))
                ->find();

            //正则匹配得到地址
            $address = preg_replace('/(.+)((市辖区)|(县))(.+)/', '$1$5', $addressinfo['areaname']);

            $whereMU['gid'] = ['EQ', $this->gid];
            $whereMU['guid'] = ['EQ', $this->guid];
            $whereMU['uid'] = ['EQ', $uid];
            $whereMU['status'] = ['EQ', 1];
            $mcard_um = Db::name('mcard_um')->where($whereMU)->find();
            $return['data']['goodslist'] = $cartlist;
            $return['data']['totalPrice'] = sprintf("%.2f", $totalPrice);
            $return['data']['goodsnum'] = $num;
            $return['data']['user'] = $userinfo;
            $return['data']['address'] = $address;
            $return['data']['useraddress'] = $addressinfo;
            $return['data']['useraddress2'] = $addressinfo2;
            $return['data']['mcard_um'] = $mcard_um;
            $return['data']['cartnum'] = $cartnum;
            $return['data']['freight_config'] = $freight_config;
            $return['code'] = 1;
            $return['msg'] = '数据加载成功！';
            return json($return);
        }
    }
    public function deletecartitem()
    {
        if (request()->isPost()) {
            $uid = session('downorderuserid');
            if ($uid) {
                $id = intval(input('param.id'));
                $map['user_id'] = $uid;
                $map['id'] = $id;
                $carts = Db::name('cart')->where($map)->find();
                if ($carts) {
                    $delid = Db::name('cart')->delete($carts['id']);
                }
                if ($delid) {
                    $cartlist = Db::name('cart')->where('user_id', $uid)->select();
                    $z['TotalNum'] = 0;
                    $z['TotalPrice'] = 0;
                    foreach ($cartlist as $key => $cart) {
                        $z['TotalNum'] = $z['TotalNum'] + $cart['goods_number'];
                        $z['TotalPrice'] = $z['TotalPrice'] + $cart['goods_price'] * $cart['goods_number'];
                        $cartlist[$key] = $cart;
                    }
                    $return['data'] = $z;
                    $return['code'] = 1;
                    $return['msg'] = '删除成功！';
                    return json($return);
                }
            } else {
                $return['code'] = 0;
                $return['msg'] = '删除失败！';
                return json($return);
            }
        }
    }
    public function deleteorderitem()
    {
        if (request()->isPost()) {
            $uid = session('downorderuserid') ? intval(session('downorderuserid')) : intval(input('uid'));
            if ($uid) {
                $id = intval(input('param.id'));
                $oid = intval(input('param.oid'));
                $map['status'] = 1;
                $map['gid'] = $this->gid;
                $map['id'] = $id;
                $ordergoods = Db::name('order_goods')->where($map)->find();
                $delid = 0;
                if ($ordergoods) {
                    //删除之前 返还商品库存
                    if ($ordergoods['sku_id'] != 0) {
                        //有多属性 返还数量  单位*数量=最后返回数量
                        $unit = Db::name('unit')->field('coefficient')->where(array('gid' => $this->gid, 'id' => $ordergoods['unitid']))->find();
                        $sku = Db::name('baojia_sku')->field('storage_num')->where(array('gid' => $this->gid, 'id' => $ordergoods['sku_id']))->find();
                        $storage_num = $sku['storage_num'] + $unit['coefficient'] * $ordergoods['num'];
                        Db::name('baojia_sku')->where(array('gid' => $this->gid, 'id' => $ordergoods['sku_id']))->setField('storage_num', $storage_num);
                    }
                    $content = '删除订单商品';
                    $status = $this->addorderPost($id,'del',$content,'','','','','','');
                    if ($status) {
                        $delid = Db::name('order_goods')
                            ->where(array('id' => $ordergoods['id'], 'gid' => $this->gid))
                            ->delete();
                        //返还库存之后 只能是未付款的订单 可以删除 修改 商品数量 价格  然后修改 order_pay 记录
                        $whereP['gid'] = $this->gid;
                        $whereP['guid'] = $this->guid;
                        $whereP['oid'] = $oid;
                        $pay = [
                            'notpay' => 0,
                            'haspay' => 0,
                            'pay' => 0,
                        ];
                        Db::name('order_pay')->where($whereP)->update($pay);
                    } else {
                        $return['code'] = 0;
                        $return['msg'] = '此订单未找到该商品！';
                        return json($return);
                    }
                }
                if ($delid) {
                    $this->editordertotal($uid,$oid);

                    $whereO['gid']=$this->gid;
                    $whereO['uid']=$uid;
                    $whereO['oid']=$oid;
                    $whereO['status']=1;
                    $orderlist = Db::name('order_goods')->field('num,price,amount')->where($whereO)->select();
                    //删除过后自己修改订单总额
                    $this->editordertotal($uid,$oid);
                    $order = Db::name('order')->field('correct')->where($whereO)->find();
                    $sum = 0;
                    $z['TotalNum'] = count($orderlist);
                    $z['TotalPrice'] = 0;
                    foreach ($orderlist as $key => $val) {
//                        $z['TotalNum'] = $z['TotalNum'] + $val['num'];
                        $sum += $z['TotalPrice'] + $val['price'] * $val['num'];
                    }
                    if ($order['correct'] < 0) {
                        $sum -= abs($order['correct']);
                    } else {
                        $sum += abs($order['correct']);
                    }
                    $z['TotalPrice'] = sprintf("%.2f", $sum);
                    $return['data'] = $z;
                    $return['code'] = 1;
                    $return['msg'] = '删除成功！';
                    return json($return);
                }
            } else {
                $return['code'] = 0;
                $return['msg'] = '删除失败！';
                return json($return);
            }
        }
    }
    public function addorder(){
        $oid='';
        $order_status='';
        $uid=session('downorderuserid')?session('downorderuserid'):intval(input('uid'));
        $authnum='';
        if(request()->isPost()){
            $omember = input('param.');
            $authnum=date('YmdHis',time()).mt_rand(100000, 999999);
            $address_id=isset($omember['address_id']) ? intval($omember['address_id']):0;
            if($uid){
                $whereU['gid']=$this->gid;
                $whereU['uid']=$uid;
                $cartlist=[];
                $userinfo=Db::name('user_member')->where($whereU)->find();
                $ord=[];
                if($userinfo){
                    $z['content'] = $omember['pssj'];
                    $z['num']=0;
                    $z['total']=0;
                    $whereC['gid']=$this->gid;
                    $whereC['user_id']=$uid;
                    $cartlist2=Db::name('cart')
                        ->where($whereC)
                        ->select();
                    foreach ($cartlist2 as $key=>$cart){
                        $goodsinfo=Db::name('goods')
                            ->where('goods_id',$cart['goods_id'])
                            ->find();
                        $cart['goods_img']=$goodsinfo['goods_img'];
                        $z['num']=$z['num']+$cart['goods_number'];
                        $z['total']=$z['total']+$cart['goods_price']*$cart['goods_number'];
                        if ($cart['skuid'] != 0) {
                            $whereUN['gid']=$this->gid;
                            $Unitinfo = Db::name('unit')
                                ->field('unitgid,uname,title,coefficient')
                                ->where('id', $cart['unitid'])
                                ->where($whereUN)->find();
                            //添加之前修改库存 如果是箱 得到最小单位 减去数量  修改库存
                            $sto = Db::name('baojia_sku')
                                ->field('storage_num')
                                ->where($whereUN)
                                ->where('id',$cart['skuid'])
                                ->find();
                            $number = $sto['storage_num'] - ($Unitinfo['coefficient'] * $cart['goods_number']);
                            Db::name('baojia_sku')->where($whereUN)->where(array('id' => $cart['skuid']))->setField('storage_num', $number);
                        }
                        $cartlist[]=$cart;
                    }
                    $ord['uid']=$uid;
                    $ord['ddid']=$userinfo['ddid'];
                    $ord['number']=$authnum;
                    $ord['type']=2;//1电话后台下单 2业务员下单
                    $ord['shopid']=$userinfo['shopid'];
                    $shopinfo=Db::name('oauth_clients')->where('id',$userinfo['shopid'])->find();
                    if($shopinfo['gtype']) $ord['ftype']=$shopinfo['gtype'];
                    $ord['mobile']=$userinfo['mobile'];
                    $ord['psid']=$userinfo['psid'];
                    if($userinfo['ppsid']){
                        $exp_date=Db::name('distributor')->where('gid',$this->gid)->where('psid',$userinfo['ppsid'])->value('expdate');
                        if($exp_date>time()){
                            $ord['ppsid']=$userinfo['ppsid'];
                        }
                    }else{
                        if($userinfo['guid']){
                            $distributorinfo=Db::name('distributor')
                                ->where('gid',$this->gid)
                                ->where('find_in_set('.$userinfo['guid'].',auth_guid)')
                                ->field('psid,expdate')
                                ->find();
                            if($distributorinfo['expdate']&&$distributorinfo['expdate']>time()){
                                $ord['ppsid']=$distributorinfo['psid'];
                            }
                        }
                    }
                    $ord['pd']=strtotime($omember['psdate']);
                    $ord['pt']=$omember['pstime'];
                    $ord['express_fee']= $omember['freight_config'];
                    $ord['correct']=$omember['money'];
                    $ord['dtype']=$omember['pstype'];
                    $ord['adate']=time();
                    $ord['state']='';
                    $ord['address_id']=$address_id;
                    $ord['gid']=$userinfo['gid'];
                    $ord['downgid']=$userinfo['gid'];
                    $ord['guid']=$userinfo['guid'];
                    $ord['ip']=get_client_ip();
                    $ord['user_agent']=$_SERVER['HTTP_USER_AGENT'];
                    if(session('guid'))$ord['dguid']=session('guid');
                    else $ord['dguid']=$userinfo['guid'];
                    $ord['content']=trim($omember['pssj']);
                    $ord['total']=$z['total'];
                    $ord['car_sale'] = 0;
                    $ord['confirm'] = 1;
                }else{
                    // 没有该用户
                    $return['code'] =2;
                    $return['msg'] ='不存在该用户！';
                    return json($return);
                }
                $order_status=Db::name('order')->insert($ord);
                $oid = Db::name('order')->where($whereU)
                    ->where('number','eq',$authnum)
                    ->value('oid');
                foreach ($cartlist as $goods) {
                    $ogoods['oid'] = $oid;
                    $ogoods['uid'] = $uid;
                    $ogoods['amount']=$goods['goods_number']*$goods['goods_price'];
                    $ogoods['goodid']=$goods['goods_id'];
                    $ogoods['sku_id']=$goods['skuid'];
                    $ogoods['name']=$goods['goods_name'];
                    $ogoods['num']=$goods['goods_number'];
                    $ogoods['price']=$goods['goods_price'];
                    $ogoods['unit']=$goods['unit'];
                    $ogoods['unitid']=$goods['unitid'];
                    $ogoods['unitg']=$goods['unitg'];
                    $ogoods['unitgid']=$goods['unitgid'];
                    $ogoods['type']=$goods['type'];
                    $ogoods['adate']=$ord['adate'];
                    $ogoods['gid']=$userinfo['gid'];
                    $ogoods['desc'] = '';
                    $ogoods['laiyuan']=2;//订单来源 1来自微信信 2来自业务员
                    $ogoods['avs']=$goods['avs'];

                    $m['oid']=$oid;
                    $m['goodid']=$goods['goods_id'];
                    $m['sku_id']=$goods['skuid'];
                    $m['unitid']=$goods['unitid'];
                    $m['type']=$goods['type'];
                    $goodvis = Db::name('order_goods')->where($m)->find();
                    unset($m);
                    if($goodvis){
                        $ginfo=Db::name('order_goods')->where('id', $goodvis['id'])->update($ogoods);
                    }else{
                        $ginfo=Db::name('order_goods')->insert($ogoods);
                    }
                }
                if(!!$userinfo['ddid']){
                    $separate = controller('fx/Separate','controller');
                    $postData = ['oid'=>$oid];
                    $sign = new Sign();
                    $postData = array_merge($postData,$sign->mkSign($postData));
                    $separate->insideIndex($postData);
                }
                if($oid){
                    //添加下单痕迹
                    $postInfo['oid']=$oid;
                    $postInfo['gid']=$this->gid;
                    $postInfo['guid']=$ord['dguid'];
                    $postInfo['uid']=$uid;
                    $postInfo['confirm']=1;
                    $postInfo['adate'] = time();
                    $postInfo['thumb'] = '';
                    $fromid = Db::name('order_post')->getLastInsID();
                    if($fromid ){
                        Db::name('user_member')->where('uid',$uid)->update(['odate'=>time()]);
                        addgps(session('guid'),$oid,$fromid,2);
                    }
                    if($omember['moneydesc'] !='' || intval($omember['money'])!=0){
                        $postInfo['content']="下订单 修正金额：".$omember['money']." 修正说明：".$omember['moneydesc'];
                        $postInfo['pay']=1;
                    }else{
                        $postInfo['content']="下订单";
                        $postInfo['pay']=0;
                    }
                    Db::name('order_post')->insert($postInfo);
                    //添加地址
                    $address=Db::name('user_address')
                        ->field('consignee,areaname,address,mobile,areaids')
                        ->where(array('gid'=>$this->gid,'delete'=>0,'id'=>$address_id))
                        ->find();
                    if($oid){
                        $om['uid']=$uid;
                        $om['oid']=$oid;
                        $om['gid']=$this->gid;
                        $om['realname']=$address['consignee'];
                        $om['address']=$address['areaname'].$address['address'];
                        $om['phone']=$address['mobile'];
                        $om['areaids']=isset($address['areaids']) ? trim($address['areaids']) : 0;
                        $om['x']='';
                        $om['y']='';
                        Db::name('order_member')->insert($om);
                    }
                    Db::name('cart')->where('user_id',$uid)->delete();
                    session('downorderuserid','');
                }
            }else{
                echo 'a';
            }
        }
        $whereUm['gid']=$this->gid;
        $whereUm['guid']=$this->guid;
        $whereUm['uid']=$uid;
        $whereUm['status']=1;
        $mcard_um=Db::name('mcard_um')->field('id')->where($whereUm)->find();
        if($order_status){
            $return['oid'] =$oid;
            $return['uid'] =$uid;
            $return['mcard_um'] =$mcard_um;
            $return['number'] =$authnum;
            $return['code'] =1;
            $return['msg'] ='下订单！';
//            $this->cxOrderStorageOut($oid,$fromid);
            return json($return);
        }else{
            $return['code'] =0;
            $return['msg'] ='下单失败！';
            return json($return);
        }
    }
    public function orderpay(){
        //支付确认 扣除金额
        $input=input('post.');
        $macard_umpost_status='';
        $order_pay_status='';
        $uid=session('downorderuserid')?session('downorderuserid'):intval(input('oid'));
        $oid=isset($input['oid']) ? intval($input['oid']) : 0;

        $status=[];
        $whereP['gid']=['EQ',$this->gid];
        $whereP['guid']=['EQ',$this->guid];
        $whereP['uid']=['EQ',$uid];
        $whereP['status']=['EQ',1];
        $macard_um=Db::name('mcard_um')->where($whereP)->find();
        $whereO['gid']=$this->gid;
        $whereO['uid']=$uid;
        $whereO['oid']=$oid;
        $orderinfo=Db::name('order')
            ->field('oid,uid,number,gid,guid,total')
            ->where($whereO)
            ->find();

        $disable=0;
        $total_money=$orderinfo['total'];
        $useable='';
        if($macard_um != null){
            //有会员卡支付
            //先扣除 会员卡账户  支付了钱的 都是已付款状态  其他都是未付款
            $useable=$macard_um['useable']- $total_money;
            if($useable > 0){
                //余额足够减
                $notpay=0;
                $paynow=$haspay=$total_money;
                $status['pay']=1;
                $status['paytype']=4;
            }else if($useable<0){
                //余额不够减
                if($macard_um['useable']==0){
                    $status['pay']=0;
                    $status['paytype']=0;
                }else{
                    $status['pay']=1;
                    $status['paytype']=4;
                }
                $notpay=abs($useable);
                $paynow=$haspay=$macard_um['useable'];
                $useable=0;
            }else{
                //余额为0
                $notpay=abs($useable);
                $paynow=$haspay=0;
                $status['paytype']=0;
                $status['pay']=0;
                $useable=0;
            }
            //扣除后 会员卡余额
            Db::name('mcard_um')->where($whereP)->setField('useable',$useable);
        }else{

            //用户没有会员卡 支付
            $notpay=$total_money;    //未付金额
            $paynow=$haspay=0;    //共计支付金额
            $status['paytype']=0;
            $status['pay']=0;
        }
        //添加下单痕迹
        $postInfo['oid']=$oid;
        $postInfo['gid']=$this->gid;
        $postInfo['guid']=$this->guid;
        $postInfo['uid']=$uid;
        $postInfo['confirm']=1;
        $postInfo['adate'] = time();
        $postInfo['thumb'] = '';
        $postInfo['content']="会员卡支付款 支付金额：".$haspay;
        Db::name('order_post')->insert($postInfo);
        //有会员卡 消费写入用户详细支付记录（首先扣除会员卡余额）  会员卡扣除不足的  其他支付方式支付
        if($orderinfo){
            $whereU['gid']=$this->gid;
            $whereU['uid']=$uid;
            $gm=Db::name('group_member')->field('uid')->where($whereU)->find();
            $um=Db::name('user_member')->field('uid,guid')->where($whereU)->find();
            $post=[];
            $post['gid']=$this->gid;
            $post['uid']=$uid;
            if($gm){
                //员工
                $post['auid']=$um['uid'];
                $post['atype']=1;
            }else{
                $post['auid']=$um['guid'];
                $post['atype']=0;
            }
            $post['ctime']=time();
            $post['ua']=$_SERVER['HTTP_USER_AGENT'];
            $post['ip']=get_client_ip();

            $post['useable']=$useable;
            $post['disable']=$disable;
            if($macard_um!=null){
                if($macard_um['useable']>$total_money){
                    $unic=+$total_money;
                }else if($macard_um['useable'] < $total_money){
                    if($macard_um['useable']==0){
                        $unic=0;
                    }else{
                        $unic=-$macard_um['useable'];
                    }
                }else{
                    $unic=-$macard_um['useable'];
                }
            }else{
                $unic=0;
            }
            $post['uinc']=$unic;
            $post['dinc']=0;
            $post['type']=6;
            $post['desc']='【移动端消费扣款】';
            $post['sn']=date('YmdHis',time()).mt_rand(100000, 999999);
            $post['oid']=$orderinfo['oid'];
            $post['osn']=$orderinfo['number'];

            $macard_umpost_status=Db::name('mcard_umpost')->insert($post);

            //同时向 order_pay写入支付记录
            $pay=[];
            $pay['gid']=$this->gid;
            $pay['type']=0;
            if($gm){
                //员工
                $pay['guid']=$gm['uid'];
            }else{
                $pay['guid']=$um['guid'];
            }
            $pay['uid']=$um['uid'];
            $pay['total']=$total_money;     //当前订单总值
            $pay['notpay']=$notpay;    //未付金额
            $pay['haspay']=$haspay;    //共计已付金额
            $pay['pay']=$paynow;       //本次支付金额
            $pay['paytime']=time();
            $pay['ppaytime']=0;
            $pay['sn']=$orderinfo['number'];
            $pay['paytype']=4;
            $pay['oid']=$orderinfo['oid'];//原订单编号

            $order_pay_status=Db::name('order_pay')->insert($pay);
            //修改状态
            $status['confirm']=1; //确认订单
            $whereP['oid']=$oid;
            Db::name('order')->where($whereP)->update($status);
        }
        if($macard_umpost_status && $order_pay_status ){
            $return['oid'] =$oid;
            $return['code'] =1;
            $return['msg'] ='确认订单，扣款！即将返回订单详情！';
//            $this->cxOrderStorageOut($oid,$fromid);
            return json($return);
        }else{
            $return['code'] =0;
            $return['msg'] ='下单失败！';
            return json($return);
        }
    }
    private function aogrog()
    {
        if (request()->isAjax()) {
            if (isset($var['oid']) && !empty($var['oid'])) {
                session('oid', $var['oid']);
            }
            $oid = session('oid');
            $uid = '';
            if (!empty($oid)) {
                $u = Db::name('order')->field('uid')->find($oid);
                $uid = $u['uid'];
            }
            return json($this->recentlyOrderGoods($uid));
        }
    }
    private function aorog()
    {
        if (request()->isAjax()) {
            $uid = input('param.id');
            if (!$uid) {
                $uid = session('downorderuserid');
            }
            session('downorderuserid', $uid);
            return json($this->recentlyOrderGoods($uid));
        }
    }
    private function recentlyOrderGoods($uid = '')
    {
        if (empty($uid)) {
            $return['p'] = 1;
            $return['code'] = 0;
            $return['msg'] = '没有更多数据！';
            $return['data'] = null;
            $return['total'] = 0;
            return $return;
        }
        $var = request()->post();
        $limit = $var['limit'];
        $p = intval($var['p']);
        $t = $var['t'];
        $offset = ($p - 1) * $limit;
        $where = [];
        $whereCount = [];
//        $where['guid'] = $this->guid;逻辑更改，补货商品跟着客户走，不在跟着业务员走
        $where['gid'] = $this->gid;//团队条件必须
        $where['uid'] = $uid;
        $where['adate'] = ['BETWEEN', [time() - 3600 * 24 * 30 * 3, time()]];
        $oids = Db::name('order')->where($where)->column('oid');
        if (empty($oids)) {
            $return['p'] = $p;
            $return['code'] = 0;
            $return['msg'] = '没有更多数据！';
            $return['data'] = null;
            $return['total'] = 0;
            return $return;
        }
        //通过获取的oids查询商品信息如果使用in语法，如果oids数量较多的时候，那么就分片处理
        $whereOG['gid'] = $this->gid;
        $whereOG['oid'] = ['IN', $oids];
        $goods_ids1 = Db::name('order_goods')->where($whereOG)->group('goodid')->column('goodid');
        $whereSku=[];
        $whereSku['gid']=$this->gid;
        $whereSku['id']=['IN',$goods_ids1];
        $goods_ids2=Db::name('baojia_sku')->where($whereSku)->group('goods_id')->column('goods_id');
        $goods_ids3=array_merge($goods_ids1,$goods_ids2);
        $where = [];
        $where['gid'] = $this->gid;
        $where['goodsName'] = ['LIKE','%'.$t.'%'];
        if (!empty($goods_ids1)){
            //优化$goods_ids
            //先查询出这个gid下报价商品的所有gid;
            $bj_goods_ids = Db::name('baojia')->where($where)->column('goods_id');
            $goods_ids = [];
            foreach ($bj_goods_ids as $k => $v) {
                if (in_array($v, $goods_ids3)) {
                    $goods_ids[] = $v;
                }
            }
            if (!empty($goods_ids)) {
                $whereCount['goods_id'] = $where['b.goods_id'] = ['IN', implode(',', $goods_ids)];
            } else {
                //直接退出了
                $return['code'] = 0;
                $return['p'] = $p;
                $return['msg'] = '没有更多数据！';
                $return['data'] = null;
                $return['total'] = 0;
                return $return;
            }
        } else {
            //直接退出了
            $return['code'] = 0;
            $return['p'] = $p;
            $return['msg'] = '没有更多数据！';
            $return['data'] = null;
            $return['total'] = 0;
            return $return;
        }
        $whereCount['gid'] = $this->gid;
        $total = Db::name('baojia')->where($whereCount)->count();
        $defaultBJ = Db::name('baojia')
            ->alias('b')
            ->field('
                b.id,
                b.goods_id,
                b.gid,
                b.yd_score,
                b.categoryid,
                b.retailprice,
                b.tuanprice,
                b.cuxiao,
                b.csdate,
                b.cedate,
                b.unit,
                b.unitid,
                b.unitgid,
                g.goods_name,
                g.goods_desc,
                g.goods_sn,
                g.keywords,
                g.goods_img,
                g.goods_thumb
            ')
            ->join('goods g', 'b.goods_id=g.goods_id', 'LEFT')
            ->where($where)
            ->order('goods_id DESC')
            ->limit($offset, $limit)
            ->select();
        $total = count($defaultBJ);
        $r = Db::name('user_member')->field('rankid')->find($uid);
        $rankid=0;
        if (!empty($r)) {
            $rankid = $r['rankid'] ? $r['rankid'] : 0;
        }
        if (!empty($defaultBJ)) {
            //对数据进行加工，有等级报价的修改为等级报价，没有的不更改
            foreach ($defaultBJ as $k => $v) {
                //通过等级去获取商品价格,促销信息等内容（没有设置这个等级对应价格那么就显示默认的价格
                $v['bj_type'] = 1;//这个是默认报价类型
                //兼容 添加商品数据格式
                if ($v['goods_thumb']) {
                    $v['goods_img'] = mkgoodsimgurl(['url' => $v['goods_thumb']]);
                } else {
                    $v['goods_img'] = mkgoodsimgurl(['url' => $v['goods_img']]);
                }
                if ($rankid) {
                    $w['gid'] = $this->gid;
                    $w['rank_id'] = $rankid;
                    $w['goods_id'] = $v['goods_id'];
                    $w['tuanprice'] = ['neq',0];

                    $rankbj = Db::name('baojia_rank')->where($w)->find();
                    $baojia_sku=Db::name('baojia_sku')->field('id')->where(array('baojia_id'=>$v['id'],'gid'=>$this->gid))->find();
                    $skuprice=Db::name('baojia_skuprice')->field('unitid,tuanprice,retailprice')->where(array('gid'=>$this->gid,'rankid'=>$rankid,'skuid'=>$baojia_sku['id']))->find();
                    $unit=Db::name('unit')->field('uname')->where(array('id'=>$skuprice['unitid'],'gid'=>$this->gid))->find();
                    if (!empty($rankbj)) {
                        $v['tuanprice'] = $rankbj['tuanprice'];
                        $v['retailprice'] = $rankbj['retailprice'];
                        $v['unit'] = $rankbj['unit'];
                        $v['unitid'] = $rankbj['unitid'];
                    }else if(!empty($baojia_sku)){
                        $v['tuanprice'] = $skuprice['tuanprice'];
                        $v['retailprice'] = $skuprice['retailprice'];
                        $v['unit'] = $unit['uname'];
                        $v['unitid'] = $skuprice['unitid'];
                    }
                }
                $v['info'] = ['goods_name' => $v['goods_name'], 'goods_img' => $v['goods_img']];
                //附加箱柜
                $gunit = Db::name('unit_group')->field('id,uname,title')->find($v['unitgid']);
                $v['gunit'] = $gunit['title'];
                if (!!$rankid) {
                    $whereR['goods_id'] = $v['goods_id'];
                    $whereR['rank_id'] = $rankid;
                    $whereR['gid'] = $this->gid;
                    $rbj = Db::name('baojia_rank')
                        ->field('   
                                id,
                                goods_id,
                                gid,
                                categoryid,
                                retailprice,
                                tuanprice,
                                cuxiao,
                                csdate,
                                cedate,
                                unit,
                                unitid,
                                unitgid')
                        ->where($whereR)
                        ->find();
                    //如果这个等级有报价，那么去更新
                    if (!empty($rbj)) {
                        $v['bj_type'] = 2;//这个是等级报价类型
                        $v = array_merge($v, $rbj);
                    }
                }
                if (!!$v['csdate']) {
                    $v['csdate'] = date('Y-m-d H:i:s', $v['csdate']);
                }
                if (!!$v['cedate']) {
                    if ($v['cedate'] < time()) {
                        $v['cuxiao'] = '';
                    }
                    $v['cedate'] = date('Y-m-d H:i:s', $v['cedate']);
                }
                $BJ[] = $v;
            }
            return ['code' => 1, 'p' => $p, 'total' => $total, 'data' => $BJ];
        } else {
            return ['code' => 0, 'p' => $p, 'total' => $total, 'data' => []];
        }
    }
    //销售订单数量修改：数量减少入库
    private function ogInSg($input = [])
    {
        if (empty($input)) {
            return false;
        }
        $whereScg['gid'] = $this->gid;
        $whereScg['goods_id'] = $input['goods_id'];
        $projectId = Db::name('storage_ci_goods')->where($whereScg)->value('id');
        if (!$projectId) {
            return false;
        }
        $whereSg['gid'] = $this->gid;
        $whereSg['owner_uid'] = $input['target_uid'];
        $whereSg['owner_type'] = $input['target_type'];
        $whereSg['project_id'] = $projectId;
        $whereSg['unitgid'] = $input['unitgid'];
        $sgid = Db::name('storage_goods')->where($whereSg)->value('id');
        if (empty($sgid)) {
            return false;
        } else {
            //组织数据
            $goods_name = $input['goods_name'];
            $unitgid = $input['unitgid'];
            $unitid = $input['unitid'];
            $goods_id = $input['goods_id'];
            $minunitId = $input['minunitid'];
            $num = $input['num'];
            unset($input['goods_name'], $input['unitgid'], $input['unitid'], $input['minunitid'], $input['goods_id'], $input['num']);
            $input['project_id'][] = $projectId;
            $input['goods_name'][$projectId] = $goods_name;
            $input['goods_id'][$projectId] = $goods_id;
            $input['sgid'][$projectId] = $sgid;
            $input['unitgid'][$projectId] = $unitgid;
            $input['unitid'][$projectId] = $unitid;
            $input['unitnum'][$projectId][$minunitId] = $num;
            $input['comment'] = '销售订单数量修改：数量减少入库！';
            $this->storageModel = new \app\common\model\Storage(['gid' => $this->gid, 'guid' => $this->guid, 'rule_type' => 1]);
            $res = $this->storageModel->doInStorageOrderAdd($input);
            if ($res['code'] === 0) {
                $input['auto_check'] = 1;
                if (isset($input['auto_check']) && $input['auto_check'] == 1) {
                    $soid = $res['soid'];
                    $input = [];
                    $input['id'] = $soid;
                    $input['confirm'] = 2;
                    $input['finish'] = 1;
                    $resx = $this->storageModel->doApplyStorageOrder($input);
                    if ($resx['code'] === 0) {
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return true;
                }
            } else {
                return false;
            }
        }
    }
    private function ogOutSg($input = [])
    {
        if (empty($input)) {
            return false;
        }
        $goods_name = $input['goods_name'];
        $unitgid = $input['unitgid'];
        $unitid = $input['unitid'];
        $goods_id = $input['goods_id'];
        $minunitId = $input['minunitid'];
        $num = $input['num'];
        unset($input['goods_name'], $input['unitgid'], $input['unitid'], $input['minunitid'], $input['goods_id'], $input['num']);
        $whereCig['gid'] = $this->gid;
        $whereCig['goods_id'] = $goods_id;
        $ciGoods = Db::name('storage_ci_goods')->where($whereCig)->find();
        if (empty($ciGoods)) {
            $ciData['gid'] = $this->gid;
            $ciData['goods_id'] = $goods_id;
            $ciData['goods_name'] = $goods_name;
            $ciData['unitid'] = $unitid;
            $ciData['unitgid'] = $unitgid;
            $ciData['type'] = 0;
            //$ciData['seller_note'] = '';
            //获取报价id
            $whereBj['gid'] = $this->gid;
            $whereBj['goods_id'] = $goods_id;
            $bid = Db::name('baojia')->where($whereBj)->value('id');
            $ciData['bid'] = $bid;
            $res = $this->doAddStorageGoodsProject($ciData);
            if ($res['code'] === 0) {
                $projectId = $res['project_id'];
            } else {
                return false;
            }
        } else {
            $projectId = $ciGoods['id'];
        }
        $whereSg['gid'] = $this->gid;
        $whereSg['owner_uid'] = $input['from_uid'];
        $whereSg['owner_type'] = $input['from_type'];
        $whereSg['project_id'] = $projectId;
        $whereSg['unitgid'] = $unitgid;
        $sgid = Db::name('storage_goods')->where($whereSg)->value('id');
        if (empty($sgid)) {
            //创建一条数据为0的入库记录
            $sgData = [];
            $sgData['gid'] = $this->gid;
            $sgData['owner_uid'] = $input['from_uid'];
            $sgData['owner_type'] = $input['from_type'];
            $sgData['goods_name'] = $goods_name;
            $sgData['project_id'] = $projectId;
            $sgData['unitid'] = $unitid;
            $sgData['unitgid'] = $unitgid;
            $sgData['mtime'] = $sgData['ctime'] = time();
            try {
                $sgid = Db::name('storage_goods')->insertGetId($sgData);
                unset($sgData);
            } catch (\think\Exception $e) {
                return false;
            }
        }
        //组织数据
        $input['sgid'][] = $sgid;
        $input['project_id'][$sgid] = $projectId;
        $input['goods_name'][$sgid] = $goods_name;
        $input['goods_id'][$sgid] = $goods_id;
        $input['unitgid'][$sgid] = $unitgid;
        $input['unitid'][$sgid] = $unitid;
        $input['unitnum'][$sgid][$minunitId] = $num;
        $input['comment'] = (isset($input['comment'])) && ($comment = trim($input['comment'])) ? $comment : '销售订单商品数量修改：商品数量增加出库！';
        $this->storageModel = new \app\common\model\Storage(['gid' => $this->gid, 'guid' => $this->guid, 'rule_type' => 1]);
        $res = $this->storageModel->doOutStorageOrderAdd($input);
        if ($res['code'] === 0) {
            $input['auto_check'] = 1;
            if (isset($input['auto_check']) && $input['auto_check'] == 1) {
                $soid = $res['soid'];
                $input = [];
                $input['id'] = $soid;
                $input['confirm'] = 2;
                $input['finish'] = 1;
                $resx = $this->storageModel->doApplyStorageOrder($input);
                if ($resx['code'] === 0) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return true;
            }
        } else {
            return false;
        }
    }
    private function cxOrderStorageIn($oid = 0, $opostid = 0)
    {
        if (!$oid) {
            return false;
        }
        $whereO['gid'] = $this->gid;
        $whereO['guid'] = $this->guid;
        $whereO['oid'] = $oid;
        $whereO['trade'] = 1;
        $whereO['is_out'] = 1;
        $whereO['confirm'] = -1;
        $ord = Db::name('order')->field('oid,gid,sid,guid,uid,number osn,car_sale')->where($whereO)->find();
        if (empty($ord)) {
            return false;
        } else if ($ord['car_sale'] === 1) {
            //去增加业务员的库存
            $input['sign'] = 'orders';
            $input['type'] = 1;
            $input['typeval'] = 4;
            $input['target_type'] = 1;
            $input['target_uid'] = $this->guid;
            $input['comment'] = '销售订单无效商品退库！';
            $input['opostid'] = $opostid;
            $input['oid'] = [$oid];
            $input['opostid'] = [$oid => $opostid];
            $input['orders'] = [$oid => $ord];
            $this->storageModel = new \app\common\model\Storage(['gid' => $this->gid, 'guid' => $this->guid, 'rule_type' => 1]);
            $res = $this->storageModel->createCxSaleInOrders($input);
            if ($res['code'] === 0) {
                $input['auto_check'] = 1;
                if (isset($input['auto_check']) && $input['auto_check'] == 1) {
                    $soids = $res['soids'];
                    for ($i = 0; $i < count($soids); $i++) {
                        $input = [];
                        $input['id'] = $soids[$i];
                        $input['confirm'] = 2;
                        $input['finish'] = 1;
                        $resx = $this->storageModel->doApplyStorageOrder($input);
                    }
                    return true;
                } else {
                    return true;
                }
            } else {
                return false;
            }
        } else if ($ord['car_sale'] === 0) {
            return false;
            //去减仓库的库存
            $input['sign'] = 'orders';
            $input['type'] = 0;
            $input['typeval'] = 1;
            $input['from_type'] = 0;
            $input['from_uid'] = $ord['sid'];
            $input['oid'] = [$oid];
        } else {

        }
    }
    private function cxOrderStorageOut($oid = 0, $opostid = 0)
    {
        if (!$oid) {
            return false;
        }
        $whereO['gid'] = $this->gid;
        $whereO['guid'] = $this->guid;
        $whereO['oid'] = $oid;
        $whereO['trade'] = 0;
        $whereO['is_out'] = 0;
        $whereO['confirm'] = ['NEQ', -1];
        $ord = Db::name('order')->field('oid,gid,sid,guid,uid,number osn,car_sale')->where($whereO)->find();
        if (empty($ord)) {
            return false;
        } else if ($ord['car_sale'] === 1) {
            //去减业务员的库存
            //数据包装
            $input['sign'] = 'orders';
            $input['type'] = 0;
            $input['typeval'] = 3;
            $input['from_type'] = 1;
            $input['from_uid'] = $this->guid;
            $input['oid'] = [$oid];
            $input['orders'] = [$oid => $ord];
            $input['opostid'] = [$oid => $opostid];
            $this->storageModel = new \app\common\model\Storage(['gid' => $this->gid, 'guid' => $this->guid, 'rule_type' => 1]);
            $res = $this->storageModel->createCxSaleOutOrders($input);
            if ($res['code'] === 0) {
                $input['auto_check'] = 1;
                if (isset($input['auto_check']) && $input['auto_check'] == 1) {
                    $soids = $res['soids'];
                    for ($i = 0; $i < count($soids); $i++) {
                        $input = [];
                        $input['id'] = $soids[$i];
                        $input['confirm'] = 2;
                        $input['finish'] = 1;
                        $resx = $this->storageModel->doApplyStorageOrder($input);
                    }
                    return true;
                } else {
                    return true;
                }
            } else {
                return false;
            }
        } else if ($ord['car_sale'] === 0) {
            return false;
            //去减仓库的库存
            $input['sign'] = 'orders';
            $input['type'] = 0;
            $input['typeval'] = 1;
            $input['from_type'] = 0;
            $input['from_uid'] = $ord['sid'];
            $input['oid'] = [$oid];
        } else {

        }
    }
    public function addressinfo(){
        if (request()->isPost()) {
            $map = array();
            $map['gid']  = $this->gid;
            $map['uid']  = session('downorderuserid');
            $map['delete']  = '0';  //  未删除
            // 返回所有地址
            $address = Db::name('user_address')
                ->where($map)
                ->order( 'isdefault', 'DESC' )
                ->select();
            $newaddress=[];
            foreach ($address as $k=>$v){
                $v['areaname']=preg_replace('/(.+)((市辖区)|(县))(.+)/','$1$5',$v['areaname']);
                array_push($newaddress,$v);
            }
            $return['data']['address'] = $newaddress;
            $return['code'] = 1;
            $return['msg'] = '数据加载成功！';
            return json($return);
        }
    }
    function editisdefault(){
        $param=input('param.');
        $whereP['id']=['NOT IN',$param['id']];
        $whereP['gid']=['EQ',$this->gid];
        $whereP['uid']=['EQ',session('downorderuserid')];
        Db::name('user_address')->where($whereP)->setField('isdefault',0);
        $res=Db::name('user_address')->where(array('gid'=>$this->gid,'id'=>$param['id']))->setField('isdefault',1);
        if($res){
            $return['data']= $res;
            $return['code'] = 1;
            $return['msg'] = '设置默认地址成功！';
        }else{
            $return['data']= $res;
            $return['code'] = 1;
            $return['msg'] = '设置默认地址失败！';
        }
        return json($return);
    }
    public function delisdefault(){
        $param=input('param.');
        $whereP['id']=['EQ',$param['id']];
        $whereP['gid']=['EQ',$this->gid];
        $res=Db::name('user_address')->where(array('gid'=>$this->gid,'id'=>$param['id']))->setField('delete','1');
        if($res){
            $return['data']= $res;
            $return['code'] = 1;
            $return['msg'] = '数据删除成功！';
        }else{
            $return['data']= $res;
            $return['code'] = 1;
            $return['msg'] = '数据删除失败！';
        }
        return json($return);
    }
    public function editaddress(){
        $param=input('param.');
        $address=null;
        if($param['id']){
            //  当前用户信息
            $map = array();
            $map['id']   = $param['id'];
            $map['gid']  = $this->gid;
            $map['uid']  = session('downorderuserid');
            $map['delete']  = '0';  //  未删除
            $address = Db::name('user_address')
                ->where( $map )
                ->find();
        }
        $return['data']= $address;
        $return['code'] = 1;
        $return['msg'] = '数据加载成功！';
        return json($return);
    }
    //  TODO: 地址列表
    public function lst(){
        //  当前用户账号
        $map = array();
        $map['gid']  = $this->gid;
        $map['uid']  = session('downorderuserid');
        $map['delete']  = '0';  //  未删除
        // 返回所有地址
        $result = Db::name('user_address')
            ->where( $map )
            ->order( 'isdefault', 'DESC' )
            ->select();
        //  整合数据
        return $this->reJson(1,$result);
    }
    //  TODO:: 返回状态和数据
    public function reJson( $code, $data, $error = '无' ){
        $reJson = array();
        $reJson['code'] = $code;
        $reJson['data'] = $data;
        $reJson['error'] = $error;
        return json( $reJson );
    }
    //  TODO: 新增地址
    public function add(){
        if( !$this->request->isPost() ){
            return $this->reJson( -1, 0, '错误请求方式');
        }
        //  查询是否超过地址条数限制,最多5条
        //  当前用户账号
        $map = array();
        $map['gid']  = $this->gid;
        $map['uid']  = session('downorderuserid');
        $map['delete']  = '0';  //  未删除
        $result = Db::name('user_address')->where( $map )->count();
        if( $result >= 5 ){
            return $this->reJson( -1, 0, '用户最多添加5个收货地址,请删除不需要的收货地址再尝试添加！');
        }
        //  取出数据
        $province   = explode( ':', input('province'));
        $city       = explode( ':', input('city'));
        $area       = explode( ':', input('area'));
        $data = array();
        $data['isdefault']  = input('isdefault');
        $data['gid']        = $this->gid;
        $data['shopid']        = 0;

        $data['uid']        = session('downorderuserid');
        $data['consignee']  = input('consignee');
        $data['areaids']    = $province[0] . ',' . $city[0] . ',' . $area[0];    //  地址ID
        $data['areaname']   = $province[1] . $city[1] . $area[1];
        $data['address']    = input('address');
        $data['mobile']        = input('mobile');
        // 验证规则
        $rule = [
            ['consignee', 'require', '收货人姓名不能为空'],
            ['areaids', 'require', '请选择省市区'],
            ['areaname', 'require', '省市区获取失败'],
            ['address', 'require', '请输入详细地址'],
            ['mobile', '/^1[3456789]\d{9}$/', '电话号码填写错误']
        ];
        $result = $this->validate( $data, $rule );
        if( $result !== true ){
            return $this->reJson( 1, 0, $result);
        }
        //  插入数据
        $results=Db::name('user_address')->field('id')->where(array('gid'=>$this->gid,'delete'=>1))->find();

        $insertId=0;
        if($results!=null){
            $data['delete'] = 0;
            //修改
            $insertId=Db::name('user_address')->where(array('gid'=>$this->gid,'id'=>$results['id']))->update($data);
        }else{
            //添加
            $insertId = Db::name('user_address')->insertGetId($data);
        }
        if($insertId=0){
            return $this->reJson( -1, 0, '插入数据错误');
        }
        //  设置其他地址为非默认
        if( input('isdefault') == 1 ){
            $result = Db::name('user_address')->where( 'id', 'neq', $insertId )->update( [ 'isdefault' => 0 ] );
        }
        // 返回所有地址
        return $this->lst();
    }
    //  TODO: 地址修改
    public function address_update(){
        //  当前用户信息
        $map = array();
        $map['id']   = input('id');
        $map['gid']  = $this->gid;
        $map['uid']  = session('downorderuserid');
        //  更新数据
        $province   = explode( ':', input('province'));
        $city       = explode( ':', input('city'));
        $area       = explode( ':', input('area'));
        $data = array();
        $data['isdefault']  = input('isdefault');
        $data['gid']        = $this->gid;
        $data['uid']        = session('downorderuserid');
        $data['consignee']  = input('consignee');
        $data['areaids']    = $province[0] . ',' . $city[0] . ',' . $area[0];    //  地址ID
        $data['areaname']   = $province[1] . $city[1] . $area[1];
        //  地址code转换成实际地址
        $data['address']    =htmlentities(input('address'));
        $data['zipcode']    = input('zipcode') ? input('zipcode') : '';
        $data['best_time']  = input('best_time') ? $data['best_time'] : '';
        $data['tel']        = input('tel') ? input('tel') : '';
        $data['mobile']        = input('mobile') ? input('mobile') : '';
        $data['sign_building']  = '';
        $data['recommend']     = input('recommend') ? input('recommend') : '';
        // 验证规则
        $rule = [
            ['consignee', 'require', '收货人姓名不能为空'],
            ['areaids', 'require', '请选择省市区'],
            ['areaname', 'require', '省市区获取失败'],
            ['address', 'require', '请输入详细地址'],
            ['mobile', '/^1[3456789]\d{9}$/', '电话号码填写错误']
        ];
        $result = $this->validate( $data, $rule );
        if( $result !== true ){
            return $this->reJson(1, 0, '数据错误：' . $result);
        }
        //  更新
        $result = Db::name('user_address')->where( $map )->update( $data );
        //  设置其他地址为非默认
        if(input('isdefault') == 1 ){
            $result = Db::name('user_address')->where(['gid'=>$this->gid,'uid'=>session('downorderuserid'),'id' => ['NEQ', $map['id']]])->update( [ 'isdefault' => 0 ] );
        }
        //  返回数据
        return $this->lst();
    }
    public function getCnum(){
        $uid=session('downorderuserid');
        $cnum = Db::name('cart')->where('user_id', $uid)->count();
        return $cnum;
    }
    /**
     * @return null|\think\response\Json
     * 订单调整
     */
    public function adjust(){
        //订单调整， 涉及 调整 金额 ，配送方式， 收货地址，预定配送时间，备注信息
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            if(isset($input['action'])){
                $action = $input['action'];
            }else{
                $action = $input['action'] = input('action');
            }
            unset($input['action']);
            switch($action){
                case 'adjustorderinfo':return $this->adjustOrderInfo($input);
                case 'doadjustorder':return $this->doAdjustOrder($input);
                default:return null;
            }
        }else{
            return json(['code' => -1,'msg' => '非法操作']);
        }
    }
    private function adjustOrderInfo($input = []){
        $oid = intval($input['oid']) ? intval($input['oid']) : session('oid');
        session('oid', $oid);
        $whereO['gid'] = $this->gid;
        $whereO['oid'] = $oid;
        $whereO['confirm'] = ['NEQ', -1];
        $whereO['trade'] = ['NEQ', 1];
        $order = Db::name('order')->field('oid,guid,dguid,uid,pay,confirm,content,dispatch,trade,total,express_fee,correct,pd,pt,dtype')->where($whereO)->find();
        $order['pd'] = date('Y-m-d',$order['pd']);
        if(empty($order)){
            return json(['code' => -1,'msg' => '请确保：1.订单是否存在;2.订单是否有效;3.订单是否未结束']);
        }
        $uid = $order['uid'];
        //获取默认收货地址信息,没有则提示用户前往设置
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
        //获取订单收货地址
        $whereOm['oid'] = $oid;
        $whereOm['uid'] = $uid;
        $whereOm['gid'] = $this->gid;
        $om = Db::name('order_member')->where($whereOm)->find();
        $om['address'] = preg_replace('/(.+)((市辖区)|(县))(.+)/','$1$5',$om['address']);
        //获取订单商品信息
        $whereOg['uid'] = ['IN',[0,$uid]];
        $whereOg['gid'] = $this->gid;
        $whereOg['oid'] = $oid;
        $ogs = Db::name('order_goods')
            ->where($whereOg)
            ->field('id,name goods_name,goodid goods_id,type,baojia_id bjid,num goods_number,amount,unit,unitid,unitgid,unitg,priceid,avs,sku_id,uid user_id,price goods_price,desc')
            ->order('id','DESC')
            ->select();
        $order['ordercount']=count($ogs);
        foreach ($ogs as $k => $v ){
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
                    $ogs[$k]['goodsThumb'] = mkgoodsimgurl(['url' => $b[$v['bjid']]['goodsThumb']]);
                }else if(!!$b[$v['bjid']]['goodsImg']){
                    $ogs[$k]['goodsThumb'] = mkgoodsimgurl(['url' => $b[$v['bjid']]['goodsImg']]);
                }else{
                    $ogs[$k]['goodsThumb'] = '';
                }
            }else if(!!$v['goods_id']){
                if(!isset($g[$v['goods_id']])){
                    $g[$v['goods_id']] = Db::name('goods')//获取sku中的预览图
                    ->field('goods_thumb goodsThumb,goods_img goodsImg')
                        ->where(['goods_id' => $v['goods_id']])
                        ->find();
                }
                if(!!$g[$v['goods_id']]['goodsThumb']){
                    $ogs[$k]['goodsThumb'] = mkgoodsimgurl(['url' => $g[$v['goods_id']]['goodsThumb']]);
                }else if(!!$g[$v['goods_id']]['goodsImg']){
                    $ogs[$k]['goodsThumb'] = mkgoodsimgurl(['url' => $g[$v['goods_id']]['goodsImg']]);
                }else{
                    $ogs[$k]['goodsThumb'] = '';
                }
            }else{
                unset($ogs[$k]);
            }
            $order_goods_status = config('order_goods_status');
            $ogs[$k]['type'] = $order_goods_status[$v['type']];
            if(!!isset($v['sku_id'])){
                $whereSku['gid']=$this->gid;
                $whereSku['id']=$v['sku_id'];
                $sku = Db::name('baojia_sku')->field('title,attrs_value')->where($whereSku)->find();
                $ogs[$k]['sku']=$sku;
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
        $fc = Db::name('freight_config')->where(['gid' => $this->gid,'status' => 1])->find();
        $data = [
            'fc' => $fc,
            'umu' => $umu,
            'om' => $om,
            'ogs' => $ogs,
            'order' => $order
        ];
        return json(['code' => 1,'msg' => '获取订单信息成功！','data' => $data]);
    }
    public function doAdjustOrder($input = []){
        //判断订单存在性
        $uid = isset($input['uid']) && intval($input['uid']) ? intval($input['uid']) : (session('uid') ? intval(session('uid')) : 0) ;
        $oid = isset($input['oid']) && intval($input['oid']) ? intval($input['oid']) : false;
        $areaids = isset($input['areaids']) ? $input['areaids'] : 0;
        $pd = isset($input['pdate']) ? strtotime(trim($input['pdate'])) : 0;
        $pt = isset($input['ptime']) ? intval($input['ptime']) : 0;
        $correct = isset($input['correct']) ? round(floatval($input['correct']),2) : 0;
        $dtype = isset($input['dtype']) ? (in_array(intval($input['dtype']),[0,1]) ? intval($input['dtype']) : 0) : 0;//配送方式
        $correctr='';
        if($correct != 0){
            $correctr = isset($input['correct_reason']) ? '，修正说明：' . trim($input['correct_reason']) : '，修正说明：未填写！';//配送方式
        }
        if(!$areaids){
            return json(['code' => -1,'msg' => '请设置好收货地址后再下单！']);
        }
        $whereO['gid'] = $this->gid;
        $whereO['uid'] = $uid;
        $whereO['oid'] = $oid;
        $order = Db::name('order')
            ->field('oid,confirm,dispatch,pay,pd,pt,dtype,trade,total,express_fee,correct,content')
            ->where($whereO)->find();
        if(empty($order)){
            return json(['code' => -1,'msg' => '订单不存在！']);
        }else if($order['confirm'] == -1){
            return json(['code' => -1,'msg' => '无效订单无法调整！']);
        }else if($order['trade'] == 1){
            return json(['code' => -1,'msg' => '已结束订单无法调整！']);
        }
        if($dtype===0){
            $fc = Db::name('freight_config')->where(['gid' => $this->gid,'status' => 1])->find();
            if($fc){
                if($fc['min'] > $order['total']){
                    $ffee = $fc['ltfee'];
                }else{
                    $ffee = $fc['egtfee'];
                }
            }else{
                $ffee = 0;
            }
        }else{
            $ffee = 0;
        }
        if(($correct + $ffee + $order['total']) < 0){
            return json(['code' => -1,'msg' => '订单金额不能小于0！']);
        }
        //order_post增加编辑记录
        $upOrder = [];
        $ps = [];//调整内容
        if($order['dtype'] !== $dtype){
            $dtypes = [
                '物流配送',
                '上门自提'
            ];
            $upOrder['dtype'] = $dtype;
            $ps[] = '配送方式：' . $dtypes[$order['dtype']] . '->' .  $dtypes[$dtype];
        }
        if($correct != $order['correct']){
            $upOrder['correct'] = $correct;
            $ps[] = '金额修正：' . $order['correct'] . '->' . $correct . $correctr;
        }
        if($order['express_fee'] != $ffee){
            $upOrder['express_fee'] = $ffee;
            $ps[] = '运费变动：' . $order['express_fee'] . '->' . $ffee ;
        }
        if($order['pd'] != $pd){
            $upOrder['pd'] = $pd;
            $ps[] = '预定配送日期：' . date('Y-m-d', $order['pd']) . '->' . date('Y-m-d', $pd);
        }
        if($order['pt'] != $pt){
            $pts = ['全天','上午','下午'];
            $upOrder['pt'] = $pt;
            $ps[] = '预定配送时间：' . $pts[$order['pt']] . '->' . $pts[$pt];
        }
        $oc = isset($input['content']) ? trim($input['content']) : '';
        if($oc !== $order['content']){
            $upOrder['content'] = $oc;
            $ps[] = '备注修改：' . $order['content'] . '->' . $oc;
        }
        if(!empty($upOrder)){
            $whereO['trade'] = 0;
            $whereO['confirm'] = ['NEQ',-1];
            Db::name('order')->where($whereO)->update($upOrder);
            $whereOm['oid']=$oid;
            $post_om=[];
            $post_om['realname']=$input['name'];
            $post_om['phone']=$input['phone'];
            $post_om['address']=$input['areas'];
            $post_om['areaids']=$areaids;
            Db::name('order_member')->where($whereOm)->update($post_om);
            $orderPost = [];
            $orderPost['oid'] = $oid;
            $orderPost['gid'] = $this->gid;
            $orderPost['guid'] = $this->guid;
            $orderPost['content'] = '【订单调整】' . implode(';',$ps);
            $orderPost['adate'] = time();
            $orderPost['uid'] = $uid;
            $orderPost['thumb'] = '';
            Db::name('order_post')->insert($orderPost);
        }
        return json(['code' => 1,'msg' => '订单调整完成， 即将返回详情页面!']);
    }
    /**
     * @param $uid
     * @param $oid
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 删除 修改订单详情 是修改订单商品的总额
     */
    private function editordertotal($uid,$oid){
        $whereO['gid']=$this->gid;
        $whereO['uid']=$uid;
        $whereO['oid']=$oid;
        $whereO['status']=1;
        $orderlist=Db::name('order_goods')->where($whereO)->select();
        //编辑商品数量过后自己修改订单总额
        $total=0;
        foreach ($orderlist as $k=>$v){
            $total+=$v['amount'];
        }
        $whereO['guid']=$this->guid;
        Db::name('order')->where($whereO)->setField('total',sprintf("%.2f",$total));
    }
    public function addorderPost($ogId,$status,$content,$dtype,$num,$unit,$price,$desc){
        //order_post增加记录
        $whereog['id']=$ogId;
        $whereog['gid']=$this->gid;
        $whereog['status']=1;
        $ordergoods = Db::name('order_goods')->where($whereog)->find();
        if($ordergoods){
            $post=[
                'oid'=>$ordergoods['oid'],
                'gid'=>$this->gid,
                'guid'=>$this->guid,
                'uid'=>$ordergoods['uid'],
                'psid'=>0,
                'ppsid'=>0,
                'thumb'=>'',
                'is_down'=>0,
                'imgsid'=>'',
                'wx_serverid'=>''
            ];
            if($status == 'edit'){
                //order_post增加编辑记录
                $upPost = [];
                $ps = [];//调整内容
                if($ordergoods['type'] !== intval($dtype)){
                    $dtypes = [
                        '--',
                        '销售',
                        '赠品'
                    ];
                    $upPost['dtype'] = $dtype;
                    $ps[] = '销售类型：' . $dtypes[$ordergoods['type']] . '->' .  $dtypes[$dtype];
                }
                if($num != $ordergoods['num'] || $unit != $ordergoods['unit']){
                    $upPost['num'] = $num;
                    $upPost['unit'] = $unit;
                    $ps[] = '数量：' .$ordergoods['num'] . $ordergoods['unit'] . '->' .$num.$unit;
                }
                if($ordergoods['price'] != $price){
                    $upPost['price'] = $price;
                    $ps[] = '单价：' . $ordergoods['price'] . '->' . $price ;
                }
                if(strip_tags($desc)){
                    $upPost['content'] = $desc;
                    $ps[] = '商品备注：' .$desc;
                }
                if(!empty($upPost)){
                    //修改
                    $msg='【订单调整】' .$content.'：'. implode(';',$ps);
                    $post['type']=$dtype;
                    $post['content']=$msg;
                    $status=Db::name('order_post')->insert($post);
                }
            }else{
                if($ordergoods['type']==1){
                    $type='销售';
                }else{
                    $type='赠送';
                }
                //删除
                $msg= '【订单调整】' .$content.'：'.$ordergoods['name'].'，数量：'.$ordergoods['num'].$ordergoods['unit'].'，单价：'.$ordergoods['price'].',销售类型：'.$type;
                $post['type']=$ordergoods['type'];
                $post['content']=$msg;
                $status=Db::name('order_post')->insert($post);
            }
            return $status;
        }
    }
}
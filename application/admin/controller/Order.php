<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://ljk.cc All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: itkk <it_kk@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\controller;
use app\admin\model\OrderModel;
use app\admin\model\OrderGoods;
use app\admin\model\OrderPost;
use app\admin\model\UserMember;
use app\admin\model\Group;
use app\common\model\Message;
use app\common\controller\Sign;

use app\common\controller\Lss;
use think\Db;
class Order extends Base{
    private $hosts = [];
    public function __construct(\think\Request $request = null) {
        parent::__construct($request);
        $this->initData = [
            'sign'=>$this->sign,
            'requestFunc'=>$this->requestFunc,
            'requestUrl'=>$this->requestUrl,
            'jsName'=>$this->jsName,
        ];
        $this->assign('gid',$this->gid);
        $this->assign('initData',$this->initData);
    }
    public function index(){
    	$this->insideIndex();
    	if(request()->isAjax()){
    		return $this->fetch('/order/ajax/order_index');
    	}
    	return $this->fetch('/order/order_index');
    }
    public function insideIndex(){
    	$member = Db::name('group_member')->find($this->guid);
    	$pay_status = config('order_pay_status');
    	$rz_status = config('rz_status');
    	$dispatch_status= config('order_dispatch_status');
    	$ord_confirm = config('order_confirm_status');
    	$ord_trade = config('order_trade_status');
    	$ord_type = config('order_type');
    	//是否有配送商
    	$ispss=$this->getnextgroups();
    	//配送司机
    	$driverlist=$this->driverList();
    	if($driverlist){
    		$driverlist=$driverlist->getData();
    	}else{
    		$driverlist['data']='';
    	}
    	if(request()->isAjax()){
    		$param = input('post.');
    		$uid = isset($param['uid'])?$param['uid']:0;
    		$where1 = [];//生成order查询条件1
    		$where2 = [];//生成order查询条件2
    		$whereOr = [];//生成or查询条件
    		$whereOM = [];//生成order_member查询条件
    		$whereUM = [];//生成user_member查询条件
    		$where1['o.gid'] = $this->gid;
    		if(!!$uid){
    			$where2['o.uid'] = $uid;
    		}
    		if(isset($param['keyword']) && !!($param['keyword'] = trim($param['keyword']))){
    			if(isset($param['type']) && $param['type'] === 'number') {
    				$whereOr['o.number'] = ['like', '%' . $param['keyword'] . '%'];
    			}
    			if(isset($param['type']) && $param['type'] === 'phone') {
    				$whereOM['phone'] = ['like', '%' . $param['keyword'] . '%'];
    			}
    			if(isset($param['type']) && $param['type'] === 'mobile') {
    				$whereUM['mobile'] = ['like', '%' . $param['keyword'] . '%'];
    			}
    			if(isset($param['type']) && $param['type'] === 'address') {
    				$whereOM['address'] = ['like', '%' . $param['keyword'] . '%'];
    			}
    			if(isset($param['type']) && $param['type'] === 'realname') {
    				$whereUM['realname'] = ['like', '%' . $param['keyword'] . '%'];
    			}
    		}
    		if(isset($param['confirm']) && in_array($param['confirm'],['-1','0','1'])) {
    			$where2['o.confirm'] = $param['confirm'];
    		}
    		if(isset($param['pay']) && in_array($param['pay'],['0','1'])) {
    			$where2['o.pay'] = $param['pay'];
    		}
    		if($member['is_admin']){
    			$whereD['status'] = 1;
    			$whereD['psid'] = $this->gid;
    			$whereD['type'] = 2;
    			$whereD['expdate'] = ['EGT',time()];
    			//验证绑定关系的有效性
    			//$prevGid = Db::name('distributor')->where($whereD)->column('gid');
    			// $prevGid[] = $this->gid;
    			// $where1['o.gid'] = ['IN', $prevGid];
    			$where1['o.gid'] = $this->gid;
    			$where1['o.ppsid'] = $this->gid;
    			if(isset($param['from']) && in_array($param['from'],['1','2'])) {
    				if($param['from'] == 1){
    					$where2['o.ppsid'] = 0;
    				}else if($param['from'] == 2){
    					$where2['o.ppsid'] = $this->gid;
    				}
    			}
    		}
    		if(isset($param['trade']) && in_array($param['trade'],['0','1'])) {
    			$where2['o.trade'] = $param['trade'];
    		}
    		if(isset($param['otype']) && (trim($param['otype']) !== '') && in_array($param['otype'],array_keys($ord_type))) {
    			$where2['o.type'] = $param['otype'];
    		}
    		if(isset($param['csdate']) && !!($csdate = trim($param['csdate']))){
    			$csdate = strtotime($csdate);
    			$where2['o.'.$param['sjtype']] = ['EGT',$csdate];
    		}
    		if(isset($param['cedate']) && !!($cedate = trim($param['cedate']))){
    			$cedate = strtotime($cedate);
    			$where2['o.'.$param['sjtype']] = ['ELT',$cedate];
    		}
    		if($csdate && $cedate){
    			$where2['o.'.$param['sjtype']] = ['BETWEEN',[$csdate,$cedate]];
    		}
    		if(!empty($whereOM)){
    			$oid = Db::name('order_member')
    			->where($whereOM)
    			->column('oid');
    			$whereOr['o.oid'] = ['IN',  implode(',', $oid)];
    		}
    		if(!empty($whereUM)){
    			$uid = Db::name('user_member')
    			->field('uid')
    			->where('gid',$this->gid)
    			->where($whereUM)
    			->column('uid');
    			$whereOr['o.uid'] = ['IN',  implode(',', $uid)];
    		}
    		$OrderModel = new OrderModel;
    		//设置查询条件
    		$res = $OrderModel->getOrdersByWhere2($where1, $where2, $whereOr, $this->rows);
    		$pageInfo = [
    		'currentPage' => $res->currentPage(),
    		'total' => $res->total(),
    		'prev' => ($res->currentPage() - 1 >= 1) ? $res->currentPage() - 1 : '',
    		'listRows' => $res->listRows(),
    		'lastPage' => $res->lastPage(),
    		'next' => ($res->currentPage() + 1 <= $res->lastPage()) ? $res->currentPage() + 1 : '',
    		'f' => (( $res->currentPage() - 1 ) * $res->listRows() + 1) >= 0 ? (( $res->currentPage() - 1 ) * $res->listRows() + 1) : 0,
    		't' => ($res->currentPage() * $res->listRows() > $res->total()) ? $res->total() : $res->currentPage() * $res->listRows()
    		];
    		$data = $res->items();
    		$orders = [];
    		foreach($data as $key=>$vo){
    			//对数据进行加工
    			$order = $vo->getData();
    			$order['sjname']=$this->getsjname($order['oid']);
    			if(isset($this->hosts[$order['uid']])){
    				$host = $this->hosts[$order['uid']];
    			}else{
    				$host = Usermember::get($order['uid']);
    				$this->hosts[$order['uid']] = $host;
    			}
    			if($order['guid']==$order['dguid']){
    				$order['gjr'] = Db::name('group_member')->where('uid',$order['guid'])->value('realname');}
    				else {
    					$order['gjr'] = Db::name('group_member')->where('uid',$order['dguid'])->value('realname');
    				}
    				$order['host']['uid'] = $host['uid'];
    				$order['host']['realname'] = $host['realname'];
    				$order['host']['mobile'] = $host['mobile'];
    				$order['host']['trust'] = $rz_status[$host['trust']];
    				if($order['confirm'] != -1){
    					if($order['confirm']) $confirm='<span style="color:green;">'.$ord_confirm[$order['confirm']].'</span>';
    					else  $confirm='<span style="color:#999;">'.$ord_confirm[$order['confirm']].'</span>';
    					if($order['pay']) $pay='<span style="color:green;">'.$pay_status[$order['pay']].'</span>';
    					else  $pay='<span style="color:#999;">'.$pay_status[$order['pay']].'</span>';
    					if($order['dispatch']) $dispatch='<span style="color:green;">'.$dispatch_status[$order['dispatch']].'</span>';
    					else  $dispatch='<span style="color:#999;">'.$dispatch_status[$order['dispatch']].'</span>';
    					if($order['trade']) $trade='<span style="color:green;">'.$ord_trade[$order['trade']].'</span>';
    					else  $trade='<span style="color:#999;">'.$ord_trade[$order['trade']].'</span>';
    					if($order['trade'] === 1){
    						$order['status_desc'] = '<span style="color:blue;">'.$ord_confirm[$order['confirm']]  . '-' . $pay_status[$order['pay']] . '-' . $dispatch_status[$order['dispatch']] . '-' . $ord_trade[$order['trade']].'</span>';
    					}else{
    						$order['status_desc'] = $confirm . '-' .$pay.'-' .$dispatch. '-' . $trade;
    					}
    				}else{
    					$order['status_desc'] = '<span style="color:red;">'.$ord_confirm[$order['confirm']].'</span>';
    				}
    				if($order['ppsid'] != 0){
    					if($order['ppsid'] == $this->gid){
    						//说明是个外部订单
    						$order['pssname'] = '自身';
    						$order['outside'] = 0;
    					}else{
    						$whereD['psid'] = $order['ppsid'];
    						$whereD['gid'] = $this->gid;
    						$whereD['expdate'] = ['GT',time()];
    						$order['pssname'] = Db::name('distributor')->where($whereD)->value('sname');
    						//已分配给配送商可以重新分配
    						// $order['outside'] = 0;
    						//已分配给配送商不能重新分配
    						$order['outside'] = 1;
    					}
    				}else{
    					$order['outside'] = 0;
    				}
    				if($order['gid']==$this->gid){
    					$order['ly'] = $ord_type[$order['type']];
    				}else{
    					$order['ly'] ='外部订单';
    				}
                        if($this->gid == 201){
                            $whereOp['gid'] = $this->gid;
                            $whereOp['oid'] = $order['oid'];
                            $whereOp['type'] = 4;
                            if(!Db::name('order_post')->where($whereOp)->count()){
                                $order['status_desc'] .= '-<span style="color:#999;">未打印</span>';
                            }else{
                                $order['status_desc'] .= '-<span style="color:darkgreen;">已打印</span>';
                            }
                        }
    				$orders[] = $order;
    		}
    		$this->assign('orders',$orders);
    		$this->assign('ispss',$ispss['code']);
    		$this->assign('driverlist',$driverlist['data']);
    		$this->assign('pageInfo',$pageInfo);
    	}
    	//获取当前的用户信息
    	$this->assign('ordType',$ord_type);
    	$this->assign('ispss',$ispss['code']);
    	$this->assign('member',$member);
    	$this->assign('driverlist',$driverlist['data']);
    }
    public function index2(){
        $member = Db::name('group_member')->find($this->guid);
        $pay_status = config('order_pay_status');
        $rz_status = config('rz_status');
        $dispatch_status= config('order_dispatch_status');
        $ord_confirm = config('order_confirm_status');
        $ord_trade = config('order_trade_status');
        $ord_type = config('order_type');
        //是否有配送商
        $ispss=$this->getnextgroups();
        //配送司机
        $driverlist=$this->driverList();
        if($driverlist){
        	$driverlist=$driverlist->getData();
        }else{
        	$driverlist['data']='';
        }
        if(request()->isAjax()){
            $param = input('post.');
            $uid = isset($param['uid'])?$param['uid']:0;
            $where1 = [];//生成order查询条件1
            $where2 = [];//生成order查询条件2
            $whereOr = [];//生成or查询条件
            $whereOM = [];//生成order_member查询条件
            $whereUM = [];//生成user_member查询条件
            $where1['o.gid'] = $this->gid;
            if(!!$uid){
                $where2['o.uid'] = $uid;
            }
            if(isset($param['keyword']) && !!($param['keyword'] = trim($param['keyword']))){
                if(isset($param['type']) && $param['type'] === 'number') {
                    $whereOr['o.number'] = ['like', '%' . $param['keyword'] . '%'];
                }
                if(isset($param['type']) && $param['type'] === 'phone') {
                    $whereOM['phone'] = ['like', '%' . $param['keyword'] . '%'];
                }
                if(isset($param['type']) && $param['type'] === 'mobile') {
                    $whereUM['mobile'] = ['like', '%' . $param['keyword'] . '%'];
                }
                if(isset($param['type']) && $param['type'] === 'address') {
                    $whereOM['address'] = ['like', '%' . $param['keyword'] . '%'];
                }
                if(isset($param['type']) && $param['type'] === 'realname') {
                    $whereUM['realname'] = ['like', '%' . $param['keyword'] . '%'];
                }
            }
            if(isset($param['confirm']) && in_array($param['confirm'],['-1','0','1'])) {
                $where2['o.confirm'] = $param['confirm'];
            }
            if(isset($param['pay']) && in_array($param['pay'],['0','1'])) {
                $where2['o.pay'] = $param['pay'];
            }
            if($member['is_admin']){
                $whereD['status'] = 1;
                $whereD['psid'] = $this->gid;
                $whereD['type'] = 2;
                $whereD['expdate'] = ['EGT',time()];
                //验证绑定关系的有效性
                //$prevGid = Db::name('distributor')->where($whereD)->column('gid');
               // $prevGid[] = $this->gid;
               // $where1['o.gid'] = ['IN', $prevGid];
                $where1['o.gid'] = $this->gid;
                $where1['o.ppsid'] = $this->gid;
                if(isset($param['from']) && in_array($param['from'],['1','2'])) {
                    if($param['from'] == 1){
                        $where2['o.ppsid'] = 0;
                    }else if($param['from'] == 2){
                        $where2['o.ppsid'] = $this->gid;
                    }
                }
            }
            if(isset($param['trade']) && in_array($param['trade'],['0','1'])) {
                $where2['o.trade'] = $param['trade'];
            }
            if(isset($param['otype']) && (trim($param['otype']) !== '') && in_array($param['otype'],array_keys($ord_type))) {
                $where2['o.type'] = $param['otype'];
            }
            if(isset($param['csdate']) && !!($csdate = trim($param['csdate']))){
                $csdate = strtotime($csdate);
                $where2['o.'.$param['sjtype']] = ['EGT',$csdate];
            }
            if(isset($param['cedate']) && !!($cedate = trim($param['cedate']))){
                $cedate = strtotime($cedate);
                $where2['o.'.$param['sjtype']] = ['ELT',$cedate];
            }
            if($csdate && $cedate){
                $where2['o.'.$param['sjtype']] = ['BETWEEN',[$csdate,$cedate]];
            }
            if(!empty($whereOM)){
                $oid = Db::name('order_member')
                        ->where($whereOM)
                        ->column('oid');
                $whereOr['o.oid'] = ['IN',  implode(',', $oid)];
            }
            if(!empty($whereUM)){
                $uid = Db::name('user_member')
                        ->field('uid')
                        ->where('gid',$this->gid)
                        ->where($whereUM)
                        ->column('uid');
                $whereOr['o.uid'] = ['IN',  implode(',', $uid)];
            }
            $OrderModel = new OrderModel;
            //设置查询条件
            $res = $OrderModel->getOrdersByWhere2($where1, $where2, $whereOr, $this->rows);
            $pageInfo = [
                'currentPage' => $res->currentPage(),
                'total' => $res->total(),
                'prev' => ($res->currentPage() - 1 >= 1) ? $res->currentPage() - 1 : '',
                'listRows' => $res->listRows(),
                'lastPage' => $res->lastPage(),
                'next' => ($res->currentPage() + 1 <= $res->lastPage()) ? $res->currentPage() + 1 : '',
                'f' => (( $res->currentPage() - 1 ) * $res->listRows() + 1) >= 0 ? (( $res->currentPage() - 1 ) * $res->listRows() + 1) : 0,
                't' => ($res->currentPage() * $res->listRows() > $res->total()) ? $res->total() : $res->currentPage() * $res->listRows()
            ];
            $data = $res->items();
            $orders = [];
            foreach($data as $key=>$vo){
                //对数据进行加工
                $order = $vo->getData();
                $order['sjname']=$this->getsjname($order['oid']);
                if(isset($this->hosts[$order['uid']])){
                    $host = $this->hosts[$order['uid']];
                }else{
                    $host = Usermember::get($order['uid']);
                    $this->hosts[$order['uid']] = $host;
                }
                if($order['guid']==$order['dguid']){
                $order['gjr'] = Db::name('group_member')->where('uid',$order['guid'])->value('realname');}
                else {
                $order['gjr'] = Db::name('group_member')->where('uid',$order['dguid'])->value('realname');
                }
                $order['host']['uid'] = $host['uid'];
                $order['host']['realname'] = $host['realname'];
                $order['host']['mobile'] = $host['mobile'];
                $order['host']['trust'] = $rz_status[$host['trust']];
                if($order['confirm'] != -1){
                	if($order['confirm']) $confirm='<span style="color:green;">'.$ord_confirm[$order['confirm']].'</span>';
                	else  $confirm='<span style="color:#999;">'.$ord_confirm[$order['confirm']].'</span>';
                	if($order['pay']) $pay='<span style="color:green;">'.$pay_status[$order['pay']].'</span>';
                	else  $pay='<span style="color:#999;">'.$pay_status[$order['pay']].'</span>';
                	if($order['dispatch']) $dispatch='<span style="color:green;">'.$dispatch_status[$order['dispatch']].'</span>';
                	else  $dispatch='<span style="color:#999;">'.$dispatch_status[$order['dispatch']].'</span>';
                	if($order['trade']) $trade='<span style="color:green;">'.$ord_trade[$order['trade']].'</span>';
                	else  $trade='<span style="color:#999;">'.$ord_trade[$order['trade']].'</span>';
                    if($order['trade'] === 1){
                        $order['status_desc'] = '<span style="color:blue;">'.$ord_confirm[$order['confirm']]  . '-' . $pay_status[$order['pay']] . '-' . $dispatch_status[$order['dispatch']] . '-' . $ord_trade[$order['trade']].'</span>';
                    }else{
                        $order['status_desc'] = $confirm . '-' .$pay.'-' .$dispatch. '-' . $trade;
                    }
                }else{
                    $order['status_desc'] = '<span style="color:red;">'.$ord_confirm[$order['confirm']].'</span>';
                }
                if($order['ppsid'] != 0){
                    if($order['ppsid'] == $this->gid){
                        //说明是个外部订单
                        $order['pssname'] = '自身';
                        $order['outside'] = 0;
                    }else{
                        $whereD['psid'] = $order['ppsid'];
                        $whereD['gid'] = $this->gid;
                        $whereD['expdate'] = ['GT',time()];
                        $order['pssname'] = Db::name('distributor')->where($whereD)->value('sname');
                        //已分配给配送商可以重新分配
                       // $order['outside'] = 0;
                        //已分配给配送商不能重新分配
                        $order['outside'] = 1;
                    }
                }else{
                    $order['outside'] = 0;
                }
                if($order['gid']==$this->gid){
                	$order['ly'] = $ord_type[$order['type']];
                }else{
                	$order['ly'] ='外部订单';
                }
                $orders[] = $order;
            }
            $this->assign('orders',$orders);
            $this->assign('ispss',$ispss['code']);
            $this->assign('driverlist',$driverlist['data']);
            $this->assign('pageInfo',$pageInfo);
            return $this->fetch('/order/ajax/order_index');
        }
        //获取当前的用户信息
        $this->assign('ordType',$ord_type);
        $this->assign('ispss',$ispss['code']);
        $this->assign('member',$member);
        $this->assign('driverlist',$driverlist['data']);
        return $this->fetch('/order/order_index');
    }
    //我的订单
    public function my(){
        $member = Db::name('group_member')->find($this->guid);
        $pay_status = config('order_pay_status');
        $rz_status = config('rz_status');
        $ord_confirm = config('order_confirm_status');
        $ord_trade = config('order_trade_status');
        $ord_type = config('order_type');
        if(request()->isAjax()){
            $param = input('post.');
            $uid = isset($param['uid'])?$param['uid']:0;
            $where1 = [];//生成order查询条件1
            $where2 = [];//生成order查询条件2
            $whereOr = [];//生成or查询条件
            $whereOM = [];//生成order_member查询条件
            $whereUM = [];//生成user_member查询条件
            $where1['o.gid'] = $this->gid;
            $where2['o.guid'] = $this->guid;
            if(!!$uid){
                $where2['o.uid'] = $uid;
            }
            if(isset($param['keyword']) && !!($param['keyword'] = trim($param['keyword']))){
                if(isset($param['type']) && $param['type'] === 'number') {
                    $whereOr['o.number'] = ['like', '%' . $param['keyword'] . '%'];
                }
                if(isset($param['type']) && $param['type'] === 'phone') {
                    $whereOM['phone'] = ['like', '%' . $param['keyword'] . '%'];
                }
                if(isset($param['type']) && $param['type'] === 'mobile') {
                    $whereUM['mobile'] = ['like', '%' . $param['keyword'] . '%'];
                }
                if(isset($param['type']) && $param['type'] === 'address') {
                    $whereOM['address'] = ['like', '%' . $param['keyword'] . '%'];
                }
                if(isset($param['type']) && $param['type'] === 'realname') {
                    $whereUM['realname'] = ['like', '%' . $param['keyword'] . '%'];
                }
            }
            if(isset($param['confirm']) && in_array($param['confirm'],['-1','0','1'])) {
                $where2['o.confirm'] = $param['confirm'];
            }
            if(isset($param['pay']) && in_array($param['pay'],['0','1'])) {
                $where2['o.pay'] = $param['pay'];
            }
            if(isset($param['trade']) && in_array($param['trade'],['0','1'])) {
                $where2['o.trade'] = $param['trade'];
            }
            if(isset($param['otype']) && (trim($param['otype']) !== '') && in_array($param['otype'],array_keys($ord_type))) {
                $where2['o.type'] = $param['otype'];
            }
            if(isset($param['csdate']) && !!($csdate = trim($param['csdate']))){
                $csdate = strtotime($csdate);
                $where2['o.adate'] = ['EGT',$csdate];
            }
            if(isset($param['cedate']) && !!($cedate = trim($param['cedate']))){
                $cedate = strtotime($cedate);
                $where2['o.adate'] = ['ELT',$cedate];
            }
            if($csdate && $cedate){
                $where2['o.adate'] = ['BETWEEN',[$csdate,$cedate]];
            }
            if(!empty($whereOM)){
                $oid = Db::name('order_member')
                        ->where($whereOM)
                        ->column('oid');
                $whereOr['o.oid'] = ['IN',  implode(',', $oid)];
            }
            if(!empty($whereUM)){
                $uid = Db::name('user_member')
                        ->field('uid')
                        ->where('gid',$this->gid)
                        ->where($whereUM)
                        ->column('uid');
                $whereOr['o.uid'] = ['IN',  implode(',', $uid)];
            }
            $OrderModel = new OrderModel;
            //设置查询条件
            $res = $OrderModel->getOrdersByWhere2($where1, $where2, $whereOr, $this->rows);
            $pageInfo = [
                'currentPage' => $res->currentPage(),
                'total' => $res->total(),
                'prev' => ($res->currentPage() - 1 >= 1) ? $res->currentPage() - 1 : '',
                'listRows' => $res->listRows(),
                'lastPage' => $res->lastPage(),
                'next' => ($res->currentPage() + 1 <= $res->lastPage()) ? $res->currentPage() + 1 : '',
                'f' => (( $res->currentPage() - 1 ) * $res->listRows() + 1) >= 0 ? (( $res->currentPage() - 1 ) * $res->listRows() + 1) : 0,
                't' => ($res->currentPage() * $res->listRows() > $res->total()) ? $res->total() : $res->currentPage() * $res->listRows()
            ];
            $data = $res->items();
            $orders = [];
            foreach($data as $key=>$vo){
                //对数据进行加工
                $order = $vo->getData();
                $host = Usermember::get($order['uid']);
                $order['host']['realname'] = $host['realname'];
                $order['host']['mobile'] = $host['mobile'];
                $order['host']['trust'] = $rz_status[$host['trust']];
                if($order['confirm'] != -1){
                    if($order['trade'] === 1){
                        $order['status_desc'] = $ord_confirm[$order['confirm']]  . '-' . $pay_status[$order['pay']] . '-' . $ord_trade[$order['trade']];
                    }else{
                        $order['status_desc'] = $ord_confirm[$order['confirm']]  . '-' . $pay_status[$order['pay']] . '-' . $ord_trade[$order['trade']];
                    }
                }else{
                    $order['status_desc'] = $ord_confirm[$order['confirm']];
                }
                $order['ly'] = $ord_type[$order['type']];
                $orders[] = $order;
            }
            $this->assign('orders',$orders);
            $this->assign('pageInfo',$pageInfo);
            return $this->fetch('/order/ajax/order_my');
        }
        //获取当前的用户信息
        $this->assign('ordType',$ord_type);
        $this->assign('member',$member);
        return $this->fetch('/order/order_my');
    }
    //订单列表
    private function getOrderList($whereInput=[],$page=1,$is_admin=1){
        if($is_admin) unset($whereInput['o.guid']);
        $res = Db::name('order o')
        ->field('o.*')
        ->where($whereInput)
        ->order([
            'o.oid' => 'DESC'
        ])
        ->paginate($this->rows);
        $orderList=$res->items();
        $pageInfo = [
            'currentPage' => $res->getCurrentPage(),
            'total' => $res->total(),
            'prev' => ($res->getCurrentPage() - 1 >= 1) ? $res->getCurrentPage() - 1 : '',
            'listRows' => $res->listRows(),
            'lastPage' => $res->lastPage(),
            'next' => ($res->getCurrentPage() + 1 <= $res->lastPage()) ? $res->getCurrentPage() + 1 : '',
            'f' => ($res->getCurrentPage() - 1 ) * $res->listRows() + 1,
            't' => ($res->getCurrentPage() * $res->listRows() > $res->total()) ? $res->total() : $res->getCurrentPage() * $res->listRows()
        ];
        foreach($orderList as $key=>$value){
            $orderList[$key]['order'] =($page-1) * $this->rows + $key + 1;
        }
        return ['data'=>$orderList,'pageInfo'=>$pageInfo];
    }
    public function daohang(){
        $OrderModel = new OrderModel();
        $userModel = new UserMember();
        $GroupModel = new Group();
        $oid = input('param.oid');
        if($oid){
            $orderinfo=$OrderModel->getOneOrder($oid);
            $userinfo=$userModel->getOneUser($this->gid,$orderinfo['uid']);
            if(!$userinfo['x']) $this->error('客户地理位置未标注，无法使用导航功能，请先标注客户地理位置！');
            $this->assign ('userinfo', $userinfo);
            $this->assign ('orderinfo', $orderinfo);
            $this->assign ('xy', $xy);
            return $this->fetch('/daohang');
        }
    }
    /**
     * 获取配送司机
     */
    private function getsjname($oid){
    	if($oid){
    		$guids=Db::name('bind_order')->where('oid',$oid)->where('gid',session('gid'))->column('guid');
    		if(is_array($guids)){
    			$uname='';
	    		foreach ($guids as $guid){
	    			$u=Db::name('group_member')->where('uid',$guid)->value('realname');
	    			$uname.=$u.',';
	    		}    		
    			$uname=substr($uname,0,-1);
    			return $uname;
    		}else{
    			return '';
    		}
    	}else{
    		return '';
    	}    	
    }
    /**
     * 订单详情
     */
    public function detail(){
    	$where = [];
        $orderinfo = [];
        $oid = input('param.oid');
        $guid = $this->guid;
        $gm = Db::name('group_member')->where('gid',$this->gid)->where('uid',$guid)->find();
    	$where['oid'] = $oid;
        if(!!$gm['is_admin']){
            $where['ppsid|gid'] = $this->gid;
        }else{
            $where['gid'] = $this->gid;
        }
    	$OrderModel = new OrderModel;
        $OrderGoodsModel = new OrderGoods;
    	$order=$orderinfo['order'] =  $OrderModel->where($where)->find()->getData();
    	
    	//print_r($orderinfo);
    	$dhr =Db::name('user_member')
                ->where('uid',$orderinfo['order']['uid'])
                ->find();
        $orderinfo['nextgroup_show'] = true;
        if(!empty($dhr) && $orderinfo['order']['ppsid'] != $this->gid){
            if(!!$dhr['rankid'] ){
                $dhr['rankname'] = Db::name('user_rank')->where('id',$dhr['rankid'])->value('rank_name');
            }
            if(!!$dhr['tpid'] ){
                $dhr['type'] = Db::name('user_type')->where('id',$dhr['tpid'])->value('title');
            }
            if(!!$dhr['shopid'] ){
                $dhr['clients'] = Db::name('oauth_clients')->where('id',$dhr['shopid'])->value('name');
            }
            //说明该订单已授权给其他经销商配送，一旦授权给其他经销商订单，那么订单就不可操作！
            if(!!($nextgid = $orderinfo['order']['ppsid'])){
                $whereD['psid'] = $nextgid;
                $whereD['gid'] = $this->gid;
               // print_r($whereD);
                $orderinfo['nextgroup'] = Db::name('distributor')->where($whereD)->find();
            }
        }
        $orderinfo['dhr'] =$dhr;
        unset($whereD);
    	$orderinfo['shr'] =Db::name('order_member')->where('oid',$oid)->find();
    	$orderinfo['postnum'] =Db::name('order_post')->where('oid',$oid)->count();
        $orderinfo['goodsnum'] =  $OrderGoodsModel->where('oid',$oid)->count(1);
        if($orderinfo['order']['ppsid'] == $this->gid){
            //说明这个订单是被分配下来的，那么去获取订单的上级经销商信息！
            //$whereD['psid'] =  $this->gid;
            $whereD['gid'] = $orderinfo['order']['gid'];
            $orderinfo['prevgroup'] = Db::name('group')->where($whereD)->find();
            $orderinfo['nextgroup_show'] = false;
        }
        //是否有配送商
        $ispss=$this->getnextgroups();
        if($ispss['code']==0){
        	$orderinfo['nextgroup_show'] = false;
        }
        //是否分配司机
        $orderinfo['is_fpsj']=Db::name('bind_order')->where('oid',$oid)->where('gid',$this->gid)->where('type',1)->value('guid');
    	if($orderinfo['is_fpsj']){
    		$orderinfo['psy']=Db::name('group_member')->where('uid',$orderinfo['is_fpsj'])->find();
    	}else{
    		$orderinfo['is_fpsj']=0;
    	}
        $orderinfo['goods'] = $this->orderGoods($oid);
        $confirm_status = config('order_confirm_status');
        $dispatch_status = config('order_dispatch_status');
        $pay_status = config('order_pay_status');
        $trade_status = config('order_trade_status');
        
        if($order['confirm'] != -1){
        	if($order['confirm']) $confirm='<span style="color:green;">'.$confirm_status[$order['confirm']].'</span>';
        	else  $confirm='<span style="color:#999;">'.$confirm_status[$order['confirm']].'</span>';
        	if($order['pay']) $pay='<span style="color:green;">'.$pay_status[$order['pay']].'</span>';
        	else  $pay='<span style="color:#999;">'.$pay_status[$order['pay']].'</span>';
        	if($order['dispatch']) $dispatch='<span style="color:green;">'.$dispatch_status[$order['dispatch']].'</span>';
        	else  $dispatch='<span style="color:#999;">'.$dispatch_status[$order['dispatch']].'</span>';
        	if($order['trade']) $trade='<span style="color:green;">'.$trade_status[$order['trade']].'</span>';
        	else  $trade='<span style="color:#999;">'.$trade_status[$order['trade']].'</span>';
        	if($order['trade'] === 1){
        		$orderinfo['order']['status_desc'] = '<span style="color:blue;">'.$confirm_status[$order['confirm']]  . '-' . $pay_status[$order['pay']] . '-' . $dispatch_status[$order['dispatch']] . '-' . $trade_status[$order['trade']].'</span>';
                }else{
        		$orderinfo['order']['status_desc'] = $confirm . '-' .$pay.'-' .$dispatch. '-' . $trade;
        	}
        }else{
        	$orderinfo['order']['status_desc'] = '<span style="color:red;">'.$confirm_status[$order['confirm']].'</span>';
        }
        $expresslist=Db::name('express')->where('is_use',1)->order('sort desc')->select();
        $sjname=$this->getsjname($oid);
        $this->assign('sjname',$sjname);
        $this->assign('expresslist',$expresslist);
        $this->assign('confirm_status',$confirm_status);
        $this->assign('dispatch_status',$dispatch_status);
        $this->assign('pay_status',$pay_status);
        $this->assign('trade_status',$trade_status);
        $this->assign('orderinfo',$orderinfo);
        $this->assign('memberuserinfo',session('userinfo'));
        return $this->fetch('/order/order_detail');
    }
    public function orderGoods(){
        if(request()->isAjax() && request()->isPost()){
            $OrderGoodsModel = new OrderGoods;
            $OrderModel = new OrderModel;
            $oid = input('param.oid');
            $map['oid'] = $oid;
            if(!$oid){ 
                $this->assign('total',0);
                $this->assign('pageInfo',false);
                $this->assign('goods',false);
                return $this->fetch('order/ajax/order_goods');
            }
            $guid = $this->guid;
            $gm = Db::name('group_member')->where('gid',$this->gid)->where('uid',$guid)->find();
            $where['oid'] = $oid;
            if(!!$gm['is_admin']){
                $where['ppsid|gid'] = $this->gid;
            }else{
                $where['gid'] = $this->gid;
            }
            if(!($order = $OrderModel->where($where)->find()->getData())){
                $this->assign('order',false);
                $this->assign('pageInfo',false);
                $this->assign('goodsList',false);
                return $this->fetch('order/ajax/order_goods');
            }
            $order_goods_status = config('order_goods_status');
            $res=  $OrderGoodsModel->getOrdersGoodsByWhere($map,$this->rows);
            $selectResult = $res->items();
            $pageInfo = pageInfo($res);
            $goods = [];
            foreach($selectResult as $key=>$vo){
                $goods[$key] = $vo->getData();
                $goodsinfo=Db::name('goods')->where('goods_id',$goods[$key]['goodid'])->field('goods_thumb,goods_img')->find();
                if($goodsinfo['goods_thumb']){
                    $goodimg=$goodsinfo['goods_thumb'];
                }else{
                    $goodimg=$goodsinfo['goods_img'];
                }
                $goods[$key]['goods_img'] = $goodimg;
                $goods[$key]['goods_type'] =$order_goods_status[$goods[$key]['type']];
            }
            $this->assign('order',$order);
            $this->assign('pageInfo',$pageInfo);
            $this->assign('goodsList',$goods);
            return $this->fetch('order/ajax/order_goods');
        }
    }
    /**
     * 订单商品
     */
    public function bjCat(){
        if(request()->isAjax()){
            //获取所有的报价类别
            $where['gid'] = $this->gid;
            $where['pid'] = 0;
            $p = Db::table('ljk_category')->field('id,title')->where($where)->select();
            $data = [];
            foreach($p as $k=>$v){
                $where['pid'] = $v['id'];
                $c = Db::table('ljk_category')->field('id,title')->where($where)->select();
                $data[] = ['p'=>$v,'c'=>$c];
            }
            return json(['code'=>1,'data'=>$data]);
        }
    }
    private function goods(){
        $order_goods_id = input('param.id');
        $oid = input('param.oid');
        $where = [];
        $where['gid'] = $this->gid;
        $where['status'] = ['<>',-1];
        $where['oid'] =$oid;
        $OrderModel = new OrderModel;
        $OrderGoodsModel = new OrderGoods;
        $orderinfo =  $OrderModel->where($where)->find()->getData();
        $map['oid'] = $oid;
        $map['id'] = $order_goods_id;
        $selectResult =  $OrderGoodsModel->where($map)->find()->getData();
        $whereu['status']=1;
        $whereu['unitgid']=$selectResult['unitgid'];
    //    $whereu['gid']=$this->gid;
        $orderinfo['unitlist']=Db::name('unit')->where($whereu)->select();
        $coefficient=Db::name('unit')->where('id',$selectResult['unitid'])->value('coefficient');
        if($coefficient) $selectResult['unitprice']=$selectResult['price']/$coefficient;
        $order_goods_status = config('order_goods_status');
        $orderinfo['adate']=date('Y-m-d H:i',$orderinfo['adate']);
        $orderinfo['shr'] =Db::name('order_member')->where('oid',$orderinfo['oid'])->find();
        $orderinfo['postnum'] =Db::name('order_post')->where('oid',$orderinfo['oid'])->count();
        $orderinfo['dhr'] =Db::name('user_member')->where('uid',$orderinfo['uid'])->find();
        $goodsinfo=Db::name('goods')->where('goods_id',$selectResult['goodid'])->field('goods_thumb,goods_img')->find();
        if($goodsinfo['goods_img']){
        	$goodsinfo['goods_img'] = mkgoodsimgurl(['url'=>$goodsinfo['goods_img']]);
        }
        if($goodsinfo['goods_thumb']){
        	$goodsinfo['goods_thumb'] = mkgoodsimgurl(['url'=>$goodsinfo['goods_thumb']]);
        }
        $selectResult['goods_type'] =$order_goods_status[$selectResult['type']];
        $return['total'] = count($selectResult);  //总数据
        $return['data'] = $orderinfo;
        $return['data']['order_goods'] = $selectResult;
        $return['data']['order_goods_status'] = $order_goods_status;
        $return['data']['goods'] = $goodsinfo;
        $return['code'] =1;
        $return['msg'] ='数据加载成功！';
        if(!$orderinfo){
           $return['code'] =0;
           $return['msg'] ='没有数据！';
        }
        return json($return);
    }
    /**
    * 订单跟进详情
    */
    public function post(){
        if(request()->isAjax() && request()->isPost()){
            $param = input('param.');
            $oid = input('param.oid');
            if(!$oid) $oid=session('oid');
            $where = [];
            $where['oid'] = $oid;
            $pay_status = config('order_pay_status');
            $order_confirm_status = config('order_confirm_status');
            $order_dispatch_status = config('order_dispatch_status');
            $order_trade_status = config('order_trade_status');
            $res =  Db::name('order_post')->where($where)->order(['adate'=>'DESC'])->paginate($this->rows);
            $pageInfo = pageInfo($res);
            $selectResult = $res->items();
            $uid=Db::name('order')->where('oid',$oid)->value('uid');
            $posts = [];
            foreach($selectResult as $key=>$vo){
                $vo['adate'] = date('Y-m-d H:i:s', $vo['adate']);
                if(!$vo['guid']){
					if($vo['uid']){
						$vo['gmrealname'] ='客户：'. Db::name('user_member')->where('uid',$vo['uid'])->value('realname');
					}else{
						$vo['gmrealname'] ='客户：'. Db::name('user_member')->where('uid',$uid)->value('realname');
					}
                }else{
                    $vo['gmrealname'] = Db::name('group_member')->where('uid',$vo['guid'])->value('realname');
                }
                $vo['pay'] = $pay_status[$vo['pay']];
                $vo['confirm'] = $order_confirm_status[$vo['confirm']];
                $vo['dispatch'] = $order_dispatch_status[$vo['dispatch']];
                $vo['trade'] = $order_trade_status[$vo['trade']];
                $pid = $vo['id'];
                $lbs = Db::name('group_memberlbs')->field('business,street,street_number,province,city,district,address')->where('pid',$pid)->where('type',2)->find();
                if(!empty($lbs)){
                    $vo['lbs'] = $lbs;
                }else{
                    $vo['lbs'] = null;
                }
                //获取这个跟进记录的图片信息
                if(!!$vo['thumb']){
                	$url=$_SERVER['HTTP_HOST'];
                	$urls=explode('.',$url);
                	if($urls[1]=='b') $url='http://'.$urls[0].'.'.$urls[1].'.m.'.$urls[2].'.'.$urls[3];
                	else $url='http://'.$urls[0].'.m.'.$urls[1].'.'.$urls[2];
                    $imgsid = explode('_', $vo['thumb']);
                    $unique  = array_unique($imgsid);
                    $imgsurl = [];
                    $imgsurlu = [];
                    foreach($unique as $key1=>$val2){
                        $imgsurlu[$val2] = mkurl(Db::table('ljk_file')->field('url,savename,savepath,ext')->find($val2));
                    }
                    for($i=0;$i<count($imgsid);$i++){
                        $imgsurl[] = $url.$imgsurlu[$imgsid[$i]];
                    }
                   
                    $vo['imgsurl'] = $imgsurl;
                }
                $posts[] = $vo;
            }
            $this->assign('pageInfo',$pageInfo);
            $this->assign('posts',$posts);
            return $this->fetch('/order/ajax/order_post');
        }
    }
    /**
     * 订单配送商
     */
    public function distributor(){
        if(request()->isPost()){
           $oid = input('param.id');
		   if(!$oid) $oid=session('oid');
           if($oid){ 
            $where['gid'] = $this->gid;
            $where['oid'] =$oid;
            $OrderModel = new OrderModel;
            $orderinfo =  $OrderModel->where($where)->find();
            $distributorInfo= Db::name('distributor')->where('psid',$orderinfo['ppsid'])->find();
            if($distributorInfo){
                    return json(['code' => 1, 'data' => $distributorInfo, 'msg' => "数据加载成功！"]);
                }else{
                    return json(['code' => 0, 'data' =>'', 'msg' => "没有配送商！"]);
                }
            }else{
                return json(['code' => 0, 'data' =>'', 'msg' => "订单有误或配送商不存在！"]);
            }
        }		
    }	
    /**
     * 修改订单商品
     */
    public function editgoods(){
        if(request()->isPost()){
            $param = input('post.');
            if(isset($param['action']) && $param['action'] === 'showgoods'){
                return $this->goods();
            }
            $param['amount']=$param['num']*$param['price'];
            $oid= $param['oid'];
            $OrderGoods = new OrderGoods;
            $OrderModel = new OrderModel;
            $orderinfo=$OrderModel->getOneOrder($oid);
            $oldgoods = $OrderGoods->getOneOrderGoods($param['id']);
            $flag=$OrderGoods->editOrderGoods($param);
            if($flag['code']==1){
		$postModel = new OrderPost;
		$oldogtype=Db::name('order_goodstype')->where('id',$oldgoods['type'])->value('title');
		$lx=' 销售类型：'.$oldogtype;
		$newogtype=Db::name('order_goodstype')->where('id',$param['type'])->value('title');
                $xlx=' 销售类型：'.$newogtype;
		if($oldgoods['unit']) $danwei1='单位：'.$oldgoods['unit'];
		if($param['unit']) $danwei2='单位：'.$param['unit'];
		if($oldgoods['desc']||@$newgoods['desc']) $beizhu1='<br/>备注：'.$oldgoods['desc'];
		if($param['desc']) $beizhu2=' <font color="#FF0000">修改为</font> '.$param['desc'];
		$content = '订单商品「'.$oldgoods['name'].'」<br/>单价：'.$oldgoods['price'].' 数量：'.$oldgoods['num'].$danwei1.$lx.' <font color="#FF0000">修改为</font> 单价：'.$param['price'].' 数量：'.$param['num'].$danwei2.$xlx.@$beizhu1.@$beizhu2;
		$adate = time();
		$oidPost=array('oid'=>$oid,'gid'=>session('gid'),'thumb'=>'','content'=>$content, 'guid'=>session('guid'), 'adate'=>$adate);
		Db::name('order_post')->insert($oidPost);
		$fromid = Db::name('order_post')->getLastInsID();
                if($fromid){
                    addgps(session('guid'),$oid,$fromid,2);
                }
		//更新总金额
                $amounts = $OrderGoods->where('oid',$oid)->select();
                foreach ($amounts as $amount) {
                    $amounts[] = $amount['amount'];
                }
                $total = array_sum($amounts);
                $OrderModel->editOrder(array('oid'=>$oid, 'confirm'=>0, 'pay'=>0, 'total'=>$total));
                if(!!$orderinfo['ddid']){
                    //重新计算分成信息
                    $separate = controller('fx/Separate','controller');
                    $postData = ['oid'=>$oid];
                    $sign = new Sign();
                    $postData = array_merge($postData,$sign->mkSign($postData));
                    $separate->insideIndex($postData);
                }
                $message = '修改订单商品成功，订单状态已重置！';
                $this->assign('message',$message);
                $this->assign('autoClose',['sign'=>1,'time'=>2000]);
                $this->assign('nextAct',['sign'=>1,'action'=>'getList({sign:"orderPost"});showLA("/admin/order/editstatus","orderCurrentStatus",{oid:"'. $oid .'",action:"show"});']);
                return $this->fetch('/common/atip');
            }else{
                $this->assign('message',$flag['msg']);
                $this->assign('autoClose',['sign'=>1,'time'=>2000]);
                return $this->fetch('/common/atip');
            }
           // $arrtips['msg'] = '修改订单商品成功，订单状态已重置！';
           // $arrtips['autoClose'] = ['sign'=>1,'time'=>2000];
           // $arrtips['nextAct'] = ['sign'=>1,'action'=>'getList({sign:"orderPost"});showLA("/admin/order/editstatus","orderCurrentStatus",{oid:"'. $oid .'",action:"show"});'];
           // return $this->tips($arrtips);
           // }else{
           // 	$arrtips['msg'] = $flag['msg'];
           // 	$arrtips['autoClose'] = ['sign'=>1,'time'=>2000];
           // 	return $this->tips($arrtips);
        }
    }
    public function addPost(){
        if(request()->isPost()){
            $post = request()->post();
            $express_company = $post['express_company']?$post['express_company']:'';
            if($express_company){
            	$ec=explode(':', $express_company);
            	$ecstr=$ec[1]?$ec[1].' 单号:':'';
            }else{
            	$ecstr='';
            }
            $express_no = $post['express_no']?$post['express_no']:'';
            $content = $ecstr.$express_no.$post['content'];
            $oid = $post['oid'];
            if(!$oid){
                $message = '非法操作！！';
                $close = array('sign'=>true);
                $this->assign('message',$message);
                $this->assign('close',$close);
                $this->assign('nextAct',$nextAct);
                return $this->fetch( '/common/atip');
            }
            $uploadImg = controller('uploadImg','event');
        	$file = request()->file('thumb');
    	     print_r($file);
            if($file){
                $imgsid = $uploadImg->index($file);
            }else{
                $imgsid['id'] = '';
            }
            //print_r($imgsid); exit;
            //获取该订单的状态信息
            $OrderModel = new OrderModel;
            $status = $OrderModel->field('confirm,status,pay,ppsid,trade,dispatch')->find($oid)->getData();
            $oidPost = [
                'content'=>$content,
                'oid'=>$oid,
                'gid'=>session('gid'),
                'guid'=>$this->guid,
                'type'=>$post['type']?$post['type']:0,
                'express_company'=>$express_company,
                'express_no'=>$express_no,
                'imgsid'=>$imgsid['id'],
                'confirm'=>$status['confirm'],
                'pay'=>$status['pay'],
                'status'=>$status['status'],
                'trade'=>$status['trade'],
                'ppsid'=>$status['ppsid'],
                'dispatch'=>$status['dispatch'],
                'adate'=>time(),
            ];
            //这一步对数据进行验证,Validate
            if($imgsid['id']){
	            $imgsid = array_count_values(explode('_',$imgsid['id']));
	            foreach($imgsid as $k=>$v){
	                Db::table('__FILE__')->where('id',$k)->setInc('cited',$v);//图片引用数据设置
	            }
            }
            Db::name('order_post')->insert($oidPost);
            $fromid = Db::name('order_post')->getLastInsID();
            if($fromid){
                addgps(session('guid'),$oid,$fromid,2);
                $message = '订单跟进成功！';
                $nextAct = ['sign'=>true,'action'=>'getList({sign:"orderPost"});'];
                $autoClose = ['sign'=>true,'time'=>'1000'];
                $this->assign('message',$message);
                $this->assign('nextAct',$nextAct);
                $this->assign('autoClose',$autoClose);
                return $this->fetch( '/common/atip');
            }else{
                $message = '订单跟进失败！';
                $close = array('sign'=>true);
                $this->assign('message',$message);
                $this->assign('close',$close);
                return $this->fetch( '/common/atip');
            }      
        }else{
            $message = '非法操作！';
            $close = array('sign'=>true);
            $this->assign('message',$message);
            $this->assign('close',$close);
            return $this->fetch( '/common/atip');
        }
    }
    public function editStatus(){
    	if(!($oid = request()->post('oid'))){
            $message = '非法操作！';
            $this->assign('message',$message);
            $this->assign('close',['sign'=>1]);
            return $this->fetch('/common/atip');
    	}
    	$OrderModel = new OrderModel;
    	$oldStatus = $OrderModel->field('confirm,pay,ppsid,trade,dispatch')->find($oid)->getData();
        if(request()->isAjax() && input('action') === 'show'){
            //获取当前订单的状态
            return json(
                [
                    'data'=>$oldStatus,
                    'code'=>1
                ]
            );
    	}
    	if($oldStatus['confirm'] == -1 || $oldStatus['trade'] == 1){
            $message = '无法对无效或已结束订单进行任何操作！';
            $this->assign('message',$message);
            $this->assign('close',['sign'=>1]);
            return $this->fetch('/common/atip');
    	}
    	if(!!$oldStatus['ppsid']&&$oldStatus['ppsid']!=$this->gid){
            $message = '该订单已分配给配送商操作，你已无权操作该订单！';
            $this->assign('message',$message);
            $this->assign('close',['sign'=>1]);
            return $this->fetch('/common/atip');
    	}
    	if(request()->isAjax() && input('action') === 'edit'){
            $post = request()->post();
            unset($post['action']);
            unset($oldStatus['ppsid']);
            $status = $post;
            //第一步先更新订单状态
            if($status['confirm'] == -1){
                $status['trade'] = 1;//交易结束
                $status['pay'] = 0;
                $status['dispatch'] = 0;
            }else if((@$status['pay'] == 1 || @$status['dispatch'] != 0) && @$status['confirm'] == 0){
                //如果已经是付款状态那么 不能将确认状态改为非确认状态！
                $message = '无法对确认状态进行更改，如需更改请先将付款状态设为未付款及配送状态设为未配送！';
                $this->assign('message',$message);
                $this->assign('close',['sign'=>1]);
                return $this->fetch('/common/atip');
            }
            $rs = $OrderModel->editOrderStatus($status,$oid);
            if($rs['code'] === 1){
                $desc = '';
                foreach($oldStatus as $k=>$v){
                	if(isset($status[$k])){
	                    if($oldStatus[$k] != $status[$k]){
	                        //说明这个状态有变化
	                        $st = config('order_'.$k.'_status');
	                        $desc .= $st[$oldStatus[$k]].'->'.$st[$status[$k]] . ',';
	                    }
                	}
                }
                $desc = trim($desc, ',');
                $status = $OrderModel->field('confirm,status,pay,ppsid,ddid,trade,dispatch')->find($oid)->getData();
                $oidPost = [
                    'content'=>'修改状态:'.$desc,
                    'oid'=>$oid,
                    'gid'=>session('gid'),
                    'guid'=>$this->guid,
                    'thumb'=>'',
                    'confirm'=>$status['confirm'],
                    'pay'=>$status['pay'],
                    'status'=>$status['status'],
                    'trade'=>$status['trade'],
                    'ppsid'=>$status['ppsid'],
                    'dispatch'=>$status['dispatch'],
                    'adate'=>time(),
                ];
                $OrderPost = new OrderPost;
                $OrderPost->addPost($oidPost);
                if(($oldStatus['pay']==0&&$status['pay']==1)||($oldStatus['pay']==1&&$status['pay']==0)){
	                //去更新积分信息
	                $this->updateScore($oid);
                }
                //去更新易品保订单信息
                if(!!$status['ddid']){
                    //设置订单状态信息
                    $separate = controller('fx/Separate','controller');
                    $postData = ['oid'=>session('oid')];
                    $sign = new Sign();
                    $postData = array_merge($postData,$sign->mkSign($postData));
                    $res = $separate->insideIndex($postData);
                }
                $message = '订单状态修改成功！';
                $this->assign('message',$message);
                $this->assign('autoClose',['sign'=>1,'time'=>50]);
                $this->assign('nextAct',['sign'=>1,'action'=>'getList({sign:"orderPost"});showLA("/admin/order/editstatus","orderCurrentStatus",{oid:"'. $oid .'",action:"show"});']);
                return $this->fetch('/common/atip');
            }else{
                $message = '订单状态修改失败！';
                $this->assign('message',$message);
                $this->assign('close',['sign'=>1]);
                return $this->fetch('/common/atip');
            }
    	}
    }
    //批量编辑司机
    private function PlEditDriver(){
    	$input=input('param.');
    	$oids=isset($input['oid'])?$input['oid']:0;
    	$guid=isset($input['guid'])?$input['guid']:0;
    	if($oids){
    		$des=0;
    		foreach($oids as $id){
    			$s=$this->doAuthDriver($id,$guid);
    			if($s['code']==-1) $des++;
    		}
    		if($des>0) {
    			$des=$des.'个订单分配司机失败,请先确认订单后重新分配。';
    		}else{
    			$des='';
    		}
    		$message = '批量分配司机完成！'.$des;
    		$this->assign('message',$message);
    		$this->assign('close',['sign'=>1]);
    		$this->assign('refresh',['sign'=>1,'time'=>1500]);
    		return $this->fetch('/common/atip');
    	}else{
    			$message = '请选择要分配司机的订单！';
    			$this->assign('message',$message);
    			$this->assign('close',['sign'=>1]);
    			return $this->fetch('/common/atip');
    		}
    }
    //批量编辑确认状态
    private function PlEditConfirmStatus(){
    	$input=input('param.');
    	if($input['confirm']===''&&$input['pay']===''&&$input['dispatch']===''&&$input['trade']===''){
    		$message = '请选择要修改的订单状态！';
    		$this->assign('message',$message);
    		$this->assign('close',['sign'=>1]);
    		return $this->fetch('/common/atip');
    	}else{
    		$oids=isset($input['oid'])?$input['oid']:0;
    		$OrderModel = new OrderModel;
    		$OrderPost = new OrderPost;
    		if($oids){
	    		foreach($oids as $id){
	    			if($input['confirm']!=='') $dataUO['confirm'] =$input['confirm'];
	    			if($input['pay']!=='') $dataUO['pay'] =$input['pay'];
	    			if($input['dispatch']!=='') $dataUO['dispatch'] =$input['dispatch'];
	    			if($input['trade']!=='') $dataUO['trade'] =$input['trade'];
	    			if($input['confirm']==-1) $dataUO['trade'] =1;
	    			$map['oid']=$id;
	    			$map['gid|ppsid']=session('gid');
	    		//	$map['trade']=['neq',1];
	    			$oldStatus = $OrderModel->field('confirm,pay,trade,dispatch')->where($map)->find()->getData();
	    			Db::name('order')->where($map)->update($dataUO);
	    		//	$rs = $OrderModel->editOrderStatus($dataUO,$id);
	    		//	print_r($rs);
	    		//	if($rs['code'] === 1){	    				
	    				$status = $OrderModel->field('confirm,status,pay,ppsid,ddid,trade,dispatch')->where($map)->find()->getData();
	    				//print_r($status);
	    				$desc = '';
	    				foreach($oldStatus as $k=>$v){
	    					if(isset($status[$k])){
	    						if($oldStatus[$k] != $status[$k]){
	    							//说明这个状态有变化
	    							$st = config('order_'.$k.'_status');
	    							$desc .= $st[$oldStatus[$k]].'->'.$st[$status[$k]] . ',';
	    						}
	    					}
	    				}
	    				$desc = trim($desc, ',');
	    			    $oidPost = [
	    				'content'=>'修改状态:'.$desc,
	    				'oid'=>$id,
	    				'gid'=>session('gid'),
	    				'guid'=>$this->guid,
	    				'thumb'=>'',
	    				'confirm'=>$status['confirm'],
	    				'pay'=>$status['pay'],
	    				'status'=>$status['status'],
	    				'trade'=>$status['trade'],
	    				'ppsid'=>$status['ppsid'],
	    				'dispatch'=>$status['dispatch'],
	    				'adate'=>time(),
	    				];	    				
	    				$OrderPost->addPost($oidPost);
	    				if(($oldStatus['pay']==0&&$status['pay']==1)||($oldStatus['pay']==1&&$status['pay']==0)){
	    					//去更新积分信息
	    					$this->updateScore($id);
	    				}
	    		//	}
	    			unset($map);
	    		}
	    		$message = '订单状态修改成功！';
	    		$this->assign('message',$message);
	    		$this->assign('close',['sign'=>1]);
	    		//$this->assign('refresh',['sign'=>1,'time'=>1500]);
	    		return $this->fetch('/common/atip');
    		}else{
    			$message = '请选择要修改的订单！';
    			$this->assign('message',$message);
    			$this->assign('close',['sign'=>1]);
    			return $this->fetch('/common/atip');
    		}
    	}
    } 
    private function updateScore($oid){
        //获取订单商品信息
        if(!!$oid){
            $where = [];
            $g = [];
            $ml = 0;
            $score = 0;
            $where['gid'] = $gid = $this->gid;
            $where['oid'] = $oid;
            $goodslist = Db::name('order_goods')->where($where)->select();
            $o = Db::name('order')->field('uid,status,confirm,trade,pay,dispatch')->where($where)->find();
            if(!empty($o)){
                $uid = $o['uid'];
                $status = $o['status'];
                $trade = $o['trade'];
                $confirm = $o['confirm'];
                $pay = $o['pay'];
                $dispatch = $o['dispatch'];
            }else{
                return 0;
            }
            $userInfo = Db::name('user_member')->find($uid);
            $rankid = $userInfo['rankid'];
            foreach($goodslist as $goods){
                if($goods['price'] > 0){
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
            $pay_point = $this->JF(['m'=>$ml,'gid'=>$gid,'t'=>2]);
            if($pay_point){
                //更新积分信息
                if($pay){
                    Db::name('user_member')->where(['uid'=>$uid])->setInc('pay_points',$pay_point);
                }else{
                    Db::name('user_member')->where(['uid'=>$uid])->setDec('pay_points',$pay_point);
                }
            }
        }else{
            return 0;
        }
    }
    private function profit($goods){
        $whereBJ = [];//报价信息查询条件
        $bj = [];//当前商品报价信息
        if(isset($goods['goods_id']) && !!$goods['goods_id']){
            $goods_id = $goods['goods_id'];
            $whereBJ['goods_id'] = $goods_id;
        }else{
            return 0;
        }
        if(isset($goods['unitid']) && !!$goods['unitid']){
            $unitid = $goods['unitid'];
        }else{
            return 0;
        }
        if(isset($goods['gid']) && !!$goods['gid']){
            $whereBJ['gid'] = $goods['gid'];
        }else{
            return 0;
        }
        if(isset($goods['goods_number']) && !!$goods['goods_number']){
            $number = intval($goods['goods_number']);
        }else{
            return 0;
        }
        if(isset($goods['price']) && !!$goods['price']){
            $price = $goods['price'];
        }else{
            return 0;
        }
        if(isset($goods['rank_id']) && !!$goods['rank_id']){
            $rank_id = $goods['rank_id'];
            $whereBJ['rank_id'] = $goods['rank_id'];
        }
        //获取报价信息
        if(isset($rank_id) && !!$rank_id){
            //获取商品的报价信息
            $bj = Db::name('baojia_rank')->where($whereBJ)->find();
        }
        if(empty($bj)){
            unset($whereBJ['rank_id']);
            $bj = Db::name('baojia')->where($whereBJ)->find();
        }
        if(empty($bj)){
            return 0;
        }
        if($bj['cbprice'] == 0){
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
    private function JF($config){
        //初始化各计算要素
        $gid = isset($config['gid']) ? $config['gid'] : 0;
        $m = isset($config['m']) ? $config['m'] : 0; //成交 单价金额
        $t = isset($config['t']) ? $config['t'] : 0;//成交类型 
        //调取积分数据库信息
        if(empty($gid) || !$gid){
            return ['code'=>0,'msg'=>'非法操作！'];
        }
        $c = 0;//系数
        $pInfo = Db::name('point')->where(['gid'=>$gid])->find();
        if(empty($pInfo) || !$pInfo['status']){
            return 0;
        }
        $b = $pInfo['base_ratio'];
        switch ($t){
            case 0:$c = $pInfo['wei_ratio'];break;
            case 1:$c = $pInfo['tel_ratio'];break;
            case 2:$c = $pInfo['ywy_ratio'];break;
            case 3:$c = $pInfo['pc_ratio'];break;
            case 4:$c = $pInfo['d_ratio'];break;
            default:$c = 0;
        }
        return round($m * $b * $c * 0.01);
    }
    /*
    private function bjGoods(){
        $action = input('action');
        switch($action){
            case 'bjgoods':return $this->bjGoods2();break;
            case 'bjcat':return $this->bjCat();break;
            case 'aogrog':return $this->aogrog();break;
            case 'bjinfo':return $this->bjInfo();break;
            default :return json(['code'=>-1,'msg'=>'非法操作！']);
        }
    }
    private function bjGoods2(){ 
    
    	if(request()->isAjax()){
    		$var = request()->post();
    		if(isset($var['type']) && !empty($var['type'])){
    			switch ($var['type']){
    				case 0:null;break;
    				case 1:return $this->aogrog();break;
    				default:null;
    			}
    		}
    		if(!isset($var['t'])){
    			$var['t'] = '';
    		}
    		if(!isset($var['cid'])){
    			$var['cid'] = '';
    		}
    		if(isset($var['oid']) && !empty($var['oid'])){
    			session('oid',$var['oid']);//更新session oid
    		}
    		$t = $var['t'];
    		$cid = $var['cid'];
    		$limit = $var['limit'];
    		$p = intval($var['p']);
    		$offset = ($p - 1) * $limit;
    		$oid = session('oid');
    		//通过oid去获取uid
    		$u= Db::name('order')->field('uid')->find($oid);
    		//判断这个用户存不存在
    		if(!empty($u)){
    			$uid = $u['uid'];
    			$where = [];
    			$whereR = [];
    			$whereOr = [];
    			$BJ = [];//这里返回报价信息到首页
    			//通过uid去获取等级
    			if($cid){
    				//判定是否有子分类
    				$ccid = Db::name('category')
    				->where('pid',$cid)
    				->where('gid',$this->gid)
    				->column('id');
    				if(!empty($ccid)){
    					array_push($ccid,$cid);
    					$ccids = implode(',',$ccid);
    					$whereC['categoryid'] = ['IN',$ccids];
    				}else{
    					$whereC['categoryid'] = $cid;
    				}
    				//获取报价分类在这个范围内的报价产品
    				$bid = Db::name('baojia_cat')
    				->where($whereC)
    				->where('gid',$this->gid)
    				->column('bid');
    				if(!empty($bid)){
    					$where['b.id'] = ['IN',$bid];
    					$whereCount['id'] = ['IN',$bid];
    					//如果bid存在，但是报价已经被删除了 说明这个报价分类多余，应该删除，但是如果有文字筛选条件，那么不能确定是否作处理
    				}else{
    					//直接退出了
    					$return['code'] =0;
    					$return['p'] = $p;
    					$return['msg'] ='没有更多数据！';
    					$return['data'] = null;
    					$return['total'] = 0;
    					return json($return);
    				}
    			}
    			$where['b.gid'] = $this->gid;
    			$whereOrG['goods_name'] = $whereOr['g.goods_name'] = ['LIKE', '%' . $t . '%'];
    			$whereOrG['goods_sn'] = $whereOr['g.goods_sn'] = ['LIKE', '%' . $t . '%'];
    			//获取符合条件的所有goods_id
    			if($t!==''){
    				$goods_ids1 = Db::name('goods')->whereOr($whereOrG)->column('goods_id');
    				//过滤$goods_ids;
    				if(!empty($goods_ids1)){
    					//优化$goods_ids
    					//先查询出这个gid下报价商品的所有gid;
    					$bj_goods_ids = Db::name('baojia')->where('gid',$this->gid)->column('goods_id');
    					$goods_ids = [];
    					foreach($bj_goods_ids as $k=>$v){
    						if(in_array($v,$goods_ids1)){
    							$goods_ids[] = $v;
    						}
    					}
    				}else{
    					//直接退出了
    					$return = ['code'=>0,'p'=>$p,'msg'=>'没有更多数据！','data'=>[],'total'=>0];
    					return json($return);
    				}
    				if(!empty($goods_ids)){
    					$whereCount['gid'] = $this->gid;
    					$where['b.goods_id'] = $whereCount['goods_id'] = ['IN',$goods_ids];
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
                                    ->join('ljk_goods g','b.goods_id=g.goods_id','LEFT')
                                    ->where($where)
                                    ->where(function ($query)use($whereOr){
                                    	$query->whereOr($whereOr);
                                    })
                                    ->order('goods_id DESC')
                                    ->limit($offset, $limit)
                                    ->select();
    				}else{
    					$total = 0;
    				}
    			}else{
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
                                g.goods_img
                            ')
                                ->join('goods g','b.goods_id=g.goods_id','LEFT')
                                ->where($where)
                                ->order('goods_id DESC')
                                ->limit($offset, $limit)
                                ->select();
    			}
    			$r = Db::name('user_member')->field('rankid')->find($uid);
    			if(!empty($r)){
    				$rankid = $r['rankid'] ? $r['rankid'] : 0;
    			}
    			if(!empty($defaultBJ)){
    				//对数据进行加工，有等级报价的修改为等级报价，没有的不更改
    				foreach($defaultBJ as $k=>$v){
    					//通过等级去获取商品价格,促销信息等内容（没有设置这个等级对应价格那么就显示默认的价格
    					$v['bj_type'] = 1;//这个是默认报价类型
    					//附加箱柜
    					$gunit = Db::name('unit_group')->field('id,uname,title')->find($v['unitgid']);
    					$v['gunit'] = $gunit['title'];
    					if(!!$rankid){
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
    						if(!empty($rbj) && $rbj['tuanprice'] > 0){
    							$v['bj_type'] = 2;//这个是等级报价类型
    							$v = array_merge($v,$rbj);
    						}
    					}
    					if(!!$v['csdate']){
    						$v['csdate'] = date('Y-m-d H:i:s',$v['csdate']);
    					}
    					if(!!$v['cedate']){
    						if($v['cedate'] < time()){
    							$v['cuxiao'] = '';
    						}
    						$v['cedate'] = date('Y-m-d H:i:s',$v['cedate']);
    					}
    					if($v['goods_thumb']){
    						$v['goods_img']=mkgoodsimgurl(['url'=>$v['goods_thumb']]);
    					}else{
    						$v['goods_img']=mkgoodsimgurl(['url'=>$v['goods_img']]);
    					}
    					$BJ[] = $v;
    				}
    				return json(['code'=>1,'p'=>$p,'total'=>$total,'data'=>$BJ]);
    			}else{
    				return json(['code'=>0,'p'=>$p,'total'=>$total,'data'=>[]]);
    			}
    		}else{
    			return ['code'=>0,'msg'=>'该客户已被禁用或删除，无法为该客户下单，如果一定要下单，请重新添加该客户资料或联系后台管理员！'];
    		}
    	}
    
    }	
    private function bjInfo(){
        if(request()->isAjax()){
            $var = request()->post();
            if(isset($var['type']) && !empty($var['type']) && in_array($var['type'],['1','2'])){
                $type = $var['type'];
            }else{
                $type = '1';
            }
            if(!isset($var['id']) || empty($var['id'])){
                return json(['code'=>0,'msg'=>'非法操作！']);
            }
            if(isset($var['oid']) && !empty($var['oid'])){
                $oid = $var['oid'];
            }else{
                return json(['code'=>-1,'msg'=>'非法操作！']);
            }
            $id = $var['id'];
            //根据type值来获取对应的报价信息
            if($type === '1'){
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
                    ->join('ljk_goods g','b.goods_id=g.goods_id','LEFT')
                    ->where('b.id',$id)
                    ->find();
            }
            if($type === '2'){
                $bj = Db::table('ljk_baojia_rank')
                    ->alias('b')
                    ->field('
                        b.id,
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
                    ->join('ljk_goods g','b.goods_id=g.goods_id','LEFT')
                    ->where('b.id',$id)
                    ->find();
                if(!empty($bj)){
                    //去附加积分信息
                    $whereB['gid'] = $this->gid;
                    $whereB['goods_id'] = $bj['goods_id'];
                    $jf = Db::table('ljk_baojia')->field('yd_score')->where($whereB)->find();
                    $bj['yd_score'] = $jf['yd_score'];
                }
            }
            if(!empty($bj)){
                //附加箱规信息
                $gunit = Db::table('ljk_unit_group')
                        ->field('id,uname,title')
                        ->find($bj['unitgid']);
                $bj['gunit'] = $gunit['title'];
                //附加单位列表信息
                $unit = Db::table('ljk_unit')
                        ->field('id,unitgid,uname,coefficient')
                  //      ->where('gid',$this->gid)
                        ->where('unitgid',$bj['unitgid'])
                        ->select();
                //附加销售类型信息
                $selltype = Db::table('ljk_order_goodstype')->field('id,title,type')->where('status',1)->select();
                $bj['unit'] = $unit;
                $bj['selltype'] = $selltype;
                //附加oid
                $bj['oid'] = $oid;
                if($bj['goods_thumb']){
                	$bj['goods_img']=mkgoodsimgurl(['url'=>$bj['goods_thumb']]);
                }else{
                	$bj['goods_img']=mkgoodsimgurl(['url'=>$bj['goods_img']]);
                }
                return json(['code'=>1,'data'=>$bj]);
            }else{
                return json(['code'=>1,'msg'=>'报价商品已被后台管理员删除！']);
            }
        }
    }
    
    public function addordergoods(){
        if(request()->isAjax() && (request()->post('action') === 'addordergoods')){
            $var = request()->post();
            //首先需要去更新order表
            if(isset($var['oid']) && !empty($var['oid'])){
                session('oid',$var['oid']);//更新session oid
            }
            $oid = $var['oid'] = session('oid');
            //更新前需要判断这个订单是否已经结束了
            $whereO['trade'] = 0;
            $whereO['oid'] = $var['oid'];
            $order = Db::name('order')->field('oid,total,ddid,confirm,status,pay,ppsid,trade,dispatch')->where($whereO)->find();
            if(empty($order)){
                $message = '该订单已结束，无法进行该操作！';
                $this->assign('message',$message);
                $this->assign('close',['sign'=>1]);
                return $this->fetch('/common/atip');
            }
            //当总金额不为0的时候才去更新ljk_order
            $orderM = new OrderModel;
            $orderM->startTrans();
            if($var['num'] * $var['price'] != 0){
                $order['total'] = $order['total'] + $var['num'] * $var['price'];
                $order['confirm'] = 0;
                $order['pay'] = 0;
                $order['dispatch'] = 0;
                if(!$orderM->isUpdate(true)->save($order,['oid'=>$var['oid']])){
                    $message = '新增订单商品失败！';
                    $this->assign('message',$message);
                    $this->assign('close',['sign'=>1]);
                    return $this->fetch('/common/atip');
                }
            }
            //更新商品列表ljk_order_goods 当goodid,oid,unitid,type,price都不相同的情况下就新增 不然就更新
            $orderGoods = new OrderGoods;
            $whereOG['oid'] = $var['oid'];
            $whereOG['goodid'] = $var['goodid'];
            $whereOG['unitid'] = $var['unitid'];
            $whereOG['type'] = $var['type'];
            $whereOG['price'] = $var['price'];
            $ogo = $orderGoods->field('*')->where($whereOG)->find();
            if(!!$ogo){
                $og = $ogo->getData();
            }else{
                $og = [];
            }
            $orderGoods->startTrans();
            $data['oid'] = $var['oid'];
            $data['laiyuan'] = $var['laiyuan'];
            $data['gid'] = $this->gid;
            $data['goodid'] = $var['goodid'];
            $data['name'] = $var['name'];
            $data['desc'] = $var['desc'];
            $data['price'] = $var['price'];
            $data['amount'] = $var['price'] * $var['num'];
            $data['unit'] = $var['unit'];
            $data['unitg'] = $var['unitg'];
            $data['num'] = $var['num'];
            $data['type'] = $var['type'];
            $data['adate'] = time();
            $data['unitid'] = $var['unitid'];
            $data['unitgid'] = $var['unitgid'];
            if(!empty($og)){
                //需要更新 amount,num,desc
                $data['amount'] = $data['amount'] + $og['amount'];
                $data['num'] = $data['num'] + $og['num'];
                $data['desc'] = $data['desc'] . '<br />' . $og['desc'];
                if(!$orderGoods->isUpdate(true)->save($data,['id'=>$og['id']])){
                    $orderM->rollback();
                    $message = '新增订单商品失败！';
                    $this->assign('message',$message);
                    $this->assign('close',['sign'=>1]);
                    return $this->fetch('/common/atip');
                }
            }else{
                if(!$orderGoods->isUpdate(false)->save($data)){
                    $orderM->rollback();
                    $message = '新增订单商品失败！';
                    $this->assign('message',$message);
                    $this->assign('close',['sign'=>1]);
                    return $this->fetch('/common/atip');
                }
            }
            //向新增order_post内容
            $ogs = config('order_goods_status');
            $content = '新增订单商品：' . $var['name'] . '，数量：' . $var['num'] . $var['unit'] . '，单价：' . $var['price'] . '，销售类型：' . $ogs[$var['type']];
            //获取该订单的状态信息
            $oidPost = [
                'content'=>$content,
                'oid'=>session('oid'),
                'gid'=>session('gid'),
                'guid'=>$this->guid,
                'thumb'=>'',
                'confirm'=>$order['confirm'],
                'pay'=>$order['pay'],
                'status'=>$order['status'],
                'trade'=>$order['trade'],
                'ppsid'=>$order['ppsid'],
                'dispatch'=>$order['dispatch'],
                'adate'=>time(),
            ];
            //这一步对数据进行验证,Validate
            $OrderPost = new OrderPost;
            $OrderPost->startTrans();
            if($OrderPost->addPost($oidPost) !== false){
                $orderGoods->commit();
                $orderM->commit();
                $OrderPost->commit();
                if(!!$order['ddid']){
                    //重新计算分成信息
                    $separate = controller('fx/Separate','controller');
                    $postData = ['oid'=>$var['oid']];
                    $sign = new Sign();
                    $postData = array_merge($postData,$sign->mkSign($postData));
                    $separate->insideIndex($postData);
                    $message = '新增订单商品成功，订单状态已重置！';
                    $this->assign('message',$message);
                    $this->assign('autoClose',['sign'=>1,'time'=>2000]);
                    $this->assign('nextAct',['sign'=>1,'action'=>'getList({sign:"orderPost"});showLA("/admin/order/editstatus","orderCurrentStatus",{oid:"'. $oid .'",action:"show"});']);
                    return $this->fetch('/common/atip');
                }else{
                    $message = '新增订单商品成功，订单状态已重置！';
                    $this->assign('message',$message);
                    $this->assign('autoClose',['sign'=>1,'time'=>2000]);
                    $this->assign('nextAct',['sign'=>1,'action'=>'getList({sign:"orderPost"});showLA("/admin/order/editstatus","orderCurrentStatus",{oid:"'. $oid .'",action:"show"});']);
                    return $this->fetch('/common/atip');
                }
                
            }else{
                $orderGoods->rollback();
                $orderM->rollback();
                $OrderPost->rollback();
                $message = '系统繁忙，请稍候再试！';
                $this->assign('message',$message);
                $this->assign('autoClose',['sign'=>1,'time'=>2000]);
                return $this->fetch('/common/atip');
            }
        }else{
        	return $this->bjGoods();
        }
    }
    private function addordergoodslist(){
     //   if(request()->isPost()){
        $param = input('param.');
      //  print_r($param);
        if(isset($param['type']) && $param['type'] == 1){
            return $this->aorog();
        }
        $uid = input('param.uid');
        $t = input('param.t');
        $cid = input('param.cid');
        $p = intval(trim(input('param.p')));
        $where = [];
        if(!$uid) $uid=session('downorderuserid');
        else session('downorderuserid',$uid);
        if($cid){
            //判定是否有子id
            $ccid = Db::name('category')
                    ->where('pid',$cid)
                    ->where('gid',$this->gid)
                    ->column('id');
            if(!empty($ccid)){
                array_push($ccid,$cid);
                $ccids = implode(',',$ccid);
                $whereC['categoryid'] = ['IN',$ccids];
            }else{
                $whereC['categoryid'] = $cid;
            }
            $bid = Db::name('baojia_cat')
                ->where($whereC)
                ->where('gid',$this->gid)
                ->column('bid');
            if(!empty($bid)){
                $where['id'] = ['IN',implode(',',$bid)];
            }else{
                //直接退出了
                $return['code'] =0;
                $return['p'] = $p;
                $return['msg'] ='没有更多数据！'; 
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
            $goods_ids1 = Db::name('goods')->whereOr($whereOrG)->column('goods_id');
            if(!empty($goods_ids1)){
                //优化$goods_ids
                //先查询出这个gid下报价商品的所有gid;
                $bj_goods_ids = Db::name('baojia')->where('gid',$this->gid)->column('goods_id');
                $goods_ids = [];
                foreach($bj_goods_ids as $k=>$v){
                    if(in_array($v,$goods_ids1)){
                        $goods_ids[] = $v;
                    }
                }
                if(!empty($goods_ids)){
                    $where['goods_id'] = ['IN',implode(',',$goods_ids)];
                }else{
                    //直接退出了
                    $return['code'] =0;
                    $return['p'] = $p;
                    $return['msg'] ='没有更多数据！'; 
                    $return['data'] = null;
                    $return['total'] = 0;
                    return json($return);
                }
            }else{
                //直接退出了
                $return['code'] =0;
                $return['msg'] ='没有更多数据！'; 
                $return['data'] = null;
                $return['total'] = 0;
                $return['p'] = $p;
                return json($return);
            }
        }
        //通过$cid 去获取报价商品是这个分类的报价id
        $limit = $param['limit'];
        $offset = ($param['p'] - 1) * $limit;
        $where['gid'] = $this->gid;
        $rankid=Db::name('user_member')->where('uid',$uid)->value('rankid');
        $goodslist=Db::name('baojia')->where($where)->limit($offset, $limit)->select();
        $units = [];
        $gg = [];
        foreach($goodslist as $key=>$goods){
            if($rankid){
                $w['gid'] = $this->gid;
                $w['rank_id'] = $rankid;
                $w['goods_id'] = $goods['goods_id'];
                $rankbj=Db::name('baojia_rank')->where($w)->find();
                if(!empty($rankbj)){
                    $goods['tuanprice']=$rankbj['tuanprice'];
                    $goods['unit'] = $rankbj['unit'];
                    $goods['unitid'] = $rankbj['unitid'];
                }
            }
            if(!isset($units[$goods['unitgid']])){
                $whereU['gid'] = $this->gid;
                $whereU['unitgid'] = $goods['unitgid'];
                $units[$goods['unitgid']] = Db::name('unit')->where($whereU)->column('id unitid,uname,coefficient');
            }
            if(!isset($gg[$goods['unitgid']])){
                $whereUg['gid'] = $this->gid;
                $whereUg['id'] = $goods['unitgid'];
                $gg[$goods['unitgid']] = Db::name('unit_group')->where($whereUg)->value('title');
            }
            $goods['utitle'] = $gg[$goods['unitgid']];
            $goods['units'] = $units[$goods['unitgid']];
            $goods_info=Db::name('goods')->where('goods_id',$goods['goods_id'])->find();
            if($goods_info['goods_thumb']){
            	$goods_info['goods_img']=mkgoodsimgurl(['url'=>$goods_info['goods_thumb']]);
            }else{
            	$goods_info['goods_img']=mkgoodsimgurl(['url'=>$goods_info['goods_img']]);
            }
            $goods['info']=$goods_info;
            unset($w);
            if(!!$goods['csdate']){
                $goods['csdate'] = date('Y-m-d H:i:s',$goods['csdate']);
            }
            if(!!$goods['cedate']){
                if($goods['cedate'] < time()){
                    $goods['cuxiao'] = '';
                }
                $goods['cedate'] = date('Y-m-d H:i:s',$goods['cedate']);
            }
            $gl[]=$goods;
        }
        $return['total'] = count($gl);//当前数据
        $return['data'] =$gl;
        $return['p'] = $p;
        $return['code'] =1;
        $return['msg'] ='数据加载成功！';
        if(count($gl)==0){
           $return['code'] =0;
           $return['msg'] ='没有更多数据！';
        }
        return json($return);
   // }
    }
    public function addgoodslistinfo(){
		$goods_id = input('param.id');
		if(!$goods_id) $goods_id=session('addgoods_id');
		session('addgoods_id',$goods_id);
		$where = [];
		$where['gid'] = $this->gid;
		$where['goods_id'] =$goods_id;
					$uid=session('downorderuserid');
					$rankid=Db::name('user_member')->where('uid',$uid)->value('rankid');
					if($rankid){
						$where['rank_id'] =$rankid;
						$goods_info=Db::name('baojia_rank')->where($where)->find();
						if(!$goods_info) { 
							unset($where['rank_id']); 
							$goods_info =  Db::name('baojia')->where($where)->find();
						}
					}else{
						$goods_info =  Db::name('baojia')->where($where)->find();
					}
					if(!!$goods_info['csdate']){
						$goods_info['csdate'] = date('Y-m-d H:i:s',$goods_info['csdate']);
					}
					if(!!$goods_info['cedate']){
						if($goods_info['cedate'] < time()){
							$goods_info['cuxiao'] = '';
						}
						$goods_info['cedate'] = date('Y-m-d H:i:s',$goods_info['cedate']);
					}
					$whereu['status']=1;
					$whereu['unitgid']=$goods_info['unitgid'];
					$whereu['gid']=$this->gid;
					$goods_info['unitlist']=Db::name('unit')->where($whereu)->select();
					$coefficient=Db::name('unit')->where('id',$goods_info['unitid'])->value('coefficient');
					if($coefficient) $goods_info['unitprice']=$goods_info['price']/$coefficient;
					$order_goods_status = config('order_goods_status');
					$goods_info['gunit']=Db::name('unit_group')->where('id',$goods_info['unitgid'])->value('title');
					$goods=Db::name('goods')->where('goods_id',$goods_info['goods_id'])->find();
					$goods_info['uid']=$uid;
					$return['total'] = count($goods_info);  //总数据
					$return['data'] = $goods_info;
					$return['data']['order_goods_status'] = $order_goods_status;
					$return['data']['info'] = $goods; 
					$return['code'] =1;
					$return['msg'] ='数据加载成功！';
					if(!$goods_info){
					   $return['code'] =0;
					   $return['msg'] ='没有数据！';
					}
		return json($return);
    }

    
    private function addcart(){	
        $uid = input('param.uid');
        if(!$uid){
            $uid=session('downorderuserid'); 
        }
        if($uid){ 
            $param = input('param.');
            if(!$param['num']){
                $param['num']=1;
            }
            $rankid=Db::name('user_member')->where('uid',$uid)->value('rankid');
            $w['gid']=$this->gid;
            $w['rank_id']=$rankid;
            $w['goods_id']=$param['goods_id'];
            $goodsBaojiaInfo=Db::name('baojia_rank')->where($w)->find();
            if(!$goodsBaojiaInfo){
                $where['gid']=$this->gid;
                $where['goods_id']=$param['goods_id'];
                $goodsBaojiaInfo =  db('baojia')->where($where)->find();
            }
            $goodsInfo=db('goods')->where('goods_id',$goodsBaojiaInfo['goods_id'])->find();
            $wherecart['user_id']=$uid;
            $wherecart['unitid']=$param['unitid'];
            $wherecart['goods_id']=$goodsBaojiaInfo['goods_id'];
            $wherecart['is_real']=2;
            $carts=db('cart')->where($wherecart)->find();
            if($carts){
                $goods_number=$carts['goods_number']+$param['num'];
                $c=db('cart')->where('id',$carts['id'])->setField('goods_number', $goods_number);
            }else{
                $cartinfo['user_id']=$uid;
                $cartinfo['goods_id']=$goodsBaojiaInfo['goods_id'];
                $cartinfo['session_id']=session_id();
                $cartinfo['goods_sn']=$goodsInfo['goods_sn'];
                $cartinfo['gid']=$this->gid;
                $cartinfo['goods_name']=$goodsInfo['goods_name'];
                $cartinfo['market_price']=$goodsBaojiaInfo['retailprice'];
                $cartinfo['goods_price']=$param['price'];
                $cartinfo['goods_number']=$param['num'];
                $cartinfo['unitid']=$param['unitid'];
                $cartinfo['unitgid']=$goodsBaojiaInfo['unitgid'];
                $Unitinfo =db('unit')->where('id',$cartinfo['unitid'])->find();
                $cartinfo['unit'] = $Unitinfo['uname'];
                $cartinfo['unitg'] = $Unitinfo['title'];
                $cartinfo['is_real']=2;//
                $c=db('cart')->insert($cartinfo);
            }
            $cnum=db('cart')->where('user_id',$uid)->count();
            if($c){
               $return['code'] =1;
               $return['data'] =$cnum;
               $return['uid'] =$uid;
               $return['msg'] ='加入购物车成功！';
            }else{			
                $return['code'] =0;
                $return['data'] =$cnum;
                $return['uid'] =$uid;
                $return['msg'] ='加入购物车失败！';
            }
        }else{
            $return['code'] =-1;
            $return['msg'] ='请选择要下单的客户！';
        }
        return json($return);
    }
    
    private function updatecartitem(){
    	if(request()->isPost()){
    		$input = input('post.');
    		$uid = isset($input['uid']) ? intval(trim($input['uid'])) :false;
    		$id = isset($input['sku']) ? intval(trim($input['sku'])) : false;
    		$num = isset($input['num']) ? ((intval(trim($input['num'])) > 0) ? intval(trim($input['num'])) : 0 ) : 0;
    		if($uid && $id && $num){
    			$map['user_id']=$uid;
    			$map['id']=$id;
    			$carts=Db::name('cart')->where($map)->setField('goods_number', $num);
    			$cartlist=Db::name('cart')->where('user_id',$uid)->select();
    			$z['TotalNum']=0;
    			$z['TotalPrice']=0;
    			foreach ($cartlist as $key=>$cart){
    				$z['TotalNum']=$z['TotalNum']+$cart['goods_number'];
    				$z['TotalPrice']=$z['TotalPrice']+$cart['goods_price']*$cart['goods_number'];
    			}
    			$return['data'] =$z;
    			$return['code'] =1;
    			$return['msg'] ='更新成功！';
    			return json($return);
    		}else{
    			if(!$uid || !$id){
    				$return['code'] =0;
    				$return['msg'] ='非法操作！';
    				return json($return);
    			}
    			if(!$num){
    				$return['code'] =0;
    				$return['msg'] ='更新失败！数量不能小于等于0';
    				return json($return);
    			}
    
    		}
    	}
    }
   
    private function cartinfo(){
      //  if(request()->isPost()){
            $param = input('post.');
            $uid = isset($param['uid'])? intval(trim($param['uid'])) : 0;
            if($uid === 0){
                $return['code'] = -1;
                $return['msg'] ='非法操作！';
                $return['data'] = null;
                return json($return);
            }
            $totalPrice=0;
            $num=0;
            $cartnum=Db::name('cart')->where('user_id',$uid)->count();
            $userinfo=Db::name('user_member')->where('uid',$uid)->find();
            if($cartnum){
                $cartlist2=Db::name('cart')->where('user_id',$uid)->select();
                foreach($cartlist2 as $key=>$goods){
                    $goods_info=Db::name('goods')->where('goods_id',$goods['goods_id'])->find();
                    if($goods_info['goods_thumb']){
                    	$goods_info['goods_img']=mkgoodsimgurl(['url'=>$goods_info['goods_thumb']]);
                    }else{
                    	$goods_info['goods_img']=mkgoodsimgurl(['url'=>$goods_info['goods_img']]);
                    }
                    $goods['info']=$goods_info;
                    $num = $num+$goods['goods_number'];
                    $totalPrice=$totalPrice+$goods['goods_price']*$goods['goods_number'];
                    $cartlist[]=$goods;
                }
            }
            $return['data']['goodslist'] =$cartlist;
            $return['data']['totalPrice'] =$totalPrice;
            $return['data']['goodsnum'] =$num;
            $return['data']['user'] =$userinfo;
            $return['data']['cartnum'] =$cartnum;
            $return['code'] =1;
            $return['msg'] ='数据加载成功！';
            return json($return);
      //      }
    }

    private function deletecartitem(){
        if(request()->isPost()){
            $input = input('post.');
            $uid = isset($input['uid']) ? intval(trim($input['uid'])) :false;
            $id = isset($input['id']) ? intval(trim($input['id'])) :false;
            if($uid && $id){    
                $map['user_id']=$uid;
                $map['id']=$id;
                $carts=Db::name('cart')->where($map)->find();
                if($carts){
                    $delid=Db::name('cart')->delete($carts['id']);
                }
                if($delid){
                    $cartlist=Db::name('cart')->where('user_id',$uid)->select();
                    $z['TotalNum']=0;
                    $z['TotalPrice']=0;
                    foreach ($cartlist as $key=>$cart){
                        $z['TotalNum']=$z['TotalNum']+$cart['goods_number'];
                        $z['TotalPrice']=$z['TotalPrice']+$cart['goods_price']*$cart['goods_number'];
                        $cartlist[$key]=$cart;
                    }
                    $return['data'] =$z;
                    $return['code'] =1;
                    $return['msg'] ='删除成功！';
                    return json($return);
                }
            }else{
                $return['code'] =0;
                $return['msg'] ='删除失败！';
                return json($return);
            }
        }
    }
    
    private function addorder(){
    	if(request()->isPost()){
    		$omember = input('post.');
    		$uid = isset($omember['uid']) ? intval(trim($omember['uid'])) : false;
    		if($uid){
    			//当前登录
    			$whereUm['uid'] = $uid;
    			$whereUm['gid'] = $this->gid;
    			$userinfo=Db::name('user_member')->where($whereUm)->find();
    			if(empty($userinfo)){
    				$return['code'] =0;
    				$return['msg'] ='非法操作！';
    				return json($return);
    			}
    			$z['content'] = $omember['pssj'];
    			$z['num']=0;
    			$z['total']=0;
    			$cartlist2=Db::name('cart')->where('user_id',$uid)->select();
    			foreach ($cartlist2 as $key=>$cart){
    				$goodsinfo=Db::name('goods')->where('goods_id',$cart['goods_id'])->find();
    				$cart['goods_img']=$goodsinfo['goods_img'];
    				$z['num']=$z['num']+$cart['goods_number'];
    				$z['total']=$z['total']+$cart['goods_price']*$cart['goods_number'];
    				$cartlist[]=$cart;
    			}
    			$ord['uid']=$uid;
    			$ord['ddid']=$userinfo['ddid'];
    			$authnum = date('YmdHis',time()).mt_rand(100000, 999999);
    			$ord['number']=$authnum;
    			$ord['type']=1;//1电话后台下单 2业务员下单
    			$ord['shopid']=$userinfo['shopid'];
    			$shopinfo=Db::name('oauth_clients')->where('id',$userinfo['shopid'])->find();
    			if($shopinfo['gtype']) $ord['ftype']=$shopinfo['gtype'];
    			$ord['mobile']=$userinfo['mobile'];
    			$ord['psid']=$userinfo['psid'];
    			$ord['ppsid']= $userinfo['ppsid'] ? $userinfo['ppsid'] : 0;
    			$ord['adate']=time();
    			$ord['state']='';
    			$ord['address_id']=0;
    			$ord['gid']=$userinfo['gid'];
    			$ord['downgid']=$userinfo['gid'];
    			$ord['guid']=$userinfo['guid'];
    			$ord['ip']=get_client_ip();
    			$ord['user_agent']=$_SERVER['HTTP_USER_AGENT'];
    			if(session('guid'))$ord['dguid']=session('guid');
    			else $ord['dguid']=$userinfo['guid'];
    			$ord['content']=trim($omember['pssj']);
    			$ord['total']=$z['total'];
    			if(isset($omember['car_sale_order'])){
    				$carsale = intval($omember['car_sale_order']);
    				in_array($carsale,[0,1])?$ord['car_sale'] = $carsale : $ord['car_sale'] = 0;
    			}else{
    				$ord['car_sale'] = 0;
    			}
    			if($cartlist){
    				Db::name('order')->insert($ord);
    				$oid = Db::name('order')->getLastInsID();
    			}else{
    				return json(['code'=>-3,'msg'=>'非法操作！']);
    			}
    			foreach ($cartlist as $goods) {
    				$ogoods['oid'] = $oid;
    				$ogoods['amount']=$goods['goods_number']*$goods['goods_price'];
    				$ogoods['goodid']=$goods['goods_id'];
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
    				$m['oid']=$oid;
    				$m['goodid']=$goods['goods_id'];
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
    			//添加下单痕迹
    			$postInfo['oid']=$oid;
    			$postInfo['guid']=$ord['dguid'];
    			$postInfo['content']="下订单";
    			$postInfo['adate'] = time();
    			$postInfo['thumb'] = '';
    			session('oid',$oid);
    			Db::name('order_post')->insert($postInfo);
    			$fromid = Db::name('order_post')->getLastInsID();
    			if($fromid ) addgps(session('guid'),$oid,$fromid,2);
    			//短信提醒
    			$groups = Db::name('group')->where('gid',$userinfo['gid'])->find();
    			$mobileArr = array($groups['mobile']);
    			$uArr = array('id'=>$oid, 'gid'=>$userinfo['gid'], 'guid'=>$ord['dguid'], 'fromid'=>'');
    			//$Message = new Message();
    			//$Message->getMessageApi($mobileArr, '', '5', $uArr);//短信 type = 5[新订单提醒]
    			if($oid){
    				$om['uid']=$uid;
    				$om['oid']=$oid;
    				$om['realname']=$omember['realname'];
    				$om['address']=$omember['address'];
    				$om['phone']=$omember['mobile'];
    				$om['x']='';
    				$om['y']='';
    				Db::name('order_member')->insert($om);
    			}
    			Db::name('cart')->where('user_id',$uid)->delete();
    			session('downorderuserid','');
    		}
    	}
    	if($oid){
    		$return['data']['oid'] =$oid;
    		$return['data']['number'] =$authnum;
    		$return['code'] =1;
    		$return['msg'] ='下单成功！即将返回订单详情！';
    		return json($return);
    	}else{
    		$return['code'] =0;
    		$return['msg'] ='下单失败！';
    		return json($return);
    	}
    }
    private function aogrog(){
        if(request()->isAjax()){
            if(isset($var['oid']) && !empty($var['oid'])){
                session('oid',$var['oid']);
            }
            $oid = session('oid');
            $uid = '';
            if(!empty($oid)){
                $u= Db::name('order')->field('uid')->find($oid);
                $uid = $u['uid'];
            }
            return json($this->recentlyOrderGoods($uid));
        }
    }
    private function aorog(){
        if(request()->isAjax()){
            $uid = input('param.id');
            if(!$uid){
                $uid=session('downorderuserid');
            }
            session('downorderuserid',$uid);
            return json($this->recentlyOrderGoods($uid));
        }
    }
    private function recentlyOrderGoods($uid=''){
        if(empty($uid)){
            $return['p'] = 1;
            $return['code'] =0;
            $return['msg'] ='没有更多数据！'; 
            $return['data'] = null;
            $return['total'] = 0;
            return $return;
        }
        $var = request()->post();
        $limit = $var['limit'];
        $p = intval($var['p']);
        $offset = ($p - 1) * $limit;
        $where = [];
        $whereCount = [];
//        $where['guid'] = $this->guid;逻辑更改，补货商品跟着客户走，不在跟着业务员走
        $where['gid'] = $this->gid;//团队条件必须
        $where['uid'] = $uid;
        $where['adate'] = ['BETWEEN',[time()-3600*24*30*3,time()]];
        $oids = Db::name('order')->where($where)->column('oid');
        if(empty($oids)){
            $return['p'] = $p;
            $return['code'] =0;
            $return['msg'] ='没有更多数据！'; 
            $return['data'] = null;
            $return['total'] = 0;
            return $return;
        }
        //通过获取的oids查询商品信息如果使用in语法，如果oids数量较多的时候，那么就分片处理
        $whereOG['gid'] = $this->gid;
        $whereOG['oid'] = ['IN',$oids];
        $goods_ids1 = Db::name('order_goods')->where($whereOG)->group('goodid')->column('goodid');
        $where = [];
        $wherebj['gid'] = $where['gid'] = $this->gid;
        if(!empty($goods_ids1)){
            //优化$goods_ids
            //先查询出这个gid下报价商品的所有gid;
            $wherebj['goods_id'] = ['IN',$goods_ids1];
            $x = Db::name('baojia')->where($wherebj)->column('id,goods_id');
            if(empty($x)){
                //直接退出了
                $return['code'] =0;
                $return['p'] = $p;
                $return['msg'] ='没有更多数据！'; 
                $return['data'] = null;
                $return['total'] = 0;
                return $return;
            }
            $goods_ids2 = array_values($x);
            $bids2 = array_keys($x);
            if(isset($var['cid']) && $cid = intval($var['cid'])){
                $ccid = Db::name('category')
                    ->where('pid',$cid)
                    ->where('gid',$this->gid)
                    ->column('id');
                if(!empty($ccid)){
                    array_push($ccid,$cid);
                    $ccids = implode(',',$ccid);
                    $whereC['categoryid'] = ['IN',$ccids];
                }else{
                    $whereC['categoryid'] = $cid;
                }
                $whereC['bid'] = ['IN',$bids2];
                //获取报价分类在这个范围内的报价产品
                $bid = Db::name('baojia_cat')
                    ->where($whereC)
                    ->where('gid',$this->gid)
                    ->column('bid');
                if(!empty($bid)){
                    $where['b.id'] = $whereCount['id'] = ['IN',$bid];
                    //如果bid存在，但是报价已经被删除了 说明这个报价分类多余，应该删除，但是如果有文字筛选条件，那么不能确定是否作处理
                }else{
                    //直接退出了
                    $return['code'] =0;
                    $return['p'] = $p;
                    $return['msg'] ='没有更多数据！'; 
                    $return['data'] = null;
                    $return['total'] = 0;
                    return $return;
                }
            }
            if(!empty($goods_ids2)){
                //如果有名称输入，筛选符合名字条件的goods_id;
                if(isset($var['t']) && $t = trim($var['t'])){
                    $whereG['goods_id'] = ['IN',$goods_ids2];
                    $whereG['goods_name'] = ['LIKE','%' . $t . '%'];
                    $goods_ids = Db::name('goods')->where($whereG)->column('goods_id');
                    if(empty($goods_ids)){
                        //直接退出了
                        $return['code'] =0;
                        $return['p'] = $p;
                        $return['msg'] ='没有更多数据！'; 
                        $return['data'] = null;
                        $return['total'] = 0;
                        return $return;
                    }
                }else{
                    $goods_ids = $goods_ids2;
                }
                $whereCount['goods_id'] = $where['b.goods_id'] = ['IN',$goods_ids];
            }else{
                //直接退出了
                $return['code'] =0;
                $return['p'] = $p;
                $return['msg'] ='没有更多数据！'; 
                $return['data'] = null;
                $return['total'] = 0;
                return $return;
            }
        }else{
            //直接退出了
            $return['code'] =0;
            $return['p'] = $p;
            $return['msg'] ='没有更多数据！'; 
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
                g.seller_note,
                g.goods_img
            ')
            ->join('goods g','b.goods_id=g.goods_id','LEFT')
            ->where($where)
            ->order('goods_id DESC')
            ->limit($offset, $limit)
            ->select();
        $total = count($defaultBJ);
        $r = Db::name('user_member')->field('rankid')->find($uid);
        if(!empty($r)){
            $rankid = $r['rankid'] ? $r['rankid'] : 0;
        }
        $units = [];
        if(!empty($defaultBJ)){
            //对数据进行加工，有等级报价的修改为等级报价，没有的不更改
            foreach($defaultBJ as $k=>$v){
                //通过等级去获取商品价格,促销信息等内容（没有设置这个等级对应价格那么就显示默认的价格
                $v['bj_type'] = 1;//这个是默认报价类型
                //兼容 添加商品数据格式
                $v['info'] = [
                    'goods_name'=>$v['goods_name'],
                    'goods_img'=>mkgoodsimgurl(['url'=>$v['goods_img']]),
                    'seller_note'=>$v['seller_note'],
                    'goods_sn'=>$v['goods_sn'],
                ];
                //附加箱柜
                if(!isset($units[$v['unitgid']])){
                    $whereU['gid'] = $this->gid;
                    $whereU['unitgid'] = $v['unitgid'];
                    $units[$v['unitgid']] = Db::name('unit')->where($whereU)->column('id unitid,uname,coefficient');
                }
                $gunit = Db::name('unit_group')->field('id,uname,title')->find($v['unitgid']);
                $v['utitle'] = $v['gunit'] = $gunit['title'];
                $v['units'] = $units[$v['unitgid']];
                if(!!$rankid){
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
                    if(!empty($rbj)){
                        $v['bj_type'] = 2;//这个是等级报价类型
                        $v = array_merge($v,$rbj);
                    }
                }
                if(!!$v['csdate']){
                    $v['csdate'] = date('Y-m-d H:i:s',$v['csdate']);
                }
                if(!!$v['cedate']){
                    if($v['cedate'] < time()){
                        $v['cuxiao'] = '';
                    }
                    $v['cedate'] = date('Y-m-d H:i:s',$v['cedate']);
                }
                if($v['goods_thumb']){
                	$v['goods_img']=mkgoodsimgurl(['url'=>$v['goods_thumb']]);
                }else{
                	$v['goods_img']=mkgoodsimgurl(['url'=>$v['goods_img']]);
                }
                
                $BJ[] = $v;
            }
            return ['code'=>1,'p'=>$p,'total'=>$total,'data'=>$BJ];
        }else{
            return ['code'=>0,'p'=>$p,'total'=>$total,'data'=>[]];
        }    
    }
    */
    public function authnextgroup(){
        if(request()->isAjax() && request()->isPost()){
            $action = input('post.action');
            switch ($action){
                case 'show':
                    $res = $this->getnextgroups();
                    return json($res);
                case 'editorderstatus': 
                    	$res = $this->PlEditConfirmStatus();
                    	return $res;
                case 'editdriver':
                    		$res = $this->PlEditDriver();
                    		return $res;
                case 'auth':
                    $res = $this->doauthnextgroup();
                    if($res['code'] === 0){
                        $message = $res['msg'];
                        $this->assign('message',$message);
                        $this->assign('refresh',['sign'=>1,'time'=>1500]);
                        return $this->fetch('/common/atip');
                    }else{
                        $message = $res['msg'];
                        $this->assign('message',$message);
                        $this->assign('close',['sign'=>1]);
                        return $this->fetch('/common/atip');
                    }
                    break;
                default : 
                $res = $this->getnextgroups();
                return json($res);
            }
        }else{
            return json(['code'=>-1,'msg'=>'非法操作！']);
        }
    }
    private function getnextgroups(){
        $gid = $this->gid;
        $whereBG['status'] = 1;
        $whereBG['gid'] = $gid;
        $whereBG['type'] = 2;
        $whereBG['expdate'] = ['EGT',time()];
        $whereBG['psid'] = ['NEQ',0];
        $data = Db::name('distributor')->field('gid,psid,sname')->where($whereBG)->select();
        if($data){
	        $res = [
	            'code'=>1,
	            'data'=>$data
	        ];
        }else{
        	$res = [
        	'code'=>0
        	];
        }
        return $res;
    }
    private function doauthnextgroup(){
        $inputData = input('post.');
        $whereO['gid'] = $whereBG['gid'] = $gid = $this->gid;
        $whereBG['psid'] = $psid = $inputData['psid'];
        $whereBG['expdate'] = ['EGT',time()];
        $whereBG['type'] = 2;
        $oid = $inputData['oid'];
        $uid = @$inputData['uid'];
        $sign = @$inputData['sign'];
        if(empty($oid) || ($sign==1 && !$uid)){
       // if(!$psid || empty($oid)){
            return ['code'=>-1,'msg'=>'<p style="text-align:left;">无法完成操作，可能的原因：<br />1.操作非法！'];
        }
        if($psid){
	        if(!Db::name('distributor')->where($whereBG)->count()){
	            return ['code'=>-1,'msg'=>'该配送商不存在或绑定关系已到期！'];
	        }
        }
        $whereO['confirm'] = ['NEQ',-1];
       // $whereO['confirm'] = 1;
        $whereO['trade'] = ['NEQ',1];
        $whereO['oid'] = ['IN',$oid];
        $oids = Db::name('order')->where($whereO)->column('oid');
        if(empty($oids)){
            return ['code'=>-1,'msg'=>'请确保当前所操作订单存在，已确认，未结束！'];
        }
        //创建订单更新数据
        $whereUM['uid'] = $uid;
        $whereUM['gid'] = $gid;
        if(!!$sign && !!$uid){
           
            if(Db::name('user_member')->where($whereUM)->count()){
                /*
                $whereDO['authid'] = $dataUDO['authid'] = $uid;
                $whereDO['type'] = 3;
                $dataDO['id'] = Db::name('distributor_oauth')->where($whereDO)->value('id'); 
                $dataDO['gid'] = $gid;
                $dataDO['type'] = 3;
                $dataDO['authid'] = $uid;
                $dataDO['ip'] = get_client_ip();
                $dataDO['supplierid'] = $psid;
                $dataDO['adate'] = time();
                */
            	$dataDO['ppsid'] = $psid;
            }
        }else{
           /*
            *$whereDO['authid'] = $dataUDO['authid'] = $uid;
            $whereDO['type'] = 3;
            $whereDO['supplierid'] = $psid;
            Db::name('distributor_oauth')->where($whereDO)->delete();
            */ 
        	$dataDO['ppsid'] = 0;
        	Db::name('user_member')->where($whereUM)->update($dataDO);
        }
        Db::startTrans();
        try{
            foreach($oids as $id){
                $dataUO = [
                    'oid' => $id,
                    'ppsid'=> $psid
                ];
                Db::name('order')->update($dataUO);
            }
            if(isset($dataDO)){
                if(!!$dataDO['ppsid']){
                    Db::name('user_member')->where($whereUM)->update($dataDO);
                }
            }
            Db::commit();
            return ['code'=>0,'msg'=>'操作成功，页面即将刷新！','refresh'=>['sign'=>1,'t'=>1500]];
        }catch (\think\Exception $e){
            Db::rollback();
            return ['code'=>-1,'msg'=>'系统繁忙，请稍候再试！Error: '. $e->getMessage() ];
        }
    }
    public function authdriver(){
        if(request()->isAjax() && request()->isPost()){
            $action = input('post.action');
            $oid = input('post.oid');
            switch ($action){
                case 'show':
                    return $this->driverList($oid);
                case 'auth':
                    $res = $this->doAuthDriver($oid);
                    if($res['code'] === 0){
                        $message = $res['msg'];
                        $this->assign('message',$message);
                        $this->assign('autoClose',['sign'=>1,'time'=>1500]);
                        if(isset($res['refresh'])) $this->assign('refresh',$res['refresh']);
                        $this->assign('nextAct',['sign'=>1,'action'=>'getList({sign:"orderPost"});']);
                        return $this->fetch('/common/atip');
                    }else{
                        $message = $res['msg'];
                        $this->assign('message',$message);
                        if(isset($res['refresh'])) $this->assign('refresh',$res['refresh']);
                        $this->assign('close',['sign'=>1]);
                        return $this->fetch('/common/atip');
                    }
                default :
                    return $this->driverList($oid);
            }
        }else{
            return json(['code'=>1,'data'=>null]);
        }
    }
    private function driverList($oid=0){
        return null;
    }
    private function doAuthDriver($oid='',$guid=''){
    	$oid = $oid?$oid:input('oid');
    	$guid = $guid?$guid:input('guid');
    	$whereO['oid'] = $oid;
    	$whereO['confirm'] = 1;
    	$whereO['ppsid'] = ['IN',[0,$this->gid]];
    	$whereO['trade'] = 0;
    	if(!$oid || !($status = Db::name('order')->field('confirm,status,pay,ppsid,trade,dispatch')->where($whereO)->find())){
    		return ['code'=>-1,'msg'=>'<p style="text-align:left;">错误操作提示信息：请确保<br />1.该订单没有授权给配送商！<br />2.订单未结束！<br />3.订单已确认！</p>'];
    	}
    	if($guid==0){
    		return $this->doUnAuthDriver($status);
    	}
    	if(!$guid || !($memberRealname=Db::name('group_member')->where('uid',$guid)->where('status',1)->value('realname')) || !(Db::name('group_account')->where('guid',$guid)->where('exp_date','GT',time())->count())){
    		return ['code'=>-1,'msg'=>'<p style="text-align:left;">错误操作提示信息：请确保<br />1.该员工账号存在！<br />2.该员工账号未屏蔽！<br />3.该员工账号未到期！</p>'];
    	}
    	//开始进行授权操作！当前订单是否已经有授权信息
    	$bo = DB::name('bind_order')->where('oid',$oid)->where('type',1)->find();
    	$dataBO['gid'] = $this->gid;
    	$dataBO['guid'] = $guid;
    	$dataBO['oid'] = $oid;
    	$dataBO['adate'] = time();
    	$dataBO['type'] = 1;
    	//跟进记录
    	$oidPost = [
    	'content'=>'已分配给司机：' . $memberRealname,
    	'oid'=>$oid,
    	'guid'=>$this->guid,
    	'gid'=>session('gid'),
    	'thumb'=>'',
    	'confirm'=>$status['confirm'],
    	'pay'=>$status['pay'],
    	'status'=>$status['status'],
    	'trade'=>$status['trade'],
    	'ppsid'=>$status['ppsid'],
    	'dispatch'=>$status['dispatch'],
    	'adate'=>time(),
    	];
    	if(!!$bo && $bo['guid'] == $guid){
    		return ['code'=>-1,'msg'=>'提示：司机信息与原订单司机信息相同！'];
    	}
    	Db::startTrans();
    	try{
    		if(!!$bo){
    			$dataBO['id'] = $bo['id'];
    			Db::name('bind_order')->update($dataBO);
    		}else{
    			Db::name('bind_order')->insert($dataBO);
    		}
    		Db::name('order_post')->insert($oidPost);
    		Db::commit();
    	}catch(\think\Exception $e){
    		Db::rollback();
    		return ['code'=>-1,'msg'=>'系统繁忙，请稍候再试！'.$e->getMessage()];
    	}
    	return ['code'=>0,'msg'=>'分配司机成功！','refresh'=>['sign'=>1,'t'=>500]];
    
    }
    private function doUnAuthDriver($status){
        $oid = input('post.oid');
       
        //开始进行授权操作！当前订单是否已经有授权信息
        $dataBO['gid'] = $this->gid;
        $dataBO['oid'] = $oid;
        $dataBO['type'] = 1;
        $bo = DB::name('bind_order')->where($dataBO)->find();
        $memberRealname=Db::name('group_member')->where('uid',$bo['guid'])->where('status',1)->value('realname');
        if($status['dispatch']!=0){
        	return ['code'=>-1,'msg'=>'配送中或配送完成的订单不能取消司机'];
        }
        //跟进记录
        $oidPost = [
            'content'=>'取消分配给司机：' . $memberRealname,
            'oid'=>$oid,
            'gid'=>session('gid'),
            'guid'=>$this->guid,
            'thumb'=>'',
            'confirm'=>$status['confirm'],
            'pay'=>$status['pay'],
            'status'=>$status['status'],
            'trade'=>$status['trade'],
            'ppsid'=>$status['ppsid'],
            'dispatch'=>$status['dispatch'],
            'adate'=>time(),
        ];
        Db::startTrans();
        try{
            if(!!$bo){
                Db::name('bind_order')->where($dataBO)->delete();
            }
            Db::name('order_post')->insert($oidPost);
            Db::commit();
        }catch(\think\Exception $e){
            Db::rollback();
            return ['code'=>-1,'msg'=>'系统繁忙，请稍候再试！'.$e->getMessage()];
        }
        return ['code'=>0,'msg'=>'取消分配司机成功！','refresh'=>['sign'=>1,'t'=>500]];
        
    }
    public function printorder(){
    	$oid = $conditions['oid'] = intval(input('oid'));
    	$sfd= intval(input('sfd'));
    	$where['gid|ppsid']=$this->gid;
    	$where['oid']=['lt',$oid];
    	$p=Db::name('order')->where($where)->order('oid desc')->find();
    	
    	$where2['gid|ppsid']=$this->gid;
    	$where2['oid']=['gt',$oid];
        
    	$p2=Db::name('order')->where($where2)->order('oid asc')->find();
        
    	if($p['oid']) $yema['s']='<INPUT type="button" value="上一个" onclick=javascript:window.location.href="/admin/order/printorder/oid/'.$p['oid'].'">';
    	else $yema['s']='';
    	if($p2['oid']) $yema['x']='<INPUT type="button" value="下一个" onclick=javascript:window.location.href="/admin/order/printorder/oid/'.$p2['oid'].'">';
    	else $yema['x']='';
    	//订单信息
    	$where['gid|ppsid']=$this->gid;
    	$where['oid']=$oid;
    	$orders=Db::name('order')->where($where)->find();
    	if($orders['confirm']==-1){
    		//$this->error('无效订单不能打印');
    	}
    	$orders_goods=Db::name('order_goods')->where('oid',$orders['oid'])->select();
    	foreach ($orders_goods as $g){
            $g['seller_note'] = Db::name('goods')->where('goods_id',$g['goodid'])->value('seller_note');
            $g['market_price'] = Db::name('baojia')->where('goods_id',$g['goodid'])->where('gid',$this->gid)->value('retailprice');
            $orders['goods'][]=$g;
    	}
    	if($orders['ppsid']){
    		/*
	    	$whereD['status'] = 1;
	    	$whereD['gid'] = $this->gid;
	    	$whereD['psid'] = $orders['ppsid'];
	    	$whereD['type'] = 2;
	    	$whereD['expdate'] = ['EGT',time()];
	    	$orders['down'] = Db::name('distributor')->where($whereD)->find();
	    	$orders['down']['title']=$orders['down']['sname'];
	    	$orders['down']['address']=$orders['down']['saddress'];
	    	$orders['down']['phone']=$orders['down']['sphone'];
	    	*/
    		$orders['down'] = Db::name('group')->where('gid',$orders['ppsid'])->find();
    	}else{
	    	$orders['down'] = Db::name('group')->where('gid',$orders['gid'])->find();
    	}
    	//订货人信息
    	$orders['user']=Db::name('order_member')->where('oid',$orders['oid'])->find();
    	if(!$orders['user']){
    		$orders['user']=Db::name('user_member')->where('uid',$orders['uid'])->find();
    	}
    	$userinfo=Db::name('user_member')->where('uid',$orders['uid'])->find();
    	$orders['user']['contact']=$userinfo['contact'];
    	if($orders['user']['realname']!=$userinfo['realname']){
    		$orders['user']['gs']=$userinfo['realname'];
    	}
    	//业务员
    	$orders['host']['gjr']=Db::name('group_member')->where('uid',$orders['dguid'])->value('realname');
    	$ordergoodsnum=count($orders['goods']);
    	if($this->gid==143) $ordergoodsnum=7-$ordergoodsnum;
    	$this->assign('ordergoodsnum', $ordergoodsnum);
    	//	print_r($orders);
    	if($orders['gid'] == $this->gid)
    	{
    
    		$orders['dtotal'] = $this->cny($orders['total']);
    		if($orders['total']==0){
    			$orders['dtotal']='零元整';
    		}
    		$shijian=date('Y-m-d',time());
    
    		if($this->gid==159) {
    			$orders['number']=substr($orders['number'], 8);
    		}
                if($this->gid==206) {
                    $orn = $orders['number'] ;
                    $orders['number'] = 'XS-';
                    $orders['number'] .= substr($orn,0,4) . '-';
                    $orders['number'] .= substr($orn,4,2) . '-';
                    $orders['number'] .= substr($orn,6,2) . '-';
                    $orders['number'] .= substr($orn,8,4);
    		}
                if($this->gid==201) {
                    $orn = $orders['number'] ;
                    $orders['number'] = 'XK-';
                    $orders['number'] .= substr($orn,0,8) . '-';
                    $orders['number'] .= substr($orn,8,5);
                    if(!empty($orders['user']['address'])){
                        $orders['user']['addr'] = '';
                        for($i = 0,$l = mb_strlen($orders['user']['address'],'utf-8');$i<$l;$i+=12){
                            $orders['user']['addr'] .= mb_substr($orders['user']['address'],$i,12,'utf-8') . '<br />';
                        }
                    }
	            //自动设置订单的配送状态为配送中
                    $whereOp['gid'] = $this->gid;
                    $whereOp['oid'] = $orders['oid'];
                    $whereOp['type'] = 4;
                    if(!Db::name('order_post')->where($whereOp)->count()){
                        try{
                            $oidPost = [
                                'content'=>'打印订单',
                                'oid'=>$orders['oid'],
                                'type'=>4,
                                'gid'=>$this->gid,
                                'guid'=>$this->guid,
                                'uid' => $orders['uid'],
                                'thumb'=>'',
                                'confirm'=>$orders['confirm'],
                                'pay'=>$orders['pay'],
                                'status'=>$orders['status'],
                                'trade'=>$orders['trade'],
                                'ppsid'=>$orders['ppsid'],
                                'dispatch'=>$orders['dispatch'],
                                'adate'=>time(),
                            ];
                            Db::name('order_post')->insert($oidPost);
                        }catch(\think\Exception $e){
		//	    echo $e->getMessage();
                        }
                    }
    		}
                $groupinfo = session('groupinfo');
                $this->assign('groupinfo',$groupinfo);
    		if($this->gid==133||$this->gid==159) {
    		//每页的商品数量
            	if($sfd) $n=6;
            	else $n=6;
            	$pp=ceil(count($orders['goods'])/$n);
            	if($pp>1){
            		$pagetotal=[];
            		for($i=1;$i<=$pp;$i++){
            		$pagegoods[$i]=array_slice($orders['goods'],$n*($i-1),$n);
            		$t=0;
            		foreach ($pagegoods[$i] as $pt){
            			$t+=$pt['amount'];
            			$pagetotal[$i]['total']=$t;
            		}
            		$pagetotal[$i]['dtotal']=$this->cny(sprintf('%.2f',$pagetotal[$i]['total']));
            		$pagetotal[$i]['total']=number_format($pagetotal[$i]['total'],2);
            		$pagetotal[$i]['total']=str_replace(',','',$pagetotal[$i]['total']);
            		$pagetotal[$i]['dtotal']=str_replace(',','',$pagetotal[$i]['dtotal']);
                    if($pagetotal[$i]['total']==0){
                            $pagetotal[$i]['dtotal']='零元整';
                            }
            		$ppp[]=$i;
            		$pagegoodsnum[$i]=$n-count($pagegoods[$i]);
            		}
            		if($this->gid==133){
            		//	print_r($pagetotal);
            		}
            		$this->assign('ppp', $ppp);
            		$this->assign('pagegoods', $pagegoods);
            		$this->assign('pagetotal', $pagetotal);
            		$this->assign('pagegoodsnum', $pagegoodsnum);
            	}
            	if($ordergoodsnum<=$n){
            		//print_r($ordergoodsnum);
            		$this->assign('yordergoodsnum', $n-$ordergoodsnum);
            	}
            	$this->assign('n', $n);
            	$this->assign('pp', $pp);
    		}
    		
    		if($this->gid==154){
    			$this->assign('yordergoodsnum', 12-$ordergoodsnum);
    		}
    		
    		$this->assign('sfd', $sfd);
    		$this->assign('yema', $yema);
    		$this->assign('shijian', $shijian);
    		$this->assign('kpr', session('userinfo.realname'));
    		//print_r($orders);
    		$this->assign('order', $orders);
    		$printfile=ROOT_PATH.'apps/admin/view/p/print_'.$this->gid.'.html';
    		if($this->gid==159){
                    if(!$sfd) {
                        return $this->fetch('p/print_demo_order');
                    }
    		}
    		if(file_exists($printfile)){
                    if($this->gid==133){
                        return $this->fetch('p/print_159');
                    }
                    return $this->fetch('p/print_'.$this->gid);
    		}else{
    			return $this->fetch('p/print_demo_order');
    		}
    	}
    }
    public function add(){
    	$addOrder = new Orderrn();
        return $addOrder->index();
    }
    /*将数字金额转成大写*/
    private function cny($ns) {
    	static $cnums=array("零","壹","贰","叁","肆","伍","陆","柒","捌","玖"),
    	$cnyunits=array("圆","角","分"),
    	$grees=array("拾","佰","仟","万","拾","佰","仟","亿");
    	$zheng='整'; //追加"整"字
    	list($ns1,$ns2)=explode(".",$ns,2);
    	$ns2=array_filter(array($ns2[1],$ns2[0]));
    	$ret=array_merge($ns2,array(implode("",$this->cny_map_unit(str_split($ns1),$grees)),""));
    	$ret=implode("",array_reverse($this->cny_map_unit($ret,$cnyunits)));
    	return str_replace(array_keys($cnums),$cnums,$ret).$zheng;
    }
    private function cny_map_unit($list,$units) {
    	$ul=count($units);
    	$xs=array();
    	foreach (array_reverse($list) as $x) {
    		$l=count($xs);
    		if ($x!="0" || !($l%4)){
    			$uu=isset($units[($l-1)%$ul])?$units[($l-1)%$ul]:'';
    			$n=($x=='0'?'':$x).$uu;
    		}else{ 
    			$xx=isset($xs[0][0])?$xs[0][0]:'';
    			$n=is_numeric($xx)?$x:'';
    		}
    		array_unshift($xs,$n);
    	}
    	return $xs;
    }
}

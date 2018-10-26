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
use app\m\model\UserMember;
use app\m\model\UserType;
use think\Db;
use think\Request;
use think\wechatsdk\Api;
class User extends Base
{
    //用户列表
    private $hsgoods;
    private $subgms = [];
    public function index(){
    	if(session('gid')==133){
    		//$this->ybdownimg();
    	}
    	if(request()->isAjax()){
            $param = input('param.');            
            return $this->getuserslist($param);
        }
        
    }
    private function getuserslist($param){
    	$limit = isset($param['limit']) ? intval($param['limit']) :5;
    	$p=isset($param['p']) ? intval($param['p']):1;
    	$offset = ( $p- 1)* $limit;
    	$where = [];
    	$wherec = [];
        $t=0;
    	switch($param['type']){
            //1.我的客户2.公司客户3.下属客户4.客户类型
            case '1':
                    $where['gm.gid'] = $this->gid;
                    $where['gm.guid'] = $this->guid;
                    $where['u.status'] = 1;
                    $wherec['gm.guid'] = $this->guid;
                    $wherec['gm.gid'] = $this->gid;
                    $wherec['u.status'] = 1;
                    $t=1;
                    break;
            case '2':
                    $where['u.gid'] = $this->gid;
                    $where['u.guid'] = '';
                    $where['u.status'] = 1;
                    $wherec['status'] = 1;
                    $wherec['guid'] = '';
                    $wherec['gid'] = $this->gid;
                    $t=0;
                    break;
            case '3':
                $where['u.gid'] = $this->gid;
                $where['u.status'] = 1;
                $wherec['gm.status'] = 1;
                $this->getsubgm($this->guid);
                $subgmsguids = [];
                $subgms = count($this->subgms);
                foreach($this->subgms as $v){
                    if($v['guid'] != $this->guid){
                        $subgmsguids[] = $v['guid'];
                    }
                }
                if(isset($param['subguid']) && !! ($subguid = trim($param['subguid']))){
                    if(in_array($subguid,$subgmsguids)){
                        $where['gm.guid'] = $subguid;
                        $wherec['gm.guid'] = $subguid;
                    }else{
                        $where['gm.guid'] = 0;
                        $wherec['gm.guid'] = 0;
                    }
                }else{
                    if($subgms){
                        $where['gm.guid'] = ['IN',$subgmsguids];
                        $wherec['gm.guid'] = ['IN',$subgmsguids];
                    }else{
                        return json(['code'=>1,'total'=>0,'data'=>'','p'=>1]);
                    }
                }
                $t=1;
                break;
            case '4':return $this->getusertype();
            case 'lbs': 
            	$lbs['lat']=input('lat');
            	$lbs['lng']=input('lng');
            	if($lbs['lat']&&$lbs['lng']){
            		session('nearbylbs',getbaidugps($lbs['lat'],$lbs['lng'],true));  
            		if($this->gid==133) print_r(session('nearbylbs'));
            		exit;
            	}else{
            		session('nearbylbs',null);
            	}
            default:
                return json(['code'=>0,'msg'=>'无效操作','type'=>1]);
        }
        if (isset($param['nearby']) && !empty($param['nearby'])) {
        	$lbs['lat']=input('lat');
        	$lbs['lng']=input('lng');
        	if($lbs['lat']&&$lbs['lng']){
        	//$xy=session('nearbylbs');
        	if(input('jl')) $jl=input('jl');
        	else $jl=0.2;
        	$xy=getbaidugps($lbs['lat'],$lbs['lng'],true);
        	$squares=GetSquarePoint($xy[1],$xy[0],$jl); //200米内的的客户       	
        	$whereNE['x'] = ['BETWEEN',[$squares['right-bottom']['lat'],$squares['left-top']['lat']]];
        	$whereNE['y'] = ['BETWEEN',[$squares['left-top']['lng'],$squares['right-bottom']['lng']]];
        	$whereNE['gid'] = $this->gid;
        	$whereNE['status'] = 1;
        	//$whereNE['guid'] = $this->guid;
        	//print_r($whereNE);
        	
        	$uids = Db::name('user_member')->where($whereNE)->column('uid');
        	if($this->gid==133){
        		//print_r($whereNE);
        	}
        	//echo $uids;exit;
        	}else{
        		$uids='';
            }        
        	if(!empty($uids)){
        		$wherec['gm.uid'] = $where['u.uid'] = ['IN',$uids];
        	}else{
        		//既然今日拜访客户没有，那就没有必要继续进行查询了！
        		$return['total'] = 0;  //总数据
        		$return['data'] = null;
        		$return['p'] = 1;
        		$return['code'] = 0;
        		$return['msg'] ='没有数据！';
        		return json($return);
        	}
        }
    	if (isset($param['visited']) && !empty($param['visited'])) {
            //$whereOr2['mobile'] = $whereOr1['mobile'] = $whereOr['u.mobile'] = ['like', '%' . $param['mobile'] . '%'];
            $s = strtotime(date('Y-m-d'));
            $e = time();
            $whereUP['adate'] = ['BETWEEN',[$s,$e]];
            $whereUP['gid'] = $this->gid;
            $uids = Db::name('user_post')->where($whereUP)->group('uid')->column('uid');
            if(!empty($uids)){
                $wherec['gm.uid'] = $where['u.uid'] = ['IN',$uids];
            }else{
                //既然今日拜访客户没有，那就没有必要继续进行查询了！
                $return['total'] = 0;  //总数据
                $return['data'] = null;
                $return['p'] = 1;
                $return['code'] = 0;
                $return['msg'] ='没有数据！';
                return json($return);
            }
    	}
    	
    	$whereOr1 = [];
    	$whereOrc1 = []; //客户名称，地址，电话 复合条件
    	if (isset($param['realname']) && !empty($param['realname'])) {
            $whereOrc1['realname'] = $whereOr1['u.realname'] = ['like', '%' . $param['realname'] . '%'];
    	}
    	if (isset($param['contact']) && !empty($param['contact'])) {
            $whereOrc1['contact'] = $whereOr1['u.contact'] = ['like', '%' . $param['contact'] . '%'];
    	}
    	if (isset($param['address']) && !empty($param['address'])) {
            $whereOrc1['address'] = $whereOr1['u.address'] = ['like', '%' . $param['address'] . '%'];
    	}
    	if (isset($param['phone']) && !empty($param['phone'])) {
            $whereOrc1['phone'] = $whereOr1['u.phone'] = ['like', '%' . $param['phone'] . '%'];
    	}
    	if (isset($param['mobile']) && !empty($param['mobile'])) {
            $whereOrc1['mobile'] = $whereOr1['u.mobile'] = ['like', '%' . $param['mobile'] . '%'];
    	}
    	$whereOr2 = [];
    	$whereOrc2 = [];//拜访类型，客户类型 复合条件
    	if(isset($param['visitids']) && !empty($param['visitids'])){
            $visitids1 = trim(trim($param['visitids']),',');
            if(!!$visitids1){
                $visitids2 = explode(',',$visitids1);
                $whereOrc2['visitid'] = $whereOr2['u.visitid'] = ['IN',$visitids2];
            }
    	}else if (isset($param['visitid']) && !empty($param['visitid'])) {
            $whereOrc2['visitid'] = $whereOr2['u.visitid'] = ['EQ',$param['visitid']];
    	}
    	if(isset($param['tpids']) && !empty($param['tpids'])){
            $tpids1 = trim(trim($param['tpids']),',');
            if(!!$tpids1){
                $tpids2 = explode(',',$tpids1);
                $whereOrc2['tpid'] = $whereOr2['u.tpid'] = ['IN',$tpids2];
            }
    	}else if(isset($param['tpid']) && !empty($param['tpid'])) {
            $whereOrc2['tpid'] = $whereOr2['u.tpid'] = ['EQ',$param['tpid']];
    	}
    	if (isset($param['weixin']) && !empty($param['weixin'])) {
            if(intval(trim($param['weixin'])) === 1){
                $wherec['weixin'] = $where['u.weixin'] = ['NEQ',''];
            }else{
                $wherec['weixin'] = $where['u.weixin'] = ['EQ',''];
            }
    	}
    	if (isset($param['bdate']) && !empty($param['bdate'])) {
    		$where['u.bdate'] = ['between time',[0,time()-$param['bdate']*84000]];
    	}
    	if (isset($param['odate']) && !empty($param['odate'])) {
    		$where['u.odate'] = ['between time',[0,time()-$param['odate']*84000]];
    	}
    	$user = new UserMember();
    	if($param['type'] === '1' || $param['type'] === '2' || $param['type'] === '3'){
            $res = $user->getUsersByWhere($where,$whereOr1,$whereOr2,$offset, $limit,$t);
            //print_r($wherec);
            $total = $user->getAllUsers($wherec,$whereOrc1,$whereOrc2,$t);
    	}
    	$status = config('user_status');
    	foreach($res as $key=>$vo){
            //处理跟进人是否是本人的问题
            $weixin=Db::name('user_open')->where('uid',$vo['uid'])->value('weixin');
    		$res[$key]['weixin']=$weixin?$weixin:'';
            $res[$key]['lastvisit'] = date('Y-m-d H:i:s', $vo['lastvisit']);
            $res[$key]['status'] = $status[$vo['status']];
            $bdate=$res[$key]['bdate']?$res[$key]['bdate']:$res[$key]['regtime'];
            if($bdate){
	            $bftime=round((time()-$bdate)/84000);
	            $res[$key]['bftime'] = $bftime;
            }else{
            	$res[$key]['bftime'] ='wuxiao';
            }
            $odate=$res[$key]['odate'];
            if($odate){
            	$otime=round((time()-$odate)/84000);
            	$res[$key]['otime'] = $otime;
            }else{
            	$res[$key]['otime'] ='wuxiao';
            }
    	}
    	$return['total'] = $total;  //总数据
    	$return['data'] = $res;
    	$return['p'] = intval($param['p']);
    	$return['code'] =1;
    	$return['msg'] ='数据加载成功！';
    	if(count($res)==0){
            $return['code'] =0;
            $return['msg'] ='没有数据！';
    	}
    	return json($return);
    }
    private function getuserslist2($param){
        $limit = $param['limit'];
        $offset = ($param['p'] - 1) * $limit;
        $where1 = [];
        $where2 = [];
        $where2['gid'] = session('gid');
       // if($this->gid==133){
       // 	$param['type']=5;
       // }
        $t=0;
        switch($param['type']){
            //1.我的客户2.公司客户3.下属客户4.客户类型5,兼容多跟进人我的客户
            case '1':
                $whereOr = [];
                $whereOr2 = [];
               // $where1['u.gid'] = $this->gid;
              //  $where1['u.guid'] = $this->guid;
                $where1['gm.gid'] = $this->gid;
                $where1['gm.guid'] = $this->guid;
                $where2['guid'] = $this->guid;
                $t=1;
                break;
            case '5':
                	$whereOr = [];
                	$whereOr2 = [];
                	$where1['gm.gid'] = $this->gid;
                	$where1['gm.guid'] = $this->guid;
                	$where2['guid'] = $this->guid;
                	$t=1;
                	break;
            case '2':
                $whereOr = [];
                $whereOr2 = [];
                $where1['u.gid'] = $this->gid;
                $where1['u.guid'] = '';
                $where2['guid'] = '';
                break;
            case '3':
                $whereOr = [];
                $whereOr2 = [];
                $where1['u.gid'] = $this->gid;
                $subgms = $this->getsubgm();
                $subgmsguids = [];
                foreach($subgms as $k=>$v){
                    $subgmsguids[] = $v['guid'];
                }
                if(isset($param['subguid']) && !! ($subguid = trim($param['subguid']))){
                    if(in_array($subguid,$subgmsguids)){
                        $where1['u.guid'] = $subguid;
                        $where2['guid'] = $subguid;
                    }else{
                        $where1['u.guid'] = 0;
                        $where2['guid'] = 0;
                    }
                }else{
                    if($subgms){ 
                    	$where1['u.guid'] = ['IN',$subgmsguids];
                    	$where2['guid'] = ['IN',$subgmsguids];
                    }else{
                    	 return json(['code'=>1,'total'=>0,'data'=>'','p'=>1]);
                    }
                }
                break;
            case '4':return $this->getusertype();
            default:
                return json(['code'=>0,'msg'=>'无效操作','type'=>1]);
        }
        if (isset($param['realname']) && !empty($param['realname'])) {
            $whereOr2['realname'] = $whereOr1['realname'] = $whereOr['u.realname'] = ['like', '%' . $param['realname'] . '%'];
        }
        if (isset($param['contact']) && !empty($param['contact'])) {
            $whereOr2['contact'] = $whereOr1['contact'] = $whereOr['u.contact'] = ['like', '%' . $param['contact'] . '%'];
        }
        if (isset($param['address']) && !empty($param['address'])) {
            $whereOr2['address'] = $whereOr1['address'] = $whereOr['u.address'] = ['like', '%' . $param['address'] . '%'];
        }
        if (isset($param['phone']) && !empty($param['phone'])) {
            $whereOr2['phone'] = $whereOr1['phone'] = $whereOr['u.phone'] = ['like', '%' . $param['phone'] . '%'];
        }
        if (isset($param['mobile']) && !empty($param['mobile'])) {
            $whereOr2['mobile'] = $whereOr1['mobile'] = $whereOr['u.mobile'] = ['like', '%' . $param['mobile'] . '%'];
        }
        if (isset($param['visitid']) && !empty($param['visitid'])) {
            $whereOr2['visitid'] = $whereOr1['visitid'] = $whereOr['u.visitid'] = ['EQ',$param['visitid']];
        }
        if (isset($param['tpid']) && !empty($param['tpid'])) {
           $whereOr2['tpid'] = $whereOr1['tpid'] = $whereOr['u.tpid'] = ['EQ',$param['tpid']];
        }
        if (isset($param['weixin']) && !empty($param['weixin'])) {
            if(intval(trim($param['weixin'])) === 1){
                $whereOr2['weixin'] = $whereOr1['weixin'] = $whereOr['u.weixin'] = ['NEQ',''];
            }else{
                $whereOr2['weixin'] = $whereOr1['weixin'] = $whereOr['u.weixin'] = ['EQ',''];
            }
        }
        $user = new UserMember();
        if($param['type'] == '1' || $param['type'] == '2' || $param['type'] == '3'){
            $res = $user->getUsersByWhere($where1,$whereOr,$offset, $limit,$t);
            //$total = $user->getAllUsers($where2,$whereOr2);
            $total = $user->getAllUsersVisit($where1,$whereOr);
        }
        if($param['type'] == '5'){
        	$res = $user->getUsersByWhere($where1,$whereOr,$offset, $limit,$t);
        	$total = $user->getAllUsersVisit($where1,$whereOr);
        }
        $status = config('user_status');
        foreach($res as $key=>$vo){
            //处理跟进人是否是本人的问题
            if($param['action'] === 'to' && $vo['guid']===session('guid')){
                $res[$key]['ismine'] = 1;
            }else{
                $res[$key]['ismine'] = 0;
            }
            $res[$key]['lastvisit'] = date('Y-m-d H:i:s', $vo['lastvisit']);
            $res[$key]['status'] = $status[$vo['status']];
        }
        $return['total'] = $total;  //总数据
        $return['data'] = $res;
        $return['p'] = intval($param['p']);
        $return['code'] =1;
        $return['msg'] ='数据加载成功！';
        if(count($res)==0){
           $return['code'] =0;
           $return['msg'] ='没有数据！';
        }
        return json($return);
    }
    public function subuser(){
    	if(request()->isAjax()){
    		$param = input('param.');
    		if(isset($param['action']) && $param['action']==='getsubgm'){
                $subgm=$this->getsubgm($this->guid);
    			$subgms = [];
    			if($subgm){
                    foreach($subgm as $v){
                        if($v['guid'] != $this->guid){
                            $subgms[] = $v;
                        }
                    }
                }
    			return json(['code'=>1,'data'=>$subgms]);
    		}else{
    			return $this->getuserslist($param);
    		}
    	}
    }
    protected function getsubgm($guid=0){
    	$subgms = Db::name('group_member')
    	->field('uid guid,realname,superid')
    	->where('gid',$this->gid)
    	->where('uid','NEQ',$guid)
    	->where(function ($query) use($guid){
    		$query
    		->whereOr(function ($query) use($guid){
    			$x['superid'] = ['LIKE', $guid . ',%'];
    			$query->whereOr($x);
    		})
    		->whereOr(function ($query) use($guid){
    			$x['superid'] = ['LIKE','%,' . $guid . ',%'];
    			$query->whereOr($x);
    		})
    		->whereOr(function ($query) use($guid){
    			$x['superid'] =  ['EQ',$guid];
    			$query->whereOr($x);
    		})
    		->whereOr(function ($query) use($guid){
                $x['superid'] = ['LIKE','%,' . $guid];
                $query->whereOr($x);
    		});
    	})
    	->select();
    	if(!!$subgms){
    		foreach($subgms as $v){
    			if(!isset($this->subgms[$v['guid']])){
    				$this->subgms[$v['guid']] = $v;
    				//$this->getsubgm($v['guid']);//多级下属资源查看开启去除注释即可!
    			}
    		}
    	}
    }
    public function getusertype(){
    	//获取用户类型
    	$gid = session('gid');
    	$visits = Db::name('user_visit')
    	->field('id,title')
    	->where('gid',$gid)
    	->select();
    	$type = Db::name('user_type')
    	->field('id,title')
    	->where('gid',$gid)
    	->select();
    	$data['v'] = $visits;
    	$data['t'] = $type;
    	return json(['code'=>1,'data'=>$data]);
    }
    public function record(){
        if(request()->isAjax()){
            $action = request()->post('action');
            switch($action){
                case 'visit':
                    return json($this->visitRecordList());
                    break;
                case 'stock':
                    return json($this->stockRecordList());
                    break;
                case 'hs':
                    return json($this->hsRecordList());
                    break;
                case 'ch':
                    return json($this->chRecordList());
                    break;
                default :
                    return json(['code'=>0,'msg'=>'无效操作！']);
            }
        }
    }
    private function hsRecordList(){
        $uid = request()->post('uid');
        $limit = request()->post('limit');
        $offset = (request()->post('p') - 1) * $limit;
        $during = $this->setInterval();
        if(!$uid){
            $uid = session('uid');
        }else{
            session('uid',$uid);
        }
        //生成查询条件
        $s = $during['start'];
        $e = $during['stop'];
        $data = Db::name('goods_snum')
                ->alias('s')
                ->field('s.snm,s.sname,s.adate,s.num,s.unit,g.goods_name')
                ->where('s.uid',$uid)
                ->where('s.adate','BETWEEN', $s . ',' .$e)
                ->join('ljk_goods g','s.goodsid=g.goods_id','LEFT')
                ->limit($offset,$limit)
                ->order('s.adate desc')
                ->select();
        $total = Db::name('goods_snum')
                ->where('uid',$uid)
                ->where('adate','BETWEEN', $s . ',' .$e)
                ->count();
        if(empty($data)){
            return ['code'=>0];
        }else{
            foreach($data as $k=>$v){
                $data[$k]['adate'] = date('Y-m-d H:i:s',$v['adate']);
            }
            return ['code'=>1,'total'=>$total,'data'=>$data];
        }
        return ['code'=>1,'msg'=>'无效操作！'];
    }
    private function chRecordList(){
        $p=intval(trim(request()->post('p')));
        $limit = intval(trim(input('limit')));
        $offset = ($p - 1) * $limit;

        $kw = request()->post('keywords');
        $during = $this->setInterval();
        $uid = session('uid')?session('uid'):input('uid');
        //生成查询条件
        $s = $during['start'];
        $e = $during['stop'];

        $ch = [];
        $chlist=[];
        $total='';
        if($uid) {
            $where=[];
            $where['og.gid']=['EQ',$this->gid];
            $where['o.confirm']=['EGT',0];
            $where['o.uid']=['EQ',$uid];
            $where['og.uid']=['IN',[0,$uid]];
            if($kw){
                $where['og.name']=['LIKE','%'.$kw.'%'];
            }
            if($s || $e){
                $where['og.adate']=['BETWEEN',$s.','.$e];
            }
            $oggoodid=Db::name('order_goods og')->where($where)
                ->join('ljk_order o','og.oid=o.oid','LEFT')
                ->where('og.sku_id','EQ',0)
                ->group('og.goodid')
                ->count();

            $ogskuid=Db::name('order_goods og')->where($where)
                ->join('ljk_order o','og.oid=o.oid','LEFT')
                ->where('og.sku_id','NEQ',0)
                ->group('og.sku_id')
                ->count();

            $average='';
            if($oggoodid===0 && $ogskuid===0){
                //两者都没有商品
                if(empty($ch)){
                    return ['code'=>0,'data'=>[],'msg'=>'没有了'];
                }
            }
            else{
                $orders1 = Db::name('order_goods og')
                    ->field('og.goodid')
                    ->join('ljk_order o', 'og.oid=o.oid', 'LEFT')
                    ->where($where)
                    ->where('og.sku_id', 'EQ', 0)
                    ->order('og.goodid desc')
                    ->group('og.goodid')
                    ->limit($offset,$limit)
                    ->select();
                foreach ($orders1 as $k=>$v){
                    $id=Db::name('order_goods og')
                        ->join('ljk_order o','o.oid=og.oid','LEFT')
                        ->field('og.id,og.uid,og.oid,og.sku_id,og.goodid,og.num,og.amount,og.name,og.type,og.avs,og.uid,og.unitid,og.unit,og.unitg,og.unitgid')
                        ->where($where)
                        ->where('og.goodid','EQ',$v['goodid'])
                        ->select();

                    foreach ($id as $key=>$val){
                        $units1=Db::name('unit')->field('coefficient')->where(array('gid'=>$this->gid,'status'=>1,'id'=>$val['unitid']))->find();
                        if (array_key_exists($val['goodid'], $ch)) {
                            //已存在sku_id就更新
                            $num = $ch[$val['goodid']]['num'] = $units1['coefficient'] * $val['num'] + $ch[$val['goodid']]['num'];
                            $amount = $ch[$val['goodid']]['amount'] = $ch[$val['goodid']]['amount'] + $val['amount'];
                            $average=$ch[$v['goodid']]['average'] = round($amount / $num, 2);
                        } else {
                            $num = $units1['coefficient'] * $val['num'];
                            if ($num) $average = round($val['amount'] / $num, 2);
                            $ch[$val['goodid']] = [
                                'id'=>$val['id'],
                                'oid'=>$val['oid'],
                                'sku_id'=>$val['sku_id'],
                                'uid'=>$val['uid'],
                                'goodid' => $val['goodid'],
                                'amount' => $val['amount'],
                                'name' => $val['name'],
                                'avs' => $val['avs'],
                                'num' => $num,
                                'type' => $val['type'],
                                'unitgid' => $val['unitgid'],
                                'average' => $average,
                                'unitg' => $val['unitg']
                            ];
                        }
                    }
                }
                $orders2 = Db::name('order_goods og')
                    ->field('og.sku_id')
                    ->join('ljk_order o', 'og.oid=o.oid', 'LEFT')
                    ->where($where)
                    ->where('sku_id', 'NEQ', 0)
                    ->order('og.sku_id desc')
                    ->group('og.sku_id')
                    ->limit($offset,$limit)
                    ->select();
                foreach ($orders2 as $k=>$v){
                    $id=Db::name('order_goods og')
                        ->field('og.id,og.uid,og.oid,og.sku_id,og.goodid,og.num,og.amount,og.name,og.type,og.avs,og.uid,og.unitid,og.unit,og.unitg,og.unitgid')
                        ->join('ljk_order o','og.oid=o.oid','LEFT')
                        ->where($where)
                        ->where('og.sku_id','EQ',$v['sku_id'])
                        ->select();
                    foreach ($id as $key=>$val){
                        $units1=Db::name('unit')->field('coefficient')->where(array('gid'=>$this->gid,'status'=>1,'id'=>$val['unitid']))->find();
                        if (array_key_exists($val['sku_id'], $ch)) {
                            //已存在sku_id就更新
                            $num = $ch[$val['sku_id']]['num'] = $units1['coefficient'] * $val['num'] + $ch[$val['sku_id']]['num'];
                            $amount = $ch[$val['sku_id']]['amount'] = $ch[$val['sku_id']]['amount'] + $val['amount'];
                            $average=$ch[$v['sku_id']]['average'] = round($amount / $num, 2);
                        } else {
                            $num = $units1['coefficient'] * $val['num'];
                            if ($num) $average = round($val['amount'] / $num, 2);
                            $ch[$val['sku_id']] = [
                                'id'=>$val['id'],
                                'oid'=>$val['oid'],
                                'sku_id'=>$val['sku_id'],
                                'uid'=>$val['uid'],
                                'goodid' => $val['goodid'],
                                'amount' => $val['amount'],
                                'name' => $val['name'],
                                'avs' => $val['avs'],
                                'num' => $num,
                                'type' => $val['type'],
                                'unitgid' => $val['unitgid'],
                                'average' => $average,
                                'unitg' => $val['unitg']
                            ];
                        }
                    }
                }
                foreach ($ch as $k=>$v){
                    $v['unit1']=$this->exchangeNum($v['num'],$v['unitgid']);
                    $chlist[]=$v;
                }
                $total = count($chlist);
                $data = $chlist;
            }
        }
        if(empty($data)){
            return ['code'=>0,'data'=>[],'msg'=>'没有了'];
        }else{
            return ['code'=>1,'p'=>$p,'total'=>$total,'data'=>$data];
        }
    }
    private function exchangeNum($num = 0,$unitgid = 0,$zeroSign = false){
        $exChangeNum = [];
        if(!isset($this->units[$unitgid])){
            $whereU['gid'] = $this->gid;
            $whereU['unitgid'] = $unitgid;
            $whereU['status'] = 1;//有效单位
            $this->units[$unitgid] = Db::name('unit')->where($whereU)->order(['coefficient'=>'DESC'])->column('id,uname,coefficient');
        }
        $units = $this->units[$unitgid];
        foreach($units as $u){
            $c = $u['coefficient'];
            $x = $num / $c;
            if($x == 0 && $zeroSign === false){
                return $exChangeNum;
            }else if(floor($x) == 0 && $zeroSign === true){
                $exChangeNum[] = [
                    'unitid' => $u['id'],
                    'uname'=>$u['uname'],
                    'num'=>0,
                    'coefficient'=>$c
                ];
            }else if($x >= 1){
                $x = floor($x);
                $unum = $x * $c;
                $num -= $unum;
                $exChangeNum[] = [
                    'unitid' => $u['id'],
                    'uname'=>$u['uname'],
                    'num'=>$x,
                    'coefficient'=>$c
                ];
            }
        }
        return $exChangeNum;
    }
    private function stockRecordList(){
        $uid = request()->post('uid');
        $limit = request()->post('limit');
        $offset = (request()->post('p') - 1) * $limit;
        $during = $this->setInterval();
        if(!$uid){
            $uid = session('uid');
        }else{
            session('uid',$uid);
        }
//生成查询条件
        $s = date('Y-m-d',$during['start']);
        $e = date('Y-m-d',$during['stop']);
        $data = Db::name('user_stock')
                ->alias('s')
                ->field('s.mdate,s.adate,s.num,s.code,s.unit,g.goods_name')
                ->where('s.uid',$uid)
                ->where('s.adate','BETWEEN', $s . ',' .$e)
                ->join('ljk_goods g','s.goodsid=g.goods_id','LEFT')
                ->limit($offset,$limit)
                ->order('s.adate desc')
                ->select();
        $total = Db::name('user_stock')
                ->where('uid',$uid)
                ->where('adate','BETWEEN', $s . ',' .$e)
                ->count();
        //处理获取的$data更改时间格式，获取图片附件url
        if(empty($data)){
            return ['code'=>0];
        }else{
            return ['code'=>1,'total'=>$total,'data'=>$data];
        }
        return ['code'=>1,'msg'=>'无效操作！'];
    }
    private function visitRecordList(){
        $uid = request()->post('uid');
        $limit = request()->post('limit');
        $offset = (request()->post('p') - 1) * $limit;
        $during = $this->setInterval();
        if(!$uid){
            $uid = session('uid');
        }else{
            session('uid',$uid);
        }
       
        $s = $during['start'];
        $e = $during['stop'];
        $data = Db::name('user_post')
                ->alias('p')
                ->field('p.id,p.imgsid,p.is_down,p.wx_serverid,p.content,p.adate,p.xm_title,m.realname')
                ->where('p.uid',$uid)
                ->where('p.gid',$this->gid)
                ->where('p.adate','BETWEEN', $s . ',' .$e)
                ->join('ljk_group_member m','p.guid=m.uid','LEFT')
                ->limit($offset,$limit)
                ->order('p.adate desc')
                ->select();
        $total = Db::name('user_post')
                ->where('uid',$uid)
                ->where('gid',$this->gid)
                ->where('adate','BETWEEN', $s . ',' .$e)
                ->count();
        //处理获取的$data更改时间格式，获取图片附件url
        foreach($data as $k=>$v){
        	if($v['is_down']==0&&$v['wx_serverid']!=''&&$v['adate']>time()-3*24*60*60){
        		$v['imgsid']= $this->ybdownwximg($v['id'], 'ydxzym'.$v['id']);
        	}
            $data[$k]['adate'] = date('Y-m-d H:i:s',$v['adate']);
            if($v['imgsid']){
	            $imgsid = explode('_',$v['imgsid']);
	            $unique  = array_values(array_unique($imgsid));
	            $imgsurl = [];
	            $imgsurlu = [];
	            for($i=0;$i<count($unique);$i++){
	                $imgsurlu[$unique[$i]] = mkurl(Db::name('file')->field('savename,savepath,ext,url')->find($unique[$i]));
	            }
	            for($i=0;$i<count($imgsid);$i++){
	                $imgsurl[] = $imgsurlu[$imgsid[$i]];
	            }
            }else{
            	$imgsurl=[];
            }
            $data[$k]['imgsurl'] = $imgsurl;
			//获取位置信息
			$pid = $v['id'];
			$lbs = Db::name('group_memberlbs')->field('id,x,y,business,street,street_number,province,city,district,address,upnum')->where('pid',$pid)->where('type',0)->find();
			if(!empty($lbs)){
				if(!$lbs['address']&&$lbs['address']<5){
					$lbs=updategps($lbs['id']);
				}
				$data[$k]['lbs'] = $lbs;
				$memberinfo=Db::name('user_member')->field('x,y')->where('uid',$uid)->find();
				if($memberinfo['x']){
					$juli=GetDistance($memberinfo['x'],$memberinfo['y'],$lbs['x'],$lbs['y'],1,0);
					if($juli<500) $data[$k]['juli']='<font color="#006600">'.$juli.'米</font>';
					if($juli<1000 and $juli>500) $data[$k]['juli']='<font color="#009900">'.$juli.'米</font>';
					if($juli<3000 and $juli>1000) $data[$k]['juli']='<font color="#996600">'.$juli.'米</font>';
					if($juli>3000) $data[$k]['juli']='<font color="#FF0000">'.$juli.'米</font>';
				}
			}else{
				$data[$k]['lbs'] = null;
			}
        }
        if(empty($data)){
            return ['code'=>0];
        }else{
            return ['code'=>1,'total'=>$total,'data'=>$data];
        }
    }
    private function setInterval(){
        //时间以csdate,cedate为准
        $s = trim(request()->post('from'));
        $e = trim(request()->post('to'));
        $n = date('Y-m-d');
        $s = !!$s ? $s : 0;
        $e = !!$e ? $e : $n;
        $start = strtotime($s);
        $stop = strtotime($e) + 3600*24 - 1;
        return ['start'=>$start,'stop'=>$stop];
    }   
    public function profile(){
        if(request()->isAjax()){
            switch (input('action')){
                case 'show':
                    return json($this->getProfile());
                    break;//获取客户资料
                case 'edit':
                    $profile = $this->dealProfile();//处理输入进来的客户资料
                    return json($this->editProfile($profile));
                    break;//编辑客户资料
                case 'add':
                    $profile = $this->dealProfile();//同样处理输入进来的客户资料
                    return json($this->addProfile($profile));
                    break;//新增客户资料
                case 'create':
                    if($this->isCreated()){
                        return json(['code'=>1,'msg'=>'客户已被创建！']);
                    }
                    $profile = $this->dealProfile();//处理输入进来的待创建客户资料
                    return json($this->editProfile($profile));
                    break;//新增客户资料
                case 'form':
                    return $this->addProfileForm();
                    break;
                case 'showcreate':
                    return json($this->getProfile());
                    break;//创建客户资料 
                default :
                    return json(['code'=>1,'msg'=>'无效操作！']);
            }
        }
    }
    private function addProfileForm(){
        $gid = session('gid');
        $type = Db::name('user_type')->field('id,status,title')->where('gid',$gid)->where('status',1)->select();
        //echo Db::name('user_type')->field('id,status,title')->where('gid',$gid)->where('status',1)->buildSql();
        $gxy = Db::name('group')->field('x,y')->where('gid',$gid)->find();
        return ['code'=>1,'data'=>['type'=>$type,'gxy'=>$gxy]];
    }
    private function isCreated(){
        $uid = request()->post('uid');
        $one = Db::name('user_member')->field('guid')->find($uid);
        if(!empty($one)){
            return $one['guid'];
        }else{
            return false;
        }
    }
    private function getProfile(){
        $uid = request()->post('uid');
        $action = request()->post('action');
        $gid = session('gid');
        $guid = session('guid');
        if(!$uid){
            $uid = session('uid');
        }else{
            session('uid',$uid);
        }
        if($action === 'showcreate'){
            $guid = '';
        }
        $one = Db::name('user_member')->where('uid',$uid)->where('gid',$gid)->find();
    	if(!empty($one)){
            $editact = false;
        }else if($one['guid'] == $guid){
            $editact = true;
        }else{
            $whereGM['uid'] = $one['guid'];
            $whereGM['gid'] = $this->gid;
            $superid = Db::name('group_member')->where($whereGM)->value('superid');
            $s1 = trim(trim($superid),',');
            if(!!$s1){
                $s1 = explode(',',$s1);
                if(in_array($this->guid,$s1)){
                    $editact = true;
                }else{
                    $editact = false;
                }
            }else{
                $editact = false;
            }
        }
        //判断是否有修改该客户的权限
        if($editact){
            return ['code'=>0,'msg'=>'没有编辑该客户的权限！'];
        }else{
            $type = Db::name('user_type')->field('id,status,title')->where('gid',$gid)->where('status',1)->select();
			$visit = Db::name('user_visit')->field('id,status,title')->where('gid',$gid)->select();
            $gxy = Db::name('group')->field('x,y')->where('gid',$gid)->find();
            //处理$type
            foreach($one as $k=>$v){
                if(!$one[$k]){
                    $one[$k] = '';
                }
            }
            foreach($type as $k=>$v){
                if(!$type[$k]){
                    $type[$k] = '';
                }
            }
            foreach($gxy as $k=>$v){
                if(!$gxy[$k]){
                    $gxy[$k] = '';
                }
            }
            $one['type'] = $type;
			$one['visit'] = $visit;
            $one['gxy'] = $gxy;
            return ['code'=>1,'data'=>$one];
        }
    }
    private function dealProfile(){
        $post = request()->post();
        if(!$post['birthday']){
            $post['birthday'] = '0000-00-00';
        }
        if($post['action'] === 'create'){
            $post['guid'] = session('guid');
        }
        if($post['action'] === 'add'){
            $post['regtime'] = time();
            $post['gid'] = session('gid');
            $post['guid'] = session('guid');
            $post['bd'] = '0000-00-00';
            $post['weixin'] = '';
            $post['user_money'] = 0;
            $post['frozen_money'] = 0;
            $post['pay_points'] = 0;
            $post['rank_points'] = 0;
            $post['bdate'] = time();
            $post['hdate'] = 0;
            $post['odate'] = 0;
            $post['regip'] = get_client_ip();
            if(isset($post['x'])&&isset($post['y'])){
            	if($post['x']){
           		 $zhxy=getbaidugps($post['x'],$post['y'],true);
	             $post['x']=$zhxy[0];
	             $post['y']=$zhxy[1];
            	}
            }
        }
        $post['lastip'] = get_client_ip();
        unset($post['action']);
        return $post;
    }
    private function addProfile($profile){
        $model = new UserMember;
        return $model->addUser($profile);
    }
    private function editProfile($profile){
        $model = new UserMember;
        return $model->editUser($profile);
    }
    public function addstock(){
        if(!!($uid = request()->post('uid'))){
            session('uid',$uid);
        }
        $sessName = 'stock_uid_' . session('uid');
        $uploadImg = controller('uploadImg','event');
        $uploadImg->setSessName($sessName);
        if(request()->isAjax() && input('action') === 'setlocalids'){
        	$localids =input('localids/a');
        	$serverids =input('serverids/a');
        	$uploadImg->setImgLocalIds($localids,$serverids);
        	return 1;
        	//return json(['data'=>['localids'=>session('imgslocalid.'. $sessName),'serverids'=>session('imgsserverid.'. $sessName)],'code'=>1]);
        }
        if(request()->isAjax() && input('action') === 'getlocalids'){
        	$localids = $uploadImg->getImgsLocalIds();
        	return json(['localids'=>$localids,'code'=>1]);
        }
        if(($file = request()->file('file'))){
            if(!empty($file)){
                $imgsid = $uploadImg->index($file);
                return json([$imgsid]);
            }
        }
       
        
        if(request()->isAjax() && input('action') === 'ybdownwximg'){
        	if(input('id')) $this->ybdownwximg(input('id'),$sessName);
        }
        if(is_weixin() && trim(input('sid'))){
        	$serverIds = input('sid');
        	$access_token =$this->getwx_access_token();
            if(!$access_token){
        		return json(['code'=>-1,'msg'=>'未设置公众账号信息，无法使用图片上传功能，请联系系统管理员！']);
        	}
        	$url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token=' . $access_token . '&media_id=' . $serverIds;
        	$imgsid = $uploadImg->wxupload($url);
        	return json([$imgsid]);
        }
        if(request()->isAjax()){
            switch(input('action')){
                case 'list':
                    $input = input('post.');
                    return $this->stockList($input);
                    break;
                case 'show':
                    $imgs = $uploadImg->getImgs();
                    if(is_weixin()){
                    	//微信本地图片
                    	$localids = $uploadImg->getImgsLocalIds();
                    }else{
                    	$localids='';
                    }
                    return json(['data'=>['imgs'=>$imgs,'upimgurl'=>'_m_user_addstock','localids'=>$localids],'code'=>1]);
                    break;
                case 'add':
                    return $this->doStockAdd();
                    break;
                case 'upimgs':
                    return $uploadImg->upimgs();
                    break;
                case 'units':
                    return json($this->unitlist());
                    break;
                default :return json(['code'=>0,'msg'=>'该操作无效！']);
            }
        }
    }
    /*
    private function doStockAdd(){
        $sessName = 'stock_uid_' . session('uid');
        $uploadImg = controller('uploadImg','event');
        $uploadImg->setSessName($sessName);
        $post = request()->post();
        $sid = $post['id'];
        $snum = $post['num'];
        $scode = $post['code'];
        $sunitid = $post['unitid'];
        $smdate = $post['mdate'];
        $imgsid = $post['imgsid'];
        $id = [];
        $num = [];
        $code = [];
        $unitid = [];
        $mdate = [];
        $lbs['x'] = input('lat');
        $lbs['y'] = input('lng');
        if($lbs['x']&&$lbs['y']){
        	$lbs['speed'] = input('speed');
        	$lbs['accuracy'] = input('accuracy');
        	$lbs['gps'] = 1;
        	session('lbsinfo',$lbs);
        	//更新
        	$xx= Db::name('user_member')->where('uid',session('uid'))->value('x');
        	if(!$xx){
        		$zhxy=getbaidugps($lbs['x'],$lbs['y'],true);
        		Db::name('user_member')->where('uid',session('uid'))->update(['x'=>$zhxy[0],'y'=>$zhxy[1]]);
        	}
        }
        for($i=0;$i<count($sid);$i++){
            //判断$shsid是否在数组$hsid内，如果在那么就将对应下标snum值与其相加
            //同时过滤$shsid[$i],$snum[$i]为0的以及非法输入的值
            if(!$smdate[$i]){
                $smdate[$i] = '';
            }
            if(!(intval($sid[$i])) || (!(intval($snum[$i])) && $snum != '0') || !(intval($sunitid[$i]))){
                continue;
            }
            if(($index = array_search($sid[$i],$id)) !== false){
                //如果他的单位也相同就合并
                if($sunitid[$index] === $sunitid[$i] && $smdate[$index] === $smdate[$i]){
                    $num[$index] += $snum[$i];
                    $code[$index] .= '<br />' . trim($scode[$i]);
                }else{
                    $id[] = intval($sid[$i]);
                    $num[] = intval($snum[$i]);
                    $mdate[] = $smdate[$i];
                    $unitid[] = intval($sunitid[$i]);
                    $code[] = trim($scode[$i]);
                }
            }else{
                $id[] = intval($sid[$i]);
                $num[] = intval($snum[$i]);
                $mdate[] = $smdate[$i];
                $unitid[] = intval($sunitid[$i]);
                $code[] = trim($scode[$i]);
            }
        }
        if(!empty($id) && !empty($num)){
            //开始进行数据写入操作！
            $stock = controller('stock','event');
            $userStock = [];//写入userStock
            $userPostContent = '上报库存：<br />';//写入user_post
            for($i=0;$i<count($id);$i++){
                $one = [];
                $unitinfo = Db::name('unit')->field('uname,coefficient,unitgid')->where('id',$unitid[$i])->find();//获取单位名称信息
                $goods = Db::name('goods')->field('goods_name')->where('goods_id',$id[$i])->find();//获取商品名称
                $goodsName = $goods['goods_name'];
                //附加数量信息
                $userPostContent .= "项目为 {$goodsName}数量为 <font color=#FF0000>{$num[$i]}{$unitinfo['uname']}</font><br />";
                //获取商品名称
                $one['code'] = $code[$i];
                $one['unit'] = $unitinfo['uname'];
                $one['coefficient'] = $unitinfo['coefficient'];
                $one['unitgid'] = $unitinfo['unitgid'];
                $one['unitid'] = $unitid[$i];
                $one['goodsid'] = $id[$i];
                $one['num'] = $num[$i];
                $one['uid'] = session('uid');
                $one['gid'] = session('gid');
                $one['guid'] = session('guid');
                $one['mdate'] = $mdate[$i];
                $one['adate'] = date('Y-m-d');
                $userStock[] = $one;
            }
            $userPost = [
                'gid'=>session('gid'),
                'content'=>$userPostContent,
                'uid'=>session('uid'),
                'guid'=>session('guid'),
                'wx_serverid'=> $uploadImg->getImgsServerIds(),
                'imgsid'=>$imgsid,
                'adate'=>time(),
                't'=>2, //上报库存
                'ip'=>get_client_ip(),
                'user_agent'=>$_SERVER['HTTP_USER_AGENT']
            ];
            if($stock->save($userStock)){
                $model = new UserMember;
                $imgsid = array_count_values(explode('_',$imgsid));
                foreach($imgsid as $k=>$v){
                    Db::table('__FILE__')->where('id',$k)->setInc('cited',$v);//图片引用数据设置
                }
            $uploadImg->clear();
			Db::name('user_post')->insert($userPost);
			$fromid = Db::name('user_post')->getLastInsID();
            if($fromid){
			    Db::name('user_member')->where('uid',$userPost['uid'])->update(['bdate'=>time()]);
            	addgps(session('guid'),session('uid'),$fromid);
                return json(['code' => 0, 'id'=>$fromid,'msg' => '添加库存信息成功']);
				}else{
                return json(['code' => 1, 'msg'=>'添加库存信息失败！']);
        	    }
            }else{
                return json(['code' => 1, 'msg'=>'添加库存息失败！']);
            }
            //开始写入跟进信息
        }else{
            return json(['code'=>2, 'msg'=>'未选择上报项目或上报数量有误！']);
        }
    }
    */
    private function doStockAdd(){
    	$sessName = 'stock_uid_' . session('uid');
    	$uploadImg = controller('uploadImg','event');
    	$uploadImg->setSessName($sessName);
    	$post = request()->post();
    	$imgsid = $post['imgsid'];
    	$sid = $post['id'];
    	$snum = $post['num'];
    	$scode = $post['code'];
    	$sunitid = $post['unitid'];
    	$smdate = $post['mdate'];
    	$sgoodsId = $post['goods_id'];
    	$id = $sid;
    	$num = $snum;
    	$code = $scode;
    	$unitid = $sunitid;
    	$mdate = $smdate;
    	$goodsId = $sgoodsId;
    	$lbs['x'] = input('lat');
    	$lbs['y'] = input('lng');
    	if($lbs['x']&&$lbs['y']){
    		$lbs['speed'] = input('speed');
    		$lbs['accuracy'] = input('accuracy');
    		$lbs['gps'] = 1;
    		session('lbsinfo',$lbs);
    		//更新
    		$xx= Db::name('user_member')->where('uid',session('uid'))->value('x');
    		if(!$xx){
    			$zhxy=getbaidugps($lbs['x'],$lbs['y'],true);
    			Db::name('user_member')->where('uid',session('uid'))->update(['x'=>$zhxy[0],'y'=>$zhxy[1]]);
    		}
    	}
    	if(!empty($id)){
    		//开始进行数据写入操作！
    		$stock = controller('stock','event');
    		$userStock = [];//写入userStock
    		$userPostContent = '上报库存：<br />';//写入user_post
    		for($i=0;$i<count($id);$i++){
    			$one = [];
    			$unitinfo = Db::name('unit')->field('uname,coefficient,unitgid')->where('id',$unitid[$id[$i]])->find();//获取单位名称信息
    			$goods = Db::name('goods')->field('goods_name')->where('goods_id',$goodsId[$id[$i]])->find();//获取商品名称
    			$goodsName = $goods['goods_name'];
    			//附加数量信息
    			$userPostContent .= "项目为 {$goodsName}数量为 <font color=#FF0000>{$num[$id[$i]]}{$unitinfo['uname']}</font><br />";
    			//获取商品名称
    			$one['code'] = $code[$id[$i]];
    			$one['unit'] = $unitinfo['uname'];
    			$one['coefficient'] = $unitinfo['coefficient'];
    			$one['unitgid'] = $unitinfo['unitgid'];
    			$one['unitid'] = $unitid[$id[$i]];
    			$one['goodsid'] = $goodsId[$id[$i]];
    			$one['num'] = $num[$id[$i]];
    			$one['uid'] = session('uid');
    			$one['gid'] = session('gid');
    			$one['guid'] = session('guid');
    			$one['mdate'] = $mdate[$id[$i]];
    			$one['adate'] = date('Y-m-d');
    			$userStock[] = $one;
    		}
    		$userPost = [
    		'gid'=>session('gid'),
    		'content'=>$userPostContent,
    		'uid'=>session('uid'),
    		'guid'=>session('guid'),
    		'wx_serverid'=> $uploadImg->getImgsServerIds(),
    		'imgsid'=>$imgsid,
    		'adate'=>time(),
    		't'=>2, //上报库存
    		'ip'=>get_client_ip(),
    		'user_agent'=>$_SERVER['HTTP_USER_AGENT']
    		];
    		if($stock->save($userStock)){
    			$model = new UserMember;
    			$imgsid = array_count_values(explode('_',$imgsid));
    			foreach($imgsid as $k=>$v){
    				Db::table('__FILE__')->where('id',$k)->setInc('cited',$v);//图片引用数据设置
    			}
    			$uploadImg->clear();
    			Db::name('user_post')->insert($userPost);
    			$fromid = Db::name('user_post')->getLastInsID();
    			if($fromid){
    				Db::name('user_member')->where('uid',$userPost['uid'])->update(['bdate'=>time()]);
    				addgps(session('guid'),session('uid'),$fromid);
    				return json(['code' => 0, 'id'=>$fromid,'msg' => '添加库存信息成功']);
    			}else{
    				return json(['code' => 1, 'msg'=>'添加库存信息失败！']);
    			}
    		}else{
    			return json(['code' => 1, 'msg'=>'添加库存息失败！']);
    		}
    		//开始写入跟进信息
    	}else{
    		return json(['code'=>2, 'msg'=>'未选择上报项目或上报数量有误！']);
    	}
    }
    private function unitlist(){
        return Db::name('unit')
                ->field('id,uname')
                ->where('status',1)
               // ->where('gid',session('gid'))
                ->where('unitgid',input('unitgid'))
                ->select();
    }
   private function stockList($input = []){
        $whereBj['b.gid'] = $this->gid;
        $whereBj['b.is_stock'] = 1;
        if(isset($input['keywords']) && strlen(trim($input['keywords']))){
            $keywords = trim($input['keywords']);
            $whereBj['g.goods_name'] = ['LIKE','%' . $keywords . '%'];
        }
        if(!isset($input['p']) || !($p = intval($input['p']))){
            $p = 1;
        }
        if(!isset($input['limit']) || !($limit = intval($input['limit']))){
            $limit = 10;
        }
        $offset = ($p - 1) * $limit;
        $total =  Db::name('baojia')
            ->alias('b')
            ->field('b.id,b.goods_id,b.unit,b.unitid,b.unitgid,g.goods_name')
            ->where($whereBj)
            ->join('goods g','b.goods_id=g.goods_id','LEFT')
            ->count();
        $_lists = Db::name('baojia')
            ->alias('b')
            ->field('b.id,b.goods_id,b.unit,b.unitid,b.unitgid,g.goods_name')
            ->where($whereBj)
            ->join('goods g','b.goods_id=g.goods_id','LEFT')
            ->limit($offset, $limit)
            ->select();
        if(!empty($_lists)){
            //附件单位组信息
            $unitg = [];
            $stockProjects = [];
            foreach($_lists as $v){
                $unitgid = $v['unitgid'];
                if(!isset($unitg[$unitgid])){
                  //  $whereU['gid'] = $this->gid;
                    $whereU['unitgid'] = $unitgid;
                    $whereU['status'] = 1;
                    $unitg[$unitgid] = json_encode(Db::name('unit')->field('id,uname,coefficient')->where($whereU)->order('displayorder DESC')->select());
                }
                $v['unitg'] = $unitg[$unitgid];
                $stockProjects[] = $v;
            }
            return json(['code' => 1,'msg' => '数据加载成功！','data' => $stockProjects,'p' => $p, 'limit' => $limit,'total'=>$total]);
        }else if($total === 0){
            return json(['code' => 0,'msg' => '数据加载成功！','data' => $_lists,'p' => $p, '' => $limit,'total'=>$total]);
        }else{
            return json(['code' => 0,'msg' => '数据加载成功！','data' => $_lists,'p' => $p, 'limit' => $limit,'total'=>$total]);
        }
    }
    public function addvisit(){
        if(!!($uid = request()->post('uid'))){
            session('uid',$uid);
        }
        $sessName = 'visit_uid_' . session('uid');
        $uploadImg = controller('uploadImg','event');
        $uploadImg->setSessName($sessName);
        if(($file = request()->file('file'))){
            if(!empty($file)){
                $imgsid = $uploadImg->index($file);
                return json([$imgsid]);
            }
        }
        if(is_weixin() && trim(input('sid'))){
            $serverIds = input('sid');
            $access_token =$this->getwx_access_token();
            if(!$access_token){
                return json(['code'=>-1,'msg'=>'未设置公众账号信息，无法使用图片上传功能，请联系系统管理员！']);
            }
            $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token=' . $access_token . '&media_id=' . $serverIds;
            $imgsid = $uploadImg->wxupload($url);
            return json([$imgsid]);
        }
        if(request()->isAjax() && input('action') === 'show'){
        	//加载拜访类型
        	$xmlist='';
        	if(session('xmlist')){
        		$xmlist=session('xmlist');
        	}else{
        		if(session('is_setxmlist')!==0){
        			$map['type']=5;  //广宣类
        			$map['status']=1;
	        		$xmlist=Db::name('group_xm')->field('id,title')->where($map)
	        		->where(function ($query){
	        			$query->where('gid',$this->gid)
	        			->whereOr('jtid',$this->jtid);
	        		})->order('displayorder desc,id desc')->select();
	        		if($xmlist){
	        		  session('xmlist',$xmlist);
	        		}else{
	        		  session('is_setxmlist',0);
	        		}
        		}
        	}
        	//本地图片
            $imgs = $uploadImg->getImgs(); 
            if(is_weixin()){           
	            //微信本地图片
	            $localids = $uploadImg->getImgsLocalIds();
            }else{
            	$localids='';
            }        
            $whereUm['gid'] = $this->gid;
            $whereUm['uid'] = session('uid');
            $realname = Db::name('user_member')->where($whereUm)->value('realname');
            return json(['data'=>['imgs'=>$imgs,'upimgurl'=>'_m_user_addvisit','xmlist'=>$xmlist,'localids'=>$localids,'realname'=>$realname],'code'=>1]);
        }
        if(request()->isAjax() && input('action') === 'getlocalids'){
        	$localids = $uploadImg->getImgsLocalIds();
        	return json(['localids'=>$localids,'code'=>1]);
        }
        if(request()->isAjax() && input('action') === 'setlocalids'){
        	$localids =input('localids/a');
        	$serverids =input('serverids/a');
        	$uploadImg->setImgLocalIds($localids,$serverids);
        	return 1;
        }
        if(request()->isAjax() && input('action') === 'ybdownwximg'){
        	if(input('id')) $this->ybdownwximg(input('id'),$sessName);
        }
        if(request()->isAjax() && input('action') === 'add'){
            $post = request()->post();
            $content = $post['content'];
            $xm_id='';$xm_title='';
            if(isset($post['xm_id'])){
            	$xm_id=implode(',',(array)$post['xm_id']);
            }
            if(isset($post['xm_title'])){
            	$xm_title=implode(',',(array)$post['xm_title']);
            }
            if(!$content){
            	return json(['code' => 1, 'msg' => '请添加拜访记录内容']);
            }
            $imgsid = isset($post['imgsid'])?$post['imgsid']:'';
            $lbs['x'] = input('lat');
            $lbs['y'] = input('lng');
            $lbs['speed'] = input('speed');
            $lbs['accuracy'] = input('accuracy');
            $lbs['gps'] = 1;
            if($lbs['x']&&$lbs['y']){           		
            	session('lbsinfo',$lbs);	            //更新
	            $xx= Db::name('user_member')->where('uid',session('uid'))->value('x');	            
		            if(!$xx){	            	
		            	$zhxy=getbaidugps($lbs['x'],$lbs['y'],true);
		            	Db::name('user_member')->where('uid',session('uid'))->update(['x'=>$zhxy[0],'y'=>$zhxy[1]]);
		          	  }     
                }
            $userPost = [
                'gid'=>session('gid'),
                'content'=>$content,
                'uid'=>session('uid'),
                'guid'=>session('guid'),
                'wx_serverid'=> $uploadImg->getImgsServerIds(),
                'xm_id'=>$xm_id,
                'xm_title'=>$xm_title,
                'imgsid'=>$imgsid,
                'adate'=>time(),
                'ip'=>get_client_ip(),
                'user_agent'=>$_SERVER['HTTP_USER_AGENT']
            ];
            $imgsid = array_count_values(explode('_',$imgsid));
            foreach($imgsid as $k=>$v){
                Db::table('__FILE__')->where('id',$k)->setInc('cited',$v);//图片引用数据设置
            }
			Db::name('user_post')->insert($userPost);
            $fromid = Db::name('user_post')->getLastInsID();
            if($fromid){
            	Db::name('user_member')->where('uid',$userPost['uid'])->update(['bdate'=>time()]);
            	$uploadImg->clear();				
				addgps(session('guid'),session('uid'),$fromid);
				session('imgslocalid.'. $sessName,null);
				session('imgsserverids.'. $sessName,null);
                return json(['code' => 0,'id' => $fromid, 'msg' => '添加拜访记录成功']);  
            }else{
                return json(['code' => 1, 'msg' => '添加拜访记录失败']);  
            }           
        }
        if(request()->isAjax() && input('action') === 'upimgs'){
            return $uploadImg->upimgs();
        }
    }
    //异步下载微信图片
    private function ybdownwximg($postid,$sessName)
    {  
    	if($postid){
    	  $serverIds = Db::name('user_post')->where('gid',session('gid'))->where('id',$postid)->where('is_down',0)->field('imgsid,wx_serverid')->find();
    	  if($serverIds['wx_serverid']){
    	  	$access_token =$this->getwx_access_token();
            if(!$access_token){
    			return json(['code'=>-1,'msg'=>'未设置公众账号信息，无法使用图片上传功能，请联系系统管理员！']);
    		}
    		$uploadImg = controller('uploadImg','event');
    		$uploadImg->setSessName($sessName);
    		$serverIds['wx_serverid']=explode(',', $serverIds['wx_serverid']);
    		foreach ($serverIds['wx_serverid'] as $sid){
    		$url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token=' . $access_token . '&media_id=' . $sid;
    	    $imgsid = $uploadImg->wxupload($url);
    		}
    		if($imgsid&&$serverIds['imgsid']!=''&&$serverIds['imgsid']!='_'){
    			$imgsid=explode('_',$serverIds['imgsid'].'_'.$imgsid);
    			$imgsid=array_unique($imgsid);
    			$imgsid=implode('_', $imgsid);
    		}
    		Db::name('user_post')->where('gid',session('gid'))->where('id',$postid)->update(['imgsid'=>$imgsid,'is_down'=>1]);
    		$imgsidcount = array_count_values(explode('_',$imgsid));
    		foreach($imgsidcount as $k=>$v){
    			Db::table('__FILE__')->where('id',$k)->setInc('cited',$v);//图片引用数据设置
    		}	
    		$uploadImg->clear();
    		return $imgsid;
    	  }
    	}
    }
    
    //异步下载微信图片
    private function ybdownimg($sessName='ybxz')
    {
	    	$uploadImg = controller('uploadImg','event');
	    	
    	   // $map['gid']=194;
    	    $map['is_down']=0;
    	    //$map['id']=36235;
    	    $map['wx_serverid']=['neq',''];
    		$ss = Db::name('user_post')->where($map)->field('id,gid,uid,guid,imgsid,wx_serverid')->select();
    		
    		
    		//print_r($ss);
    		foreach ($ss as $serverIds){
    			$uploadImg->setSessName($sessName.$serverIds['id']);
    			$access_token =$this->getwx_access_token($serverIds['gid']);
                if(!$access_token){
    				return json(['code'=>-1,'msg'=>'未设置公众账号信息，无法使用图片上传功能，请联系系统管理员！']);
    			}
    			
	    		if($serverIds['wx_serverid']){
	    			$wx_serveridarr=explode(',', $serverIds['wx_serverid']);
	    			foreach ($wx_serveridarr as $sid){
	    				$url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token=' . $access_token . '&media_id=' . $sid;
	    				/*$ch = curl_init($url);
	    				curl_setopt($ch, CURLOPT_HEADER, 0);
	    				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    				$out_put = curl_exec($ch);
	    				curl_close($ch);
	    				print_r($out_put);
	    				exit;
	    				*/
	    				$imgsid = $uploadImg->ybwxupload($url,$serverIds);
	    			}
	    			//if($imgsid&&$serverIds['imgsid']!=''&&$serverIds['imgsid']!='_'){
	    				$imgsidarr=explode('_',$imgsid);
	    				
	    				$imgsidarr=array_unique($imgsidarr);
	    				$imgsid=implode('_', $imgsidarr);
	    				unset($imgsidarr);
	    			//}
	    				$uploadImg->clear();
	    				
	    			Db::name('user_post')->where('gid',$serverIds['gid'])->where('id',$serverIds['id'])->update(['imgsid'=>$imgsid,'is_down'=>1]);
	    			$imgsidcount = array_count_values(explode('_',$imgsid));
	    			foreach($imgsidcount as $k=>$v){
	    				Db::table('__FILE__')->where('id',$k)->setInc('cited',$v);//图片引用数据设置
	    			}
	    			
	    			//return $imgsid;
	    		}
    		}
    }
    /*
    public function addhs(){
    	
    	if(request()->isAjax() && input('action') === 'show'){
    		//设置session
    		if(!!($uid = request()->post('uid'))){
    			session('uid',$uid);
    		}
    		$hs = controller('hs','event');
    		$uploadImg = controller('uploadImg','event');
    		$sessName = 'hs_uid_' . session('uid');
    		$uploadImg->setSessName($sessName);
    		$imgs = $uploadImg->getImgs();
    		$hsList = $hs->hsList();
    		foreach($hsList as $k=>$v){
    			$hsList[$k] = $v->getData();
    		}
    		if(is_weixin()){
    			//微信本地图片
    			$localids = $uploadImg->getImgsLocalIds();
    		}else{
    			$localids='';
    		}
    		return json(['data'=>['hsList'=>$hsList,'imgs'=>$imgs,'upimgurl'=>'_m_user_addhs','localids'=>$localids],'code'=>1]);
    	}
    	
        //文件上传处理
        $sessName = 'hs_uid_' . session('uid');
        $uploadImg = controller('uploadImg','event');
        $uploadImg->setSessName($sessName);
        if(($file = request()->file('file'))){
            if(!empty($file)){
                $imgsid = $uploadImg->index($file);
                return json([$imgsid]);
            }
        }
        if(request()->isAjax() && input('action') === 'getlocalids'){
        	$localids = $uploadImg->getImgsLocalIds();
        	return json(['localids'=>$localids,'code'=>1]);
        }
        if(request()->isAjax() && input('action') === 'setlocalids'){
        	$localids =input('localids/a');
        	$serverids =input('serverids/a');
        	$uploadImg->setImgLocalIds($localids,$serverids);
        	return 1;
        	//return json(['data'=>['localids'=>session('imgslocalid.'. $sessName),'serverids'=>session('imgsserverid.'. $sessName)],'code'=>1]);
        }
        if(request()->isAjax() && input('action') === 'ybdownwximg'){
        	if(input('id')) $this->ybdownwximg(input('id'),$sessName);
        }
        if(is_weixin() && trim(input('sid'))){
        	$serverIds = input('sid');
        	$access_token =$this->getwx_access_token();
            if(!$access_token){
        		return json(['code'=>-1,'msg'=>'未设置公众账号信息，无法使用图片上传功能，请联系系统管理员！']);
        	}
        	$url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token=' . $access_token . '&media_id=' . $serverIds;
        	$imgsid = $uploadImg->wxupload($url);
        	return json([$imgsid]);
        }
        if(request()->isAjax() && input('action') === 'upimgs'){
            return $uploadImg->upimgs();
        }
        if(request()->isAjax() && input('action') === 'add'){
        	$lbs['x'] = input('lat');
        	$lbs['y'] = input('lng');
        	if($lbs['x']&&$lbs['y']){
        		$lbs['speed'] = input('speed');
        		$lbs['accuracy'] = input('accuracy');
        		$lbs['gps'] = 1;
        		session('lbsinfo',$lbs);
        		//更新
        		$xx= Db::name('user_member')->where('uid',session('uid'))->value('x');
        		if(!$xx){
        			$zhxy=getbaidugps($lbs['x'],$lbs['y'],true);
        			Db::name('user_member')->where('uid',session('uid'))->update(['x'=>$zhxy[0],'y'=>$zhxy[1]]);
        		}
        	}
            $post = request()->post();
            $shsid = $post['hsid'];
            $snum = $post['num'];
            $imgsid = $post['imgsid'];
            //对数据进行一个处理，相同hsid的项目合并
            $hsid = [];
            $num = [];
            for($i=0;$i<count($shsid);$i++){
                //判断$shsid是否在数组$hsid内，如果在那么就将对应下标snum值与其相加
                //同时过滤$shsid[$i],$snum[$i]为0的以及非法输入的值
                if(!(intval($shsid[$i])) || !(intval($snum[$i]))){
                    continue;
                }
                if(($index = array_search($shsid[$i],$hsid)) !== false){
                    $num[$index] += $snum[$i];
                }else{
                    $hsid[] = intval($shsid[$i]);
                    $num[] = intval($snum[$i]);
                }
            }
            if(!empty($hsid) && !empty($num)){
                //开始进行数据写入操作！
                $goodsSnum = [];//写入goods_snum
                $userPostContent = '上报回收：<br />';//写入user_post
                $goodsName = [];//获取所有的名称
                $hs = controller('hs','event');
                $t = time();
                for($i=0;$i<count($hsid);$i++){
                    $one = $hs->getOneHsId($hsid[$i]);
                    $goods = Db::name('goods')->field('goods_name')->where('goods_id',$one['goodsid'])->find();
                    $goodsName = $goods['goods_name'];
                    //附加数量信息
                    $userPostContent .= "项目为 {$goodsName} {$one['snm']}{$one['sname']} 数量为 <font color=#FF0000>{$num[$i]}</font><br />";
                    //获取商品名称
                    $one['num'] = $num[$i];
                    $one['uid'] = session('uid');
                    $one['gid'] = session('gid');
                    $one['guid'] = session('guid');
                    $one['gsid'] = $hsid[$i];//
                    $one['adate'] = $t;
                    unset($one['type']);
                    unset($one['id']);
                    $goodsSnum[] = $one;
                }
                $userPost = [
                    'gid'=>session('gid'),
                    'content'=>$userPostContent,
                    'uid'=>session('uid'),
                    'guid'=>session('guid'),
                    'wx_serverid'=> $uploadImg->getImgsServerIds(),
                    'imgsid'=>$imgsid,
                    'adate'=>time(),
                    't'=>3, //上报回收
                    'ip'=>get_client_ip(),
                    'user_agent'=>$_SERVER['HTTP_USER_AGENT']
                ];
                //开始批量写入回收信息
                if($hs->save($goodsSnum)){
                    $model = new UserMember;
                    $imgsid = array_count_values(explode('_',$imgsid));
                    foreach($imgsid as $k=>$v){
                        Db::table('__FILE__')->where('id',$k)->setInc('cited',$v);//图片引用数据设置
                    }
                    $uploadImg->clear();
					Db::name('user_post')->insert($userPost);
					$fromid = Db::name('user_post')->getLastInsID();
					if($fromid){
						Db::name('user_member')->where('uid',$userPost['uid'])->update(['bdate'=>time()]);
						addgps(session('guid'),session('uid'),$fromid);
						return json(['code' => 0,'id'=>$fromid, 'msg' => '添加回收信息成功']);
						}else{
						return json(['code' => 1, 'msg'=>'添加回收信息失败！']);
						}
                }else{
                    return json(['code' => 1, 'msg'=>'添加回收信息失败！']);
                }
                //开始写入跟进信息
            }else{
                return json(['code'=>2, 'msg'=>'未选择上报项目或上报数量有误！']);
            }
        }
    }
    */ 
    public function addhs(){
    	if(request()->isAjax() && input('action') === 'show'){
            //设置session
            if(!!($uid = request()->post('uid'))){
                session('uid',$uid);
            }
            $hs = controller('hs','event');
            $uploadImg = controller('uploadImg','event');
            $sessName = 'hs_uid_' . session('uid');
            $uploadImg->setSessName($sessName);
            $imgs = $uploadImg->getImgs();
            $hsList = $hs->hsList();
            foreach($hsList as $k=>$v){
                $hsdata = $v->getData();
                $hsdata['goods_name1']=msubstr($hsdata['goods_name'],0, 14).'..';
                $hsList[$k]=$hsdata;
            }
            if(is_weixin()){
                //微信本地图片
                $localids = $uploadImg->getImgsLocalIds();
            }else{
                $localids='';
            }
            return json(['data'=>['hsList'=>$hsList,'imgs'=>$imgs,'upimgurl'=>'_m_user_addhs','localids'=>$localids],'code'=>1]);
    	}
        //文件上传处理
        $sessName = 'hs_uid_' . session('uid');
        $uploadImg = controller('uploadImg','event');
        $uploadImg->setSessName($sessName);
        if(($file = request()->file('file'))){
            if(!empty($file)){
                $imgsid = $uploadImg->index($file);
                return json([$imgsid]);
            }
        }
        if(request()->isAjax() && input('action') === 'getlocalids'){
            $localids = $uploadImg->getImgsLocalIds();
            return json(['localids'=>$localids,'code'=>1]);
        }
        if(request()->isAjax() && input('action') === 'setlocalids'){
            $localids =input('localids/a');
            $serverids =input('serverids/a');
            $uploadImg->setImgLocalIds($localids,$serverids);
            return 1;
            //return json(['data'=>['localids'=>session('imgslocalid.'. $sessName),'serverids'=>session('imgsserverid.'. $sessName)],'code'=>1]);
        }
        if(request()->isAjax() && input('action') === 'ybdownwximg'){
            if(input('id')) $this->ybdownwximg(input('id'),$sessName);
        }
        if(is_weixin() && trim(input('sid'))){
            $serverIds = input('sid');
            $access_token =$this->getwx_access_token();
            if(!$access_token){
                return json(['code'=>-1,'msg'=>'未设置公众账号信息，无法使用图片上传功能，请联系系统管理员！']);
            }
            $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token=' . $access_token . '&media_id=' . $serverIds;
            $imgsid = $uploadImg->wxupload($url);
            return json([$imgsid]);
        }
        if(request()->isAjax() && input('action') === 'upimgs'){
            return $uploadImg->upimgs();
        }
        if(request()->isAjax() && input('action') === 'add'){
            $lbs['x'] = input('lat');
            $lbs['y'] = input('lng');
            if($lbs['x']&&$lbs['y']){
                $lbs['speed'] = input('speed');
                $lbs['accuracy'] = input('accuracy');
                $lbs['gps'] = 1;
                session('lbsinfo',$lbs);
                //更新
                $xx= Db::name('user_member')->where('uid',session('uid'))->value('x');
                if(!$xx){
                    $zhxy=getbaidugps($lbs['x'],$lbs['y'],true);
                    Db::name('user_member')->where('uid',session('uid'))->update(['x'=>$zhxy[0],'y'=>$zhxy[1]]);
                }
            }
            $post = request()->post();
            $hsid = $post['hsid'];
            $num = $post['num'];
            $imgsid = $post['imgsid'];
            if(!empty($hsid)){
                //开始进行数据写入操作！
                $goodsSnum = [];//写入goods_snum
                $userPostContent = '上报回收：<br />';//写入user_post
                $goodsName = [];//获取所有的名称
                $hs = controller('hs','event');
                $t = time();
                for($i = 0, $l = count($hsid);$i < $l;$i++){
                    $one = $hs->getOneHsId($hsid[$i]);
                    $goods = Db::name('goods')->field('goods_name')->where('goods_id',$one['goodsid'])->find();
                    $goodsName = $goods['goods_name'];
                    //附加数量信息
                    $userPostContent .= "项目为 {$goodsName} {$one['snm']}{$one['sname']} 数量为 <font color=#FF0000>{$num[$hsid[$i]]}</font><br />";
                    //获取商品名称
                    $one['num'] = $num[$hsid[$i]];
                    $one['uid'] = session('uid');
                    $one['gid'] = session('gid');
                    $one['guid'] = session('guid');
                    $one['gsid'] = $hsid[$i];//
                    $one['adate'] = $t;
                    unset($one['type']);
                    unset($one['id']);
                    $goodsSnum[] = $one;
                }
                $userPost = [
                    'gid'=>session('gid'),
                    'content'=>$userPostContent,
                    'uid'=>session('uid'),
                    'guid'=>session('guid'),
                    'wx_serverid'=> $uploadImg->getImgsServerIds(),
                    'imgsid'=>$imgsid,
                    'adate'=>time(),
                    't'=>3, //上报回收
                    'ip'=>get_client_ip(),
                    'user_agent'=>$_SERVER['HTTP_USER_AGENT']
                ];
                //开始批量写入回收信息
                if($hs->save($goodsSnum)){
                    $model = new UserMember;
                    $imgsid = array_count_values(explode('_',$imgsid));
                    foreach($imgsid as $k=>$v){
                        Db::table('__FILE__')->where('id',$k)->setInc('cited',$v);//图片引用数据设置
                    }
                    $uploadImg->clear();
                    Db::name('user_post')->insert($userPost);
                    $fromid = Db::name('user_post')->getLastInsID();
                    if($fromid){
                        Db::name('user_member')->where('uid',$userPost['uid'])->update(['bdate'=>time()]);
                        addgps(session('guid'),session('uid'),$fromid);
                        return json(['code' => 0,'id'=>$fromid, 'msg' => '添加回收信息成功']);
                    }else{
                        return json(['code' => 1, 'msg'=>'添加回收信息失败！']);
                    }
                }else{
                    return json(['code' => 1, 'msg'=>'添加回收信息失败！']);
                }
                //开始写入跟进信息
            }else{
                return json(['code'=>2, 'msg'=>'未选择上报项目或上报数量有误！']);
            }
        }
    }
    public function detail(){
        if(request()->isAjax()){
            $model = new UserMember();
            $uid = input('param.uid');
            $gid = session('gid');
            if(!!$uid && $uid != session('uid')){
                $olduid = session('uid');
                session('uid',$uid);
            }else{
                $uid = session('uid');
            }
            $info = $model->getOneUser($gid, $uid);
            if($info){
                //获取首页统计数据 跟进总条数,回收总条数，订单总数，出货总数，库存总条数
                $info['visit_record_num'] = Db::name('user_post')
                ->where('uid',$uid)
                ->count();
                $info['order_record_num'] = Db::name('order')
                ->where('uid',$uid)
                ->count();
                $info['stock_record_num'] =  Db::name('user_stock')
                ->where('uid',$uid)
                ->count();
//                $info['hs_record_num'] = Db::name('goods_snum')
//                ->where('uid',$uid)
//                ->count();
//                $info['rg_record_num'] =  Db::name('rgorder')
//                ->where('uid',$uid)
//                ->where('gid',$this->gid)
//                ->count();
                $uos = Db::name('user_open')->where(['gid'=>$this->gid,'uid'=>$uid])->column('shopid');
                $shops = Db::name('group_shop')->field('id,gid,name,url')->where(['gid' => $this->gid,'status' => 1,'url'=>['NEQ','']])->select();
                if(count($uos) < count($shops)){
                    $qrurl = [];
                    for($i = 0, $l = count($shops); $i < $l; $i++){
                        $shop = $shops[$i];
                        if(in_array($shop['id'],$uos)){
                            continue;
                        }
                        $param['shopid'] = $shop['id'];
                        $param['uid'] = $info['uid'];
                        $param['gid'] = $this->gid;
                        $sign = new \app\common\controller\Sign();
                        $s = $sign->mkSign($param);
                        $param['timestamp'] = $s['timestamp'];
                        $param['sign'] = $s['sign'];
                        $qs = '';
                        foreach($param as $k=>$v){
                            $qs .= '/' . $k . '/' . $v;
                        }
                        $qrurl[] = [
                            'url'=>$shop['url'] .'/shop/bindwx/index'. $qs,
                            'shopname' => $shop['name']
                        ];
                    }
                    $info['qrurl'] = $qrurl;
                }
                if($info['bdate']){
                        $info['bftime']=round((time()-$info['bdate'])/84000);
                }else{
                        $info['bftime']='';
                }
                if($info['odate']){
                        $info['otime']=round((time()-$info['odate'])/84000);
                }else{
                        $info['otime']='';
                }
                $info['tt'] ?null:$info['tt']='未指定';
                $info['lastvisit'] = date('Y-m-d H:i:s',$info['lastvisit']);
                if($info['guid']==session('guid')){
                    $info['ismine'] = 1;
                }else{
                    $info['ismine'] = 0;
                }
                //更新点击次数、最后访问时间
                $model->where('uid',$uid)->setInc('clicknum');
               // $model->save(['lastvisit'=>time()],['uid'=>$uid]);
                //获取当前跟进人的上一级guid
                if($this->guid == $info['guid']){
                        $info['actpromise'] = true;
                }else{
                        $whereGM['uid'] = $info['guid'];
                        $whereGM['gid'] = $this->gid;
                        $superid = Db::name('group_member')->where($whereGM)->value('superid');
                        $s1 = trim(trim($superid),',');
                        if(!!$s1){
                                $s1 = explode(',',$s1);
                                if(in_array($this->guid,$s1)){
                                        $info['actpromise'] = true;
                                }else{
                                        $info['actpromise'] = false;
                                }
                        }else{
                                $info['actpromise'] = false;
                        }
                }
                return json(['data'=>$info,'code'=>1]);
            }else{
                return json(['data'=>[],'code'=>0]);
            }
        }else{
            $input = input();
            $action = '';
            if(isset($input['action'])){
                $action = $input['action'];
                unset($input['action']);
            }
            switch($action){

                case 'getqr':return $this->getQr();
            }
        }
    }
    public function detail2(){
        if(request()->isAjax()){
            $model = new UserMember();
            $uid = input('param.uid');
            $gid = session('gid');
            if(!!$uid && $uid != session('uid')){
                $olduid = session('uid');
                session('uid',$uid);
            }else{
                $uid = session('uid');
            }
            $info = $model->getOneUser($gid, $uid);
            if($info){
                //获取首页统计数据 跟进总条数,回收总条数，订单总数，出货总数，库存总条数
                $info['visit_record_num'] = Db::name('user_post')
                    ->where('uid',$uid)
                    ->count();
                $info['order_record_num'] = Db::name('order')
                    ->where('uid',$uid)
                    ->count();
                $info['stock_record_num'] =  Db::name('user_stock')
                    ->where('uid',$uid)
                    ->count();
//                $info['hs_record_num'] = Db::name('goods_snum')
//                ->where('uid',$uid)
//                ->count();
//                $info['rg_record_num'] =  Db::name('rgorder')
//                ->where('uid',$uid)
//                ->where('gid',$this->gid)
//                ->count();
                $uos = Db::name('user_open')->where(['gid'=>$this->gid,'uid'=>$uid])->column('shopid');
                $shops = Db::name('group_shop')->field('id,gid,name,url')->where(['gid' => $this->gid,'status' => 1,'url'=>['NEQ','']])->select();
                if(count($uos) < count($shops)){
                    $qrurl = [];
                    for($i = 0, $l = count($shops); $i < $l; $i++){
                        $shop = $shops[$i];
                        if(in_array($shop['id'],$uos)){
                            continue;
                        }
                        $param['shopid'] = $shop['id'];
                        $param['uid'] = $info['uid'];
                        $param['gid'] = $this->gid;
                        $sign = new \app\common\controller\Sign();
                        $s = $sign->mkSign($param);
                        $param['timestamp'] = $s['timestamp'];
                        $param['sign'] = $s['sign'];
                        $qs = '';
                        foreach($param as $k=>$v){
                            $qs .= '/' . $k . '/' . $v;
                        }
                        $qrurl[] = [
                            'url'=>$shop['url'] .'/shop/bindwx/index'. $qs,
                            'shopname' => $shop['name']
                        ];
                    }
                    $info['qrurl'] = $qrurl;
                }
                if($info['bdate']){
                    $info['bftime']=round((time()-$info['bdate'])/84000);
                }else{
                    $info['bftime']='';
                }
                if($info['odate']){
                    $info['otime']=round((time()-$info['odate'])/84000);
                }else{
                    $info['otime']='';
                }
                $info['tt'] ?null:$info['tt']='未指定';
                $info['lastvisit'] = date('Y-m-d H:i:s',$info['lastvisit']);
                if($info['guid']==session('guid')){
                    $info['ismine'] = 1;
                }else{
                    $info['ismine'] = 0;
                }
                //更新点击次数、最后访问时间
                $model->where('uid',$uid)->setInc('clicknum');
                // $model->save(['lastvisit'=>time()],['uid'=>$uid]);
                //获取当前跟进人的上一级guid
                if($this->guid == $info['guid']){
                    $info['actpromise'] = true;
                }else{
                    $whereGM['uid'] = $info['guid'];
                    $whereGM['gid'] = $this->gid;
                    $superid = Db::name('group_member')->where($whereGM)->value('superid');
                    $s1 = trim(trim($superid),',');
                    if(!!$s1){
                        $s1 = explode(',',$s1);
                        if(in_array($this->guid,$s1)){
                            $info['actpromise'] = true;
                        }else{
                            $info['actpromise'] = false;
                        }
                    }else{
                        $info['actpromise'] = false;
                    }
                }
                return json(['data'=>$info,'code'=>1]);
            }else{
                return json(['data'=>[],'code'=>0]);
            }
        }else{
            $input = input();
            $action = '';
            if(isset($input['action'])){
                $action = $input['action'];
                unset($input['action']);
            }
            switch($action){

                case 'getqr':return $this->getQr();
            }
        }
    }
    private function getQr($input){
        $content = $input['content'];
        if(isset($input['content'])){
            $content = $input['content'];
        }
        
    }
    public function UserDel(){
        $id = input('param.id');
        $role = new UserModel();
        $flag = $role->delUser($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }
}
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
class Qzuser extends Base
{
    //用户列表
    private $hsgoods;
    public function index(){
        if(request()->isAjax()){
        	if(session('guid')==216||session('guid')==455||session('guid')==568){
        		
            $param = input('param.');
            if($param['action']=='visit_type'){
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
            $limit = $param['limit'];
            $offset = ($param['p'] - 1) * $limit;
            $where1 = [];
            $where2 = [];
            $where2['gid'] = session('gid');
            switch($param['action']){
            	case 'bddh': return $this->bddh();
            		break;
                case 'mine':
                    $whereOr = [];
                    $whereOr2 = [];
                    $where1['u.gid'] = session('gid');
                    $where1['u.guid'] = session('guid');
                    $where2['guid'] = session('guid');
                    break;
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
            if (isset($param['title']) && !empty($param['title'])) {
                  $where['cname|aaTablecounty|aaTel|aaWriter|aaWritertel'] =['like', '%' . $param['title'] . '%'];
               
            }
            $res = Db::name('kehu')->field('id,cname,aaTablecounty,aaColawer,aaTel,aaWriter,aaWritertel,aaBusstype,aaPaddress')
            ->where($where)->limit($offset,$limit)->order('id desc')->select();
            $total = Db::name('kehu')->where($where)->count();
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
        	}else{
        		
        			$return['code'] =0;
        			$return['msg'] ='没有数据！';
        		
        		return json($return);
        	}
        }
        
    }
    private function bddh(){
    	$quid = input('quid');
    	if($quid){
    		$map['gid']=session('gid');
    		$map['guid']=session('guid');
    		$map['create_time']=['gt',strtotime(date("Y-m-d"))];
    		$scount=0;
    		$scount=Db::name('group_member_log')->where($map)->count();
    		if($scount>30){
    			session('qzchaoxian',1);
    			return json(['code'=>-1]);
    		}else{
	    		$map['quid']=$quid;
	    		if(!Db::name('group_member_log')->where($map)->value('id')){
		    		$data['gid']=session('gid');
		    		$data['guid']=session('guid');
		    		$data['quid']=$quid;
		    		$data['create_time']=time();
		    		$data['content']='拨打电话';
		    		Db::name('group_member_log')->insert($data);
		    	
	    		}
    		   session('qzchaoxian',0);
    			return json(['code'=>1]);
    		}
    	}
    	
    }
    public function searchset(){
        if(request()->isAjax()){
            //获取用户类型
            $gid = session('gid');
            $visits = Db::table('ljk_user_visit')
                    ->field('id,title')
                    ->where('gid',$gid)
                    ->select();
            $type = Db::table('ljk_user_type')
                    ->field('id,title')
                    ->where('gid',$gid)
                    ->select();
            $data['v'] = $visits;
            $data['t'] = $type;
            return json(['code'=>1,'data'=>$data]);
        }
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
                case 'chgoods':
                    return json($this->baojiaGoodsList());
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
        $data = Db::table('ljk_goods_snum')
                ->alias('s')
                ->field('s.snm,s.sname,s.adate,s.num,s.unit,g.goods_name')
                ->where('s.uid',$uid)
                ->where('s.adate','BETWEEN', $s . ',' .$e)
                ->join('ljk_goods g','s.goodsid=g.goods_id','LEFT')
                ->limit($offset,$limit)
                ->order('s.adate desc')
                ->select();
        $total = Db::table('ljk_goods_snum')
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
        $uid = request()->post('uid');
        $limit = request()->post('limit');
        $offset = (request()->post('p') - 1) * $limit;
        $goodsid = request()->post('goodsid');
        $during = $this->setInterval();
        if(!$uid){
            $uid = session('uid');
        }else{
            session('uid',$uid);
        }
        //生成查询条件
        $s = $during['start'];
        $e = $during['stop'];
        $ch = [];
        //先查询这个时间段内的订单号
        
        if(!!$goodsid){
            $orders = Db::table('ljk_order_goods g')
                    ->field('g.oid,g.name,g.goodid,g.num,g.amount,g.unit,g.unitgid,u.coefficient,ut.coefficient ucoefficient,ut.uname')
                    ->join('ljk_order o','g.oid=o.oid')
                    ->join('ljk_unit_group ug','g.unitgid=ug.id','LEFT')
                    ->join('ljk_unit u','g.unitid=u.id','LEFT')
                    ->join('ljk_unit ut','ut.id=ug.minunitid','LEFT')
                    ->where('o.uid',$uid)
                    ->where('g.goodid',$goodsid)
                    ->where('o.confirm','EGT',0)
                    ->where('o.status',1)
                    ->where('o.adate','BETWEEN',$s . ',' . $e)
                    ->order('o.adate DESC')
                    ->select();
        }else{
            $orders = Db::table('ljk_order_goods g')
                    ->field('g.oid,g.name,g.goodid,g.num,g.amount,g.unit,g.unitgid,u.coefficient,ut.coefficient ucoefficient,ut.uname')
                    ->join('ljk_order o','g.oid=o.oid')
                    ->join('ljk_unit_group ug','g.unitgid=ug.id','LEFT')
                    ->join('ljk_unit u','g.unitid=u.id','LEFT')
                    ->join('ljk_unit ut','ut.id=ug.minunitid','LEFT')
                    ->where('o.uid',$uid)
                    ->where('o.confirm','EGT',0)
                    ->where('o.status',1)
                    ->where('o.adate','BETWEEN',$s . ',' . $e)
                    ->order('o.adate DESC')
                    ->select();
        }
        foreach($orders as $k=>$v){
            //首先判断$v['goodid']是否已经存在于 $ch的下标中
            if(array_key_exists($v['goodid'], $ch)){
                //存在那么就不新增，而是去更新里面的num值以及price,以及平均价格
                $num = $ch[$v['goodid']]['num'] = ($v['coefficient'] / $v['ucoefficient']) * $v['num'] + $ch[$v['goodid']]['num'];
                $amount = $ch[$v['goodid']]['amount'] = $ch[$v['goodid']]['amount'] +  $v['amount'];
                $ch[$v['goodid']]['average'] = round($amount/$num,2);
             }else{
                if(!$v['ucoefficient'] || !$v['num']){
                    continue;
                }
                $num = ($v['coefficient'] / $v['ucoefficient']) * $v['num'];
                if($num) $average = round($v['amount'] / $num,2);
                $ch[$v['goodid']] = [
                    'amount'=>$v['amount'],
                    'name'=>$v['name'],
                    'uname'=>$v['uname'],
                    'num'=>$num,
                    'average'=>$average,
                ];
            }
        }
        $total = count($ch);
        $data = array_slice($ch, $offset, $limit);
        if(empty($data)){
            return ['code'=>0,'msg'=>'没有了'];
        }
        return ['code'=>1,'total'=>$total,'data'=>$data];
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
        $data = Db::table('ljk_user_stock')
                ->alias('s')
                ->field('s.mdate,s.adate,s.num,s.code,s.unit,g.goods_name')
                ->where('s.uid',$uid)
                ->where('s.adate','BETWEEN', $s . ',' .$e)
                ->join('ljk_goods g','s.goodsid=g.goods_id','LEFT')
                ->limit($offset,$limit)
                ->order('s.adate desc')
                ->select();
        $total = Db::table('ljk_user_stock')
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
        $data = Db::table('ljk_user_post')
                ->alias('p')
                ->field('p.id,p.imgsid,p.content,p.adate,m.realname')
                ->where('p.uid',$uid)
                ->where('p.adate','BETWEEN', $s . ',' .$e)
                ->join('ljk_group_member m','p.guid=m.uid','LEFT')
                ->limit($offset,$limit)
                ->order('p.adate desc')
                ->select();
        $total = Db::table('ljk_user_post')
                ->where('uid',$uid)
                ->where('adate','BETWEEN', $s . ',' .$e)
                ->count();
        //处理获取的$data更改时间格式，获取图片附件url
        foreach($data as $k=>$v){
            $data[$k]['adate'] = date('Y-m-d H:i:s',$v['adate']);
            $imgsid = explode('_',$v['imgsid']);
            $unique  = array_unique($imgsid);
            $imgsurl = [];
            $imgsurlu = [];
            for($i=0;$i<count($unique);$i++){
                $imgsurlu[$unique[$i]] = mkurl(Db::table('ljk_file')->field('savename,savepath,ext,url')->find($unique[$i]));
            }
            for($i=0;$i<count($imgsid);$i++){
                $imgsurl[] = $imgsurlu[$imgsid[$i]];
            }
            $data[$k]['imgsurl'] = $imgsurl;
			//获取位置信息
			$pid = $v['id'];
			$lbs = Db::name('group_memberlbs')->field('business,street,street_number,province,city,district,address')->where('pid',$pid)->where('type',0)->find();
			if(!empty($lbs)){
				$data[$k]['lbs'] = $lbs;
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
        $type = db('user_type')->field('id,status,title')->where('gid',$gid)->where('status',1)->select();
        $gxy = db('group')->field('x,y')->where('gid',$gid)->find();
        return ['code'=>1,'data'=>['type'=>$type,'gxy'=>$gxy]];
    }
    private function isCreated(){
        $uid = request()->post('uid');
        $one = db('user_member')->field('guid')->find($uid);
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
        $one = db('user_member')->where('uid',$uid)->where('gid',$gid)->where('guid',$guid)->find();
        //判断是否有修改该客户的权限
        if(empty($one)){
            return ['code'=>0,'msg'=>'没有编辑该客户的权限！'];
        }else{
            $type = db('user_type')->field('id,status,title')->where('gid',$gid)->where('status',1)->select();
            $gxy = db('group')->field('x,y')->where('gid',$gid)->find();
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
        if(request()->isAjax()){
            switch(input('action')){
                case 'list':
                    return $this->stockList();
                    break;
                case 'show':
                    $imgs = $uploadImg->getImgs();
                    return json(['data'=>['imgs'=>$imgs,'upimgurl'=>'_wap_user_addstock'],'code'=>1]);
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
        $lbs['x'] = input('lat');
        $lbs['y'] = input('lng');
        if($lbs['x']&&$lbs['y']){
        	$lbs['speed'] = input('speed');
        	$lbs['accuracy'] = input('accuracy');
        	$lbs['gps'] = 1;
        	session('lbsinfo',$lbs);
        }
        $id = [];
        $num = [];
        $code = [];
        $unitid = [];
        $mdate = [];
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
            $userPostContent = '<br />上报库存：<br />';//写入user_post
            for($i=0;$i<count($id);$i++){
                $one = [];
                $unitinfo = db('unit')->field('uname,coefficient,unitgid')->where('id',$unitid[$i])->find();//获取单位名称信息
                $goods = db('goods')->field('goods_name')->where('goods_id',$id[$i])->find();//获取商品名称
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
                'imgsid'=>$imgsid,
                'adate'=>time(),
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
				addgps(session('guid'),session('uid'),$fromid);
                return json(['code' => 0, 'msg' => '添加库存信息成功']);
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
        return Db::table('ljk_unit')
                ->field('id,uname')
                ->where('status',1)
                ->where('gid',session('gid'))
                ->where('unitgid',input('unitgid'))
                ->select();
    }
    private function stockList(){
        $_lists = Db::table('ljk_baojia')
                ->alias('b')
                ->field('b.id,b.goods_id,b.unit,b.unitid,b.unitgid,g.goods_name')
                ->where('b.is_stock',1)
                ->where('b.gid',session('gid'))
                ->where('g.goods_name','LIKE','%'.input('keywords').'%')
                ->join('ljk_goods g','b.goods_id=g.goods_id','LEFT')
                ->select();
        return json($_lists);
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
            $imgs = $uploadImg->getImgs();
            return json(['data'=>['imgs'=>$imgs,'upimgurl'=>'_wap_user_addvisit'],'code'=>1]);
        }
        if(request()->isAjax() && input('action') === 'add'){
            $post = request()->post();
            $content = $post['content'];
            $imgsid = $post['imgsid'];
            $lbs['x'] = input('lat');
            $lbs['y'] = input('lng');
            $lbs['speed'] = input('speed');
            $lbs['accuracy'] = input('accuracy');
            $lbs['gps'] = 1;
            if($lbs['x']&&$lbs['y']){
            	session('lbsinfo',$lbs);
            }
            $userPost = [
                'gid'=>session('gid'),
                'content'=>$content,
                'uid'=>session('uid'),
                'guid'=>session('guid'),
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
                $uploadImg->clear();
				addgps(session('guid'),session('uid'),$fromid);
                return json(['code' => 0, 'msg' => '添加拜访记录成功']);  
            }else{
                return json(['code' => 1, 'msg' => '添加拜访记录失败']);  
            }           
        }
        if(request()->isAjax() && input('action') === 'upimgs'){
            return $uploadImg->upimgs();
        }
    }
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
    		return json(['data'=>['hsList'=>$hsList,'imgs'=>$imgs,'upimgurl'=>'_wap_user_addhs'],'code'=>1]);
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
                $userPostContent = '<br />上报回收：<br />';//写入user_post
                $goodsName = [];//获取所有的名称
                $hs = controller('hs','event');
                $t = time();
                for($i=0;$i<count($hsid);$i++){
                    $one = $hs->getOneHsId($hsid[$i]);
                    $goods = db('goods')->field('goods_name')->where('goods_id',$one['goodsid'])->find();
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
                    'imgsid'=>$imgsid,
                    'adate'=>time(),
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
						addgps(session('guid'),session('uid'),$fromid);
						return json(['code' => 0, 'msg' => '添加回收信息成功']);
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
    public function addhsList(){
        if(request()->isAjax()){
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
            return json(['data'=>['hsList'=>$hsList,'imgs'=>$imgs,'upimgurl'=>'_wap_user_addhs'],'code'=>1]);
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
                $info['visit_record_num'] = Db::table('ljk_user_post')
                ->where('uid',$uid)
                ->count();
                $info['order_record_num'] = Db::table('ljk_order')
                ->where('uid',$uid)
                ->count();
                $info['stock_record_num'] =  Db::table('ljk_user_stock')
                ->where('uid',$uid)
                ->count();
                $info['hs_record_num'] = Db::table('ljk_goods_snum')
                ->where('uid',$uid)
                ->count();
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
                return json(['data'=>$info,'code'=>1]);
            }else{
                return json(['data'=>[],'code'=>0]);
            }
        }
    }
    public function UserDel(){
        $id = input('param.id');
        $role = new UserModel();
        $flag = $role->delUser($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }
    public function baojiaGoodsList(){
        if(request()->isAjax()){
            $goods_name = request()->post('keywords');
            $bgoods = Db::table('ljk_baojia')
                    ->alias('b')
                    ->field('b.goods_id,g.goods_name')
                    ->join('ljk_goods g','b.goods_id=g.goods_id','LEFT')
                    ->where('b.gid',session('gid'))
                    ->where('g.goods_name','LIKE','%'. $goods_name .'%')
                    ->select();
            if(!empty($bgoods)){
                return $bgoods;
            }else {
                return [];
            }
        }
    }
}
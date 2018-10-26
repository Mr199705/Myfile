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
use think\Db;
class Daily extends Base{
    //每日工作
    private $coefficient = [];//单位换算系数
    private $goods = [];
    private $xmUnitname = [];
    public function index(){
    	if(request()->isAjax() && request()->isPost()){
            //获取所有bb子节点
            $m = strtolower(request()->module());
            $c = strtolower(request()->controller());
            $a = strtolower(request()->action());
            $whereGAR['module'] = $m;
            $whereGAR['url'] = $c . '/' . $a;
            $whereGAR['allow_authorize'] = 1;
            $pid = Db::name('group_auth_rule')->where($whereGAR)->value('id');
            if(!!$pid){
                $whereGAR = [];
                $whereGAR['module'] = $m;
                $whereGAR['allow_authorize'] = 1;
                $whereGAR['pid'] = $pid;
                $nodes = Db::name('group_auth_rule')->where($whereGAR)->order('sort asc')->select();
                $cnodes = [];
                foreach($nodes as $v){
                    if(in_array($v['id'],$this->rules)){
                        $cnodes[] = $v;
                    }
                }
                return json(['code'=>0,'data'=>$cnodes,'msg'=>'加载成功！']);
            }else{
                return json(['code'=>-1,'msg'=>'没有数据！']);
            }
    	}else{
            return json(['code'=>-1,'msg'=>'非法操作！']);
    	}
    }
    //回收项目
    public function huishou(){
    	if(request()->isAjax() && request()->isPost()){
    		$input = input('post.');
    		if(isset($input['action'])){
    			$action = trim($input['action']);
    			unset($input['action']);
    		}else{
    			$action = '';
    		}
    		switch($action){
    			case 'getHuiShouXMList':return $this->getHuiShouXMList($input);
    			case 'addHuiShouPoints':return $this->addHuiShouPoints($input);
    		}
    	}else{
    		return json(['code'=>-4,'msg'=>'非法操作！']);
    	}
    }
    
    private function getHuiShouXMList($param){
        $where['gid']=$this->gid;
        $where['guid']=$this->guid;
        //$where['adate']=['between',[strtotime(date("Y-m-d"))-86400,strtotime(date("Y-m-d"))]];
        $where['adate']=['gt',strtotime(date("Y-m-d"))];
        $data=Db::name('goods_snum')->field('id,gsid,uid,guid,num,sname,adate')->where($where)->select();
        if($data){  
            $hj=0;  		
            foreach($data as $key=>$vo){
                    $data[$key]['isrk'] =Db::name('group_xm_points')->where('hid',$vo['id'])->where('gid',$this->gid)->where('status','neq',-1)->value('id');
                    if(!$data[$key]['isrk']){ 
                        $xminfo=Db::name('group_xm')->where(function ($query){
                        $query->where('gid',$this->gid)
                        ->whereOr('jtid',$this->jtid);
                    })
                    //->where("FIND_IN_SET('{$vo['gsid']}',`hs_id`)")
                    ->where(function ($query) use ($vo){
                            $query->whereOr('hs_id','LIKE',$vo['gsid'].',%')
                            ->whereOr('hs_id','LIKE','%,'.$vo['gsid'].',%')
                            ->whereOr('hs_id','EQ',$vo['gsid'])
                            ->whereOr('hs_id','LIKE','%,'.$vo['gsid']);
                    })
                    ->find();
                            if($xminfo){    				
                                    $xmtype=Db::name('group_xm_type')->where('id',$xminfo['type'])->field('title,remark')->find();
                                    $data[$key]['type_title']=$xmtype['title'];
                                    $data[$key]['type_xm']=$xmtype['remark'];
                                    $data[$key]['xm_title']=$xminfo['title'];
                                    $data[$key]['unit']=$xminfo['unit'];
                                    $data[$key]['score']=$xminfo['score'];
                                    $data[$key]['points']=$xminfo['score']*$vo['num'];
                                    $hj+=$data[$key]['points'];
                                    $data[$key]['create_date']=date('Y-m-d',$vo['adate']);
                                    $data[$key]['realname'] =Db::name('user_member')->where('uid',$vo['uid'])->where('gid',session('gid'))->value('realname');
                                    if($xminfo['hs_id']){
                                        $hslist = explode(',',$xminfo['hs_id']);
                                        $hs_title='';
                                        foreach ($hslist as $hsid){
                                            $hsxm=Db::name('goods_s')->where('id',$hsid)->field('goodsid,sname,snm')->find();
                                            $goods_name=Db::name('goods')->where('goods_id',$hsxm['goodsid'])->value('goods_name');
                                            $hs_title.=$hsxm['snm'].'|'.$hsxm['sname'].'|'.$goods_name.'<br>';
                                        }
                                        $data[$key]['hs_title'] =$hs_title;
                                    }else{
                                        $data[$key]['hs_title'] ='';
                                    }				
                    }else{
                                    unset($data[$key]);
                    }
                            }else{
                                            unset($data[$key]);
                            }

                    }
            if($data){
                return json(['code'=>1,'msg'=>'','data'=>['data'=>$data,'total_points'=>$hj]]);
            }else{
                return json(['code'=>-4,'msg'=>'<span style="color:red;">未查询到符合条件的回收项目或今日已提交所有项目数据信息，点击回收可刷新数据！</span>']);
            }
        }else{
            return json(['code'=>-4,'msg'=>'<span style="color:red;">未查询到符合条件的回收项目或今日已提交所有项目数据信息，点击回收可刷新数据！</span>','data'=>'']);
        }
    }
	 //获取回收积分
    private function addHuiShouPoints($param){
    	if(is_array($param['id'])){
    		$ids=implode(',',$param['id']);
    	}else{
    		$ids=$param['id'];
    	}
    	$where['gid']=$this->gid;
    	$where['id']=['in',$ids];
    	$hslist=Db::name('goods_snum')->field('id,gsid,uid,guid,num,sname,adate')->where($where)->select();
    	foreach ($hslist as $key=>$hs){
    		$data['hid']=$hs['id'];
    		$data['uid']=$hs['uid'];
    		$data['guid']=$hs['guid'];
    		$data['hs_id']=$hs['gsid'];
    		$data['num']=$hs['num'];
    		$data['gid']=$this->gid;
    		$data['create_date']=date('Y-m-d',$hs['adate']);
    		$xminfo=Db::name('group_xm')->where(function ($query){
            		$query->where('gid',$this->gid)
            		->whereOr('jtid',$this->jtid);
            		})->where(function ($query) use ($hs){
            $query->whereOr('hs_id','LIKE',$hs['gsid'].',%')
            ->whereOr('hs_id','LIKE','%,'.$hs['gsid'].',%')
            ->whereOr('hs_id','EQ',$hs['gsid'])
            ->whereOr('hs_id','LIKE','%,'.$hs['gsid']);
       		 })->find();
       		 if($xminfo){
       		 	$data['xm_id']=$xminfo['id'];
       		 	$data['xm_title']=$xminfo['title'];
       		 	$data['xm_type']=$xminfo['type'];       		 	
       		 	$data['unit']=$xminfo['unit'];
       		 	$data['score']=$xminfo['score'];
       		 	$data['points']=$xminfo['score']*$hs['num'];
       		 }
       		 $data['create_time']=time();	 
       		 $pointsinfo=Db::name('group_xm_points')->where('hid',$hs['id'])->where('gid',$this->gid)->where('status','neq',-1)->find();

       		 $whereLG['gid'] = $logData['gid'] = $this->gid;
       		 $whereLG['guid'] = $logData['action_uid'] = $logData['guid'] = $this->guid;
       		 $logData['status'] = 1;
       		 $logData['user_agent'] = input('server.HTTP_USER_AGENT');
       		 $logData['action_ip'] = get_client_ip();
       		 $logData['create_time'] = time();
       		 $logData['remark'] = '回收项目'. $xminfo['title'].'积分变化' . $data['points'] . '分';
       		 $logData['increase_points'] = $data['points'];
       		 Db::startTrans();
       		 try{
       		 if($pointsinfo){
       		 	if($hs['num']>$pointsinfo['num']){
       		 	Db::name('group_xm_points')->where('id',$pointsinfo['id'])->update(['status'=>-1,'update_time'=>time()]);
       		 	$logData['points_id'] =Db::name('group_xm_points')->insertGetId($data);
       		 	}else{
       		 		$logData['points_id'] =$pointsinfo['id'];
       		 	}
       		 }else{
       			$logData['points_id'] = Db::name('group_xm_points')->insertGetId($data);
       		 }
       		 //获取最近一次的记录
       		 $oldXmLog = Db::name('group_xm_log')->field('id,after_points')->where($whereLG)->order('create_time DESC')->find();
       		 if(empty($oldXmLog)){
       		 	//第一次提交
       		 	$logData['before_points'] = 0;
       		 	$logData['after_points'] = $data['points'];
       		 	Db::name('group_xm_log')->insert($logData);
       		 }else{
       		 	$logData['before_points'] = $oldXmLog['after_points'];
       		 	$logData['after_points'] = $logData['before_points'] + $data['points'];
       		 	Db::name('group_xm_log')->insert($logData);
       		 }
       		 Db::commit();
       		 }catch(\think\Exception $e){
       		 	Db::rollBack();
       		 }
    	}
        	return json(['code'=>0,'msg'=>'成功提交回收数据！']);
    }
    //回款项目
    public function huikuan(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }else{
                $action = '';
            }
            switch($action){
                case 'getHuiKuanXMList':return $this->getHuiKuanXMList($input);
                case 'addHuiKuanPoints':return $this->addHuiKuanPoints($input);
            }
        }else{
            return json(['code'=>-4,'msg'=>'非法操作！']);
        }
    }
    //回款项目 与 客户跟进人/回款项目/订单状态/
    private function coreHK($input = []){
        //先查人再根据项目进行筛选，获取今日有订单的客户
        //订单需要是今天的订单
        $s = strtotime(date('Y-m-d'));
        $e = time();
        $whereXM = [
           // 'gid' => $this->gid,
            'status' => 1,
            'type' => 2
        ];
        $xms = [];
        $hkps = Db::name('group_xm')
            ->where($whereXM)
            ->where(function ($query){
            	$query->where('gid',$this->gid)
            	->whereOr('jtid',$this->jtid);
            })
            ->select();
        if(empty($hkps)){
            return (['code'=>-4,'msg'=>'当前未设定回款积分项目！']);
        }else{
            $goods_id2 = [];
            foreach($hkps as $hkp){
                $hkp1 = trim(trim($hkp['goods_id']),',');
                if(!!$hkp1){
                    $hkp2 = explode(',',$hkp1);
                    for($i=0;$i<(count($hkp2));$i++){
                        $xms[$hkp2[$i]] = $hkp;
                        $goods_id2[] = $hkp2[$i];
                    }
                }
            }
        }
        $goods_id3 = implode(',',$goods_id2);
        if(!$goods_id3){
            return (['code'=>-4,'msg'=>'当前未设定回款积分项目！']);
        }
        $goods_idx = array_values(array_unique(explode(',', $goods_id3)));
        if(empty($goods_idx)){
            return (['code'=>-4,'msg'=>'当前未设定回款积分项目！']);
        }
        $whereOG['og.gid'] = $this->gid;
        $whereOG['og.goodid'] = ['IN',$goods_idx];
        $whereOG['og.type'] = ['NEQ',2];
        $whereOG['og.price'] = ['NEQ',0];
        
        $whereO['o.pay_time'] = ['BETWEEN',[$s,$e]];
        $whereO['o.gid'] = $this->gid;
        $whereO['o.dguid'] = $this->guid;//
        $whereO['o.confirm'] = 1;
        $whereO['o.pay'] = 1;
        //执行效率过低，暂待优化
        $xx = Db::name('order_goods og')
                ->field('og.goodid,o.uid')
                ->where($whereOG)
                ->join('order o','og.oid=o.oid')
                ->where($whereO)
                 ->group('og.goodid,o.uid')
                ->select();
        //进一步优化
        $uids1 = [];
        $goods_id = [];
        $isUgm = [];
        $u = [];
        foreach($xx as $aa){
            $uid = $aa['uid'];
            if(!isset($isUgm[$uid])){
                $isUgm[$uid] = false;
                $whereUG['uid'] = $uid;
                $whereUG['gid'] = $this->gid;
                $uu = Db::name('user_member')
                    ->field('uid,realname,mobile,guid')
                    ->where($whereUG)
                    ->find();
                if(!empty($uu)){
                    if($uu['guid'] != $this->guid){
                        $whereUgm['gid'] = $this->gid;
                        $whereUgm['guid'] = $this->guid;
                        $whereUgm['uid'] = $uu['uid'];
                        if(Db::name('user_gm')->where($whereUgm)->count()){
                            $isUgm[$uid] = true;
                        }
                    }else{
                        $isUgm[$uid] = true;
                    }
                }
            }
            if($isUgm[$uid] === true){
                if(!isset($u[$uid])){
                    $u[$uid] = $uu;
                }
                if(!in_array($aa['uid'],$uids1)){
                    $uids1[] = $uid;
                }
                if(!in_array($aa['goodid'],$goods_id)){
                    $goods_id[] = $aa['goodid'];
                }
            }
        }
        //过滤以往已有产品购买记录的客户
        $hkParts = [];//分项统计 （到人）
        $goodsHKGather = [];//总体的统计
        $uPoints = [];
        foreach($uids1 as $v){
            $whereO['o.uid'] = $v;
            $hkParts[$v] = [];
            $uPoints[$v] = 0;
            for($i=0;$i<count($goods_id);$i++){
                $whereOG['og.goodid'] = $goods_id[$i];
                $ogs = Db::name('order_goods og')
                    ->field('og.*,SUM(og.num) sumnum')
                    ->where($whereOG)
                    ->join('order o','og.oid=o.oid')
                    ->where($whereO)
                    ->group('o.uid,og.unitid,og.oid')
                    ->select();
                //人一样产品 单位一样  就进行分组
                if(!empty($ogs)){
                    //调取规则
                    $sumnum = [];
                    foreach($ogs as $og){
                        //判断这个订单中的这个商品总数是否符合条件，如果符合
                        //根据 ogid 判断是否已经被积过分，积过分的就不需要再查询显示出来了 , 部分积分，那么所有的就不需要再积分
                        $whereXMP['gid'] = $this->gid;
                        $whereXMP['xm_type'] = 2;
                        $whereXMP['goods_id'] = $og['goodid'];
                        $whereXMP['oid'] = $og['oid'];
                        $whereXMP['uid'] = $v;
                        $k = $og['id'];
                        $c = Db::name('group_xm_points')
                            ->where($whereXMP)
                            ->where("FIND_IN_SET('{$k}',`ogid`)")
                            /*
                            ->where(function ($query) use($k){
                                $query
                                ->whereOr(function ($query) use($k){
                                    $x['ogid'] = ['LIKE', $k . '&%'];
                                    $query->whereOr($x);
                                })
                                ->whereOr(function ($query) use($k){
                                    $x['ogid'] = ['LIKE','%&' . $k . '&%'];
                                    $query->whereOr($x);
                                })
                                ->whereOr(function ($query) use($k){
                                    $x['ogid'] =  ['EQ',$k];
                                    $query->whereOr($x);
                                })
                                ->whereOr(function ($query) use($k){
                                    $query->whereOr('ogid',['LIKE','%&' . $k]);
                                });
                            }
                            )
                             * 
                             */
                            ->count();
                        if(!!$c){
                            continue;
                        }
                        $pointsRules = $xms[$goods_id[$i]];
                        if(!isset($this->xmUnitname[$pointsRules['unitid']])){
                            $this->xmUnitname[$pointsRules['unitid']] = Db::name('unit')->where('id',$pointsRules['unitid'])->value('uname');
                        }
                        if(!isset($goodsHKGather[$og['goodid']])){
                            $goodsHKGather[$og['goodid']]['goods_name'] = $og['name'];
                            $goodsHKGather[$og['goodid']]['sumnum'] = 0;
                            $goodsHKGather[$og['goodid']]['ogid'] = $og['id'];
                        }else{
                            $goodsHKGather[$og['goodid']]['ogid'] = $goodsHKGather[$og['goodid']]['ogid'] . '&' . $og['id'];
                        }
                        if($og['unitid'] !== $pointsRules['unitid']){
                            //需要进行一个单位转换
                            if(!isset($this->coefficient[$og['unitid']])){
                                $this->coefficient[$og['unitid']] = Db::name('unit')->where('id',$og['unitid'])->value('coefficient');
                            }
                            $og['sumnum'] = $og['sumnum'] * $this->coefficient[$og['unitid']];
                        }
                        $goodsHKGather[$og['goodid']]['sumnum'] += round($og['sumnum'],2);
                        if(isset($sumnum[$v][$og['goodid']])){
                            //说明这个订单内有相同的商品但是只是没有符合条件而已
                            //这里就需要对其进行一个累加操作
                            //再将累加后的值进行一个判断
                            //主要是处理一个订单相同产品但是有多个单位的情况
                            $og['sumnum'] = $og['sumnum'] + $sumnum[$v][$og['goodid']]['sumnum'];
                        }
                        $og['sumnum'] = round($og['sumnum'],2);
                        $sumnum[$v][$og['goodid']]['sumnum'] = $og['sumnum'];
                        $hkParts[$v][$goods_id[$i]] = $og;
                    }
                    if(!empty($hkParts[$v][$goods_id[$i]])){
                        $hkParts[$v][$goods_id[$i]]['point_xmid'] = $pointsRules['id'];
                        $hkParts[$v][$goods_id[$i]]['point_unitid_uname'] = $this->xmUnitname[$pointsRules['unitid']];
                        $hkParts[$v][$goods_id[$i]]['point_unit'] = $pointsRules['unit'];
                        $hkParts[$v][$goods_id[$i]]['point_unitid'] = $pointsRules['unitid'];
                        $hkParts[$v][$goods_id[$i]]['point_unitgid'] = $pointsRules['unitgid'];
                        $hkParts[$v][$goods_id[$i]]['point_title'] = $pointsRules['title'];
                        $hkParts[$v][$goods_id[$i]]['point_score'] = round($pointsRules['score'] * $hkParts[$v][$goods_id[$i]]['sumnum'],2);
                        $hkParts[$v][$goods_id[$i]]['point'] = round($pointsRules['score'],2);
                        $uPoints[$v] += $hkParts[$v][$goods_id[$i]]['point_score'];
                    }
                }
            }
        }
        $total_points = 0;
        foreach($goodsHKGather as $goodid=>$info){
            $pointsRules = $xms[$goodid];
            if(!isset($this->xmUnitname[$pointsRules['unitid']])){
                $this->xmUnitname[$pointsRules['unitid']] = Db::name('unit')->where('id',$pointsRules['unitid'])->value('uname');
            }
            $goodsHKGather[$goodid]['point_xmid'] = $pointsRules['id'];
            $goodsHKGather[$goodid]['point_unitid_uname'] = $this->xmUnitname[$pointsRules['unitid']];
            $goodsHKGather[$goodid]['point_unit'] = $pointsRules['unit'];
            $goodsHKGather[$goodid]['point_unitid'] = $pointsRules['unitid'];
            $goodsHKGather[$goodid]['point_unitgid'] = $pointsRules['unitgid'];
            $goodsHKGather[$goodid]['point_title'] = $pointsRules['title'];
            $goodsHKGather[$goodid]['point_score'] = round($pointsRules['score'] * $goodsHKGather[$goodid]['sumnum'],2);
            $goodsHKGather[$goodid]['point'] = round($pointsRules['score'],2);
            $total_points += $goodsHKGather[$goodid]['point_score'];
        }
        $newHKParts = [];
        foreach($hkParts as $uidx=>$hkPart){
            $x = [];
            if(!empty($hkPart)){
                $u[$uidx]['upoints'] = $uPoints[$uidx];
                $x['project'] = array_values($hkPart);
                $x['user'] = $u[$uidx];
                $newHKParts[] = $x;
            }
        }
        if(empty($newHKParts)){
            return (['code'=>-4,'msg'=>'<span style="color:red;">未查询到符合条件的回款项目或今日已提交所有项目数据信息，点击回款可刷新数据！</span>']);
        }else{
            return (['code'=>1,'msg'=>'客户数据加载成功！','data'=>$newHKParts, 'gather'=>$goodsHKGather,'total_points'=>$total_points]);
        }
    }
    private function getHuiKuanXMList($input = []){
        $res = $this->coreHK($input);
        if($res['code'] === 1){
            return json(['code'=>1,'msg'=>$res['msg'],'data'=>['data'=>$res['data'],'gather'=>$res['gather'],'total_points'=>$res['total_points']]]);
        }else{
            return json($res);
        }
    }
    private function addHuiKuanPoints($input = []){
        //先查人再根据项目进行筛选，获取今日有订单的客户
        //订单需要是今天的订单
        $s = strtotime(date('Y-m-d'));
        $e = time();
        $whereXM = [
           // 'gid' => $this->gid,
            'status' => 1,
            'type' => 2
        ];
        $xms = [];
        $hkps = Db::name('group_xm')
            ->where($whereXM)
            ->where(function ($query){
            	$query->where('gid',$this->gid)
            	->whereOr('jtid',$this->jtid);
            })
            ->select();
            
        if(empty($hkps)){
            return (['code'=>-4,'msg'=>'当前未设定回款积分项目！']);
        }else{
            $goods_id2 = [];
            foreach($hkps as $hkp){
                $hkp1 = trim(trim($hkp['goods_id']),',');
                if(!!$hkp1){
                    $hkp2 = explode(',',$hkp1);
                    for($i=0;$i<(count($hkp2));$i++){
                        $xms[$hkp2[$i]] = $hkp;
                        $goods_id2[] = $hkp2[$i];
                    }
                }
            }
        }
        
        $goods_id3 = implode(',',$goods_id2);
        if(!$goods_id3){
            return (['code'=>-4,'msg'=>'当前未设定回款积分项目！']);
        }
        $goods_idx = array_values(array_unique(explode(',', $goods_id3)));
        if(empty($goods_idx)){
            return (['code'=>-4,'msg'=>'当前未设定回款积分项目！']);
        }
        
        $whereOG['og.gid'] = $this->gid;
        $whereOG['og.goodid'] = ['IN',$goods_idx];
        $whereOG['og.type'] = ['NEQ',2];
        $whereOG['og.price'] = ['NEQ',0];
        
        $whereO['o.pay_time'] = ['BETWEEN',[$s,$e]];
        $whereO['o.gid'] = $this->gid;
        $whereO['o.dguid'] = $this->guid;
        $whereO['o.confirm'] = 1;
        $whereO['o.pay'] = 1;
        
        $xx = Db::name('order_goods og')
                ->field('og.goodid,o.uid')
                ->where($whereOG)
                ->join('order o','og.oid=o.oid')
                ->where($whereO)
                ->group('og.goodid,o.uid')
                ->select();
        
        //进一步优化
        //进一步优化
        $uids1 = [];
        $goods_id = [];
        $isUgm = [];
        foreach($xx as $aa){
            $uid = $aa['uid'];
            if(!isset($isUgm[$uid])){
                $isUgm[$uid] = false;
                $whereUG['uid'] = $uid;
                $whereUG['gid'] = $this->gid;
                $uu = Db::name('user_member')
                    ->field('uid,realname,mobile,guid')
                    ->where($whereUG)
                    ->find();
                if(!empty($uu)){
                    if($uu['guid'] != $this->guid){
                        $whereUgm['gid'] = $this->gid;
                        $whereUgm['guid'] = $this->guid;
                        $whereUgm['uid'] = $uu['uid'];
                        if(Db::name('user_gm')->where($whereUgm)->count()){
                            $isUgm[$uid] = true;
                        }
                    }else{
                        $isUgm[$uid] = true;
                    }
                }
            }
            if($isUgm[$uid] === true){
                if(!in_array($aa['uid'],$uids1)){
                    $uids1[] = $uid;
                }
                if(!in_array($aa['goodid'],$goods_id)){
                    $goods_id[] = $aa['goodid'];
                }
            }
        }
        $dataInsert = [];
        foreach($uids1 as $v){
            $whereO['o.uid'] = $v;
            for($i=0;$i<count($goods_id);$i++){
                $whereOG['og.goodid'] = $goods_id[$i];
                $ogs = Db::name('order_goods og')
                    ->field('og.*,SUM(og.num) sumnum')
                    ->where($whereOG)
                    ->join('order o','og.oid=o.oid')
                    ->where($whereO)
                    ->group('o.uid,og.oid,og.unitid')
                    ->select();
                //人一样产品 单位一样 订单一样 就进行分组
                if(!empty($ogs)){
                    //调取规则
                    foreach($ogs as $og){
                        //判断这个订单中的这个商品总数是否符合条件，如果符合
                        //根据 ogid 判断是否已经被积过分，积过分的就不需要再查询显示出来了 , 部分积分，那么所有的就不需要再积分
                        $whereXMP['gid'] = $this->gid;
                        $whereXMP['xm_type'] = 2;
                        $whereXMP['goods_id'] = $og['goodid'];
                        $whereXMP['uid'] = $v;
                        $whereXMP['oid'] = $og['oid'];
                        $k = $og['id'];
                        $c = Db::name('group_xm_points')
                            ->where($whereXMP)
                            ->where("FIND_IN_SET('{$k}',`ogid`)")
                            /*->where(function ($query) use($k){
                                    $query
                                    ->whereOr(function ($query) use($k){
                                        $x['ogid'] = ['LIKE', $k . '&%'];
                                        $query->whereOr($x);
                                    })
                                    ->whereOr(function ($query) use($k){
                                        $x['ogid'] = ['LIKE','%&' . $k . '&%'];
                                        $query->whereOr($x);
                                    })
                                    ->whereOr(function ($query) use($k){
                                        $x['ogid'] =  ['EQ',$k];
                                        $query->whereOr($x);
                                    })
                                    ->whereOr(function ($query) use($k){
                                        $query->whereOr('ogid',['LIKE','%&' . $k]);
                                    });
                            })
                             * 
                             */
                            ->count();
                        if(!!$c){
                            continue;
                        }
                        $pointsRules = $xms[$goods_id[$i]];
                        if(!isset($this->xmUnitname[$pointsRules['unitid']])){
                            $this->xmUnitname[$pointsRules['unitid']] = Db::name('unit')->where('id',$pointsRules['unitid'])->value('uname');
                        }
                        $kk = implode('_',[$v ,$goods_id[$i],$og['oid']]);
                        if($og['unitid'] !== $pointsRules['unitid']){
                            //需要进行一个单位转换
                            if(!isset($this->coefficient[$og['unitid']])){
                                $this->coefficient[$og['unitid']] = Db::name('unit')->where('id',$og['unitid'])->value('coefficient');
                            }
                            $og['sumnum'] = $og['sumnum'] * $this->coefficient[$og['unitid']];
                        }
                        $og['sumnum'] = round($og['sumnum'],2);
                        if(!isset($dataInsert[$kk])){
                            $dataInsert[$kk] = [
                                'uid' => $v,
                                'gid' => $this->gid,
                                'guid' => $this->guid,
                                'oid' => $og['oid'],
                                'goods_id' => $goods_id[$i],
                                'goods_name' => $og['name'],
                                'xm_id' => $pointsRules['id'],
                                'xm_title' => $pointsRules['title'],
                                'xm_type' => $pointsRules['type'],
                                'hs_id' => 0,
                                'num' => $og['sumnum'],
                                'score' => $pointsRules['score'],
                                'points' => round($pointsRules['score'] * $og['sumnum'],2),
                                'unit' => $this->xmUnitname[$pointsRules['unitid']],
                                'unitid' => $pointsRules['unitid'],
                                'unitgid' => $pointsRules['unitgid'],
                                'status' => 1,
                                'ogid' => $og['id'],
                                'create_time' => time(),
                                'create_date' => date('Y-m-d')
                            ];
                        }else{
                            $dataInsert[$kk]['num'] += $og['sumnum'];
                            $dataInsert[$kk]['ogid'] = $dataInsert[$kk]['ogid'] . '&' . $og['id'];
                            $dataInsert[$kk]['points'] = round($dataInsert[$kk]['points'] + $og['sumnum'] * $dataInsert[$kk]['score'],2);
                        }
                    }
                }
            }
        }
        if(!empty($dataInsert)){
            foreach($dataInsert as $pointData){
                $whereLG['gid'] = $logData['gid'] = $this->gid;
                $whereLG['guid'] = $logData['action_uid'] = $logData['guid'] = $this->guid;
                $logData['status'] = 1;
                $logData['user_agent'] = input('server.HTTP_USER_AGENT');
                $logData['action_ip'] = get_client_ip();
                $logData['create_time'] = time();
                $logData['remark'] = '回款项目'. $project['point_title'] .'商品名称：' . $pointData['goods_name'] .'积分变化' . $logData['increase_points'] . '分';
                $logData['increase_points'] = $pointData['points'];
                unset($pointData['goods_name']);
                Db::startTrans();
                try{
                    $logData['points_id'] = Db::name('group_xm_points')->insertGetId($pointData);
                    //获取最近一次的记录
                    $oldXmLog = Db::name('group_xm_log')->field('id,after_points')->where($whereLG)->order('create_time DESC')->find();
                    if(empty($oldXmLog)){
                        //第一次提交
                        $logData['before_points'] = 0;
                        $logData['after_points'] = $pointData['points'];
                        Db::name('group_xm_log')->insert($logData);
                    }else{
                        $logData['before_points'] = $oldXmLog['after_points'];
                        $logData['after_points'] = $logData['before_points'] + $pointData['points'];
                        Db::name('group_xm_log')->insert($logData);
                    }
                    Db::commit();
                }catch(\think\Exception $e){
                    Db::rollBack();
                }
            }
            return json(['code'=>0,'msg'=>'成功提交回款数据！']);
        }else{
            return json(['code'=>-4,'msg'=>'非法操作！']);
        }
    }
    //新开项目
    public function xinkai(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }else{
                $action = '';
            }
            switch($action){
                case 'xkusers':return $this->getXKUsers($input);
                case 'addXKPoints':return $this->addXKPoints($input);
            }
        }else{
            return json(['code'=>-4,'msg'=>'非法操作！']);
        }
    }
    private function coreXK($input=[]){
        //先查人再根据项目进行筛选，获取今日有订单的客户
        //订单需要是今天的订单
        $s = strtotime(date('Y-m-d'));
        $e = time();
        if(isset($input['uid'])){
            $inputUid = $input['uid'];
        }
        if(isset($input['goods_id'])){
            $inputGoodsId = $input['goods_id'];
        }
        if(isset($input['oid'])){
            $inputOid = $input['oid'];
        }
        if(isset($input['xmid'])){
            $inputXmid = $input['xmid'];
        }
        $whereXM = [
           // 'gid' => $this->gid,
            'status' => 1,
            'type' => 1
        ];
        if(isset($inputXmid)){
            $whereXM['id'] = $inputXmid;
        }
        $xms = [];
        $xkps = Db::name('group_xm')->where($whereXM)
            ->where(function ($query){
            	$query->where('gid',$this->gid)
            	->whereOr('jtid',$this->jtid);
            })
            ->select();
        if(empty($xkps)){
            if(isset($inputXmid)){
                return (['code'=>-4,'msg'=>'该新开考核项目不存在或已被禁用！']);
            }else{
                return (['code'=>-4,'msg'=>'当前未设定新开考核项目！']);
            }
        }else{
            $goods_id2 = [];
            foreach($xkps as $xkp){
                $xkp1 = trim(trim($xkp['goods_id']),',');
                if(!!$xkp1){
                    $xkp2 = explode(',',$xkp1);
                    for($i=0;$i<(count($xkp2));$i++){
                        $xms[$xkp2[$i]] = $xkp;
                        $goods_id2[] = $xkp2[$i];
                    }
                }
            }
        }
        $goods_id3 = implode(',',$goods_id2);
        if(!$goods_id3){
            return (['code'=>-4,'msg'=>'当前未设定新开考核项目！']);
        }

        $goods_id = array_values(array_unique(explode(',', $goods_id3)));
        
        if(empty($goods_id)){
            return (['code'=>-4,'msg'=>'当前未设定新开考核项目！']);
        }
        if(isset($inputGoodsId)){
            if(in_array($inputGoodsId,$goods_id)){
                $goods_id = [$inputGoodsId];
            }else{
                return (['code'=>-4,'msg'=>'当前新开考核项目不存在！']);
            }
        }
        $whereOG['og.adate'] = ['BETWEEN',[$s,$e]];//今日有，以往没有如何筛选
        $whereOG['og.gid'] = $this->gid;
        $whereOG['og.goodid'] = ['IN',$goods_id];
        $whereOG['og.type'] = ['NEQ',2];
       // $whereOG['og.price'] = ['NEQ',0];
        
        $whereO['o.gid'] = $this->gid;
        $whereO['o.dguid'] = $this->guid;
        $whereO['o.confirm'] = 1;
        if(isset($inputUid)){
            $whereO['o.uid'] = $inputUid;
        }
        if(isset($inputOid)){
            $whereO['o.oid'] = $inputOid;
        }
        /*
        $uids1 = Db::name('order_goods og')
            ->where($whereOG)
            ->join('order o','og.oid=o.oid')
            ->where($whereO)
            ->group('og.goodid,o.uid')
            ->column('o.uid');
        */
        $xx = Db::name('order_goods og')
                ->field('og.goodid,o.uid')
                ->where($whereOG)
                ->join('order o','og.oid=o.oid')
                ->where($whereO)
                ->group('og.goodid,o.uid')
                ->select();
        //进一步优化，过滤掉不符合的goods_id效率快一半
        $uids1 = [];
        $goods_id = [];
        $isUgm = [];
        $u = [];
        foreach($xx as $aa){
            $uid = $aa['uid'];
            if(!isset($isUgm[$uid])){
                $isUgm[$uid] = false;
                $whereUG['uid'] = $uid;
                $whereUG['gid'] = $this->gid;
                $uu = Db::name('user_member')
                    ->field('uid,realname,mobile,guid')
                    ->where($whereUG)
                    ->find();
                if(!empty($uu)){
                    if($uu['guid'] != $this->guid){
                        $whereUgm['gid'] = $this->gid;
                        $whereUgm['guid'] = $this->guid;
                        $whereUgm['uid'] = $uu['uid'];
                        if(Db::name('user_gm')->where($whereUgm)->count()){
                            $isUgm[$uid] = true;
                        }
                    }else{
                        $isUgm[$uid] = true;
                    }
                }
            }
            if($isUgm[$uid] === true){
                if(!isset($u[$uid])){
                    $u[$uid] = $uu;
                }
                if(!in_array($aa['uid'],$uids1)){
                    $uids1[] = $uid;
                }
                if(!in_array($aa['goodid'],$goods_id)){
                    $goods_id[] = $aa['goodid'];
                }
            }
        }
        //过滤以往已有产品购买记录的客户
        $xkPoints = [];
        foreach($uids1 as $v){
            $whereO['o.uid'] = $v;
            $whereO['o.confirm'] = ['NEQ',-1];
            $xkPoints[$v] = [];
            for($i=0;$i<count($goods_id);$i++){
                
                $whereOG['og.adate'] = ['BETWEEN',[$s,$e]];
                $whereOG['og.goodid'] = $goods_id[$i];

                $ogs = Db::name('order_goods og')
                    ->field('og.*,SUM(og.num) sumnum')
                    ->where($whereOG)
                    ->join('order o','og.oid=o.oid')
                    ->where($whereO)
                    ->group('og.unitid,og.oid')
                    ->select();
                
                $whereOG['og.adate'] = ['LT',$s];
                $c1 = Db::name('order_goods og')
                    ->where($whereOG)
                    ->join('order o','og.oid=o.oid')
                    ->where($whereO)
                    ->count();
                
                if(!$c1 && $ogs){
                    //调取规则
                    $pointsRules = $xms[$goods_id[$i]];
                    $sumnum = [];
                    $isPoint = [];
                    $isPoint[$v][$goods_id[$i]] = false;
                    foreach($ogs as $og){
                        //判断这个订单中的这个商品总数是否符合条件，如果符合
                        if(isset($xkPoints[$v][$goods_id[$i]])){
                            //说明这个客户对应的这个商品已经有符合规定的数据了，
                            //continue;//由于需要累加数据，暂注释掉
                        }
                        if(!!$isPoint[$v][$goods_id[$i]]){
                            continue;
                        }
                        if($og['unitid'] !== $pointsRules['unitid']){
                            //需要进行一个单位转换
                            if(!isset($this->coefficient[$og['unitid']])){
                                $this->coefficient[$og['unitid']] = Db::name('unit')->where('id',$og['unitid'])->value('coefficient');
                            }
                            $og['sumnum'] = $og['sumnum'] * $this->coefficient[$og['unitid']];
                        }
                        $k = $og['id'];
                        if(isset($sumnum[$og['oid']][$og['goodid']])){
                            //说明这个订单内有相同的商品但是只是没有符合条件而已
                            //这里就需要对其进行一个累加操作
                            //再将累加后的值进行一个判断
                            //主要是处理一个订单相同产品但是有多个单位的情况
                            $og['sumnum'] = $og['sumnum'] + $sumnum[$og['oid']][$og['goodid']]['sumnum'];
                            $og['id'] = $sumnum[$og['oid']][$og['goodid']]['ogids'] . '&' . $og['id'];
                        }
                        //根据 goods_id , xm_type ,gid ,uid 判断是否已经被积过分，积过分的就不需要再查询显示出来了 , 部分积分，那么所有的就不需要再积分
                        $whereXMP['gid'] = $this->gid;
                        //$whereXMP['guid'] = $this->guid; 客户有可能是已经变更过跟进人，那么这里条件不需要了
                        $whereXMP['uid'] = $v;
                        $whereXMP['xm_type'] = 1;
                        $whereXMP['goods_id'] = $og['goodid'];
                        $c = Db::name('group_xm_points')->where($whereXMP)->count();
                        if(!!$c){
                            //说明已经是这个客户的这个新开产品已经是积过分了，其后所有同类产品不需要再遍历了
                            $isPoint[$v][$goods_id[$i]] = true;
                            continue;
                        }else{
                            $isPoint[$v][$goods_id[$i]] = false;
                        }
                        $og['sumnum'] = round($og['sumnum'],2);
                        $sumnum[$og['oid']][$og['goodid']]['sumnum'] = $og['sumnum'];
                        $sumnum[$og['oid']][$og['goodid']]['ogids'] = $og['id'];
                        $xkPoints[$v][$goods_id[$i]] = $og;
                    }
                    //此处进行积分计算
                    $op =  trim($pointsRules['rule_op_min']);
                    switch ($op){
                        case 'gt':
                            if($xkPoints[$v][$goods_id[$i]]['sumnum'] > $pointsRules['rule_min']){
                                if(!isset($this->xmUnitname[$pointsRules['unitid']])){
                                    $this->xmUnitname[$pointsRules['unitid']] = Db::name('unit')->where('id',$pointsRules['unitid'])->value('uname');
                                }
                                $xkPoints[$v][$goods_id[$i]]['point_xmid'] = $pointsRules['id'];
                                $xkPoints[$v][$goods_id[$i]]['point_rule_min'] = $pointsRules['rule_min'];
                                $xkPoints[$v][$goods_id[$i]]['point_op_desc'] = '>';
                                $xkPoints[$v][$goods_id[$i]]['point_unitid_uname'] = $this->xmUnitname[$pointsRules['unitid']];
                                $xkPoints[$v][$goods_id[$i]]['point_unit'] = $pointsRules['unit'];
                                $xkPoints[$v][$goods_id[$i]]['point_unitid'] = $pointsRules['unitid'];
                                $xkPoints[$v][$goods_id[$i]]['point_unitgid'] = $pointsRules['unitgid'];
                                $xkPoints[$v][$goods_id[$i]]['point_title'] = $pointsRules['title'];
                                $xkPoints[$v][$goods_id[$i]]['point_score'] = $pointsRules['score'];
                                $xkPoints[$v][$goods_id[$i]]['point'] = round($pointsRules['score'],2);
                                $xkPoints[$v][$goods_id[$i]]['point_type'] = $pointsRules['type'];
                            }else{
                                unset($xkPoints[$v][$goods_id[$i]]);//说明这个客户购买数量不够积分
                            }
                            break;
                        case 'egt':
                            if($xkPoints[$v][$goods_id[$i]]['sumnum'] >= $pointsRules['rule_min']){
                                if(!isset($this->xmUnitname[$pointsRules['unitid']])){
                                    $this->xmUnitname[$pointsRules['unitid']] = Db::name('unit')->where('id',$pointsRules['unitid'])->value('uname');
                                }
                                $xkPoints[$v][$goods_id[$i]]['point_xmid'] = $pointsRules['id'];
                                $xkPoints[$v][$goods_id[$i]]['point_rule_min'] = $pointsRules['rule_min'];
                                $xkPoints[$v][$goods_id[$i]]['point_op_desc'] = '>=';
                                $xkPoints[$v][$goods_id[$i]]['point_unitid_uname'] = $this->xmUnitname[$pointsRules['unitid']];
                                $xkPoints[$v][$goods_id[$i]]['point_unit'] = $pointsRules['unit'];
                                $xkPoints[$v][$goods_id[$i]]['point_unitid'] = $pointsRules['unitid'];
                                $xkPoints[$v][$goods_id[$i]]['point_unitgid'] = $pointsRules['unitgid'];
                                $xkPoints[$v][$goods_id[$i]]['point_title'] = $pointsRules['title'];
                                $xkPoints[$v][$goods_id[$i]]['point_score'] = $pointsRules['score'];
                                $xkPoints[$v][$goods_id[$i]]['point'] = round($pointsRules['score'],2);
                                $xkPoints[$v][$goods_id[$i]]['point_type'] = $pointsRules['type'];
                            }else{
                                unset($xkPoints[$v][$goods_id[$i]]);//说明这个客户购买数量不够积分
                            }
                            break;
                        case 'eq':
                            if($xkPoints[$v][$goods_id[$i]]['sumnum'] >= $pointsRules['rule_min']){
                                if(!isset($this->xmUnitname[$pointsRules['unitid']])){
                                    $this->xmUnitname[$pointsRules['unitid']] = Db::name('unit')->where('id',$pointsRules['unitid'])->value('uname');
                                }
                                $xkPoints[$v][$goods_id[$i]]['point_xmid'] = $pointsRules['id'];
                                $xkPoints[$v][$goods_id[$i]]['point_rule_min'] = $pointsRules['rule_min'];
                                $xkPoints[$v][$goods_id[$i]]['point_op_desc'] = '=';
                                $xkPoints[$v][$goods_id[$i]]['point_unitid_uname'] = $this->xmUnitname[$pointsRules['unitid']];
                                $xkPoints[$v][$goods_id[$i]]['point_unit'] = $pointsRules['unit'];
                                $xkPoints[$v][$goods_id[$i]]['point_unitid'] = $pointsRules['unitid'];
                                $xkPoints[$v][$goods_id[$i]]['point_unitgid'] = $pointsRules['unitgid'];
                                $xkPoints[$v][$goods_id[$i]]['point_title'] = $pointsRules['title'];
                                $xkPoints[$v][$goods_id[$i]]['point_score'] = $pointsRules['score'];
                                $xkPoints[$v][$goods_id[$i]]['point'] = round($pointsRules['score'],2);
                            }else{
                                unset($xkPoints[$v][$goods_id[$i]]);//说明这个客户购买数量不够积分
                            }
                            break;
                        default:unset($xkPoints[$v][$goods_id[$i]]);//
                    }
                }
            }
        }
        $newXKPoints = [];
        $sumtotal = 0;
        foreach($xkPoints as $uidx=>$xkPoint){
            $x = [];
            if(!empty($xkPoint)){
                $n = 0;
                foreach($xkPoint as $y){
                    $n += $y['point'];
                }
                $x['project'] = array_values($xkPoint);
                $x['point'] = $n;
                $sumtotal += $n;
                $x['user'] = $u[$uidx];
                $newXKPoints[] = $x;
            }
        }
        if(empty($newXKPoints)){
            return (['code'=>-4,'msg'=>'<span style="color:red;">未查询到符合条件的新开客户项目或今日已提交所有项目数据信息，点击新开可刷新数据！</span>']);
        }else{
            return (['code'=>1,'msg'=>'客户数据加载成功！','data'=>$newXKPoints,'sum'=>$sumtotal]);
        }
    }
    private function getXKUsers($input){
        $res = $this->coreXK($input);
        if($res['code'] === 1){
            return json(['code'=>1,'msg'=>$res['msg'],'data'=>['data'=>$res['data'],'sum'=>$res['sum']]]);
        }else{
            return json($res);
        }
    }
    private function addXKPoints($input){
        if(!empty($input)){
            $xks = (array)$input['xkprojects'];
            $inputData = [];
            for($i=0;$i<count($xks);$i++){
                list($inputData['uid'],$inputData['ogids'],$inputData['goods_id'],$inputData['oid'],$inputData['xmid']) = explode('_',$xks[$i]);
                $res = $this->coreXK($inputData);
                if($res['code'] === 1){
                    //写库操作
                    $pointData = [];
                    $xkData = $res['data'][0];
                    $project = $xkData['project'][0];
                    $whereLG['gid'] = $logData['gid'] = $pointData['gid'] = $this->gid;
                    $whereLG['guid'] = $logData['action_uid'] = $logData['guid'] = $pointData['guid'] = $this->guid;
                    $pointData['uid'] = $inputData['uid'];
                    $pointData['goods_id'] = $inputData['goods_id'];
                    $pointData['hs_id'] = 0;
                    $pointData['hid'] = 0;
                    $pointData['oid'] = $inputData['oid'];
                    $pointData['ogid'] = $inputData['ogids'];
                    $pointData['status'] = 1;
                    //$pointData['num'] = $project['sumnum'];
                    $pointData['num'] = 1;
                    $pointData['score'] = $project['point_score'];
                    $logData['increase_points'] = $pointData['points'] = $project['point'];
                    $pointData['xm_title'] = $project['point_title'];
                    $pointData['xm_id'] = $inputData['xmid'];
                    $pointData['xm_type'] = $project['point_type'];
                    $pointData['unit'] = $project['point_unit'];
                    $pointData['unitid'] = $project['point_unitid'];
                    $pointData['unitgid'] = $project['point_unitgid'];
                    $pointData['create_time'] = time();
                    $pointData['create_date'] = date('Y-m-d',$pointData['create_time']);
                    $logData['status'] = 1;
                    $logData['user_agent'] = input('server.HTTP_USER_AGENT');
                    $logData['action_ip'] = get_client_ip();
                    $logData['create_time'] = time();
                    $logData['remark'] = '新开项目'. $project['point_title'] .'商品名称：' . $project['name'] .'积分变化' . $logData['increase_points'];
                    Db::startTrans();
                    try{
                        $logData['points_id'] = Db::name('group_xm_points')->insertGetId($pointData);
                        //获取最近一次的记录
                        $oldXmLog = Db::name('group_xm_log')->field('id,after_points')->where($whereLG)->order('create_time DESC')->find();
                        if(empty($oldXmLog)){
                            //第一次提交
                            $logData['before_points'] = 0;
                            $logData['after_points'] = $pointData['points'];
                            Db::name('group_xm_log')->insert($logData);
                        }else{
                            $logData['before_points'] = $oldXmLog['after_points'];
                            $logData['after_points'] = $logData['before_points'] + $pointData['points'];
                            Db::name('group_xm_log')->insert($logData);
                        }
                        Db::commit();
                    }catch(\think\Exception $e){
                        Db::rollBack();
                    }
                }
            }
            return json(['code'=>0,'msg'=>'成功提交新开数据！']);
        }else{
            return json(['code'=>-4,'msg'=>'非法操作！']);
        }
    }
    //广宣项目
    public function guangxuan(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }else{
                $action = '';
            }
            switch($action){
                case 'getGXProjects':return $this->getGXProjects($input);
               // case 'addGXPoints':return $this->addGXPoints($input);   //手动输入
                case 'addGXPoints':return $this->addGXDataPoints($input); //自动填写
            }
        }else{
            return json(['code'=>-4,'msg'=>'非法操作！']);
        }
    }
    private function getGXProjects($input = []){
        $whereXM = [
           // 'gid' => $this->gid,
            'status' => 1,
            'type' => 5
        ];
        $projects = Db::name('group_xm')
            ->field('id,gid,type,title,score,unit,rule_min,rule_max,rule_op_min,rule_op_max')
            ->where($whereXM)
            ->where(function ($query){
            	$query->where('gid',$this->gid)
            	->whereOr('jtid',$this->jtid);
            })
            ->order('displayorder DESC')
            ->select();
        foreach ($projects as $key=>$pr){
        	$map['gid']=$this->gid;
        	$map['guid']=$this->guid;
        	$map['xm_id']=$pr['id'];
        	$newdate=Db::name('group_xm_points')->where($map)->order('create_time desc')->value(create_time);
        	if($newdate){
        		$map['adate']=['gt',$newdate];
        	}else{
        		$map['adate']=['gt',strtotime(date('Y-m-d'))];
        	}
        	$map['id']=['gt',33847];
        	unset($map['xm_id']);
        	$pr['num']=Db::name('user_post')->where($map)->where('FIND_IN_SET('.$pr['id'].',`xm_id`)')->group('uid')->count('id');
        	$pr['points']=number_format($pr['num']*$pr['score'], 2);
        	if($pr['num']){
        		$projectss[]=$pr;
        	}        	
        	unset($map);
        }
        return json(['code'=>1,'data'=>$projectss]);
    }
    private function addGXDataPoints($input = []){    	
    	$whereXM = [
    	//'gid' => $this->gid,
    	'status' => 1,
    	'type' => 5
    	];
    	$projects = Db::name('group_xm')
    	->field('id,gid,type,title,score,unit,rule_min,rule_max,rule_op_min,rule_op_max')
    	->where($whereXM)
    	->where(function ($query){
    		$query->where('gid',$this->gid)
    		->whereOr('jtid',$this->jtid);
    	})
    	->order('displayorder DESC')
    	->select();    	
    	foreach ($projects as $key=>$pr){
    		$map['gid']=$this->gid;
    		$map['guid']=$this->guid;
    		$map['xm_id']=$pr['id'];
    		$newdate=Db::name('group_xm_points')->where($map)->order('create_time desc')->value(create_time);
    		if($newdate){
    			$map['adate']=['gt',$newdate];
    		}else{
    			$map['adate']=['gt',strtotime(date('Y-m-d'))];
    		}
    		$map['id']=['gt',33480];
    		unset($map['xm_id']);
    		$projects[$key]['num']=Db::name('user_post')->where($map)->where('FIND_IN_SET('.$pr['id'].',`xm_id`)')->group('uid')->count('id');
    		$projects[$key]['points']=$projects[$key]['num']*$pr['score'];
    		unset($map);
    	}
    	foreach($projects as $k=>$v){    		
    		if($v['num']>0){
	    		//写入数据库
	    		$whereLG['gid'] = $logData['gid'] = $pointData['gid'] = $this->gid;
	    		$whereLG['guid'] = $logData['action_uid'] = $logData['guid'] = $pointData['guid'] = $this->guid;
	    		$pointData['uid'] = 0;
	    		$pointData['goods_id'] = 0;
	    		$pointData['hs_id'] = 0;
	    		$pointData['hid'] = 0;
	    		$pointData['oid'] = 0;
	    		$pointData['status'] = 1;
	    		$pointData['num'] = intval($v['num']);
	    		$pointData['score'] = $v['score'];
	    		$logData['increase_points'] = $pointData['points'] = $v ['points'];
	    		$pointData['xm_title'] = $v['title'];
	    		$pointData['xm_id'] = $v['id'];
	    		$pointData['xm_type'] = $v['type'];
	    		$pointData['unit'] = $v['unit'];
	    		$pointData['create_time'] = time();
	    		$pointData['create_date'] = date('Y-m-d',$pointData['create_time']);
	    		$logData['status'] = 1;
	    		$logData['user_agent'] = input('server.HTTP_USER_AGENT');
	    		$logData['action_ip'] = get_client_ip();
	    		$logData['create_time'] = time();
	    		$logData['remark'] = '广宣项目'. $v['title'] .'积分变化' . $logData['increase_points'];
	    		$sign = true;
	    		Db::startTrans();
	    		try{
	    			$logData['points_id'] = Db::name('group_xm_points')->insertGetId($pointData);
	    			//获取最近一次的记录
	    			$oldXmLog = Db::name('group_xm_log')->field('id,after_points')->where($whereLG)->order('create_time DESC')->find();
	    			if(empty($oldXmLog)){
	    				//第一次提交
	    				$logData['before_points'] = 0;
	    				$logData['after_points'] = $pointData['points'];
	    				Db::name('group_xm_log')->insert($logData);
	    			}else{
	    				$logData['before_points'] = $oldXmLog['after_points'];
	    				$logData['after_points'] = $logData['before_points'] + $pointData['points'];
	    				Db::name('group_xm_log')->insert($logData);
	    			}
	    			Db::commit();
	    			
	    		}catch(\think\Exception $e){
	    			Db::rollBack();
	    		}
    		}
    	}
    	return json(['code'=>0,'msg'=>'您已成功提交今日广宣工作内容！']);
    }
    private function addGXPoints($input = []){
    	//if($this->gid==133){
    	//	return $this->addGXDataPoints($input);
    	//}
        if(!isset($input['num'])){
            return json(['code'=>-1,'msg'=>'警告：无效输入的输入！']);
        }
        $sign1 = false;
        $sign2 = false;
        $sign3 = false;
        foreach($input['num'] as $k=>$v){
            //等于0不做处理
            if(!!$v){
                $whereXM = [
                   // 'gid' => $this->gid,
                    'status' => 1,
                    'type' => 5,
                    'id' => $k
                ];
                $project = Db::name('group_xm')
                    ->field('id,gid,type,title,num,score,unit,rule_min,rule_max,rule_op_min,rule_op_max')
                    ->where($whereXM)
                    ->where(function ($query){
                    	$query->where('gid',$this->gid)
                    	->whereOr('jtid',$this->jtid);
                    })
                    ->find();//先确定这条规则是否还存在
                if(!empty($project)){
                	
                    //写入数据库
                    $whereLG['gid'] = $logData['gid'] = $pointData['gid'] = $this->gid;
                    $whereLG['guid'] = $logData['action_uid'] = $logData['guid'] = $pointData['guid'] = $this->guid;
                    $pointData['uid'] = 0;
                    $pointData['goods_id'] = 0;
                    $pointData['hs_id'] = 0;
                    $pointData['hid'] = 0;
                    $pointData['oid'] = 0;
                    $pointData['status'] = 1;
                    $pointData['num'] = intval($v);
                    $pointData['score'] = $project['score'];
                    $logData['increase_points'] = $pointData['points'] = $v * $project['score'];
                    $pointData['xm_title'] = $project['title'];
                    $pointData['xm_id'] = $project['id'];
                    $pointData['xm_type'] = $project['type'];
                    $pointData['unit'] = $project['unit'];
                    $pointData['create_time'] = time();
                    $pointData['create_date'] = date('Y-m-d',$pointData['create_time']);                  
                    
                    $logData['status'] = 1;
                    $logData['user_agent'] = input('server.HTTP_USER_AGENT');
                    $logData['action_ip'] = get_client_ip();
                    $logData['create_time'] = time();
                    $logData['remark'] = '广宣项目'. $project['title'] .'积分变化' . $logData['increase_points'];
                    $sign = true;
                    Db::startTrans();
                    try{
                        $logData['points_id'] = Db::name('group_xm_points')->insertGetId($pointData);
                        //获取最近一次的记录
                        $oldXmLog = Db::name('group_xm_log')->field('id,after_points')->where($whereLG)->order('create_time DESC')->find();
                        if(empty($oldXmLog)){
                            //第一次提交
                            $logData['before_points'] = 0;
                            $logData['after_points'] = $pointData['points'];
                            Db::name('group_xm_log')->insert($logData);
                        }else{
                            $logData['before_points'] = $oldXmLog['after_points'];
                            $logData['after_points'] = $logData['before_points'] + $pointData['points'];
                            Db::name('group_xm_log')->insert($logData);
                        }
                        Db::commit();
                    }catch(\think\Exception $e){
                        Db::rollBack();
                    }
                }else{
                    $sign2 = true;//部分项目被删除
                }
            }else{
                $sign3 = true;
            }
        }
        if($sign && !$sign2){
            if(!$sign3){
                return json(['code'=>0,'msg'=>'您已成功提交今日广宣工作内容！']);
            }else{
            	return json(['code'=>0,'msg'=>'您已成功提交今日广宣工作内容！']);
               // return json(['code'=>0,'msg'=>'您已成功提交今日广宣工作内容，部分未设置正确数量项目未保存！']);
            }
        }
        if($sign && $sign2){
            if(!$sign3){
             //   return json(['code'=>0,'msg'=>'您已成功提交今日广宣工作内容，部分删除或已禁用项目未保存！']);
                return json(['code'=>0,'msg'=>'您已成功提交今日广宣工作内容！']);
            }else{
            	return json(['code'=>0,'msg'=>'您已成功提交今日广宣工作内容！']);
               // return json(['code'=>0,'msg'=>'您已成功提交今日广宣工作内容，部分删除或已禁用项目及部分未设置正确数量项目未保存！']);
            }
        }
        if(!$sign && !$sign2){
            if(!$sign3){
                return json(['code'=>-1,'msg'=>'提交失败，所提交项目已被删除或禁用！']);
            }else{
                return json(['code'=>-1,'msg'=>'提交失败，所提交项目已被删除或禁用或未设置正确数量！']);
            }
        }
    }
}
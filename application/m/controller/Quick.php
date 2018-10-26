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
use think\Db;
class Quick extends Base{
    private $unitgmin = [];//保存已经查询过得组最小单位信息，避免重复查询
    private $unit = [];//保存已经查询过得单位信息，避免重复查询
    private $jf_unitg = [];
    private $jf_unit = [];
    //快捷方式：报价查询
    public function bjQuery(){
        if(request()->isAjax() && request()->isPost()){
            $action = input('post.action');
            switch ($action){
                case 'bjgoods':$r = $this->bjGoods();break;
                case 'bjcat':$r = $this->bjCat();break;
                case 'ranklist':$r = $this->rankList();break;

                default:$r = $this->bjGoods();break;
            }
            return json($r);
        }
    }
    //商品多属性
    public function bjsquery(){
        $param = input('param.');
        $rankids = trim(input('rankid'));
        $id=$param['cid'];
        $p = intval(trim(input('p')));
        $limit = intval(trim(input('limit')));
        $offset = ($p - 1) * $limit;
        //根据查询商品id sku查询多属性
        if(request()->isAjax()){
            $res=Db::name('baojia_sku')
                ->field('id,baojia_id,attrs_value')
                ->where(array('baojia_id'=>$id,'gid'=>session('gid')))
                ->order('id desc')
                ->limit($offset, $limit)
                ->select();
            $total = Db::name('baojia_sku')
                ->where(array('baojia_id'=>$id,'gid'=>session('gid')))
                ->count();
            $lbs=[];
            foreach($res as $k=>$v){
                if(isset($v['id'])){
                    $wheresku=[];
                    if($rankids!=='all' && $rankids!=='none'){
                        $wheresku['skuid']=['=',$v['id']];
                        $wheresku['gid']=['=',session('gid')];
                        $wheresku['rankid']=['=',$rankids];
                        $wheresku['unitid']=['neq',0];
                        $skuprices=Db::name('baojia_skuprice')->field('id,unitid,rankid,tuanprice,retailprice')->where($wheresku)->select();

                    }else if($rankids==='none'){
                        $wheresku['skuid']=['=',$v['id']];
                        $wheresku['gid']=['=',session('gid')];
                        $wheresku['rankid']=['=',0];
                        $wheresku['unitid']=['neq',0];
                        $skuprices=Db::name('baojia_skuprice')->field('id,unitid,rankid,tuanprice,retailprice')->where($wheresku)->select();

                    }else{

                        $wheresku['skuid']=['=',$v['id']];
                        $wheresku['gid']=['=',session('gid')];
//                        $wheresku['rankid']=['neq',0];
                        $wheresku['unitid']=['neq',0];
                        $skuprices=Db::name('baojia_skuprice')->field('id,unitid,rankid,tuanprice,retailprice')->where($wheresku)->select();
                    }
                    $rank=[];
                    $unit=[];
                    $price=[];
                    foreach($skuprices as $key =>$val){
                        if(isset($val['rankid'])){
                            $rankid=[];
                            if($val['rankid']===0){
                                $first = [
                                    'id'=>0,
                                    'rankname'=>'默认价格'
                                ];
                                array_unshift($rankid,$first);
                            }else{
                                $rankid = Db::name('user_rank')->field('id,rank_name')->where(array('id' => $val['rankid'], 'gid' => session('gid')))->find();
                                if($rankid==null || empty($rankid)){
                                    return json(['msg'=>'不存在该等级客户！']);
                                }
                            }
                            $rank[$val['rankid']]=$rankid;

                        }
                        if(isset($val['unitid'])){
                            $unitid=Db::name('unit')->field('id,uname')->where(array('id'=>$val['unitid'],'gid'=>session('gid')))->find();
                            if($unitid==null || empty($unitid)){
                                return json(['msg'=>'不存在该商品单位！']);
                            }
                            $unit[$val['unitid']]=$unitid;
                        }
                        $val['rank']=$rank[$val['rankid']];
                        $val['unit']=$unit[$val['unitid']];
                        $price[]=$val;
                    }
                    $v['skuprice']=$price;
                }
                $lbs[]=$v;
            }
            $return = $lbs;
            if(count($res)==0){
                $return['code'] =0;
                $return['msg'] ='没有数据！';
            }
            return json(['code'=>1,'data'=>$return,'p'=>$p,'total'=>$total, 'msg'=>'数据加载成功！']);
        }
    }
    //快捷方式：报价查询->报价商品
    private function bjGoods(){
       $cid = trim(input('cid'));
        //判断cid在报价是否有 cid是多属性返回的商品id  有--不是多属性报价 搜索当前  没有--是多属性报价 默认搜索全部
        $count=Db::name('baojia')->where(array('id'=>$cid,'gid'=>session('gid')))->count();
        if($count>0){
            $cid='';
        }else if($count<=0){
            $cid=trim(input('cid'));
        }
       //查询当前商品的名称
       $rankid = trim(input('rankid'));
       $t = input('t');
       $p = intval(trim(input('p')));
       $limit = intval(trim(input('limit')));
       $offset = ($p - 1) * $limit;
       if($cid){
           //判定是否有子id
           $ccid = Db::name('category')->where('pid',$cid)
               ->where('gid',$this->gid)->column('id');
           if(!empty($ccid)){
               array_push($ccid,$cid);
               $ccids = implode(',',$ccid);
               $whereC['categoryid'] = ['IN',$ccids];
           }else{
               $whereC['categoryid'] = $cid;
           }
           $bid = Db::name('baojia_cat')->where($whereC)
               ->where('gid',$this->gid)->column('bid');
           if(!empty($bid)){
               $where['b.id'] = ['IN',$bid];
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
       if(!empty($t)){
           $whereOrG = [];
           $whereOrG['goods_sn'] = ['like', '%' . $t . '%'];
           $whereOrG['goods_name'] = ['like', '%' . $t . '%'];
           //获取所有goods_id
           $goods_ids1 = Db::name('goods')->whereOr($whereOrG)->column('goods_id');
           if(!empty($goods_ids1)){
               //先查询出这个gid下报价商品的所有gid;
               $bj_goods_ids = Db::name('baojia')->where('gid',$this->gid)->column('goods_id');
               $goods_ids = [];
               foreach($bj_goods_ids as $k=>$v){
                   if(in_array($v,$goods_ids1)){
                       $goods_ids[] = $v;
                   }
               }
               if(!empty($goods_ids)){
                   $where['b.goods_id'] = ['IN',$goods_ids];
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
               $return['msg'] ='没有更多数据！'; 
               $return['data'] = null;
               $return['total'] = 0;
               $return['p'] = $p;
               return $return;
           }
       }
       $res = $this->rankList();
       if($res['code'] === 1){
           $x = $res['data'];
           $rl = [];
           foreach($x as $k=>$v){
               $rl[] = $v['rankid'];
           }
       }
       if($rankid != 'all'){
           if(!$rankid || !in_array($rankid,$rl)){
               $return['total'] = 0;//当前数据
               $return['data'] =[];
               $return['p'] = $p;
               $return['msg'] ='没有数据！';
               $return['code'] =0;
               return $return;
           }
       }
       $where['b.gid'] = $this->gid;
       //获取所有的商品默认报价
       $default = Db::name('baojia b')
           ->field('b.*,g.goods_id,g.goods_name,g.goods_img')->where($where)
           ->join('goods g','b.goods_id=g.goods_id','LEFT')
           ->limit($offset, $limit)->select();
       $total = Db::name('baojia b')->where($where)->count();
       $data = [];//多个或一个报价
       $whereBJR = [];
       if($rankid === 'all'){
           $whereBJR['b.gid'] = $this->gid;
           $whereBJR['b.rank_id'] = ['IN',$rl];
       }else if($rankid === 'none'){

       }else{
           $whereBJR['b.gid'] = $this->gid;
           $whereBJR['b.rank_id'] = $rankid;
       }
       foreach($default as $k=>$v){
           $baojia_sku=[];
           $bj = [];
           $data[$k]['info'] = [
               'goods_id'=>$v['goods_id'],
               'id'=>$v['id'],
               'goods_name'=>$v['goods_name'],
               'goods_img'=>mkgoodsimgurl(['url'=>$v['goods_img']])
           ];
           if(!empty($whereBJR)){
               $whereBJR['b.goods_id'] = $v['goods_id'];
               $bj = Db::name('baojia_rank b')
                   ->field('b.goods_id,b.id,b.tuanprice,b.retailprice,b.cuxiao,b.csdate,b.cedate,b.unit,b.unitid,r.rank_name rankname')
                   ->where($whereBJR)
                   ->join('user_rank r','b.rank_id=r.id','LEFT')
                   ->select();
           }
           $baojia_sku[$v['id']]=Db::name('baojia_sku')->where(array('baojia_id'=>$v['id']))->count();
           if($baojia_sku[$v['id']]>0){
                //为多属性
           }else{
               $first = [
                   'goods_id'=>$v['goods_id'],
                   'id'=>$v['id'],
                   'tuanprice'=>$v['tuanprice'],
                   'retailprice'=>$v['retailprice'],
                   'cuxiao'=>$v['cuxiao'],
                   'csdate'=>$v['csdate'],
                   'cedate'=>$v['cedate'],
                   'unit'=>$v['unit'],
                   'unitid'=>$v['unitid'],
                   'rankname'=>'默认价格'
               ];
               array_unshift($bj,$first);
           }
           $data[$k]['bj'] = $bj;
           $data[$k]['bj_sku'] = $baojia_sku[$v['id']];
       }
       //获取报价
       if(count($data)){
           $return['code'] = 1;
       }else{
           $return['code'] = 0;
       }
       $return['total'] = $total;//当前数据
       $return['data'] = $data;
       $return['p'] = $p;
       $return['msg'] ='数据加载成功！';
       return $return;
    }
    //快捷方式：查询报价->商品报价分类信息获取
    private function bjCat(){
        if(request()->isAjax()){
            //获取所有的报价类别
            $where['gid'] = session('gid');
            $where['pid'] = 0;
            $p = Db::name('category')->field('id,title')->where($where)->order('id ASC')->select();
            $data = [];
            foreach($p as $k=>$v){
                $where['pid'] = $v['id'];
                $c = Db::name('category')->field('id,title')->where($where)->select();
                $data[] = ['p'=>$v,'c'=>$c];
            }
            return ['code'=>1,'data'=>$data];
        }
    }
    //快捷方式：查询报价->授权等级列表
    private function rankList(){
        $guid = $this->guid;
        $gid = $this->gid;
        if(!!$guid && !!$gid){
            $whereGMP['gid'] =  $whereUR['gid'] = $gid;
            $whereGMP['guid'] = $guid;
            $whereGMP['status'] = 1;
            $whereGMP['type'] = 2;
            $whereUR['status'] = 1;
            $data[] = [
            'rankid'=>'all',
            'rankname'=>'所有等级',
            ];
            $data[] = [
            'rankid'=>'none',
            'rankname'=>'默认价格',
            ];
			//print_r($data);
            if(session('isadmin')==1){
            	$allRankId = Db::name('user_rank')->where($whereUR)->column('id,rank_name');
            	if(!empty($allRankId)){
            		foreach($allRankId as $k=>$v){
            				$data[] = [
            				'rankid' => $k,
            				'rankname' => $v
            				];
            		}
            	}
            }else{
	            $relation_values = Db::name('group_member_project')->where($whereGMP)->value('relation_values');
	            $relation_values = trim(trim($relation_values),',');
	           // $data = [];
	           
	            if(!!$relation_values){
	                $allRankId = Db::name('user_rank')->where($whereUR)->column('id,rank_name');
	                if(!empty($allRankId)){
	                    $gmRank = explode(',',$relation_values);
	                    foreach($allRankId as $k=>$v){
	                        if(in_array($k,$gmRank)){
	                            $data[] = [
	                                'rankid' => $k,
	                                'rankname' => $v
	                            ];
	                        }
	                    }
	                }
	            }
            }
			//print_r($data);
            return ['code'=>1,'msg'=>'','data'=>$data];
        }else{
            return ['code'=>-1,'msg'=>'非法操作！'];
        }
    }
    //快捷方式：签到打卡功能
    public function dk(){
    	if(request()->isAjax() && request()->isPost()){
    		$input = input('post.');
    		if(isset($input['action'])){
    			$action = $input['action'];
    			unset($input['action']);
    		}else{
    			$action = '';
    		}
    		switch($action){
    			case 'convertgps':
                                //经纬度转换
    				$lng = $input['lng'];
    				$lat = $input['lat'];
    				return json(['status'=>0,'xy'=>getbaidugps($lat,$lng,'gps')]);
    			case 'dodk':return $this->dodk($input);
    			case 'dklist':return $this->dklist($input);break;
    		}
    	}
    }
    //打卡写库
    private function dodk($dkdata){
    	$dkdata['gid'] =$map['gid'] = $this->gid;
    	$dkdata['uid'] =$map['uid'] = $this->guid;
    	$dkdata['ip'] = get_client_ip();
    	$dkdata['dktype'] =$map['dktype']=$dkdata['sign'];
    	$dkdata['type'] = $map['type']=0;
    	$dkdata['dkdt'] = date('Y-m-d H:i:s');
    	$dkdata['optdt'] = date('Y-m-d H:i:s');
    	$dksign = $dkdata['sign'];
    	$map['dkdt']=['gt',date('Y-m-d H:i:s',time()-120)];
    	$isdk=Db::name('oa_kqdkjl')->where($map)->value('id');
    	if($isdk){
    		return json(['code'=>-1,'msg'=>'您刚刚已经打卡成功，2分钟后可再次打卡！']);
    	}
    	unset($dkdata['sign']);
    	switch($dksign){
    		case 1:$dkdata['explain'] = '上班打卡';break;
    		case 2:$dkdata['explain'] = '下班打卡';break;
    		default:return json(['code' => -1,'msg' => '未知打卡类型！']);
    	}
    	if($dkdata['lng'] && $dkdata['lat']){
    		try{
    			Db::name('oa_kqdkjl')->insert($dkdata);
    			return json(['code'=>0,'msg'=>'您已打卡成功！']);
    		}catch(\think\Exception $e){
    			return json(['code'=>-1,'msg'=>'系统繁忙，请稍候再试！' . $e->getMessage()]);
    		}
    	}else{
    		return json(['code'=>-1,'msg'=>'未获取到位置信息！']);
    	}
    }
    //打卡记录
    private function dklist($input){
    	$p = trim($input['p']);
    	$limit = trim($input['limit']);
    	$offset = ($p - 1) * $limit;
    	$whereDK['gid'] = $this->gid;
    	$whereDK['uid'] = $this->guid;
    	if((isset($input['s']) && !!(trim($input['s']))) || (isset($input['e']) && !!(trim($input['e'])))){
    		$s = trim($input['s']);
    		$e = trim($input['e']);
    		if($s && !$e){
    			$whereDK['dkdt'] = ['EGT',$s];
    		}else if(!$s && $e){
    			$whereDK['dkdt'] = ['ELT',$e];
    		}else{
    			$whereDK['dkdt'] = ['BETWEEN',[$s,$e]];
    		}
    	}
    	if(isset($input['t']) && !!(trim($input['t']))){
    		$whereDK['dktype'] = trim($input['t']);
    	}
    	$total = Db::name('oa_kqdkjl')->where($whereDK)->count();
    	$data = Db::name('oa_kqdkjl')->where($whereDK)->order('dkdt DESC')->limit($offset,$limit)->select();
    	if(count($data) !== 0){
    		$res = ['code'=>1,'total'=>$total,'p'=>$p,'data'=>$data,'msg'=>'数据加载成功'];
    	}else{
    		$res = ['code'=>0,'total'=>$total,'p'=>$p,'data'=>null,'msg'=>'数据加载成功'];
    	}
    	return json($res);
    }
    //报表|助手功能
    public function bb(){
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
                $nodes = Db::name('group_auth_rule')->where($whereGAR)->select();
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
    //助手：出货功能
    public function bbch(){
    	if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $action = isset($input['action'])?trim($input['action']):'';
            unset($input['action']);
            switch($action){
                case 'showallbbch':return $this->showallbbch($input);
                case 'showbbonegoodsch':return $this->showbbonegoodsch($input);
                case 'searchChGoods':return $this->searchChGoods($input);
                case 'searchChUsers':return $this->searchChUsers($input);
                case 'setsessionuid':session('uid',$input['uid']);return json(['code'=>0,'msg'=>'Success!']);
                default:null;
            }
    	}else{
            return json(['code'=>-1,'msg'=>'非法操作！']);
    	}
    }
    //助手：出货功能->单品出货->客户搜索
    private function searchChUsers($input){
    	$guid = $this->guid;
    	$t = isset($input['t'])?trim($input['t']):'';
    	$p = $input['p'];
    	$limit = $input['limit'];
    	$offset = ($p-1) * $limit;
    	if(empty($t)){
            return json(['code'=>0,'data'=>null]);
    	}
        //在前面还需确认1:当前登录人的绑定商品是否
    	$whereUGM['u.gid'] = $whereUM['um.gid'] = $this->gid;
    	$whereUGM['u.guid'] = $whereUM['um.guid'] = $this->guid;
    	$total = Db::name('user_member um')
            ->join('user_gm u','u.uid=um.uid','LEFT')
            ->where($whereUM)
            ->where(function ($query) use ($t){
                $whereOr['um.realname'] = ['LIKE','%' . $t . '%'];
                $whereOr['um.mobile'] = ['LIKE','%' . $t . '%'];
                $query->whereOr($whereOr);
            })
            ->where($whereUGM)
            ->count();
    	$users = Db::name('user_member um')
            ->field('um.uid,um.realname,um.mobile')
            ->join('user_gm u','u.uid=um.uid','LEFT')
            ->where($whereUM)
            ->where(function ($query) use ($t){
                $whereOr['um.realname'] = ['LIKE','%' . $t . '%'];
                $whereOr['um.mobile'] = ['LIKE','%' . $t . '%'];
                $query->whereOr($whereOr);
            })
            ->where($whereUGM)
            ->limit($offset,$limit)
            ->order('uid','DESC')
            ->select();
    	$data = [];
    	if(!empty($users)){
            //处理超长名称
            foreach($users as $v){
                if(mb_strlen($v['realname'],'UTF-8') >= 10){
                    $v['realname'] = mb_substr($v['realname'], 0, 10) . '...';
                }
                $data[] = $v;
            }
            return json(['code'=>1,'data'=>$data,'p'=>$p,'total'=>$total, 'msg'=>'数据加载成功！']);
    	}else{
            return json(['code'=>0,'data'=>$data,'p'=>$p,'total'=>$total, 'msg'=>'数据加载成功！']);
    	}
    }
    //单个商品出货信息
    private function showbbonegoodsch($input){
    	$p = $input['p'];
    	$limit = $input['limit'];
    	$offset = ($p - 1) * $limit;
    	$data = [];
    	if(isset($input['goodid']) && !empty($input['goodid']) && isset($input['unitgid']) && !empty($input['unitgid'])){
            $goodid = $input['goodid'];
            $unitgid = $input['unitgid'];
            //商品存不存在？
            $goods = Db::name('goods')->find($goodid);
            if(empty($goods)){
                return json(['code'=>0,'data'=>['data'=>$data,'num'=>0,'tam'=>0],'total'=>0,'p'=>$p]);
            }
            //商品在不在绑定商品范围内
            $whereBG['guid'] = $this->guid;
            $whereBG['gid'] = $this->gid;
            $whereBG['type'] = 1;
            if(Db::name('bind_goods bg')->where($whereBG)->count()){
                $whereBG['goods_id'] = $goodid;
                if(!Db::name('bind_goods')->where($whereBG)->count()){
                    return json(['code'=>0,'data'=>['data'=>$data,'num'=>0,'tam'=>0],'total'=>0,'p'=>$p]);
                }
            }
    	}else{
            return json(['code'=>0,'data'=>['data'=>$data,'num'=>0,'tam'=>0],'total'=>0,'p'=>$p]);
    	}
    	if(isset($input['usersids']) && !empty($input['usersids'])){
            $usersids1 = $input['usersids'];
            $whereUM['uid'] = ['IN',$usersids1];
            $whereUM['gid'] = $this->gid;
            $whereUM['guid'] = $this->guid;
            $usersids = Db::name('user_member')->where($whereUM)->column('uid');//过滤非当前登录业务员客户s
    	}else{
            //获取当前登录员工的所有客户
            $whereUM['gid'] = $this->gid;
            $whereUM['guid'] = $this->guid;
            $usersids = Db::name('user_member')->where($whereUM)->column('uid');//过滤非当前登录业务员客户s
    	}
    	//设置时间段
    	$during = $this->setInterval($input);
    	$s = $during['start'];
    	$e = $during['stop'];
    	$whereO = [];
    	if(!empty($usersids)){
            $whereO['o.uid'] = ['IN',$usersids];
        }else{
            //说明这个员工没有客户，那么不在进行下一步操作！
            //return json(['code'=>0,'data'=>['data'=>$data,'num'=>0,'tam'=>0],'total'=>0,'p'=>$p]);
    	}
        
    	$whereO['o.guid|o.dguid'] = $this->guid;
    	$whereO['o.gid'] = $this->gid;
    	//$whereO['o.confirm'] = ['NEQ',-1];
    	$whereO['o.confirm'] = 1;

    	$whereOG['og.adate'] = ['BETWEEN',[$s,$e]];//这个条件是跟着订单走还是跟着商品走？？？？？
    	$whereOG['og.gid'] = $this->gid;
    	$whereOG['og.unitid'] = ['NEQ',''];
    	$whereOG['og.unitgid'] = $unitgid;
    	$whereOG['og.goodid'] = $goodid;
        
    	$totalAllMoney = Db::name('order_goods og')
            ->join('order o','o.oid=og.oid','LEFT')
            ->where($whereOG)
            ->where($whereO)
            ->sum('amount');
        
    	//筛选出符合条件的人
    	$total = Db::name('order_goods og')
            ->field('o.uid')
            ->join('order o','o.oid=og.oid','LEFT')
            ->where($whereOG)
            ->where($whereO)
            ->group('o.uid')
            ->count();//符合条件的人数

    	$num = 0;
    	$nums = Db::name('order_goods og')
            ->field('og.unitid,SUM(og.num) num,og.unitgid')
            ->join('order o','o.oid=og.oid','LEFT')
            ->where($whereOG)
            ->where($whereO)
            ->group('og.unitid')
            ->select();
    	$u = Db::name('order_goods og')
            ->field('o.uid')
            ->join('order o','o.oid=og.oid','LEFT')
            ->where($whereOG)
            ->where($whereO)
            ->group('o.uid')
            ->limit($offset,$limit)
            ->select();
    	if(empty($u)){
            //最近没有符合条件的订货人，那么就没有出货记录
            return json(['code'=>0,'data'=>['data'=>$data,'num'=>$num,'tam'=>$totalAllMoney],'total'=>0,'p'=>$p]);
    	}else{
            //单位转换
            //以最小单位计算
            if(!isset($this->unitgmin[$unitgid])){
                //如果这个单位组都没有了，那就没有必要再去统计信息了
                $whereUG = [];
               // $whereUG['gid'] = $this->gid;
                $whereUG['id'] = $unitgid;
                $minunitid = Db::name('unit_group')->where($whereUG)->value('minunitid');
                if(!!$minunitid){
                    $this->unitgmin[$unitgid] = $minunitid;
                }else{
                    $minunitid = $this->unitgmin[$unitgid] = 0;
                }
            }else{
                $minunitid = $this->unitgmin[$unitgid];
            }
            if(!!$minunitid){
                if(!isset($this->unit[$minunitid])){
                    $whereU = [];
                    $whereU['id'] = $minunitid;
                   // $whereU['gid'] = $this->gid;
                    $whereU['unitgid'] = $unitgid;
                    $minunit = Db::name('unit')->field('id,uname,unitgid,coefficient')->where($whereU)->find();
                    if(!empty($minunit)){
                        $this->unit[$minunitid] = $minunit;
                    }else{
                        $this->unit[$minunitid] = 0;
                    }
                }else{
                    $minunit = $this->unit[$minunitid];
                }
            }
            if(!!$minunit){
                //单位换算开始
                foreach($nums as $vx){
                    if(!isset($this->unit[$vx['unitid']])){
                        $whereU = [];
                        $whereU['id'] = $vx['unitid'];
                        //$whereU['gid'] = $this->gid;
                        $whereU['unitgid'] = $vx['unitgid'];
                        $unit = Db::name('unit')->field('id,uname,unitgid,coefficient')->where($whereU)->find();
                        if(!empty($unit)){
                            $this->unit[$vx['unitid']] = $unit;
                        }else{
                            $this->unit[$vx['unitid']] = 0;
                        }
                    }else{
                        $unit = $this->unit[$vx['unitid']];
                    }
                    if(!!$unit){
                        $num += ($vx['num'] * $unit['coefficient']);
                    }
                }
            }
            foreach($u as $v){
                $v['unitgid'] = $unitgid;
                $whereUM = [];
                $whereUM['uid'] = $v['uid'];
                $whereUM['gid'] = $this->gid;
                $user = Db::name('user_member')
                    ->field('uid,realname,mobile')
                    ->where($whereUM)
                    ->find();
                //获取这个人的出货商品信息
                $whereO['o.uid'] = $v['uid'];
                $og = Db::name('order_goods og')
                    ->field('og.goodid,og.unit,og.name,og.unitid,SUM(og.num) num,SUM(og.amount) amount')
                    ->join('order o','o.oid=og.oid','LEFT')
                    ->where($whereOG)
                    ->where($whereO)
                    ->group('unitid')
                    ->select();
                $datax = [];
                $datay = [];
                foreach($og as $v1){
                    if(empty($datax)){
                        $datax['goodid'] = $v1['goodid'];
                        $datax['name'] = $v1['name'];
                        $datay['unit'] = '';
                        $datay['unitid'] = '';
                        $datay['num'] = 0;
                        $datay['amount'] = 0;
                        $datay['price'] = 0;
                    }
                        //获取最小单位信息
                        //如果最小单位不存在，什么也不做
                    if(!!$minunit){
                        //单位换算开始
                        if(!isset($this->unit[$v1['unitid']])){
                            $whereU = [];
                            $whereU['id'] = $v1['unitid'];
                           // $whereU['gid'] = $this->gid;
                            $whereU['unitgid'] = $v['unitgid'];
                            $unit = Db::name('unit')->field('id,uname,unitgid,coefficient')->where($whereU)->find();
                            if(!empty($unit)){
                                $this->unit[$v1['unitid']] = $unit;
                            }else{
                                $this->unit[$v1['unitid']] = 0;
                            }
                        }else{
                            $unit = $this->unit[$v1['unitid']];
                        }
                        if(!!$unit){
                            $datay['amount'] += $v1['amount'];
                            $datay['num'] += ($v1['num'] * $unit['coefficient']);
                            $datay['unit'] = $minunit['uname'];
                            $datay['unitid'] = $minunit['id'];
                        }
                    }
                }
                if(!!$datay['unitid']){
                    $y = array_merge($datax,$datay,$user);
                    $y['price'] = sprintf('%0.2f',round($y['amount'] / $y['num'],2));
                    $y['amount'] = sprintf('%0.2f',$y['amount']);
                    $data[] = $y;
                }
            }
            $price = round($totalAllMoney / $num,2);
            $totalAllMoney = sprintf('%0.2f',$totalAllMoney);
            return json(['code'=>1,'data'=>['data'=>$data,'uname'=>$minunit['uname'],'num'=>$num,'price'=>$price,'total'=>$total,'tam'=>$totalAllMoney],'total'=>$total,'p'=>$p]);
    	}
    }
    //授权商品出货统计信息
    private function showallbbch($input){
    	$whereO['o.gid'] = $this->gid;
    	//$whereO['o.confirm'] = ['NEQ',-1];
    	$whereO['o.confirm'] = 1;
    	if(isset($input['goodsids']) && !empty($input['goodsids'])){
            $goodsids = $input['goodsids'];
            $whereOG['og.goodid'] = ['IN',$goodsids];
    	}else{
            //那么获取这个员工的所有绑定商品
            $whereBG['bg.guid'] = $this->guid;
            $whereBG['bg.gid'] = $this->gid;
            $whereBG['bg.type'] = 1;
            $goodsids = Db::name('bind_goods bg')->where($whereBG)->column('goods_id');
            if(!empty($goodsids)){
                $whereOG['og.goodid'] = ['IN',$goodsids];
            }
    	}
    	$whereO['o.guid|o.dguid'] = $this->guid;
    	$during = $this->setInterval($input);
    	$s = $during['start'];
    	$e = $during['stop'];
    	$whereOG['og.adate'] = ['BETWEEN',[$s,$e]];
    	$whereOG['og.gid'] = $this->gid;
    	$whereOG['og.unitid'] = ['NEQ',''];
    	$whereOG['og.unitgid'] = ['NEQ',''];
    	//这里最主要就是因为报价单位不同的问题需要转换
    	$p = $input['p'];
    	$limit = $input['limit'];
    	$offset = ($p - 1) * $limit;
    	$type = $total = Db::name('order_goods og')
            ->join('order o','o.oid=og.oid','LEFT')
            ->where($whereOG)
            ->where($whereO)
            ->group('og.goodid,og.unitgid')
            ->count();//总计总类
    	$totalAllMoney = Db::name('order_goods og')
            ->join('order o','o.oid=og.oid','LEFT')
            ->where($whereOG)
            ->where($whereO)
            ->sum('amount');
    	$g = Db::name('order_goods og')
            ->field('og.goodid goodid,og.unitgid unitgid')
            ->join('order o','o.oid=og.oid','LEFT')
            ->where($whereOG)
            ->where($whereO)
            ->group('goodid,unitgid')
            ->limit($offset,$limit)
    //	->fetchsql(true)
    	->select();
    //	echo $g;exit;
    	$data = [];//作为最终存储结果的输出数组
    	if(!empty($g)){
            $whereOG = [];
            $whereOG['og.adate'] = ['BETWEEN',[$s,$e]];//时间段
            $whereOG['og.gid'] = $this->gid;
            $whereOG['og.unitid'] = ['NEQ',''];
            foreach($g as $v){
                $whereOG['og.goodid'] = $v['goodid'];
                $whereOG['og.unitgid'] = $v['unitgid'];
                //按照unitid进行分组
                $og = Db::name('order_goods og')
                    ->field('og.goodid,og.unit,og.unitgid,og.name,og.unitid,SUM(og.num) num,SUM(og.amount) amount')
                    ->join('order o','o.oid=og.oid','LEFT')
                    ->where($whereOG)
                    ->where($whereO)
                    ->group('unitid')
                    ->select();
                    //如果说$og只有一条数据 那么就没有必要进行单位换算
                if(!empty($og)){
                    if(count($og) === 1){
                        $x = $og[0];
                        $x['price'] = sprintf('%0.2f',round($x['amount'] / $x['num'],2));
                        $x['amount'] = sprintf('%0.2f',$x['amount']);
                        $data[] = $x;
                    }else{
                        $datax = [];
                        $datay = [];
                        foreach($og as $v1){
                            if(empty($datax)){
                                $datax['goodid'] = $v1['goodid'];
                                $datax['name'] = $v1['name'];
                                $datay['unit'] = '';
                                $datay['unitgid'] = '';
                                $datay['unitid'] = '';
                                $datay['num'] = 0;
                                $datay['amount'] = 0;
                                $datay['price'] = 0;
                            }
                            //单位转换
                            //以最小单位计算
                            if(!isset($this->unitgmin[$v['unitgid']])){
                                //如果这个单位组都没有了，那就没有必要再去统计信息了
                                $whereUG = [];
                               // $whereUG['gid'] = $this->gid;
                                $whereUG['id'] = $v['unitgid'];
                                $minunitid = Db::name('unit_group')->where($whereUG)->value('minunitid');
                                if(!!$minunitid){
                                    $this->unitgmin[$v['unitgid']] = $minunitid;
                                }else{
                                    $minunitid = $this->unitgmin[$v['unitgid']] = 0;
                                }
                            }else{
                                $minunitid = $this->unitgmin[$v['unitgid']];
                            }
                            if(!!$minunitid){
                                //获取最小单位信息
                                if(!isset($this->unit[$minunitid])){
                                    $whereU = [];
                                    $whereU['id'] = $minunitid;
                                    //$whereU['gid'] = $this->gid;
                                    $whereU['unitgid'] = $v['unitgid'];
                                    $minunit = Db::name('unit')->field('id,uname,unitgid,coefficient')->where($whereU)->find();
                                    if(!empty($minunit)){
                                        $this->unit[$minunitid] = $minunit;
                                    }else{
                                        $this->unit[$minunitid] = 0;
                                    }
                                }else{
                                    $minunit = $this->unit[$minunitid];
                                }
                                    //如果最小单位不存在，什么也不做
                                if(!!$minunit){
                                    //单位换算开始
                                    if(!isset($this->unit[$v1['unitid']])){
                                        $whereU = [];
                                        $whereU['id'] = $v1['unitid'];
                                        //$whereU['gid'] = $this->gid;
                                        $whereU['unitgid'] = $v['unitgid'];
                                        $unit = Db::name('unit')->field('id,uname,unitgid,coefficient')->where($whereU)->find();
                                        if(!empty($unit)){
                                            $this->unit[$v1['unitid']] = $unit;
                                        }else{
                                            $this->unit[$v1['unitid']] = 0;
                                        }
                                    }else{
                                        $unit = $this->unit[$v1['unitid']];
                                    }
                                    if(!!$unit){
                                        $datay['amount'] += $v1['amount'];
                                        $datay['num'] += ($v1['num'] * $unit['coefficient']);
                                        $datay['unit'] = $minunit['uname'];
                                        $datay['unitid'] = $minunit['id'];
                                        $datay['unitgid'] = $minunit['unitgid'];
                                    }
                                }
                            }
                        }
                        if(!!$datay['unitid']){
                            $y = array_merge($datax,$datay);
                            $y['price'] = sprintf('%0.2f',round($y['amount'] / $y['num'],2));
                            $y['amount'] = sprintf('%0.2f',$y['amount']);
                            $data[] = $y;
                        }
                    }
                }
            }
            $totalAllMoney = sprintf('%0.2f',$totalAllMoney);
            return json(['code'=>1,'data'=>['data'=>$data,'type'=>$type,'tam'=>$totalAllMoney],'total'=>$total,'p'=>$p]);
    	}else{
            $totalAllMoney = sprintf('%0.2f',$totalAllMoney);
            return json(['code'=>0,'data'=>['data'=>$data,'type'=>$type,'tam'=>$totalAllMoney],'total'=>$total,'p'=>$p]);
    	}
    }
    private function searchChGoods($input){
    	$guid = isset($input['guid'])?trim($input['guid']):$this->guid;
    	$t = isset($input['t'])?trim($input['t']):'';
    	$p = $input['p'];
    	$limit = $input['limit'];
    	$offset = ($p-1) * $limit;
    	if(empty($t)){
            return json(['code'=>0,'data'=>null]);
    	}
    	//获取这个员工是否有绑定的商品
    	$whereG['g.goods_name'] = ['LIKE','%' . $t . '%'];
    	$whereG['g.seller_note'] = ['LIKE','%' . $t . '%'];
    	$whereBG['bg.guid'] = $guid;
    	$whereBG['bg.gid'] = $this->gid;
    	$whereBG['bg.type'] = 1;
    	$whereB['b.gid'] = $this->gid;
    	$sign = Db::name('bind_goods bg')->where($whereBG)->count();
    	if($sign){
            //说明已经有过绑定商品了
            $total = Db::name('bind_goods bg')
                ->where($whereBG)
                ->join('goods g','bg.goods_id=g.goods_id','LEFT')
                ->join('baojia b','b.goods_id=bg.goods_id','LEFT')
                ->where(function ($query) use($whereG){
                    $query->whereOr($whereG);
                })
                ->where($whereB)
                ->count();
            $bindGoods = Db::name('bind_goods bg')
                ->field('g.goods_id,g.goods_name')
                ->where($whereBG)
                ->join('goods g','bg.goods_id=g.goods_id','LEFT')
                ->join('baojia b','b.goods_id=bg.goods_id','LEFT')
                ->where(function ($query) use($whereG){
                        $query->whereOr($whereG);
                })
                ->where($whereB)
                ->limit($offset,$limit)
                ->select();
            $data = [];
            if(!empty($bindGoods)){
                //处理超长名称
                foreach($bindGoods as $v){
                    //if(mb_strlen($v['goods_name'],'UTF-8') >= 15){
                    //	$v['goods_name'] = mb_substr($v['goods_name'], 0, 15) . '...';
                    //}
                    $data[] = $v;
                }
                return json(['code'=>1,'data'=>$data,'p'=>$p,'total'=>$total, 'msg'=>'数据加载成功！']);
            }else{
                return json(['code'=>0,'data'=>$data,'p'=>$p,'total'=>$total, 'msg'=>'数据加载成功！']);
            }
    	}else{
            //说明这个员工没有绑定任何商品，就去调用组商品
            $total = Db::name('baojia b')
                ->where($whereB)
                ->join('goods g','b.goods_id=g.goods_id','LEFT')
                ->where(function ($query) use($whereG){
                    $query->whereOr($whereG);
                })
                ->count();
            $goods = Db::name('baojia b')
                ->field('g.goods_id,g.goods_name')
                ->where($whereB)
                ->join('goods g','b.goods_id=g.goods_id','LEFT')
                ->where(function ($query) use($whereG){
                    $query->whereOr($whereG);
                })
                ->limit($offset,$limit)
                ->select();
            if(!empty($goods)){
                //处理超长名称
                $data = [];
                foreach($goods as $v){
                    //if(mb_strlen($v['goods_name'],'UTF-8') >= 15){
                    //	$v['goods_name'] = mb_substr($v['goods_name'], 0, 15) . '...';
                    //}
                    $data[] = $v;
                }
                return json(['code'=>1,'data'=>$data,'p'=>$p,'total'=>$total, 'msg'=>'数据加载成功！']);
            }else{
                return json(['code'=>0,'data'=>$data,'p'=>$p,'total'=>$total, 'msg'=>'数据加载成功！']);
            }
    	}
    }
    
    //积分排行
    public function jfph(){
    	$types =Db::name('group_xm_type')->where('gid',0)->where('type',1)->where('status',1)->select();
    	if(!$types) $types='';
    	$this->assign('types',$types);
    	$param = input('param.');
    	$BeginDate=date('Y-m-01', strtotime(date("Y-m-d")));
    	$EndDate=date('Y-m-d', strtotime("$BeginDate +1 month -1 day"));
    	$s = isset($param['s'])?trim($param['s']):$BeginDate;
    	$e = isset($param['e'])?trim($param['e']):$EndDate;
    	$p=input('page')?input('page'):1;
    	$this->assign('s',$s);
    	$this->assign('e',$e);
    	if(request()->isAjax()){
    
    		$limit = isset($param['limit'])?$param['limit']:$this->rows;
    		$where['gid'] = session('gid');
    		$where['status'] = 1;
    		if(isset($param['keyword']) && !empty($param['keyword'])){
    			$where['xm_title'] =  ['like', '%' . $param['keyword'] . '%'];
    		}
    		if (isset($param['xm_type']) && !empty($param['xm_type'])) {
    			$where['xm_type'] =['EQ',$param['xm_type']];
    		}
    		if (isset($param['guid']) && !empty($param['guid'])) {
    			$where['guid'] =['EQ',$param['guid']];
    		}
    
    		if($s || $e){
    			if($s && !$e){
    				$where['create_date'] = ['EGT',$s];
    			}else if(!$s && $e){
    				$where['create_date'] = ['ELT',$e];
    			}else{
    				$where['create_date'] = ['BETWEEN',[$s,$e]];
    			}
    		}
    		//print_r($where);
    		$hj=Db::name('group_xm_points')->where($where)->sum('points');
    		$res = Db::name('group_xm_points')->field('guid,sum(points) points')->where($where)->order('points desc')->group('guid')->paginate($limit);
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
    
    		$status = config('jfz_status');
    		$ii = 1;
    		foreach($data as $key=>$vo){
    			$data[$key]['order'] = ($res->currentPage() - 1) * $res->listRows() + $ii++;
    			if($vo['guid']){
    				$data[$key]['realname'] =Db::name('group_member')->where('uid',$vo['guid'])->where('gid',session('gid'))->value('realname');
    			}else{
    				$data[$key]['realname'] ='';
    			}
    		}
    		$return['data'] = $data;
    		$return['hj'] =$hj;
    		$return['msg'] ='数据加载成功 ！';
    		if(count($res)==0){
    			$return['code'] =0;
    			$return['msg'] ='没有数据！';
    		}
    		return json(['code'=>1,'data'=>$return,'p'=>$p,'total'=>$res->total(), 'msg'=>'数据加载成功！']);
    	}
    }
    
    //我的积分
    public function myjf(){
		if(input('action')=='guangxuanxmlist'){
			$xmlist='';
			$map['gid']=$this->gid;
			$map['type']=5;  //广宣类
		    $map['status']=1;
			if($map['gid']==133){
			$xmlist=Db::name('group_xm')->field('id,title')->where($map)->order('displayorder desc,id desc')->select();
			}
			return json(['code'=>1,'data'=>$xmlist]);
		}
    	return $this->hjjflist($this->guid);
    }
    
    //下属积分
    public function subjf(){
    	if(request()->isAjax()){
	    	$param=input('param.');
	    	if(isset($param['action']) && $param['action']==='getsubgm'){
	    		$this->getsubgm($this->guid);
	    		$subgms = [];
                        if(!empty($this->subgms)){
                            foreach($this->subgms as $v){
                                    //if($v['guid'] != $this->guid){
                                            $subgms[] = $v;
                                    //}
                            }
                        }
	    		return json(['code'=>1,'data'=>$subgms]);
	    	}
	    	$guid = input('guid');
	    	if(!$guid){
                    $this->getsubgm($this->guid);
	            $subgms = [];
                    if(!empty($this->subgms)){
                        foreach($this->subgms as $v){
                            $subgms[] = $v['guid'];
                        }
                        $guid=implode(',',$subgms);
                    }
                }else{
                    $guid = $guid;
                }
	    	return $this->hjjflist($guid);
    	}
    }
    
   //下属查询 
    protected function getsubgm($guid=0){
    	$subgms = Db::name('group_member')
    	->field('uid guid,realname,superid')
    	->where('gid',$this->gid)
    	//->where('uid','NEQ',$guid)
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
    			$query->whereOr('superid',['LIKE','%,' . $guid]);
    		}
    			);
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
    //合计项目积分列表
    private function hjjflist($guid){    	
    	$types =Db::name('group_xm_type')->where('gid',0)->where('type',1)->where('status',1)->select();
    	if(!$types) $types='';
    	$this->assign('types',$types);
    	$param = input('param.');    
    	$BeginDate=date('Y-m-d', strtotime(date("Y-m-d")));
    	$EndDate=date('Y-m-d', strtotime("$BeginDate +1 month -1 day"));
    	$s = $param['s']?trim($param['s']):$BeginDate;
    	$e = $param['e']?trim($param['e']):$EndDate;
    	if(request()->isAjax()){
    		$p = $param['p'];
    		//$limit = isset($param['limit'])?$param['limit']:$this->rows;
    		$limit = 20;
    		$offset = ($p-1) * $limit;
    		$where['gid'] = $this->gid;
    		if(is_numeric($guid)){
    			$where['guid'] = $guid;
    			$groupstr='xm_id,guid';
    			$is_yg=1;
    		}
    		if(is_string($guid)){
    			$where['guid'] = ['in',$guid];
    			$groupstr='xm_id';
    			$is_yg=0;
    		}
    		$where['status'] = 1;
    		if(isset($param['keyword']) && !empty($param['keyword'])){
    			$where['xm_title'] =  ['like', '%' . $param['keyword'] . '%'];
    		}
    		if (isset($param['xm_type']) && !empty($param['xm_type'])) {
    			$where['xm_type'] =['EQ',$param['xm_type']];
    		}
    		if (isset($param['guid']) && !empty($param['guid'])) {
    			$where['guid'] =['EQ',$param['guid']];
    		}
    		if($s || $e){
    			if($s && !$e){
    				$where['create_date'] = ['EGT',$s];
    			}else if(!$s && $e){
    				$where['create_date'] = ['ELT',$e];
    			}else{
    				$where['create_date'] = ['BETWEEN',[$s,$e]];
    			}
    		}
    		$hj=Db::name('group_xm_points')->where($where)->sum('points');
    		$total =Db::name('group_xm_points')->where($where)->group($groupstr)->count();
    		$data = Db::name('group_xm_points')->field('guid,xm_type,xm_title,unit,create_date,xm_id,unitid,unitgid,status,goods_id,hs_id,score,SUM(num) num,SUM(points) points')->where($where)->order('status desc,id desc')
    		->group($groupstr)
    		->limit($offset,$limit)
            ->select();
    
    		$status = config('jfz_status');
    		$ii=1;
    		
    		foreach($data as $key=>$vo){
    			$data[$key]['order'] = ($p - 1) * $limit + $ii++;
    			$xmtype=Db::name('group_xm_type')->where('id',$vo['xm_type'])->field('title,remark')->find();
    			$xminfo=Db::name('group_xm')->where('id',$vo['xm_id'])->where('gid',$this->gid)->find();
    			$data[$key]['type_title']=$xmtype['title'];
    			$data[$key]['score']=floatval($data[$key]['score']);
    			$data[$key]['num'] = floatval($data[$key]['num']);
    			
    			if($vo['unitid']){
    				$data[$key]['unit_name'] =Db::name('unit')->where('id',$vo['unitid'])->value('uname');
    				if(trim($xminfo['rule_op_min'])=='eq') $data[$key]['rule_op_min']='=';
    				if(trim($xminfo['rule_op_min'])=='egt') $data[$key]['rule_op_min']='>=';
    			}
    			if($vo['goods_id']){
    				$goodslist = explode(',',$vo['goods_id']);
    				$goods_title='';
    				foreach ($goodslist as $goods){
    					$goods_title.=Db::name('goods')->where('goods_id',$goods)->value('goods_name').'<br>';
    				}
    				$data[$key]['goods_title'] =$goods_title;
    			}else{
    				$data[$key]['goods_title'] ='';
    			}
    
    			if($vo['hs_id']){
    				$hslist = explode(',',$vo['hs_id']);
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
    			if($vo['guid']){
    				$data[$key]['realname'] =Db::name('group_member')->where('uid',$vo['guid'])->where('gid',session('gid'))->value('realname');
    			}else{
    				$data[$key]['realname'] ='';
    			}
    			$t=0;
    			if($data[$key]['unitgid']&&$data[$key]['xm_type']!=1){
	    			if(isset($this->jf_unitg[$data[$key]['unitgid']])){
	    				$unitginfo = $this->jf_unitg[$data[$key]['unitgid']];
	    			}else{
	    				$unitginfo = $this->jf_unitg[$data[$key]['unitgid']] = Db::name('unit_group')->find($data[$key]['unitgid']);
	    			}
	    			if($unitginfo['minunitid'] === $data[$key]['unitid']){
	    				$t = $data[$key]['num'];
	    			}else{
	    				if(!isset($this->jf_unit[$data[$key]['unitid']])){
	    					$this->jf_unit[$data[$key]['unitid']] = Db::name('unit')->field('coefficient,uname')->find($data[$key]['unitid']);
	    				}
	    				$t = $data[$key]['num'] * $this->jf_unit[$data[$key]['unitid']]['coefficient'];
	    			}
	    			$desc = $this->unitConvert(['unitgid'=>$data[$key]['unitgid'],'total'=>$t]);
    			}else{
    				$desc=$data[$key]['num'].$data[$key]['unit'];
    			}
    			$data[$key]['zhnum']=$desc;
    		}
    		$return['data'] = $data;
    		$return['hj'] =$hj;
    		$return['is_yg'] =$is_yg;
    		if(count($data)==0){
    			$re['code'] =0;
    			$re['total'] =0;
    			$re['p'] =$p;
    			$return['hj'] =$hj?$hj:0;
    			$re['data'] =$return;
    			$re['msg'] ='没有数据！';
    			return json($re);
    		}else{
    		return json(['code'=>1,'data'=>$return,'p'=>$p,'total'=>$total, 'msg'=>'数据加载成功！']);
    		}
    	}
    }
    
    private function unitConvert($conditions = []){
    	if(empty($conditions) || !isset($conditions['unitgid']) || !isset($conditions['total'])){
    		return '';
    	}else{
    		//去查询最大报价单位信息
    		$total = $conditions['total'];
    		$unitgid = $conditions['unitgid'];
    		$desc = '';
    		if($total > 0 ){
    			if(!isset($this->units[$unitgid])){
    				$whereU['unitgid'] = $unitgid;
    				$whereU['status'] = 1;
    				$this->units[$unitgid] = Db::name('unit')->field('coefficient,uname')->where($whereU)->order('coefficient DESC')->select();
    			}
    			if(isset($this->units[$unitgid]) && !empty($this->units[$unitgid])){
    				$units = $this->units[$unitgid];
    				for($i=0;$i<count($units);$i++){
    					if($units[$i]['coefficient'] > $total){
    						//$desc .= '0'.$units[$i]['uname'];
    						continue;
    					}else{
    						$x = floor($total / $units[$i]['coefficient']);
    						$total = $total - $x * $units[$i]['coefficient'];
    						$desc .= $x . $units[$i]['uname'];
    					}
    					if($x === 0){
    						break;
    					}
    				}
    				return $desc;
    			}else{
    				return '';
    			}
    		}else{
    			return $desc;
    		}
    	}
    }
    //积分列表
    private function jflist($guid){
    	$types =Db::name('group_xm_type')->where('gid',0)->where('type',1)->where('status',1)->select();
    	if(!$types) $types='';
    	$this->assign('types',$types);
    	$param = input('param.');
    	$BeginDate=date('Y-m-01', strtotime(date("Y-m-d")));
    	$EndDate=date('Y-m-d', strtotime("$BeginDate +1 month -1 day"));
    	$s = isset($param['s'])?trim($param['s']):$BeginDate;
    	$e = isset($param['e'])?trim($param['e']):$EndDate;
    	$this->assign('s',$s);
    	$this->assign('e',$e);
    	if(request()->isAjax()){
    		$p = $param['p'];
    		$limit = isset($param['limit'])?$param['limit']:$this->rows;
    		$offset = ($p-1) * $limit;
    		$where['gid'] = $this->gid;
    		if(is_numeric($guid)){
    			$where['guid'] = $guid;
    		}
    		if(is_string($guid)){
    			$where['guid'] = ['in',$guid];
    		}
    		$where['status'] = 1;
    		if(isset($param['keyword']) && !empty($param['keyword'])){
    			$where['xm_title'] =  ['like', '%' . $param['keyword'] . '%'];
    		}
    		if (isset($param['xm_type']) && !empty($param['xm_type'])) {
    			$where['xm_type'] =['EQ',$param['xm_type']];
    		}
    		if (isset($param['guid']) && !empty($param['guid'])) {
    			$where['guid'] =['EQ',$param['guid']];
    		}
    		if($s || $e){
    			if($s && !$e){
    				$where['create_date'] = ['EGT',$s];
    			}else if(!$s && $e){
    				$where['create_date'] = ['ELT',$e];
    			}else{
    				$where['create_date'] = ['BETWEEN',[$s,$e]];
    			}
    		}
    		$hj=Db::name('group_xm_points')->where($where)->sum('points');
    		$total =Db::name('group_xm_points')->where($where)->count();
    		$data = Db::name('group_xm_points')->where($where)->order('status desc,id desc')
    		->limit($offset,$limit)
    		->select();
    
    		$status = config('jfz_status');
    		$ii=1;
    		foreach($data as $key=>$vo){
    			$data[$key]['order'] = ($p - 1) * $limit + $ii++;
    			$xmtype=Db::name('group_xm_type')->where('id',$vo['xm_type'])->field('title,remark')->find();
    			$xminfo=Db::name('group_xm')->where('id',$vo['xm_id'])->where('gid',$this->gid)->find();
    			$data[$key]['type_title']=$xmtype['title'];
    			$data[$key]['type_xm']=$xmtype['remark'];
    			$data[$key]['status_txt'] = $status[$vo['status']];
    			if($vo['unitid']){
    				$data[$key]['unit_name'] =Db::name('unit')->where('id',$vo['unitid'])->value('uname');
    				if(trim($xminfo['rule_op_min'])=='eq') $data[$key]['rule_op_min']='=';
    				if(trim($xminfo['rule_op_min'])=='egt') $data[$key]['rule_op_min']='>=';
    			}
    			if($vo['goods_id']){
    				$goodslist = explode(',',$vo['goods_id']);
    				$goods_title='';
    				foreach ($goodslist as $goods){
    					$goods_title.=Db::name('goods')->where('goods_id',$goods)->value('goods_name').'<br>';
    				}
    				$data[$key]['goods_title'] =$goods_title;
    			}else{
    				$data[$key]['goods_title'] ='';
    			}
    
    			if($vo['hs_id']){
    				$hslist = explode(',',$vo['hs_id']);
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
    			if($vo['guid']){
    				$data[$key]['realname'] =Db::name('group_member')->where('uid',$vo['guid'])->where('gid',session('gid'))->value('realname');
    			}else{
    				$data[$key]['realname'] ='';
    			}
    		}
    		$return['data'] = $data;
    		$return['hj'] =$hj;
    		$return['msg'] ='数据加载成功 ！';
    		if(count($res)==0){
    			$return['code'] =0;
    			$return['msg'] ='没有数据！';
    		}
    		$this->assign('userslist',$data);
    		$this->assign('hj',$hj);
    		$this->assign('pageInfo',$pageInfo);
    		return json(['code'=>1,'data'=>$return,'p'=>$p,'total'=>$total, 'msg'=>'数据加载成功！']);
    	}
    }
    private function setInterval($param){
    	//时间以csdate,cedate为准
    	$s = str_replace('T','',trim($param['s']));
    	$e = str_replace('T','',trim($param['e']));
    	$n = date('Y-m-d H:i:s');
    	$s = !!$s ? $s : date('Y-m-d');
    	$e = !!$e ? $e : $n;
    	$start = strtotime($s);
    	$stop = strtotime($e);
    	return ['start'=>$start,'stop'=>$stop];
    }
}
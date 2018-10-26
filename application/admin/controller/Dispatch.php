<?php
/**
 * Description of qlopc
 *
 * @author JL
 */
namespace app\admin\controller;
use think\Db;

/*
 *  XXX::  配送提成规则,配送组,配送提成审核
 *      dg = ljk_dispatch_group
 *      dgu = groupu
 *      dri = rule
 *      dru = ruleu
 *      dgn = gain
 */
class Dispatch extends Base{
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
    public function index(){
        $this->redirect('/admin/dispatch/group');
    }
    private function reTip( $msg, $autoclose_sign = true, $refresh_sign = false ){
        $arrtips['msg'] = $msg;
        $arrtips['autoclose_sign'] = $autoclose_sign;
        $arrtips['refresh_sign'] = $refresh_sign;
        return $this->tips($arrtips,false);
    }
    //返回二维数据的差集
    private function array_diff2($array, $array2 ){
        $reArr = [];
        foreach($array as $value) {
            if(!in_array($value,$array2)){
                $reArr[] = $value;
            }
        }
        return $reArr;
    }
    public function rule(){
    	if(request()->isPost()){
            $input = input('post.');
            $action = '';
            if(isset($input['action'])){
                $action = $input['action'];
                unset($input['action']);
            }
            switch($action){
                case 'showlist':return $this->ruleShowlist($input);
                case 'add':return $this->ruleAdd($input);
                case 'edit':return $this->ruleEdit($input);
                case 'del':return $this->ruleDel($input);
                case 'goods':return $this->ruleGoods($input);
                default:return null ;
            }
    	}else{
            $goodsList = Db::name('baojia')->where([ 'gid' => $this->gid ])->select();
            $this->assign( 'goodsList', $goodsList);
            return $this->fetch('/dispatch/rule_index');
    	}
    }
    private function ruleGoods( $input = [] ){
        try {
            $whereA['gid'] = $this->gid;
            $whereA['goodsName'] = [ 'LIKE', "%{$input['word']}%" ];
            $dataRe = Db::name('baojia')->where($whereA)->page($input['page'])->paginate( 15 );
            return json([ 'code' => 1, 'data' => $dataRe->items(), 'pageInfo' => pageInfo($dataRe) ]);
        } catch (Exception $exc) {
            return json([ 'code' => -1, 'msg' => '获取失败，错误信息：' . $exc->getMessage() ]);
        }
    }
    private function ruleShowlist( $input = [] ){
        $dr = Db::name('dispatch_rule');
        $whereA['gid'] = $this->gid;
        $whereA['delete'] = 0;
        if ( !empty($input['sdate']) ){
            $whereA['ctime'] =[ 'BETWEEN', [strtotime($input['sdate']),strtotime($input['edate'])]];
        }
        if( !empty($input['search_type']) ){
            if( $input['search_type'] == 1 ){
                $whereA['title'] = [ 'LIKE', '%'.$input['keywords'].'%'];
            }else if( $input['search_type'] == 2 ){
                $whereBj['gid'] = $this->gid;
                $whereBj['goodsName'] = [ 'LIKE', '%'.$input['keywords'].'%'];
                $bjIds = Db::name('baojia')->where($whereBj)->column('id');
                $dr->where(function ($query) use($bjIds){
                    foreach($bjIds as $bjId){
                        $query->whereOr("FIND_IN_SET('{$bjId}',`baojia_ids`)");
                    }
                });
            }
        }
        $dr->where($whereA);
        $res = $dr->order(['ctime' => 'DESC'])->paginate($this->rows);
        $items = $res->items();
        $disData = [];
        foreach($items as $item){
            $item['rules'] = Db::name('dispatch_ruleu')
                    ->where([ 'gid' => $this->gid, 'drid' => $item['id'] ])
                    ->order([ 'min' => 'ASC'])
                    ->select();
            if(!!strlen(trim($item['baojia_ids'],','))){
                $item['baojia_ids'] = explode( ',', trim($item['baojia_ids'],','));
                foreach($item['baojia_ids'] as $ind => $val ){
                    if(empty($val) ){continue; }
                    if(empty($this->bjname[$val] ) ){
                        $this->bjname[$val] = Db::name('baojia')->where([ 'gid' => $this->gid, 'id' => $val ])->value('goodsName');
                    }
                    $bj['id'] = $val;
                    $bj['goodsName'] = $this->bjname[$val];
                    $item['baojia_ids'][$ind] = $bj;
                }
                $disData[] = $item;
            }
        }
        $this->assign( 'disData', $disData);
        $this->assign( 'pageInfo', pageInfo($res) );
        return $this->fetch('/dispatch/ajax/rule_lists');
    }
    private function ruleAdd( $input = [] ){
        // 验证规则
        $rule = [
            ['title', 'require|min:1|max:32', '规则标题不能为空！|规则标题最低一个字！|规则标题最多32个字！'],
            ['umin', 'require', '规则不能为空！'],
        ];
        $result = $this->validate( $input, $rule );
        if( $result !== true ){
            $arrtips['msg'] = $result;
            $arrtips['autoclose_sign'] = true;
            $arrtips['close'] = ['sign' => false];
            return $this->tips($arrtips);
        }
        Db::startTrans();
        try {
            //  配送组配置
            $whereA['gid'] = $this->gid;
            $whereA['title'] = $input['title'];
            $whereA['delete'] = 0;
            if( !empty( Db::name('dispatch_rule')->where($whereA)->find() ) ){
                $arrtips['msg'] = '已有相同标题的提成规则！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                return $this->tips($arrtips);
            }
            //  180905 JL:: 判断商品是否存在其他规则中
            if( array_key_exists( 'baojia_id',$input ) ){
                $whereRex['gid'] = $this->gid;
                $whereRex['delete'] = 0;
                //$whereRex['id'] = [ 'neq', $input['editid'] ];
                foreach( $input['baojia_id'] as $val2 ){
                    $drEx = Db::name('dispatch_rule')->where($whereRex)->where("FIND_IN_SET( {$val2}, baojia_ids)")->find();
                    if(!empty($drEx)){
                        $goodsName = Db::name('baojia')->where([ 'gid' => $this->gid, 'id' => $val2 ])->value('goodsName');
                        $arrtips['msg'] = "“{$goodsName}”已在“{$drEx['title']}”规则中，不能再次添加到当前规则中！";;
                        $arrtips['autoclose_sign'] = true;
                        $arrtips['close'] = ['sign' => false];
                        return $this->tips($arrtips);
                    }
                }
                $disData['baojia_ids'] = implode( ',', $input['baojia_id']);
            }else{
                $disData['baojia_ids'] = "";
            }
            $disData['gid'] = $this->gid;
            $disData['guid'] = $this->guid;
            $disData['title'] = $input['title'];
            $disData['delete'] = 0;
            $disData['baojia_ids'] = (!empty($input['baojia_id'])) ? implode( ',', $input['baojia_id']) : '';
            $disData['ctime'] = time();
            $disData['status'] = ( $input['status'] == 1) ? 1: 0;
            $drid = Db::name('dispatch_rule')->insertGetId( $disData );
            $uindexs = $input['uindex'];
            $umins = $input['umin'];
            $umaxs = $input['umax'];
            $utotals = $input['utotal'];
            $disRule = [];
            foreach( $uindexs as $ind){
                $item['gid'] = $this->gid;
                $item['drid'] = $drid;
                $item['min'] = $umins[$ind];
                $item['max'] = $umaxs[$ind];
                $item['total'] = $utotals[$ind];
                $disRule[$ind] = $item;
            }
            Db::name('dispatch_ruleu')->insertAll($disRule);
            Db::commit();
            $arrtips['msg'] = '添加成功！';
            $arrtips['autoclose_sign'] = true;
            $arrtips['close'] = ['sign' => false];
            $arrtips['next_action'] = "setTimeout(function(){getList({sign:'dispatchRule'});$('#modalBackdropBox').html('');$('#modalBackdropBox').hide();},1500)";
            return $this->tips($arrtips,false);
        } catch (\Exception $e) {
            Db::rollback();
            $arrtips['msg'] = '系统繁忙，请稍后再试！';
            $arrtips['autoclose_sign'] = true;
            $arrtips['close'] = ['sign' => false];
            return $this->tips($arrtips);
        }
    }
    private function ruleEdit( $input = [] ){
        if( isset($input['editid']) ){
            // 验证规则
            $rule = [
                ['title', 'require|min:1|max:32', '规则标题不能为空！|规则标题最低一个字！|规则标题最多32个字！'],
                ['umin', 'require', '规则不能为空！'],
            ];
            $result = $this->validate( $input, $rule );
            if( $result !== true ){
                return $this->reTip( '添加错误：' . $result );
            }
            Db::startTrans();
            try {
                //  配送组配置
                $input['editid'] = intval($input['editid']);
                //  180905 JL:: 判断商品是否存在其他规则中
                if( array_key_exists( 'baojia_id',$input ) ){
                    $whereRex['gid'] = $this->gid;
                    $whereRex['delete'] = 0;
                    $whereRex['id'] = [ 'neq', $input['editid'] ];
                    foreach( $input['baojia_id'] as $val2 ){
                        $drEx = Db::name('dispatch_rule')->where($whereRex)->where("FIND_IN_SET( {$val2}, baojia_ids)")->find();
                        if( !empty($drEx) ){
                            $goodsName = Db::name('baojia')->where([ 'gid' => $this->gid, 'id' => $val2 ])->value('goodsName');
                            $msgStr = "“{$goodsName}”已在“{$drEx['title']}”规则中，不能再次添加到当前规则中！";
                            return $this->reTip( $msgStr, false, false );
                        }
                    }
                    $disData['baojia_ids'] = implode( ',', $input['baojia_id']);
                }else{
                    $disData['baojia_ids'] = "";
                }
                $whereA['gid'] = $this->gid;
                $whereA['id'] = $input['editid'];
                $disData['gid'] = $this->gid;
                $disData['guid'] = $this->guid;
                $disData['title'] = $input['title'];
                $disData['delete'] = 0;
                $disData['baojia_ids'] = (!empty($input['baojia_id'])) ? implode( ',', $input['baojia_id']) : '';
                //$disData['ctime'] = time();
                $disData['status'] = ( $input['status'] == 1) ? 1: 0;
                Db::name('dispatch_rule')->where( $whereA )->update( $disData );
                
                $uindexs = $input['uindex'];
                $umins = $input['umin'];
                $umaxs = $input['umax'];
                $utotals = $input['utotal'];
                $disRule = [];
                foreach( $uindexs as $uval){
                    $item['gid'] = $this->gid;
                    $item['drid'] = $input['editid'];
                    $item['min'] = $umins[$uval];
                    $item['max'] = $umaxs[$uval];
                    $item['total'] = $utotals[$uval];
                    $disRule[$uval] = $item;
                }
                $whereDru['gid'] = $this->gid;
                $whereDru['drid'] = $input['editid'];
                $dbRuleAll = Db::name('dispatch_ruleu')->where($whereDru)->field('gid,drid,min,max,total')->select();
                $ruleDel = $this->array_diff2( $dbRuleAll, $disRule);
                $ruleAdd = $this->array_diff2( $disRule, $dbRuleAll);
                if( !empty($ruleDel) ){
                    foreach ( $ruleDel as $ruval ){
                        Db::name('dispatch_ruleu')->where($ruval)->delete();
                    }
                }
                if( !empty($ruleAdd) ){
                    Db::name('dispatch_ruleu')->insertAll($ruleAdd);
                }
                Db::commit();
                $arrtips['msg'] = '规则编辑成功！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                $arrtips['next_action'] = "setTimeout(function(){getList({sign:'dispatchRule'});$('#modalBackdropBox').html('');$('#modalBackdropBox').hide();},1500)";
                return $this->tips($arrtips,false);
            } catch (\Exception $exc) {
                Db::rollback();
                return $this->reTip( '编辑错误：' . $exc->getMessage(), false, false );
            }
        }else{
            $whereA['gid'] = $this->gid;
            $whereA['id'] = $input['id'];
            $ruleInfo = Db::name('dispatch_rule')->where($whereA)->find();
            unset( $whereA['id'] );
            $whereA['drid'] = $input['id'];
            $ruleInfo['rules'] = Db::name('dispatch_ruleu')->where($whereA)->order([ 'min' => 'ASC' ])->select();
            if( !empty($ruleInfo['baojia_ids']) ){
                $ruleInfo['baojia_ids'] = explode( ',', $ruleInfo['baojia_ids']);
                foreach ( $ruleInfo['baojia_ids'] as $ind => $val ){
                    if( empty($val) ){ continue; }
                    $bj['id'] = $val;
                    $bj['goodsName'] = Db::name('baojia')
                            ->where([ 'gid' => $this->gid, 'id' => $bj['id'] ])
                            ->value('goodsName');
                    $ruleInfo['baojia_ids'][$ind] = $bj;
                }
            }
            $ruleInfo['action'] = 'edit';
            return json([ 'code' => 1 , 'data' => $ruleInfo ]);
        }
    }
    private function ruleDel( $input = [] ){
        try {
            $whereA['gid'] = $this->gid;
            $whereA['id'] = $input['id'];
            Db::name('dispatch_rule')->where($whereA)->update([ 'delete' => 1 ]);
            $arrtips['msg'] = '删除成功！';
            $arrtips['autoclose_sign'] = true;
            $arrtips['next_action'] = "getList({sign:'dispatchRule'});";
            return $this->tips($arrtips,false);
        } catch (Exception $exc) {
            return $this->reTip( '删除失败！' . $exc->getMessage() , true, true );
        }
    }
    /*
     *  TODO::  配送组管理
     */
    public function group(){
    	if(request()->isPost()){
            $input = input('post.');
            $action = '';
            if(isset($input['action'])){
                $action = $input['action'];
                unset($input['action']);
            }
            switch($action){
                case 'showlist':return $this->groupShowlist($input);
                case 'add':return $this->groupAdd($input);
                case 'edit':return $this->groupEdit($input);
                case 'del':return $this->groupDel($input);
                default:return null ;
            }
    	}else{
            return $this->fetch('/dispatch/group_index');
    	}
    }
    private function groupShowlist( $input = [] ){
        $whereA['dg.gid'] = $this->gid;
        $whereA['dg.delete'] = 0;
        if ( !empty($input['sdate']) ){
            $whereA['dg.ctime'] = [
                [ 'egt', strtotime($input['sdate'])],
                [ 'elt', strtotime($input['edate'])]
            ];
        }
        if( !empty($input['search_type']) ){
            if( $input['search_type'] == 1 ){
                $whereA['dg.title'] = [ 'LIKE', '%'.$input['keywords'].'%'];
            }else if( $input['search_type'] == 2 ){
                $whereA['dgu.t_name'] = [ 'LIKE', '%'.$input['keywords'].'%'];
            }
        }
        $res = Db::name('dispatch_group dg')
                ->join( 'dispatch_groupu dgu', 'dgu.dgid = dg.id', 'LEFT')
                ->where($whereA)
                ->order([ 'dg.ctime' => 'DESC' ])
                ->field('dg.*')
                ->group('dg.id')
                ->paginate( $this->rows );
        $items = $res->items();
        $disData  = [];
        foreach($items as $item){
            $item['car'] = Db::name('carm')
                ->where([ 'gid' => $this->gid, 'id' => $item['carid'] ])
                ->find();
            $item['t_list'] = Db::name('dispatch_groupu')
                ->where([ 'gid' => $this->gid, 'dgid' => $item['id'] ])
                ->order([ 'id' => 'ASC' ])
                ->select();
            foreach( $item['t_list'] as $ind => $val ){
                if($val['type'] == 0 ){//账号
                    if(!strlen(trim($val['t_name']))){
                        $whereGm['gid'] = $this->gid;
                        $whereGm['uid'] = $val['t_guid'];
                        if(empty($this->guidname[$val['t_guid']]) ){
                            $this->guidname[$val['t_guid']] = Db::name('group_member')
                                ->where($whereGm)
                                ->value('realname');
                        }
                        $item['t_list'][$ind]['t_name'] = $this->guidname[$val['t_guid']];
                    }
                }else{//记名
                    $item['t_list'][$ind]['t_name'] = $val['t_name'];
                }
            }
            $disData[] = $item;
        }
        $this->assign( 'disData', $disData);
        $this->assign( 'pageInfo', pageInfo($res) );
        return $this->fetch('/dispatch/ajax/group_lists');
    }
    private function groupAdd( $input = [] ){
        // 验证规则
        $rule = [
            ['title', 'require|min:1|max:32', '标题不能为空！|规则标题最低一个字！|规则标题最多32个字！'],
            ['status', 'between:0,1', '状态错误！'],
        ];
        $result = $this->validate( $input, $rule );
        if( $result !== true ){
            $arrtips['msg'] = $result;
            $arrtips['autoclose_sign'] = true;
            $arrtips['close'] = ['sign' => false];
            return $this->tips($arrtips);
        }
        Db::startTrans();
        try {
            //  配送组配置
            $dataDgr['gid'] = $this->gid;
            $dataDgr['title'] = $input['title'];
            $dataDgr['delete'] = 0;
            if( !empty( Db::name('dispatch_group')->where($dataDgr)->find() ) ){
                $arrtips['msg'] = '已有相同标题的配送组！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                return $this->tips($arrtips);
            }
            //  车辆判断，不能使用情况
            $carInfo = Db::name('carm')->where([ 'gid' => $this->gid, 'id' => $input['carid'] ])->find();
            if( $carInfo['state'] != 0 && $carInfo['state'] != 1 ){
                switch ($carInfo['state']) {
                    case 2: $stateStr = '正在维修中';break;
                    case 3: $stateStr = '已经报废';break;
                    case 4: $stateStr = '正在使用中';break;
                }
                $arrtips['msg'] = $carInfo['cartype'] . $carInfo['carnum'] . ' 不能使用，因为该车' . $stateStr;
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                return $this->tips($arrtips);
            }
            //Db::name('carm')->where([ 'gid' => $this->gid, 'id' => $input['carid'] ])->update([ 'state' => 4 ]);
            $dataDgr['carid']   = (isset($input['carid']) ) ? $input['carid'] : 0;
            $dataDgr['guid'] = $this->guid;
            $dataDgr['carid'] = (isset($input['carid']) ) ? $input['carid'] : 0;
            $dataDgr['ctime'] = time();
            $dgid = Db::name('dispatch_group')->insertGetId($dataDgr);	
            //  180906 JL::	添加到申请中
            $input['editid'] = $dgid;
            $res = $this->groupStaffChange($input,$dataDgr);
            if( $res !== true ){
                return $res;
            }
            Db::commit();
            $arrtips['msg'] = '添加成功！';
            $arrtips['autoclose_sign'] = true;
            $arrtips['next_action'] = "setTimeout(function (){getList({sign:'dispatchGroup'});$('.modal-close').click();},1000)";
            return $this->tips($arrtips,false);
        } catch (\Exception $exc) {
            Db::rollback();
            $arrtips['msg'] = '系统繁忙，请稍后再试。' .  $exc->getMessage();
            $arrtips['autoclose_sign'] = true;
            $arrtips['close'] = ['sign' => false];
            return $this->tips($arrtips);
        }
    }
    private function groupEdit( $input = [] ){
        if(empty($input['editid'])){
            $guids = [];
            if(!empty($input['id']) ){
                $whereA['gid'] = $this->gid;
                $whereA['id'] = $input['id'];
                $whereA['delete'] = 0;
                $groupInfo = Db::name('dispatch_group')->where($whereA)->find();
                if(!!$groupInfo['carid']){
                    $carid = $groupInfo['carid'];
                }
                unset( $whereA['id'],$whereA['delete']);
                $whereA['dgid'] = $input['id'];
                $groupInfo['groups'] = Db::name('dispatch_groupu')->where($whereA)->order([ 'id' => 'ASC' ])->select();
                foreach ($groupInfo['groups'] as $ind => $val ){
                    if($val['type'] == 0){
                        $guids[] = $val['t_guid'];
                        if(!$val['t_name']){
                            $whereGm['gid'] = $this->gid;
                            $whereGm['uid'] = $val['t_guid'];
                            $val['t_name'] = Db::name('group_member')->where($whereGm)->value('realname');
                            $groupInfo['groups'][$ind] = $val;
                        }
                    }
                }
                $groupInfo['action'] = 'edit';
            }else {
                $groupInfo['action'] = 'add';
            }
            $whereCm['gid'] = $this->gid;
            if(isset($carid)){
                $groupInfo['carList'] = Db::name('carm')->where($whereCm)->where(function ($query) use($carid){
                    $query->whereOr(['id' => $carid,'state' => ['IN',[0,1]]]);
                })->select();
            }else{
                $whereCm['state'] = ['IN',[0,1]];
                $groupInfo['carList'] = Db::name('carm')->where($whereCm)->select();
            }
            //在本组当中，在其他组中没有的或者在本组中有的
            $usedGuids = Db::name('dispatch_groupu')->where(['gid' => $this->gid, 'type' => 0, 't_guid' => ['NOT IN' , $guids]])->group('t_guid')->column('t_guid');
            //获取还没有使用的
            $groupInfo['groupMember']  = Db::name('group_member')
                ->where([ 'gid' => $this->gid,'uid' => ['NOT IN',$usedGuids]])
                ->field('uid,gid,realname')
                ->select();
            return json([ 'code' => 1 , 'data' => $groupInfo ]);
        }else{
            //验证规则
            $rule = [
                ['title', 'require|min:1|max:32', '标题不能为空！|规则标题最低一个字！|规则标题最多32个字！'],
                ['status', 'between:0,1', '状态错误！'],
            ];
            $result = $this->validate( $input, $rule );
            if( $result !== true ){
                $arrtips['msg'] = $result;
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                return $this->tips($arrtips);
            }
            Db::startTrans();
            try {
                //配送组配置
                $input['editid'] = intval($input['editid']);
                if(!!$input['editid']){
                    $whereDgr['gid'] = $this->gid;
                    $whereDgr['id'] = $input['editid'];
                    $whereDgr['delete'] = 0;
                    $dgrInfo = Db::name('dispatch_group')->where($whereDgr)->find();
                    if(empty($dgrInfo)){
                        $arrtips['msg'] = '该配送小组不存在！';
                        $arrtips['autoclose_sign'] = true;
                        $arrtips['close'] = ['sign' => false];
                        return $this->tips($arrtips,false);
                    }
                }
                //更新数据
                $dataDgr['title']   = $input['title'];
                $dataDgr['ctime']   = time();
                //180906 JL:: 更新成员
                $res = $this->groupStaffChange($input,$dgrInfo);
                if( $res !== true && $dgrInfo['title'] == $input['title']){
                    return $res;
                }
                Db::name('dispatch_group')->where($whereDgr)->update($dataDgr);
                Db::commit();
                $arrtips['msg'] = '编辑成功！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                $arrtips['next_action'] = "setTimeout(function (){getList({sign:'dispatchGroup'});$('.modal-close').click();},1000);";
                return $this->tips($arrtips,false);
            }catch (\Exception $e) {
                Db::rollback();
                $arrtips['msg'] = '系统繁忙，请稍后再试！' . $e->getMessage().$e->getLine();
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                return $this->tips($arrtips,false);
            }
        }
    }
    private function groupDel( $input = [] ){
        try {
            $whereA['gid'] = $this->gid;
            $whereA['id'] = $input['id'];
            Db::name('dispatch_group')->where($whereA)->update([ 'delete' => 1 ]);
            $arrtips['msg'] = '删除成功！';
            $arrtips['autoclose_sign'] = true;
            $arrtips['next_action'] = "getList({sign:'dispatchGroup'});";
            return $this->tips($arrtips,false);
        } catch (Exception $exc) {
            $arrtips['msg'] = '系统繁忙，请稍后再试！';
            $arrtips['autoclose_sign'] = true;
            $arrtips['close'] = ['sign' => false];
            return $this->tips($arrtips);
        }
    }
    /*
     *  TODO::  配送组管理
     */
    public function group_audit(){
    	if(request()->isPost()){
            $input = input('post.');
            $action = '';
            if(isset($input['action'])){
                $action = $input['action'];
                unset($input['action']);
            }
            switch($action){
                case 'showlist':return $this->groupAuditShowlist($input);
                case 'audit_success':return $this->groupAuditSuccess($input);
                case 'audit_vote':return $this->groupAuditVote($input);
                default:return null ;
            }
    	}else{
            return $this->fetch('/dispatch/group_audit_index');
    	}
    }
    private function groupAuditShowlist( $input = [] ){
        $whereA['dga.gid'] = $this->gid;
        isset($input['status']) && in_array(intval($input['status']),[0,1,2,3]) ? $whereA['dga.status'] = intval($input['status']) : null;
        if (!empty($input['sdate']) ){
            $whereA['dga.ctime'] = [
                [ 'egt', strtotime($input['sdate']) ],
                [ 'elt', strtotime($input['edate']) ]
            ];
        }
        if( !empty($input['search_type']) ){
            if( $input['search_type'] == 1 ){
                $whereA['dg.title'] = [ 'LIKE', '%'.$input['keywords'].'%'];
            }else if( $input['search_type'] == 2 ){
                $whereA['dga.changes'] = [ 'LIKE', '%'.$input['keywords'].'%'];
            }
        }
        $res = Db::name('dispatch_group_audit dga')
                ->field('dga.id,dg.title,dga.ctime,dga.changes,dga.guid,dga.status')
                ->join( 'dispatch_group dg', 'dg.id = dga.dgid', 'LEFT')
                ->where($whereA)
                ->order(['dga.ctime' => 'DESC'])
                ->paginate( $this->rows );
        $items = $res->items();
        $disData = [];
        foreach($items as $item){
            if(empty($this->carinfo[$item['new_carid']]) ){
                $this->carinfo[$item['new_carid']] = Db::name('carm')->where([ 'gid' => $this->gid, 'id' => $item['new_carid'] ])->find();
            }
            $item['car'] = $this->carinfo[$item['new_carid']];
            $changes = json_decode($item['changes'],true);
            $item['adds'] = $changes['add'];
            if($item['guid']){
                if(!isset($this->guidName[$item['guid']])){
                    $whereGm['gid'] = $this->gid;
                    $whereGm['uid'] = $item['guid'];
                    $this->guidName[$item['guid']] = (string)Db::name('group_member')->where($whereGm)->value('realname');
                }
                $item['grealname'] = $this->guidName[$item['guid']];
            }else{
                $item['grealname'] = '';
            }
            $item['adds'] = isset($changes['add']) ? $changes['add'] : [];
            $item['dels'] = isset($changes['del']) ? $changes['del'] : [];
            $item['cleader'] = isset($changes['leader']) ? $changes['leader'] : [];
            $ccar = isset($changes['car']) ? $changes['car'] : false;
            if($ccar){
                $item['ccar'] = [];
                if(isset($ccar['old']) && $ccar['old']){
                    $item['ccar']['old'] = Db::name('carm')->field('cartype,carnum')->where(['gid' => $this->gid,'id' => $ccar['old']])->find();
                }
                if(isset($ccar['new']) && $ccar['new']){
                    $item['ccar']['new'] = Db::name('carm')->field('cartype,carnum')->where(['gid' => $this->gid,'id' => $ccar['new']])->find();
                }
            }
            $disData[] = $item;
        }
        $this->assign( 'disData', $disData );
        $this->assign( 'pageInfo', pageInfo($res) );
        return $this->fetch('/dispatch/ajax/group_audit_lists');
    }
    private function groupAuditSuccess( $input = [], $resType = false ){
        Db::startTrans();
        try {
            $whereDga['gid'] = $this->gid;
            $whereDga['id'] = $input['id'];
            $itemInfo = Db::name('dispatch_group_audit')->where($whereDga)->find();
            if (empty($itemInfo)){
                $arrtips['msg'] = '申请不存在！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                return $this->tips($arrtips);
            }else if( $itemInfo['status'] === 1 ){
                $arrtips['msg'] = '申请已通过！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                return $this->tips($arrtips);
            }else if( $itemInfo['status'] === 2 ){
                $arrtips['msg'] = '申请已被否决！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                return $this->tips($arrtips);
            }
            Db::name('dispatch_group_audit')->where($whereDga)->update([ 'aguid' => $this->guid, 'status' => 1 ]);
            $changes = json_decode($itemInfo['changes'],true);
            //	180906	JL::	
            if(!empty($changes) ){
                $delItems = isset($changes['del']) && count($changes['del']) ? $changes['del'] : [];
                $addItems = isset($changes['add']) && count($changes['add']) ? $changes['add'] : [];
                $cleader = isset($changes['leader']) && count($changes['leader']) ? $changes['leader'] : false;
                if(!!$cleader){
                    if(isset($cleader['old'])){
                        $oldLeader = $cleader['old'];
                        unset($oldLeader['t_name']);
                    }else{
                        $oldLeader = false;
                    }
                    $newLeader = $cleader['new'];
                    unset($newLeader['t_name']);
                }else{
                    $newLeader = false;
                    $oldLeader = false;
                }
                $updateLeader = false;
                while(!empty($delItems)){
                    $delItem = array_pop($delItems);
                    if(!empty($addItems)){
                        $addItem = array_pop($addItems);
                        $addItem['leader'] = 0;
                        if($updateLeader === false){
                            if(!!$newLeader && $newLeader['t_guid'] === $addItem['t_guid']){
                                $addItem['leader'] = 1;
                                $updateLeader = true;
                                if(!!$oldLeader){
                                    Db::name('dispatch_groupu')->where($oldLeader)->update(['leader' => 0]);
                                }
                            }
                        }
                        Db::name('dispatch_groupu')->where($delItem)->update($addItem);
                    }else{
                        //删除多余的未被覆盖的delItem
                        Db::name('dispatch_groupu')->where($delItem)->delete();
                    }
                }
                while(!empty($addItems)){
                    $addItem = array_pop($addItems);
                    $addItem['leader'] = 0;
                    if($updateLeader === false){
                        if(!!$newLeader && $newLeader['t_guid'] === $addItem['t_guid']){
                            $addItem['leader'] = 1;
                            $updateLeader = true;
                            if(!!$oldLeader){
                                Db::name('dispatch_groupu')->where($oldLeader)->update(['leader' => 0]);
                            }
                        }
                    }
                    Db::name('dispatch_groupu')->insert($addItem);
                }
                if($updateLeader === false && !!$newLeader){
                    if(!!$oldLeader){
                        Db::name('dispatch_groupu')->where($oldLeader)->update(['leader' => 0]);
                    }
                    Db::name('dispatch_groupu')->where($newLeader)->update(['leader' => 1]);
                }
            }
            //  3-1.车辆判断
            $carChange = isset($changes['car']) && !empty($changes['car']) ? $changes['car'] : false;
            if(!!$carChange){
                if(isset($carChange['new'])){
                    $newCarid = $carChange['new'];
                    $whereCm['gid'] = $this->gid;
                    $whereCm['id'] = $newCarid;
                    $carInfo = Db::name('carm')->where($whereCm)->find();
                    if($carInfo['state'] != 0 && $carInfo['state'] != 1 ){
                        switch ($carInfo['state']) {
                            case 2: $stateStr = '正在维修中';break;
                            case 3: $stateStr = '已经报废';break;
                            case 4: $stateStr = '正在使用中';break;
                        }
                        $arrtips['msg'] = $carInfo['cartype'] . $carInfo['carnum'] . ' 不能使用，因为该车' . $stateStr;
                        $arrtips['autoclose_sign'] = true;
                        $arrtips['close'] = ['sign' => false];
                        return $this->tips($arrtips);
                    }
                    //  新车锁定，原车解锁
                    Db::name('carm')->where([ 'gid' => $this->gid, 'id' => $newCarid ])->update([ 'state' => 4 ]);
                    Db::name('dispatch_group')->where([ 'gid' => $this->gid, 'id' => $itemInfo['dgid'] ])->update([ 'carid' => $newCarid]);
                }
                if(isset($carChange['old'])){
                    $oldCarid = $carChange['old'];
                    Db::name('carm')->where([ 'gid' => $this->gid, 'id' => $oldCarid])->update([ 'state' => 1 ]);
                }
            }
            Db::commit();
            $arrtips['msg'] = '审核“通过”成功！';
            $arrtips['autoclose_sign'] = true;
            $arrtips['next_action'] = "setTimeout(function (){getList({sign:'dispatchGroup_audit'});},1000);";
            $arrtips['close'] = ['sign' => false];
            if($resType){
                return true;
            }
            return $this->tips($arrtips);
        } catch (\Exception $e) {
            Db::rollback();
            return $this->reTip( '系统繁忙，请稍后再试！'. $e->getMessage() .$e->getLine());
        }
    }
    private function groupAuditVote( $input = [] ){
        try {
            $whereDga['gid'] = $this->gid;
            $whereDga['id'] = $input['id'];
            $itemInfo = Db::name('dispatch_group_audit')->where($whereDga)->find();
            if ( empty($itemInfo) ){
                return $this->reTip( '申请不存在！', false, false );
            }else if( $itemInfo['status'] === 1 ){
                return $this->reTip( '申请已通过！', false, false );
            }else if( $itemInfo['status'] === 2 ){
                return $this->reTip( '申请已被否决！', false, false );
            }
            Db::name('dispatch_group_audit')->where($whereDga)->update([ 'status' => 2,'aguid' => $this->guid]);
            $arrtips['msg'] = '“否决”成功！';
            $arrtips['autoclose_sign'] = true;
            $arrtips['next_action'] = "getList({sign:'dispatchGroup_audit'});";
            return $this->tips($arrtips,false);
        } catch (\Exception $exc) {
            return $this->reTip( '审核“否决”失败！错误信息：' . $exc->getMessage() , false, false );
        }
    }
    public function gain(){
    	if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $action = '';
            if(isset($input['action'])){
                $action = $input['action'];
                unset($input['action']);
            }
            switch($action){
                case 'getGainStatics':return $this->getGainStatics($input);
                case 'showlist':return $this->gainShowlist($input);
                case 'auditGain':return $this->auditGain($input);
                case 'adjustGain':
                    $res = $this->adjustGain($input);
                    if($res['code'] === 0){
                        $arrtips['next_action'] = "setTimeout(function (){getList({sign:'dispatchGain'});$('#modalBackdropBox').hide();$('#modalBackdropBox').html('');},1500)";
                    }
                    $arrtips['msg'] = $res['msg'];
                    $arrtips['autoclose_sign'] = true;
                    $arrtips['close'] = ['sign' => false];
                    return $this->tips($arrtips,false);
                case 'getGainDetail': return $this->getGainDetail($input);
                default:return null ;
            }
    	}else{
            return $this->fetch('/dispatch/gain_index');
    	}
    }
    public function gainr(){
    	if(request()->isPost() || (request()->isAjax() && request()->isPost())){
            $input = input('post.');
            $action = '';
            if(isset($input['action'])){
                $action = $input['action'];
                unset($input['action']);
            }
            switch($action){
                case 'showlist':return $this->gainrlist($input);
                case 'exportExcel': return $this->exportGainr($input);
                case 'adjustGain':
                    $res = $this->adjustGain($input);
                    if($res['code'] === 0){
                        $arrtips['next_action'] = "setTimeout(function (){getList({sign:'dispatchGainr'});$('#modalBackdropBox').hide();$('#modalBackdropBox').html('');},1500)";
                    }
                    $arrtips['msg'] = $res['msg'];
                    $arrtips['autoclose_sign'] = true;
                    $arrtips['close'] = ['sign' => false];
                    return $this->tips($arrtips,false);
                default:return null ;
            }
    	}else{
            return $this->fetch('/dispatch/gainr');
    	}
    }
    private function exportGainr($input = []){
        $dgnl = $this->coreGainr($input);
        $rtype = isset($input['rtype']) && strlen(trim($input['rtype'])) ? trim($input['rtype']) : 'water';
        switch ($rtype){
           case 'statics':$res = $this->staticsGainr($dgnl,false);break;
           case 'water':$res = $this->waterGainr($dgnl,false);break;
        }
        return $res['data'];
    }
    private function gainrList($input = []){
        $p = isset($input['page']) && (intval($input['page']) > 0) ?  intval($input['page'])  : 1;
        $limit = isset($input['limit']) && (intval(trim($input['limit'])) > 0) ?  intval(trim($input['limit'])) : $this->rows; 
        $dgnl = $this->coreGainr($input);
        $rtype = isset($input['rtype']) && strlen(trim($input['rtype'])) ? trim($input['rtype']) : 'water';
        switch ($rtype){
           case 'statics':$res = $this->staticsGainr($dgnl,['p' => $p, 'limit' => $limit]);$tpl = '/dispatch/ajax/gains';break;
           case 'water':$res = $this->waterGainr($dgnl,['p' => $p, 'limit' => $limit]);$tpl = '/dispatch/ajax/gainr';break;
        }
        $this->assign('pageInfo',$res['pageInfo']);
        $this->assign('data',$res['data']);
        return $this->fetch($tpl);
    }
    private function coreGainr($input = []){
        $dgnl = Db::name('dispatch_gain_log dgnl');
        //获取这个小组所有的申请单信息
        $dgnl->where(['dgnl.gid' => $this->gid]);
        $csdate = isset($input['csdate']) && trim($input['csdate']) ? strtotime(trim($input['csdate'])) : false;
        $cedate = isset($input['cedate']) && trim($input['cedate']) ? strtotime(trim($input['cedate'])) : false;
        if($csdate || $cedate){
            $st = 'ctime';
            if($csdate && $cedate){
                $dgnl->where(['dgnl.' . $st => ['BETWEEN',[$csdate,$cedate]]]);
            }else if($csdate){
                $dgnl->where(['dgnl.' . $st => ['EGT',$csdate]]);
            }else{
                $dgnl->where(['dgnl.' . $st => ['ELT',$cedate]]);
            }
        }
        $keyword = isset($input['keyword']) && strlen(trim($input['keyword'])) ? trim($input['keyword']) : false;
        $matchType = isset($input['matchtype']) ? trim($input['matchtype']) : '';
        if($keyword !== false){
            switch($matchType){
                case 'eq':
                    $dgnl->where(['dgnl.t_name' => ['EQ',$keyword]]);
                    break;//订单号
                case 'like':
                    $dgnl->where(['dgnl.t_name' => ['LIKE','%' . $keyword . '%']]);
                    break;//订单号
                default:null;
            }
        }
        return $dgnl;
    }
    private function staticsGainr($dgnl,$pcfg = false){
        if($pcfg !== false){
            $p = $pcfg['p'];
            $limit = $pcfg['limit'];
        }
        $odgnl['ctime'] = 'DESC';
        if($pcfg === false){
            $data = $dgnl->field('dgnl.t_name,dgnl.type,SUM(`total`) tt')->group('t_name,t_guid,type')->select();
            for($i = 0, $l = count($data); $i < $l; $i++){
                $data[$i] = [
                    $data[$i]['t_name'],
                    $data[$i]['type'] ? '记名添加' : '系统账号', 
                    $data[$i]['tt']
                ];
            }
            $pageInfo = false;
        }else{
            $dgnlt = clone($dgnl);
            $total =  $dgnlt->group('t_name,t_guid,type')->count();
            if($total === 0){
                $pageInfo = [];
                $data = [];
            }else{
                $pg = new \app\admin\event\PageInfo(['p' => $p, 'limit' => $limit, 'total' => $total]);
                $pageInfo = pageInfo($pg);
                $data = $dgnl->field('dgnl.t_name,dgnl.t_guid,dgnl.type,SUM(`total`) tt')->order($odgnl)->group('t_name,t_guid,type')->page($p,$limit)->select();
            }
        }
        return ['data' => $data,'pageInfo' => $pageInfo];
    }
    private function waterGainr($dgnl,$pcfg = false){
        if($pcfg !== false){
            $p = $pcfg['p'];
            $limit = $pcfg['limit'];
        }
        $odgnl['ctime'] = 'DESC';
        if($pcfg === false){
            $data = $dgnl->field('dgnl.*')->order($odgnl)->select();
            $pageInfo = false;
        }else{
            $dgnlt = clone($dgnl);
            $total =  $dgnlt->count();
            if($total === 0){
                $pageInfo = [];
                $data = [];
            }else{
                $pg = new \app\admin\event\PageInfo(['p' => $p, 'limit' => $limit, 'total' => $total]);
                $pageInfo = pageInfo($pg);
                $data = $dgnl->field('dgnl.*')->order($odgnl)->page($p,$limit)->select();
            }
        }
        $dgnls = [];
        $dgos = [];
        for($i = 0, $l = count($data); $i < $l; $i++){
            $v = $data[$i];
            if(!isset($dgos[$v['dgoid']])){
                $whereDgn['gid'] = $this->gid;
                $whereDgn['status'] = 1;
                $whereDgn['id'] = $v['dgoid'];
                $dgos[$v['dgoid']] =  Db::name('dispatch_gain')->field('dtitle,oid,number')->where($whereDgn)->find();
            }
            if(!empty($dgos[$v['dgoid']])){
                $v['dtitle'] = $dgos[$v['dgoid']]['dtitle'];
                $v['oid'] = $dgos[$v['dgoid']]['oid'];
                $v['number'] = $dgos[$v['dgoid']]['number'];
            }
            $v['getDt'] = date('Y-m-d H:i:s',$v['ctime']);
            $v['mDt'] = date('Y-m-d H:i:s',$v['mtime']);
            if($pcfg === false){
                $dgnls[] = [
                    $v['t_name'],
                    $v['type'] ? '记名添加' : '系统账号',
                    $v['number'],
                    $v['dtitle'],
                    $v['getDt'],
                    $v['mDt'],
                    $v['total']
                ];
            }else{
                $dgnls[] = $v;
            }
        }
        return ['data' => $dgnls,'pageInfo' => $pageInfo];
    }
    private function getGainStatics($input = []){
        $dgn = Db::name('dispatch_gain dgn');
        //获取这个小组所有的申请单信息
        $dgn->where(['dgn.gid' => $this->gid]);
        isset($input['status']) && strlen(trim($input['status'])) && in_array((int)$input['status'],[-1,0,1]) ? $status = (int)$input['status'] : $status = false;
        ($status !== false) ? $dgn->where(['dgn.status' => $status]) : null;
        $csdate = isset($input['csdate']) && trim($input['csdate']) ? strtotime(trim($input['csdate'])) : false;
        $cedate = isset($input['cedate']) && trim($input['cedate']) ? strtotime(trim($input['cedate'])) : false;
        if($csdate || $cedate){
            //$st = $input['sjtype'];
            $st = 'apply_time';
            if($csdate && $cedate){
                $dgn->where(['dgn.'.$st => ['BETWEEN',[$csdate,$cedate]]]);
            }else if($csdate){
                $dgn->where(['dgn.'.$st => ['EGT',$csdate]]);
            }else{
                $dgn->where(['dgn.'.$st => ['ELT',$cedate]]);
            }
        }
        $keyword = isset($input['keyword']) && strlen(trim($input['keyword'])) ? trim($input['keyword']) : false;
        $ktype = isset($input['keytype']) ? trim($input['keytype']) : '';
        if($keyword !== false){
            switch($ktype){
                case 'number':
                    $dgn->where(['dgn.number' => ['LIKE','%' . $keyword . '%']]);
                    break;//订单号
                case 'dtitle':
                    $dgn->where(['dgn.dtitle' => ['LIKE','%' . $keyword . '%']]);
                    break;//订单号
                case 'm':
                    $dgn->where(['dgn.m' => ['LIKE','%' . $keyword . '%']]);
                    break;//订单号
                case 'phone':
                    $dgn->where(['om.phone' => ['LIKE','%' . $keyword . '%']]);
                    break;//收货人电话
                case 'address':
                    $dgn->where(['om.address' => ['LIKE','%' . $keyword . '%']]);
                    break;//收货人地址
                default:null;
            }
            if(in_array($ktype,['address','phone'])){
                $dgn->join('order_member om','om.oid=dgn.oid AND dgn.gid=om.gid','LEFT');
            }
        }
        $s = [];
        $s[] = $dgn->sum('total') + $dgn->where(['status' => 1])->sum('adjust');
        if($status === false){
            $s[] = $dgn->where(['status' => 0])->sum('total');
            $s[] = $dgn->where(['status' => 1])->sum('total') + $dgn->where(['status' => 1])->sum('adjust');
            $s[] = $dgn->where(['status' => -1])->sum('total');
        }else if($status === 0){
            $s[] = $dgn->where(['status' => 0])->sum('total');
            $s[] = 0;
            $s[] = 0;
        }else if($status === 1){
            $s[] = 0;
            $s[] = $dgn->where(['status' => 1])->sum('total') + $dgn->where(['status' => 1])->sum('adjust');
            $s[] = 0;
        }else if($status === -1){
            $s[] = 0;
            $s[] = 0;
            $s[] = $dgn->where(['status' => -1])->sum('total');
        }
        return json($s);
    }
    private function getGainDetail($input = []){
        $dgnid = isset($input['dgnid']) ? intval($input['dgnid']) : false;
        if(!!$dgnid){
            $asts = [
                -1 => '未通过',
                0 => '未审核',
                1 => '已通过'
            ];
            $whereDgn['gid'] = $this->gid;
            $whereDgn['id'] = $dgnid;
            $v = Db::name('dispatch_gain')->where($whereDgn)->find();
            if(!empty($v)){
                $whereGm['gid'] = $this->gid;
                if(!!$v['audit_guid']){
                    $v['auditDt'] = date('Y-m-d H:i:s',$v['audit_time']);
                    $whereGm['uid'] = ['IN', [$v['audit_guid'],$v['apply_guid']]];
                }else{
                    $whereGm['uid'] = $v['apply_guid'];
                }
                $gms = Db::name('group_member')->where($whereGm)->column('uid,realname');
                (!empty($gms) && isset($gms[$v['apply_guid']])) ? $v['apRealname'] = $gms[$v['apply_guid']] : null;
                (!empty($gms) && isset($gms[$v['audit_guid']])) ? $v['auRealname'] = $gms[$v['audit_guid']] : null;
                //获取收货地址信息
                $whereOm['gid'] = $this->gid;
                $whereOm['oid'] = $v['oid'];
                $addr = Db::name('order_member')->where($whereOm)->value('address');
                $v['shaddr'] = preg_replace('/(.+)((市辖区)|(县))(.+)/','$1$5',$addr);
                $dgus = json_decode($v['m'],true);
                if($v['status'] === 1){
                    if(!empty($dgus)){
                        $whereDgl['gid'] = $this->gid;
                        $whereDgl['dgoid'] = $v['id'];
                        $gainLogs = Db::name('dispatch_gain_log')->where($whereDgl)->select();
                        for($gli = 0,$gl = count($gainLogs); $gli < $gl; $gli++){
                            for($d = 0; $d < count($dgus); $d++){
                                if(($gainLogs[$gli]['type'] === $dgus[$d]['type']) && ($gainLogs[$gli]['t_guid'] === $dgus[$d]['t_guid']) && ($gainLogs[$gli]['t_name'] === $dgus[$d]['t_name'])){
                                    $dgus[$d]['total'] = $gainLogs[$gli]['total'];
                                    $dgus[$d]['id'] = $gainLogs[$gli]['id'];
                                }
                            }
                        }
                    }
                }else{
                    if(!empty($dgus)){
                        $ll = count($dgus);
                        $per = floor($v['total'] * 100  / $ll) / 100;
                        $cj = $v['total'] - $per * $ll;
                        for($d = 0; $d < $ll; $d++){
                            if($dgus[$d]['leader'] && ($dgus[$d]['type'] === 0)){
                                $dgus[$d]['total'] = $per + $cj;
                            }else{
                                $dgus[$d]['total'] = $per;
                            }
                        }
                    }
                }
                $v['m'] = $dgus;
                $v['applyDt'] = date('Y-m-d H:i:s',$v['apply_time']);
                $v['sts'] = $asts[$v['status']];
                $v['total'] = sprintf('%0.2f',$v['total']);
                $d = $v['distance'];
                switch($d){
                    case $d > 100000: $v['dw'] = '公里'; $v['clr'] = '#4fb234';$v['dst'] = sprintf('%0.2f',$d / 1000);break;
                    case $d > 10000: $v['dw'] = '公里'; $v['clr'] = '#0fbbff';$v['dst'] = sprintf('%0.2f',$d / 1000);break;
                    case $d > 1000: $v['dw'] = '公里'; $v['clr'] = '#00f';$v['dst'] = sprintf('%0.2f',$d / 1000);break;
                    default:$v['dw'] = '米';$v['clr'] = '#000';$v['dst'] = sprintf('%0.2f',$d / 1);
                }
                //获取提成单商品信息
                $whereDgg['gid'] = $this->gid;
                $whereDgg['oid'] = $v['oid'];
                $whereDgg['dgoid'] = $v['id'];
                $dggs = Db::name('dispatch_gain_goods')->field('total,goodsName,num,unitgid,unit,unitid,cnum')->where($whereDgg)->select();
                $units = [];
                for($i = 0, $l = count($dggs); $i < $l; $i++){
                    $dgg = $dggs[$i];
                    $mnum = $dgg['num'];
                    if(!isset($units[$dgg['unitgid']])){
                        $whereU['unitgid'] = $dgg['unitgid'];
                        $whereU['gid'] = $this->gid;
                        $whereU['status'] = 1;
                        $units[$dgg['unitgid']] = Db::name('unit')->field('coefficient,uname')->where($whereU)->order(['coefficient' => 'DESC'])->select();
                    }
                    $us = $units[$dgg['unitgid']];
                    $onum = [];
                    foreach($us as $u){
                        if($mnum === 0){
                            break;
                        }
                        $c = $u['coefficient'];
                        $n = $mnum / $c;
                        if($n >= 1){
                            $nn = floor($n);
                            $mnum -= $nn * $c;
                            $onum[] = [$nn, $u['uname']];
                        }
                    }
                    $dgg['onum'] = $onum;
                    $dggs[$i] = $dgg;
                }
                $v['dggs'] = $dggs;
                unset($v['apply_time'],$v['distance']);
            }
            return json(['code' => 0,'msg' => '获取成功！', 'data' => $v]);
        }else{
            return json(['code' => -1,'msg' => '请点击具体的订单！']);
        }
    }
    private function auditGain($input = []){
        $dgnid = isset($input['dgnid']) && intval($input['dgnid']) ? intval($input['dgnid']) : false;
        if(!$dgnid){
            
            $arrtips['msg'] = '请选择需操作的配送提成申请单！';
            $arrtips['autoclose_sign'] = true;
            $arrtips['close'] = ['sign' => false];
            return $this->tips($arrtips,false);
        }
        $whereDgn['gid'] = $this->gid;
        $whereDgn['id'] = $dgnid;
        $dgn = Db::name('dispatch_gain')->where($whereDgn)->find();
        if(empty($dgn)){
            $arrtips['msg'] = '该配送提成申请单不存在！';
            $arrtips['autoclose_sign'] = true;
            $arrtips['close'] = ['sign' => false];
            return $this->tips($arrtips,false);
        }else if($dgn['status'] !== 0){
            $arrtips['msg'] = '请勿重复对该配送提成申请单进行审核操作！';
            $arrtips['autoclose_sign'] = true;
            $arrtips['close'] = ['sign' => false];
            return $this->tips($arrtips,false);
        }else{
            $status = isset($input['status']) && intval($input['status']) ? intval($input['status']) : false;
            if(!in_array($status,[-1,1])){
                $arrtips['msg'] = '非法操作！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                return $this->tips($arrtips,false);
            }else if($status === -1){
                Db::name('dispatch_gain')->where($whereDgn)->update(
                    [
                        'status' => -1,
                        'audit_guid' => $this->guid,
                        'audit_time' => time()
                    ]
                );
                $arrtips['msg'] = '操作成功，页面即将刷新！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                $arrtips['next_action'] = "setTimeout(function (){getList({sign:'dispatchGain'});$('#modalBackdropBox').hide();$('#modalBackdropBox').html('');},1500)";
                return $this->tips($arrtips,false);
            }else{
                Db::startTrans();
                try{
                    $input['dgnid'] = $dgnid;
                    $res = $this->doAdjustGain($input);
                    if($res['code'] === 0){
                        $adjust = $res['total'] - $dgn['total'];
                        Db::name('dispatch_gain')->where($whereDgn)->update(
                            [
                                'status' => 1,
                                'audit_guid' => $this->guid,
                                'audit_time' => time(),
                                'adjust' => $adjust
                            ]
                        );
                        Db::commit();
                        $arrtips['msg'] = '操作成功，页面即将刷新！';
                        $arrtips['autoclose_sign'] = true;
                        $arrtips['close'] = ['sign' => false];
                        $arrtips['next_action'] = "setTimeout(function (){getList({sign:'dispatchGain'});$('#modalBackdropBox').hide();$('#modalBackdropBox').html('');},1500)";
                        return $this->tips($arrtips,false);
                    }else{
                        $arrtips['msg'] = $res['msg'];
                        $arrtips['autoclose_sign'] = true;
                        $arrtips['close'] = ['sign' => false];
                        return $this->tips($arrtips,false);
                    }
                }catch(\think\Exception $e){
                    Db::rollback();
                    $arrtips['msg'] = '系统繁忙，请稍后再试！' . $e->getMessage();
                    $arrtips['autoclose_sign'] = true;
                    $arrtips['close'] = ['sign' => false];
                    return $this->tips($arrtips,false);
                }
                
            }
        }
    }
    private function doAdjustGain($input = []){
        $dgoid = $input['dgnid'];
        $items = (array)$input['dguitem'];
        $ids = (array)$input['id'];
        $types = (array)$input['type'];
        $tguids = (array)$input['tguid'];
        $tnames = (array)$input['tname'];
        $totals = (array)$input['total'];
        if(!empty($items)){
            $tt = 0;
            $t = time();
            $idata = [];
            for($i = 0, $l = count($items); $i < $l; $i++){
                $k = $items[$i];
                $id = $ids[$k];
                $tt +=  $totals[$k];
                if(!!$id){
                    $whereDgnl['gid'] = $this->gid;
                    $whereDgnl['dgoid'] = $dgoid;
                    $whereDgnl['id'] = $id;
                    $whereDgnl['type'] = $types[$k];
                    $whereDgnl['t_guid'] = $tguids[$k];
                    Db::name('dispatch_gain_log')->where($whereDgnl)->update(
                        [
                            'total' => $totals[$k],
                            'mtime' => $t,
                            't_name' => $tnames[$k]
                        ]
                    );
                }else{
                    $whereDgnl['gid'] = $this->gid;
                    $whereDgnl['dgoid'] = $dgoid;
                    $whereDgnl['type'] = $types[$k];
                    $whereDgnl['t_guid'] = $tguids[$k];
                    if($types[$k] === 1){
                        $whereDgnl['t_name'] = $tnames[$k];
                    }
                    $id =  Db::name('dispatch_gain_log')->where($whereDgnl)->value('id');
                    if(!!$id){
                        $whereDgnl['gid'] = $this->gid;
                        $whereDgnl['dgoid'] = $dgoid;
                        $whereDgnl['id'] = $id;
                        $whereDgnl['type'] = $types[$k];
                        $whereDgnl['t_guid'] = $tguids[$k];
                        Db::name('dispatch_gain_log')->where($whereDgnl)->update(
                            [
                                'total' => $totals[$k],
                                'mtime' => $t,
                                't_name' => $tnames[$k]
                            ]
                        );
                    }else{
                        $idata[] = [
                            'gid' => $this->gid,
                            'dgoid' => $dgoid,
                            'type' => $types[$k],
                            't_guid' => $tguids[$k],
                            't_name' => $tnames[$k],
                            'total' => $totals[$k],
                            'ctime' => $t,
                            'mtime' => $t
                        ];
                    }
                }
            }
            if(!empty($idata)){
                Db::name('dispatch_gain_log')->insertAll($idata);
            }
            return ['code' => 0, 'total' => $tt, 'msg' => '操作成功！'];
        }else{
            return ['code' => -1, 'msg' => '当前小组无小组成员，无法进行操作！'];
        }
    }
    private function adjustGain($input = []){
        $dgnid = isset($input['dgnid']) && intval($input['dgnid']) ? intval($input['dgnid']) : false;
        if(!$dgnid){
            return ['code' => -1,'msg' => '请选择需操作的配送提成申请单！'];
        }
        $whereDgn['gid'] = $this->gid;
        $whereDgn['id'] = $dgnid;
        $dgn = Db::name('dispatch_gain')->where($whereDgn)->find();
        if(empty($dgn)){
            return ['code' => -1,'msg' => '该配送提成申请单不存在！'];
        }else if($dgn['status'] !== 1){
            return ['code' => -1,'msg' => '请勿对未审核或未通过配送提成申请单进行调整操作！'];
        }else{
            Db::startTrans();
            try{
                $input['dgnid'] = $dgnid;
                $res = $this->doAdjustGain($input);
                if($res['code'] === 0){
                    $adjust = $res['total'] - $dgn['total'];
                    Db::name('dispatch_gain')->where($whereDgn)->update(
                        [
                            'audit_guid' => $this->guid,
                            'audit_time' => time(),
                            'adjust' => $adjust
                        ]
                    );
                    Db::commit();
                    return ['code' => 0,'msg' => '操作成功，页面即将刷新！'];
                }else{
                    return ['code' => -1,'msg' => $res['msg']];
                }
            }catch(\think\Exception $e){
                Db::rollback();
                return ['code' => -1,'msg' => '系统繁忙，请稍后再试！'];
            }
        }
    }
    private function gainShowlist( $input = [] ){
        $p = isset($input['page']) && (intval($input['page']) > 0) ?  intval($input['page'])  : 1;
        $limit = isset($input['limit']) && (intval(trim($input['limit'])) > 0) ?  intval(trim($input['limit'])) : $this->rows; 
        $dgn = Db::name('dispatch_gain dgn');
        //获取这个小组所有的申请单信息
        $dgn->where(['dgn.gid' => $this->gid]);
        isset($input['status']) && strlen(trim($input['status'])) && in_array((int)$input['status'],[-1,0,1]) ? $dgn->where(['dgn.status' => (int)$input['status']]) : null;
        $csdate = isset($input['csdate']) && trim($input['csdate']) ? strtotime(trim($input['csdate'])) : false;
        $cedate = isset($input['cedate']) && trim($input['cedate']) ? strtotime(trim($input['cedate'])) : false;
        if($csdate || $cedate){
            //$st = $input['sjtype'];
            $st = 'apply_time';
            if($csdate && $cedate){
                $dgn->where(['dgn.'.$st => ['BETWEEN',[$csdate,$cedate]]]);
            }else if($csdate){
                $dgn->where(['dgn.'.$st => ['EGT',$csdate]]);
            }else{
                $dgn->where(['dgn.'.$st => ['ELT',$cedate]]);
            }
        }
        $keyword = isset($input['keyword']) && strlen(trim($input['keyword'])) ? trim($input['keyword']) : false;
        $ktype = isset($input['keytype']) ? trim($input['keytype']) : '';
        if($keyword !== false){
            switch($ktype){
                case 'number':
                    $dgn->where(['dgn.number' => ['LIKE','%' . $keyword . '%']]);
                    break;//订单号
                case 'dtitle':
                    $dgn->where(['dgn.dtitle' => ['LIKE','%' . $keyword . '%']]);
                    break;//订单号
                case 'm':
                    $dgn->where(['dgn.m' => ['LIKE','%' . $keyword . '%']]);
                    break;//订单号
                case 'phone':
                    $dgn->where(['om.phone' => ['LIKE','%' . $keyword . '%']]);
                    break;//收货人电话
                case 'address':
                    $dgn->where(['om.address' => ['LIKE','%' . $keyword . '%']]);
                    break;//收货人地址
                default:null;
            }
            if(in_array($ktype,['address','phone'])){
                $dgn->join('order_member om','om.oid=dgn.oid AND dgn.gid=om.gid','LEFT');
            }
        }
        $dgnt = clone($dgn);
        $total =  $dgnt->count();
        if($total === 0){
            $pageInfo = [];
            $dgns = [];
        }else{
            $pg = new \app\admin\event\PageInfo(['p' => $p, 'limit' => $limit, 'total' => $total]);
            $pageInfo = pageInfo($pg);
            $odgn['dgn.apply_time'] = 'DESC';
            $data = $dgn->field('dgn.*')->order($odgn)->page($p,$limit)->select();
            $dgns = [];
            $asts = [
                -1 => '未通过',
                0 => '未审核',
                1 => '已通过'
            ];
            $gms = [];
            $whereGm['gid'] = $this->gid;
            for($i = 0, $l = count($data); $i < $l; $i++){
                $v = $data[$i];
                if(!!$v['audit_guid']){
                    $v['auditDt'] = date('Y-m-d H:i:s',$v['audit_time']);
                    if(!isset($gms[$v['audit_guid']]) && !isset($gms[$v['apply_guid']])){
                        $whereGm['uid'] = ['IN', [$v['audit_guid'],$v['apply_guid']]];
                    }else if(!isset($gms[$v['audit_guid']])){
                        $whereGm['uid'] = ['EQ', $v['audit_guid']];
                    }else if(!isset($gms[$v['apply_guid']])){
                        $whereGm['uid'] = ['EQ', $v['apply_guid']];
                    }
                    $gmxs = Db::name('group_member')->where($whereGm)->column('uid,realname');
                    (!empty($gmxs) && isset($gmxs[$v['apply_guid']])) ? $gms[$v['apply_guid']] = $gmxs[$v['apply_guid']] : null;
                    (!empty($gmxs) && isset($gmxs[$v['audit_guid']])) ? $gms[$v['audit_guid']] = $gmxs[$v['audit_guid']] : null;
                }else if(!isset($gms[$v['apply_guid']])){
                    $whereGm['uid'] = ['EQ', $v['apply_guid']];
                    $gmxs = Db::name('group_member')->where($whereGm)->column('uid,realname');
                    (!empty($gmxs) && isset($gmxs[$v['apply_guid']])) ? $gms[$v['apply_guid']] = $gmxs[$v['apply_guid']] : null;
                }
                (!empty($gms) && isset($gms[$v['apply_guid']])) ? $v['apRealname'] = $gms[$v['apply_guid']] : null;
                (!empty($gms) && isset($gms[$v['audit_guid']])) ? $v['auRealname'] = $gms[$v['audit_guid']] : null;
                $whereOm['gid'] = $this->gid;
                $whereOm['oid'] = $v['oid'];
                $addr = Db::name('order_member')->where($whereOm)->value('address');
                $v['shaddr'] = preg_replace('/(.+)((市辖区)|(县))(.+)/','$1$5',$addr);
                $v['applyDt'] = date('Y-m-d H:i:s',$v['apply_time']);
                $v['sts'] = $asts[$v['status']];
                $v['total'] = sprintf('%0.2f',$v['total']);
                $d = $v['distance'];
                switch($d){
                    case $d > 100000:$v['dw'] = '公里';$v['clr'] = '#4fb234';$v['dst'] = sprintf('%0.2f',$d / 1000);break;
                    case $d > 10000:$v['dw'] = '公里';$v['clr'] = '#0fbbff';$v['dst'] = sprintf('%0.2f',$d / 1000);break;
                    case $d > 1000:$v['dw'] = '公里';$v['clr'] = '#00f';$v['dst'] = sprintf('%0.2f',$d / 1000);break;
                    default:$v['dw'] = '米';$v['clr'] = '#000';$v['dst'] = sprintf('%0.2f',$d / 1);
                }
                unset($v['apply_time'],$v['distance']);
                $dgns[] = $v;
            }
        }
        $this->assign('pageInfo',$pageInfo);
        $this->assign('gainOrders',$dgns);
        return $this->fetch('/dispatch/ajax/gain_lists');
    }

    /**
     * 添加 getdispatchdetail()
     * @return mixed|null|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function group_order(){
    	if(request()->isPost()){
            $input = input('post.');
            $action = '';
            if(isset($input['action'])){
                $action = $input['action'];
                unset($input['action']);
            }
            switch($action){
                case 'showlist':return $this->groupOrderShowlist($input);
                case 'getdispatchdetail':return $this->getgroupdispatchDetail();
                default:return null ;
            }
    	}else{
            $whereDpg['gid'] = $this->gid;
            $whereDpg['delete'] = 0;
            $whereDpg['status'] = 1;
            $dpgs = Db::name('dispatch_group')->field('id,title')->where($whereDpg)->select();
            if(!empty($dpgs)){
                $this->assign('dpgs',$dpgs);
            }
            return $this->fetch('/dispatch/group_order_index');
    	}
    }

    /**
     * 添加 请求显示列表数据
     * @param array $input 接受参数
     * @return mixed
     * @throws \think\exception\DbException
     */
    private function groupOrderShowlist($input = []){
        $odb = [];
        $or = Db::name('order o');
        $or->where([
            'o.gid' => $this->gid,
            'o.ppsid' => 0,
            'o.confirm' => ['NEQ', -1]
        ]);
        if(isset($input['dispatch']) && strlen(trim($input['dispatch'])) && in_array(intval($input['dispatch']),[0,1,2])){
            $dsp = intval($input['dispatch']);
            $or->where(['o.dispatch' => $dsp]);
        }
        if(isset($input['dpgid']) && count($input['dpgid']) && $input['dpgid']!=0) {
            $dpgid = $input['dpgid'];
            $or->where(['o.dpgid' => ['IN', $dpgid]]);
        }else{
            $or->where(['o.dpgid' => ['NEQ',0]]);
        }
        $csdate = isset($input['csdate']) && trim($input['csdate']) ? strtotime(trim($input['csdate'])) : false;
        $cedate = isset($input['cedate']) && trim($input['cedate']) ? strtotime(trim($input['cedate'])) : false;
        if($csdate || $cedate){
            //$st = $input['sjtype'];
            $st = 'adtime';
            if($csdate && $cedate){
                $or->where(['o.'.$st => ['BETWEEN',[$csdate,$cedate]]]);
            }else if($csdate){
                $or->where(['o.'.$st => ['EGT',$csdate]]);
            }else{
                $or->where(['o.'.$st => ['ELT',$cedate]]);
            }
        }
        $keyword = isset($input['keyword']) && strlen(trim($input['keyword'])) ? trim($input['keyword']) : false;
        $ktype = isset($input['keytype']) ? trim($input['keytype']) : '';
        if($keyword !== false){
            switch($ktype){
                case 'number':
                    $or->where(['o.number' => ['LIKE','%' . $keyword . '%']]);
                    break;//订单号
                case 'dgtitle':
                    $or->where(['dg.title' => ['LIKE','%' . $keyword . '%']]);
                    break;//收货人电话
            }
            if(in_array($ktype,['dgtitle'])){
                $or->join('dispatch_group dg', 'o.dpgid=dg.id AND o.gid=dg.gid','LEFT');
            }
        }
        $odb[] = 'o.adtime DESC';
        $odb[] = 'o.oid DESC';
        $odbs = join(',', $odb);
        $res = $or->order($odbs)->paginate($this->rows);
        $pageInfo = pageInfo($res);
        $data = $res->items();
        $orders = [];
        $dpgs = [];
        foreach($data as $vo){
            //对数据进行加工
            $order = $vo;
            if(!!$order['dpgid'] && !isset($dpgs[$order['dpgid']])){
                $whereDpg['gid'] = $this->gid;
                $whereDpg['id'] = $order['dpgid'];
                $dpgs[$order['dpgid']] = Db::name('dispatch_group')->where($whereDpg)->value('title');
            }
            if(!!$order['dpgid']){
                $order['dpgtitle'] = $dpgs[$order['dpgid']];
            }else{
                $order['dpgtitle'] = '';
            }
            $orders[] = $order;
        }
        $this->assign('orders',$orders);
        $this->assign('pageInfo',$pageInfo);
        return $this->fetch('/dispatch/ajax/group_order_lists');
    }
    /**
     * 添加 接受条件 检索order，order_goods得到商品信息数据
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getgroupdispatch($input = []){
        $dpgid=isset($input['dpgid']) ? intval($input['dpgid']) : 0;
        $keyword = isset($input['keyword']) && strlen(trim($input['keyword'])) ? trim($input['keyword']) : false;
        $ktype = isset($input['keytype']) ? trim($input['keytype']) : '';
        $csdate = isset($input['csdate']) && trim($input['csdate']) ? strtotime(trim($input['csdate'])) : false;
        $cedate = isset($input['cedate']) && trim($input['cedate']) ? strtotime(trim($input['cedate'])) : false;

        //统计该小组送货的状态 时间 及 商品信息
        $data=Db::name('dispatch_group')->field('id,title,carid')->where(array('gid'=>$this->gid,'id'=>$dpgid))->find();
        $ch = [];
        $chlist=[];
        $where=[];
        $where['o.gid']=['EQ',$this->gid];
        $where['o.confirm']=['EGT',0];
        $where['og.goodid']=['neq',0];
        $where['og.unitid']=['neq',0];
        $where['og.unitgid']=['neq',0];
        if($dpgid!=0){
            $where['o.dpgid']=['EQ',$dpgid];
        }else{
            $where['o.dpgid']=['NEQ',0];
        }
        if(isset($input['dispatch']) && strlen(trim($input['dispatch'])) && in_array(intval($input['dispatch']),[0,1,2])){
            $dsp = intval($input['dispatch']);
            $str='';
            if($dsp==0){
                $str='未配送';
            }else if($dsp==1){
                $str='配送中';
            }else if($dsp ==2){
                $str='已配送';
            }
            $data['dispatch']=$str;
            $data['sdate']=$csdate;
            $data['edate']=$cedate;
            $where['o.dispatch']=['eq',$dsp];
        }
        if($keyword && $ktype){
            $where['o.'.$ktype]=['LIKE','%' . $keyword . '%'];
        }
        if($csdate || $cedate){
            $where['o.adate']=['BETWEEN',$csdate.','.$cedate];
        }
        $orders = Db::name('order_goods og')
            ->field('og.goodid')
            ->join('ljk_order o', 'og.oid=o.oid', 'LEFT')
            ->where($where)
            ->order('og.goodid desc')
            ->group('og.goodid')
            ->select();
        foreach ($orders as $k=>$v){
            $fie_str='og.id,og.oid,og.goodid,og.num,og.name,og.unitid,og.unitgid';
            $og=Db::name('order_goods og');
            $ogList=$og->join('ljk_order o','o.oid=og.oid','LEFT')
                ->field($fie_str)
                ->where($where)
                ->where('og.goodid','EQ',$v['goodid'])
                ->select();
            foreach ($ogList as $key=>$val){
                $whereUn['id']=$val['unitid'];
                $whereUn['gid']=$this->gid;
                $whereUn['status']=1;
                $units1=Db::name('unit')->field('coefficient')->where($whereUn)->find();
                if (array_key_exists($val['goodid'], $ch)) {
                    //已存在sku_id就更新
                    $ch[$val['goodid']]['num'] = $units1['coefficient'] * $val['num'] + $ch[$val['goodid']]['num'];
                } else {
                    $num = $units1['coefficient'] * $val['num'];
                    $ch[$val['goodid']] = [
                        'id'=>$val['id'],
                        'oid'=>$val['oid'],
                        'goodid' => $val['goodid'],
                        'name' => $val['name'],
                        'num' => $num,
                        'unitid' => $val['unitid'],
                        'unitgid' => $val['unitgid'],
                    ];
                }
            }
        }
        foreach ($ch as $k=>$v){
            $v['unit1']=$this->exchangeNum($v['num'],$v['unitgid']);
            $chlist[]=$v;
        }
        $data['goods']= $chlist;
        if($dpgid==0){
            $data['id']=0;
        }
        return $data;
}
    /**
     *添加 添加汇总 小组 送货详情预览
     * @return \think\response\Json json保存数据
     */
    public function getgroupdispatchDetail(){
        $input=input('post.');
    $data= $this->getgroupdispatch($input);
    if($data){
        return json(['code' => 1, 'data' => $data,'msg' => '配送订单数据记载成功！']);
    }else{
        return json(['code' => 0, 'data' => '','msg' => '没有更多数据！']);
    }
}

    /**
     * 添加 导出小组的送货信息
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function DispatcGroupDetailexport(){
        $input=input('get.');
        $list = $this->getgroupdispatch($input);
        $ps = [];
        if(isset($input['dpgid'])){
            $whereDg['gid']=$this->gid;
            $whereDg['id']=$input['dpgid'];
            $title=Db::name('dispatch_group')->where($whereDg)->value('title');
            $ps[] = $title ;
        }
        if(isset($input['dispatch']) && in_array(intval($input['dispatch']),[0,1,2])){
            $dsp = intval($input['dispatch']);
            $str='';
            if ($dsp == 0) {
                $str = '未配送';
            } else if ($dsp == 1) {
                $str = '配送中';
            } else if ($dsp == 2) {
                $str = '已配送';
            }
            $ps[] = $str;
        }
        if(isset($input['sdate']) && trim($input['sdate'])  || $input['edate'] && trim($input['edate'])){
            if(isset($input['sdate'])){
                $date=$input['sdate'];
            }else if($input['edate']){
                $date=$input['edate'];
            }else{
                $date=$input['sdate']-->$input['edate'];
            }
            $ps[] =$date;
        }
        $data=$list['goods'];
        $arrs=[];
        foreach ($data as $key=>$val){
            $arr=[];
            $arr['name']=$val['name'];
            foreach ($val['unit1'] as $m=>$n){
                $arr['unit']=$n['num'].$n['uname'];
            }
            array_push($arrs,$arr);
        }
        $title = [
            'name'=>'商品',
            'unit'=>'数量',
        ];
        array_unshift($arrs,$title);
        $excelData = [];
        foreach($arrs as $k=>$v){
            $x = [];
            $x['name'] = $v['name'];
            $x['unit'] = $v['unit'];
            $excelData[] = $x;
        }
        if(!empty($ps)){
            $st=implode('-',$ps);
            $filename=date('YmdHis',time()) .'小组配送'.'【'.$st.'】';
        }else{
            $filename=date('YmdHis',time()) .'小组配送';
        }
        $this->newOutExcel($excelData, $title, $filename);
        exit();
    }
    private function newOutExcel($data = [],$fields = [],$filename = '报表'){
        ob_start();
       if(empty($data) || empty($fields)){
            echo '没有数据，导出失败！';
            exit();
        }
        vendor("PHPExcel.PHPExcel");
        vendor("PHPExcel.PHPExcel.Writer.IWriter");
        vendor("PHPExcel.PHPExcel.Writer.Abstract");
        vendor("PHPExcel.PHPExcel.Writer.Excel5");
        vendor("PHPExcel.PHPExcel.IOFactory");
        vendor("PHPExcel.PHPExcel.Cell.DataType");
        vendor("PHPExcel.PHPExcel.Style.Color");
        vendor("PHPExcel.PHPExcel.Style.Fill");
        vendor("PHPExcel.PHPExcel.Worksheet.Drawing");
        $objPHPExcel = new \PHPExcel();
        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $objActSheet = $objPHPExcel->getActiveSheet();
        $ri = 1;
        //获取生成列字符
        $n = count($fields);
        $cols = $this->colums($n);
        foreach($data as $v){
            $vv = array_values($v);
            for($i = 0, $l = count($vv); $i < $l; $i++){
                $c = $cols[$i];
                if($ri === 1){
                    $objActSheet->getColumnDimension($c)->setAutoSize(true);
                    $objActSheet->getStyle($c)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER)->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setWrapText(false);
                }
                $iv = !($vv[$i]) ?  '' : $vv[$i];
                $objActSheet->setCellValueExplicit($c . $ri, $iv ,\PHPExcel_Cell_DataType::TYPE_STRING);
            }
            if($ri === 1){
                $objActSheet->getStyle($cols[0] . $ri . ':' . $cols[count($cols) - 1] . $ri)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
            }else if($ri % 2 === 0){
                $objActSheet->getStyle($cols[0] . $ri . ':' . $cols[count($cols) - 1] . $ri)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFEE');
            }
            $ri++;
        }
        //结束以后设置边框
        $styleThinBlackBorderOutline = [
            'borders' => [
                'allborders' => [ //设置全部边框
                    'style' => \PHPExcel_Style_Border::BORDER_THIN //粗的是thick
                ],
            ],
        ];
        $objActSheet->getStyle($cols[0] . '1:' . $cols[count($cols) - 1] . ($ri - 1))->applyFromArray($styleThinBlackBorderOutline);
        $filename = $filename . '.xlsx';
        $filename = iconv("UTF-8", "GB2312//IGNORE", $filename);
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: max-age=0');
        $objWriter->save('php://output');
    }
    //  180905  JL:: 人员变化申请记录
    private function groupStaffChange($input = [],$dgInfo = []){
        if(isset($input['itemid']) && count((array)$input['itemid'])){
            $ids = $input['itemid'];
            $types = $input['type'];
            $tguids = $input['tguid'];
            $leaders = $input['leader'];
            $tnames = $input['tname'];
            $dgid = $input['editid'];
            $carid = isset($input['carid']) ? (int)$input['carid'] : 0;
            $userAll = [];
            $inputLeaderUid = 0;
            foreach($ids as $val ){
                if(!$tguids[$val] && !strlen(trim($tnames[$val]))){
                    continue;//去掉空
                }
                $item = [];
                $item['gid'] = $this->gid;
                $item['dgid'] = $dgid;
                $item['type'] = intval($types[$val]);
                $isLeader = intval($leaders[$val]);
                $item['t_guid'] = intval($tguids[$val]);
                $item['t_name'] = trim($tnames[$val]);
                //  添加相同人员
                ksort($item);
                if(in_array($item,$userAll)){
                    $arrtips['msg'] = '组内不能有相同账号或记名的配送员！';
                    $arrtips['autoclose_sign'] = true;
                    $arrtips['close'] = ['sign' => false];
                    return $this->tips($arrtips);
                }
                $userAll[] = $item;
                if($isLeader && ($item['type'] === 0)){
                    $inputLeaderUid = $item['t_guid'] ;
                    $inputLeader = $item;
                }
            }
            //  数据库中所有成员
            if($dgid !== 0){
                $whereDgu['gid'] = $this->gid;
                $whereDgu['dgid'] = $input['editid'];
                $dbUserAll = Db::name('dispatch_groupu')->where($whereDgu)->field('gid,dgid,type,t_guid,id,t_name,leader')->select();
            }
            $oldLeaderGuid = 0;
            if(isset($dbUserAll) && !empty($dbUserAll)){
                foreach($dbUserAll as $k => $v){
                    if($dbUserAll[$k]['leader'] && ($dbUserAll[$k]['type']===0)){
                        $oldLeaderGuid = $dbUserAll[$k]['t_guid'];
                        $oldLeader = $dbUserAll[$k];
                    }
                    unset($dbUserAll[$k]['id']);
                    unset($dbUserAll[$k]['leader']);
                    ksort($dbUserAll[$k]);
                }
                $dguDel = $this->array_diff2( $dbUserAll, $userAll );//删除队列, 表单中没有，数据库有，需要删除
                $dguAdd = $this->array_diff2( $userAll, $dbUserAll ); //新增队列, 表单中有，数据库没有，需要添加
            }else{
                $dguDel = [];
                $dguAdd = $userAll;
            }
            //再次提交的数据中是否有组长，再次提交数据中的组长是否在
            //与原数据库变化
            if(($inputLeaderUid === $oldLeaderGuid) && !empty($dgInfo) && (count($dguDel) === 0 && count($dguAdd) === 0) && $dgInfo['status'] == $input['status'] && $dgInfo['carid'] == $input['carid'] && $dgInfo['title'] == $input['title']){
                $arrtips['msg'] = '组信息未更改！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                return $this->tips($arrtips);
            }
            $change = [];
            if($inputLeaderUid === 0){
                $arrtips['msg'] = '必须设置一位非记名成员为组长！';
                $arrtips['autoclose_sign'] = true;
                $arrtips['close'] = ['sign' => false];
                return $this->tips($arrtips);
            }else if(!!$oldLeaderGuid && ($oldLeaderGuid !== $inputLeaderUid)){
                $change['leader'] = [
                    'old' => $oldLeader,
                    'new' => $inputLeader
                ];
            }else if(!$oldLeaderGuid){
                $change['leader'] = [
                    'new' => $inputLeader
                ];
            }
            if(empty($dgInfo)){
                if($carid){
                    //新增组配置了车辆
                    $change['car'] = [
                        'new' => $carid
                    ];
                }
            }else if (($dgInfo['carid'] !== $carid)){
                //新增组配置的车辆与原来的组不相同
                $change['car'] = [
                    'new' => $carid,
                    'old' => $dgInfo['carid']
                ];
            }
            //  180905 JL:: 判断成员是否在其他组,有则返回提示
            $whereDg['dg.gid'] = $this->gid;
            $whereDg['dg.delete'] = 0;
            $whereDg['dg.id'] = [ 'neq', $input['editid'] ];
            foreach( $dguAdd as $val2){
                $whereDgu = [];
                if($val2['type'] === 0){
                    //记名
                    $whereDgu['dgu.gid'] = $this->gid;
                    $whereDgu['dgu.t_guid'] = $val2['t_guid'];
                    $whereDgu['dgu.type'] = $val2['type'];
                    $dgt = Db::name('dispatch_groupu dgu')->where($whereDgu)->join( 'dispatch_group dg', 'dgu.dgid = dg.id', 'LEFT')->where($whereDg)->value('dg.title');
                }else{
                    //非记名
                    $whereDgu['dgu.gid'] = $this->gid;
                    $whereDgu['dgu.t_guid'] = 0;
                    $whereDgu['dgu.t_name'] = $val2['t_name'];
                    $whereDgu['dgu.type'] = 1;
                    $dgt = Db::name('dispatch_groupu dgu')->where($whereDgu)->join( 'dispatch_group dg', 'dgu.dgid = dg.id', 'LEFT')->where($whereDg)->value('dg.title');
                }
                if(!empty($dgt)){
                    $arrtips['msg'] = "‘{$val2['t_name']}’已存在‘{$dgt}’小组中，请先在‘{$dgt}’组中删除后添加！";
                    $arrtips['autoclose_sign'] = false;
                    $arrtips['close'] = ['sign' => true];
                    return $this->tips($arrtips);
                }
            }
        }else{
            //保存没有组成员，则全部删除
            $arrtips['msg'] = "请至少设置一位小组成员！";
            $arrtips['autoclose_sign'] = true;
            $arrtips['close'] = ['sign' => false];
            return $this->tips($arrtips);
        }
        if(!empty($dguAdd)){
            $change['add'] = $dguAdd;
        }
        if(!empty($dguDel)){
            $change['del'] = $dguDel;
        }
        if(!empty($change)){
            $changeJson = json_encode($change,JSON_UNESCAPED_UNICODE );
        }else{
            //说明人没变，组长没变，车也没变， 仅仅 小组标题或者状态变了。那么不需要去写记录
           return true;
        }
        
        // 创建审核变动
        $groupAudit['gid'] = $this->gid;
        $groupAudit['guid'] = $this->guid;
        $groupAudit['dgid'] = $input['editid'];
        $groupAudit['ctime'] = time();
        $groupAudit['new_carid'] = $input['carid'];//变化车辆
        $groupAudit['status'] = 0;
        $groupAudit['changes'] = $changeJson;
        $groupAuditId = Db::name('dispatch_group_audit')->insertGetId( $groupAudit );
        //调用审核 groupAuditSuccess 审核；
        return $this->groupAuditSuccess([ 'id' => $groupAuditId ], true);
    }

    /**
     * 添加 单位换算
     * @param int $num 数量
     * @param int $unitgid 单位
     * @param bool $zeroSign 状态
     * @return array
     */
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
    private function colums($n = 0){
        $ch = range('A','Z');
        $chl = count($ch);
        $cols = [];
        for($i = 0; $i < $n; $i++){
            $j = $i;
            do{
                $s = floor($j / $chl);
                $y = $j - $s * $chl;
                $j = $s;
                //最低位数
                $col[] = $y;
            }while($s);
            $cols[] = array_reverse($col);
            $col = [];
        }
        $ss = [];
        for($k = 0, $kl = count($cols); $k < $kl; $k++){
            $s = [];
            $col = $cols[$k];
            for($x = 0, $xl = count($col); $x < $xl; $x++){
                if($x  === ($xl -1)){
                    $s[] = $ch[$col[$x]];
                }else{
                    $s[] = $ch[$col[$x] - 1];
                }
            }
            $ss[] = join('',$s);
        }
        return $ss;
    }
}
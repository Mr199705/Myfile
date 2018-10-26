<?php
namespace app\shop\controller;
use think\Db;
class Views extends Base{
    public function index(){
        $baojia_id = input('id');
        $whereA = [];
        $whereA['gid'] = $this->gid;
        $whereA['id'] = isset($baojia_id) ? $baojia_id : 0 ;
        $whereA['status'] = 1;
        $whereA['unitgid'] = ['NEQ',0];
        $whereA['sell_status'] = ['NEQ',0];
        $bInfo = Db::name('baojia')
            ->where( $whereA )
            ->field(['id','goods_id','unitgid','unitid','unit','goodsName','seller_note','sn','brandId','catId', 'imgsid', 'retailprice','tuanprice','goodsImg','goodsThumb','detail'])
            ->find();
        // 是否存在该商品
        if(empty($bInfo)){
            $this->assign('itemInfo',$bInfo );
            return view('/ljk/views');
        }
        $goods = Db::name('goods')->field('goods_name,goods_id,seller_note,brand_id,cat_id,goods_sn,imgsid,goods_img,goods_thumb,goods_desc')->where(['goods_id'=>$bInfo['goods_id']])->find();
        if(!$bInfo['goodsName']){
            $bInfo['goodsName'] = $goods['goods_name'];
        }
        if(!$bInfo['brandId']){
            $bInfo['brandId'] = $goods['brand_id'];
        }
        if(!$bInfo['catId']){
            $bInfo['catId'] = $goods['cat_id'];
        }
        if(!$bInfo['seller_note']){
            $bInfo['seller_note'] = $goods['seller_note'];
        }
        if(!$bInfo['detail']){
            $bInfo['detail'] = $goods['goods_desc'];
        }
        if(!$bInfo['sn']){
            $bInfo['sn'] = $goods['goods_sn'];
        }
        if(!$bInfo['imgsid']){
            $bInfo['imgsid'] = $goods['imgsid'];
        }
        if(!$bInfo['goodsThumb']){
            $bInfo['goodsThumb'] = $goods['goods_thumb'];
        }
        if(!$bInfo['goodsThumb']){
            $bInfo['goodsImg'] = $goods['goods_img'];
        }
        if(!empty($bInfo['catId'])){
            $bInfo['catname'] = Db::name('goods_type')->where(['cat_id' => $bInfo['catId']])->value('cat_name');
        }
        if(!empty($bInfo['brandId'])){
            $bInfo['brandname'] = Db::name('brand')->where(['brand_id' => $bInfo['brandId']])->value('brand_name');
        }
        $res = $this->bjAtt(['bjid' => $baojia_id]);
        if($res['code'] === 0){
            $bInfo['batt'] = $res['data'];
        }
        //获取类型名称
        if(!empty($bInfo['imgsid'])){
            $imgsid = preg_split('/[,_]/', $bInfo['imgsid']);
            $whereF['id'] = ['IN',$imgsid];
            $imgs1 = Db::name('goods_file')->where($whereF)->column('id,savepath,url,savename,ext');
            $imgs = [];
            if(!empty($imgs1)){
                for($i=0,$l = count($imgsid);$i < $l;$i++){
                    if(isset($imgs1[$imgsid[$i]])){
                        $x = $imgs1[$imgsid[$i]];
                        $x['imgurl']=mkgoodsimgurl(['url'=>$x['url']]);
                        $imgs[] = $x;
                    }
                }
            }
            $bInfo['imgs'] = $imgs;
        }else{
            $imgurl = !!$bInfo['goodsImg'] ? $bInfo['goodsImg'] : ((!!$bInfo['goodsThumb']) ? $bInfo['goodsThumb'] : '');
            $bInfo['imgs'] = [
                ['imgurl' => mkgoodsimgurl(['url'=> $imgurl])]
            ];
        }
        //  增加浏览次数
        Db::name('baojia')->where( $whereA )->setInc('clicknum');
        //对价格信息进行加工
        //  模版参数
        $whereC = [];
        $whereC['user_id']  = $this->uid;
        $whereC['gid']      = $this->gid;
        $whereC['status']      = 1;
        $whereC['is_real']      = 2;
        $whereC['type'] = 1;
        $cartNum = Db::name('cart')->where($whereC)->count('id');
        $this->assign('cartNum', $cartNum );
        $this->assign('bInfo', $bInfo );
        return view('/ljk/views/views');
    }
    private function bjAtt($input = []){
        $bjid = isset($input['bjid']) ? intval($input['bjid']) : false;
        if(!!$bjid){
            $whereBj['gid'] = $this->gid;
            $whereBj['id'] = $bjid;
            $whereBj['status'] = 1;
            $whereBj['sell_status'] = 1;
            $bj = Db::name('baojia')
                ->field('id,catId,goods_id')
                ->where( $whereBj)
                ->find();
            if(empty($bj)){
                return ['code' => -1, 'msg' => '商品不存在或已下架！'];
            }
            $whereBat['b.bjid'] = $bjid;
            $whereGa['g.cat_id'] = $bj['catId'];
            $bjAtt = Db::name('baojia_att b')->field('g.attr_name,b.attrValue')->where($whereBat)->join('goods_attribute g','b.attrId=g.attr_id','LEFT')->where($whereGa)->select();
            if(empty($bjAtt)){
                $catId = Db::name('goods')->where(['goods_id'=>$bj['goods_id']])->value('cat_id');
                if(!$catId){
                    return ['code' => 0,'data' => [],'msg'=>'没有数据'];
                }
                $whereGat['ga.goods_id'] = $bjid['goods_id'];
                $whereGa['g.cat_id'] = $catId;
                $bjAtt = Db::name('goods_att ga')->field('g.attr_name,ga.attr_value attrValue')->where($whereGat)->join('goods_attribute g','ga.attr_id=g.attr_id','LEFT')->where($whereGa)->select();
            }
            return ['code' => 0,'data' => $bjAtt];
        }else{
            return ['code' => -1, 'msg' => '商品不存在或已下架！'];
        }
    }
    //评论
    public function evaluate(){
    	return view('/ljk/evaluate');
    }
    /*
    *  TODO:: 异步数据获取
    */
    public function viewFun(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $action = '';
            if(isset($input['action']) && trim($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                case 'getprice':return $this->getPrice($input);
                case 'getdetails':return $this->getDetails($input);
                case 'getguige':return $this->getGuige($input);
                case 'getcomment':return $this->getComment($input);
                case 'getprice':return $this->getPrice($input);
            }
        }
    }
    //  TODO:: 商品属性报价
    private function getPrice($input = []){
        $bjid = isset($input['bjid']) ? intval(trim($input['bjid'])) : false;
        if(!$bjid){
            return json(['code' => -1,'msg' => '非法操作！']);
        }
        $whereBj['gid'] = $this->gid; 
        $whereBj['id'] = $bjid;
        $whereBj['unitgid'] = ['NEQ',0];
        $whereBj['sell_status'] = 1;
        $bj = Db::name('baojia')->field('id,tuanprice,retailprice,unitid,unitgid,goods_id')->where($whereBj)->find();
        if(empty($bj)){
            return json(['code' => -1,'msg' => '商品不存在！']);
        }
        $whereU['unitgid'] = $bj['unitgid'];
        $whereU['gid'] = $this->gid;
        $whereU['status'] = 1;
        //单位列表
        $units = Db::name('unit')->where($whereU)->order(['coefficient' => 'DESC'])->column('id,uname,coefficient');
        if(empty($units)){
            return json(['code' => -1,'msg' => '商品不存在！']);
        }
        //sku信息
        $whereBjs['gid'] = $this->gid;
        $whereBjs['baojia_id'] = $bj['id'];
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
                    //无属性新报价
                    $whereBjsp['skuid'] = $bjs['id'];
                    $whereBjsp['bjid'] = $bjid;
                    $whereBjsp['status'] = 1;
                    $whereBjsp['sell_status'] = 1;
                    $whereBjsp['unitgid'] = $bj['unitgid'];
                    $whereBjsp['tuanprice'] = ['NEQ',0];
                    $order = '';
                    if(!!$this->rankid){
                        if($this->gid==133){
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
                        if($this->gid==133){
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
                    $objsps = Db::name('baojia_skuprice')->field('id,unitid,sale_num,skuid,rankid,unitgid,tuanprice,retailprice,buypoint,costpoint')->where($whereBjsp)->order($order)->select();
                    if(empty($objsps)){
                        return json(['code' => -1, 'msg' => '该商品已下架！']);
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
                        return json(['code' => 0, 'data' => ['ptype'=>'2','sku' => $bjs,'units' => $sunits,'prices' => $bjsps]]);
                    }
                }
            }
            $prices = [];
            $sattrs = [];
            for($j = 0, $k = count($bjss); $j < $k; $j++){
                $bjs = $bjss[$j];
                $whereBjsp['skuid'] = $bjs['id'];
                $whereBjsp['bjid'] = $bjid;
                $whereBjsp['status'] = 1;
                $whereBjsp['sell_status'] = 1;
                $whereBjsp['unitgid'] = $bj['unitgid'];
                $whereBjsp['tuanprice'] = ['NEQ',0];
                $order = '';
                if(!!$this->rankid){
                    if($this->gid==133){
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
                    if($this->gid==133){
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
                $objsps = Db::name('baojia_skuprice')->field('id,unitid,sale_num,skuid,rankid,unitgid,tuanprice,retailprice,buypoint,costpoint')->where($whereBjsp)->order($order)->select();
                if(empty($objsps)){
                    continue;
                }else{
                    $rids = explode(',',trim($bjs['attr_ids']));
                    $vids = explode(',',trim($bjs['attr_value_ids']));
                    $ridss = implode('_',$rids);
                    $vidss = implode('_',$vids);
                    if(empty($sattrs)){
                        $whereBa['gid'] = $this->gid;
                        $whereBa['id'] = ['IN',$rids];
                        $whereBa['bjid'] = $bjid;
                        $whereBa['status'] = 1;
                        $sattrs = Db::name('baojia_attr')->where($whereBa)->order(['id' => 'ASC'])->column('id,gid,attr_name aname,bjid');
                        if(empty($sattrs)){
                            return json(['code' => -1, 'msg' => '该商品已下架！']);
                        }else{
                            $whereBav['gid'] = $this->gid;
                            $whereBav['baojia_id'] = $bjid;
                            $whereBav['status'] = 1;
                            $whereBav['attr_id'] = ['IN',$rids];
                            $bavs = Db::name('baojia_attr_value')->where($whereBav)->order(['attr_id' => 'ASC','id' => 'ASC'])->column('id,gid,attr_id,attr_value avalue');
                            if(empty($bavs)){
                                return json(['code' => -1, 'msg' => '该商品已下架！']);
                            }
                        }
                        $ssattrs = [];
                        foreach($sattrs as $sk => $v){
                            $ssattrs[$sk]['attr'] = $v;
                        }
                        $sattrs = $ssattrs;
                    }
                    for($x = 0, $xl = count($vids); $x < $xl; $x++){
                        $attrId = $bavs[$vids[$x]]['attr_id'];
                        if(!isset($sattrs[$attrId]['attrv'][$vids[$x]])){
                            $sattrs[$attrId]['attrv'][$vids[$x]] = $bavs[$vids[$x]];
                        }
                    }
                    $prices[$vidss]['sku'] = $bjs;
                    $prices[$vidss]['prices'] = [];
                    //获取属性组信息
                    $bjsps = [];
                    for($i = 0, $l = count($objsps); $i < $l; $i++){
                        $objsp = $objsps[$i];
                        if(!isset($sunits[$objsp['unitid']])){
                            $sunits[$objsp['unitid']] = $units[$objsp['unitid']];
                        }
                        if(!isset($bjsps[$objsp['unitid']]) || ($bjsps[$objsp['unitid']]['rankid'] == 0 && $objsp['rankid'] == $this->rankid)){
                            $bjsps[$objsp['unitid']] = $objsp;
                        }
                    }
                    $prices[$vidss]['prices'] = $bjsps;
                }
            }
            return json(['code' => 0, 'data' => ['ptype'=>'3','prices' => $prices,'sattrs' => $sattrs, 'units' => $sunits]]);
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
                return json(['code' => -1, 'msg' => '该商品已下架！']);
            }else{
                return json(['code' => 0, 'data' => ['ptype'=>'1','prices' => $bj,'units' => $units]]);
            }
        }
    }
    //  TODO:: 商品图文详情
    private function getDetails( $input = '' ){
        $whereA = [];
        $whereA['gid'] = $this->gid;
        $whereA['id'] = $input['goods_id'];
        try {
            $reData = Db::name('baojia')->where($whereA)->field(['detail'])->find();
        } catch (\Exception $e) {
            return $this->reJson( 0, 0, '获取失败：'. $e);
        }
        return $this->reJson( 1, $reData['detail'], '获取成功');
    }

    //  TODO:: 商品规格参数
    private function getGuige( $input = '' ){
        $whereA = [];
        $whereA['gid'] = $this->gid;
        $whereA['baojia_id'] = $input['goods_id'];
        try {
            $reData = Db::name('baojia_attr_value')
                ->where($whereA)
                ->field(['attr_id','attr_value'])
                ->select();
            foreach ( $reData as $k => $v ){
                $reData[$k]['attr_name'] = Db::name('baojia_attr')->where( [ 'gid' => $this->gid, 'id' => $v['attr_id'] ])->value('attr_name');
            }
            return $this->reJson( 1, $reData, '获取成功');
        } catch (\Exception $e) {
            return $this->reJson( 0, 0, '获取失败：'. $e);
        }
    }
    //  TODO:: 商品评论
    private function getComment( $input = '' ){
    	$page = isset($input['page']) ? $input['page'] : 1;
    	$whereA = [];
    	$whereA['baojia_id'] = $input['goods_id'];
    	$whereA['gid'] 		= $this->uMap['gid'];
    	$whereA['status'] 	= 1;	//	正常显示
    	$whereA['utype'] 	= 0;	//	客户评论
    	$whereA['type'] 	= 1;	//	对商品
    	$whereA['delete'] 	= 0;	//	未删除
    	$field = [ 'id', 'pid', 'uid', 'utype', 'content', 'imgsid', 'date_time'];
    	$reDataA = Db::name('order_comment')
            ->where($whereA)
            ->field($field)
            ->page( $page, 10)
            ->select();
    	foreach ($reDataA as $ind => $val){
            $reDataA[$ind]['uid'] = Db::name('user_member')->where(['gid' => $this->gid, 'uid' => $val['uid'] ])->value('realname');
            $reDataA[$ind]['date_time'] = date( 'Y年m月d日', $val['date_time']);
            //评论图片
            $imgsid2 = null;
            $imgsid = explode( ",", $reDataA[$ind]['imgsid']);
            foreach ($imgsid as $ind2 => $val2){
                if($val2){
                    $whereB = [];
                    $whereB['gid'] = $this->gid;
                    $whereB['id'] = $val2;
                    $imgsid2[$ind2] = Db::name('file_comment')->where($whereB)->value("url");
                }
            }
            $reDataA[$ind]['imgsid'] = $imgsid2;
    	}
        return $this->reJson( 1, $reDataA, '获取成功:' . $input['goods_id']);
    }
    //  TODO:: 商品sku价格
}





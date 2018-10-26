<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/2
 * Time: 10:38
 */

namespace app\admin\controller;
use think\Db;
use think\Request;

class Goodsbank extends Base{
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
    public function gb(){
        $action = '';
        if(request()->isAjax() && request()->isPost()){
            //这里是获取ajax列表数据
            $input = input('post.');
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                case 'showlist':return $this->showGbList($input);break;
                case 'displayorder':return $this->displayorder();break;
                case 'editStatus':return $this->editStatus();break;
                default:return $this->showGbtpl();
            }
        }else{
            $input = input();
            if(isset($input['action'])){
                $action = trim($input['action']);
                unset($input['action']);
            }
            switch($action){
                default:return $this->showGbtpl();
            }
        }
    }
    private function GbList($input = [],$p = true){
        $whereP = [];
        $whereP['a.gid']=$this->gid;
        //库存 和该分类关键词查询
        $is_stock=isset($input['is_stock']) ? trim($input['is_stock']) :false;
        if($is_stock){
            if($is_stock==2){
                $whereP['a.is_stock']=['IN',[0]];
            }else if($is_stock==1){
                $whereP['a.is_stock']=['IN',[1]];
            }else{
                $whereP['a.is_stock']=['IN',[0,1]];
            }
        }
        $sell_status=isset($input['sell_status']) ? trim($input['sell_status']) :false;
        if($sell_status){
            if($sell_status==2){
                $whereP['a.sell_status']=['IN',[0]];
            }else if($sell_status==1){
                $whereP['a.sell_status']=['IN',[1]];
            }else{
                $whereP['a.sell_status']=['IN',[0,1]];
            }
        }
        $type=isset($input['type']) ? trim($input['type']) :false;
        $kw=isset($input['keywords']) ? trim($input['keywords']) :false;
        if($type || $kw){
            if($type=="goods_name"){
                $whereP['a.goodsName']=['LIKE','%'.$kw.'%'];
            }else if($type=="seller_note"){
                $whereP['a.seller_note']=['LIKE','%'.$kw.'%'];
            }
        }
//        $pids=input('pids');
//        if($pids){
//            $whereP['c.pid']=['=',$pids];
//        }
        $ids=input('ids/a');
        if($ids){
            $whereP['b.categoryid']=['IN',$ids];
        }
        if($p == true){
//            $res = Db::name('baojia')
//                ->field('id,unitid,goodsName,goodsThumb,goodsImg,seller_note,cuxiao,displayorder,retailprice,tuanprice')->where($whereP)
//                ->order('displayorder desc')->paginate($this->rows);
            $res = Db::name('baojia')->alias('a')
                ->field('a.id,a.unitid,a.goodsName,a.goodsThumb,a.goodsImg,a.seller_note,a.sell_status,a.cuxiao,a.displayorder,a.retailprice,a.tuanprice')
                ->join('ljk_baojia_cat b','a.id=b.bid','LEFT')
                ->join('ljk_category c','b.categoryid=c.id','LEFT')
                ->where($whereP)->order('a.displayorder desc')->group('a.id')->paginate($this->rows);
            $pageInfo = pageInfo($res);
            $data = $res->items();
            $page = $res->currentPage();
        }else{
            $data =Db::name('baojia')->alias('a')
                ->field('a.id,a.unitid,a.goodsName,a.goodsThumb,a.goodsImg,a.seller_note,a.sell_status,a.cuxiao,a.displayorder,a.retailprice,a.tuanprice')
                ->join('ljk_baojia_cat b','a.id=b.bid','LEFT')
                ->join('ljk_category c','b.categoryid=c.id','LEFT')
                ->where($whereP)->order('a.displayorder desc')->group('a.id')->select();
            $pageInfo = false;
        }
        $sku = [];
        $unit = [];
        $lbsinfolist = [];
        foreach ($data as $k=>$v){
            if(!isset($sku[$v['id']])){
                $whereSku = [];
                $whereSku['gid']=$this->gid;
                $whereSku['baojia_id']=$v['id'];
                //sku属性商品
                $skus=[];
                $sku=Db::name('baojia_sku')->field('id,title,gid,goods_id,baojia_id,retailprice,tuanprice,attrs_value')->where($whereSku)->select();
                foreach ($sku as $key=>$val){
                    //sku_price循环每个商品
                    $data=Db::name('baojia_skuprice')->field('unitid,tuanprice,retailprice')->where(array('skuid'=>$val['id'],'gid'=>$this->gid))->find();
                    $unit=Db::name('unit')->field('uname,title')->where(array('id'=>$data['unitid'],'gid'=>$this->gid))->find();
                    $val['jiage']=$data;
                    $val['unit']=$unit;
                    $skus[]=$val;
                }
                $sku[$v['id']]=$skus;
            }
            if(!isset($unit[$v['unitid']])){
                $whereU = [];
                $whereU['gid']=$this->gid;
                $whereU['id']=$v['unitid'];
                $unit[$v['id']]=Db::name('unit')->field('uname,title')->where($whereU)->find();
            }
            $v['sku']=$sku[$v['id']];
            $v['unit']=$unit[$v['id']];
            //处理图片
            $imgarr1['url']=$v['goodsThumb'];
            $imgarr2['url']=$v['goodsImg'];
            $v['img_url']=$this->mkgoodsimgurl($imgarr1);
            $v['img_url']=$this->mkgoodsimgurl($imgarr2);
            $lbsinfolist[]=$v;
        }
        return ['pageInfo' => $pageInfo,'data' => $lbsinfolist];
    }
    private function showGbList($input = []){
        $res = $this->GbList($input);
        $this->assign('pageInfo',$res['pageInfo']);
        $this->assign('goodsbank',$res['data']);
        return $this->fetch('/goodsbank/ajax/gb');
    }
    private function displayorder(){
        $data=input('displayorder/a');
        foreach($data as $k=>$v){
            Db::name('baojia')->where(array('id'=>$k,'gid'=>$this->gid))->setField('displayorder',$data[$k]);
        }
        $arrtips['autoclose_sign'] = true;
        $arrtips['msg'] = '批量更新成功，页面即将刷新！';
        $arrtips['close'] = ['sign'=>true];
        $arrtips['next_action'] = "getList({sign:'goodsbankGb'})";
        return $this->tips($arrtips);

    }
    private function editStatus(){
        $id=input('x');
        $sell_status=input('value');
        $res=Db::name('baojia')->where(array('id'=>$id,'gid'=>$this->gid))->setField('sell_status',$sell_status);
        if($sell_status==1){
            //上架
            if($res>0){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '商品上架成功，页面即将刷新！';
                $arrtips['close'] = ['sign'=>true];
                $arrtips['next_action'] = "getList({sign:'goodsbankGb'})";
                return $this->tips($arrtips);
            }else{
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '商品上架失败！';
                $arrtips['close'] = ['sign'=>true];
                return $this->tips($arrtips);
            }
        }else{
            if($res>0){
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '商品下架成功，页面即将刷新！';
                $arrtips['close'] = ['sign'=>true];
                $arrtips['next_action'] = "getList({sign:'goodsbankGb'})";
                return $this->tips($arrtips);
            }else{
                $arrtips['autoclose_sign'] = true;
                $arrtips['msg'] = '商品下架失败！';
                $arrtips['close'] = ['sign'=>true];
                return $this->tips($arrtips);
            }
        }
    }
    private function showGbtpl(){
        $data=$this->categoryList();
        $this->assign('categorys',$data);

        $cates=$this->getCategorytwoList();
        $this->assign('cates',$cates);
        $data=Db::name('baojia')->where(array('gid'=>$this->gid))->order('id asc')->select();
        $this->assign('baojia',$data);
        return $this->fetch('/goodsbank/gb');
    }
    private function categoryList(){
        $whereP['a.gid']=$this->gid;
        $whereP['a.pid']=0;
        $whereP['a.level']=0;
        $data=Db::name('category')->alias('a')
            ->field('a.id,a.title,a.pid')
            ->join('baojia_cat b','a.id=b.categoryid')
            ->where($whereP)->group('a.id')->order('a.id asc')->select();
        return $data;
    }
    private function getCategorytwoList(){
        $whereBc['gid']=['=',$this->gid];
        $whereBc['level']=['=',1];
        $data=Db::name('category')
            ->field('id,title,pid')
            ->where($whereBc)->select();
        $data=$this->sortInitials($data);
        return $data;
    }
    /**
     * 显示商品图片
     * @param array $param
     * @return string
     */
    private function mkgoodsimgurl($param=[]){
        if(empty($param)){
            return '';
        }else{
            if($param['url']){
                $parseUrl = parse_url($param['url']);
                $path = $parseUrl['path'];
                if(file_exists(substr($path,1))){
                    return $path;
                }else{
                    return 'http://sx.img.ljk.cc'.$path;
                }
            }
        }
    }
    private function sortInitials(array $data){
        $sortData = [];
        foreach ($data as $key => $value) {
            $sortData[$value['pid']][] = $value;
        }
        ksort($sortData);
        return $sortData;
    }
}
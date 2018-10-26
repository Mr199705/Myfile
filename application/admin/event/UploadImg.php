<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\admin\event;
use app\test\controller\Base;
use think\Db;

/**
 * Description of File
 *
 * @author Administrator
 */
class UploadImg extends Base {
    public function index($file,$path=''){
        $sinfo = $file->getInfo();
        $smd5 = md5_file($sinfo['tmp_name']);
        $d = Db::name('file')->field('id,savename,savepath,ext,url')->where('md5',$smd5)->find();
        $id = $d['id'];
        if(!$id){
        	if(!$path){
                    $spath=ROOT_PATH . 'public' . config('upload_path');
        	}else{
                    $spath= ROOT_PATH . 'public' . $path;
        	}
            $info = $file->validate(['ext'=>'jpg,png,gif'])->move($spath);
            if($info){
                $si = $info->getInfo();
                if(!$path) $path=config('upload_path');
                $url = str_replace('\\','/',$path .'/'. $info->getSaveName());
                $snp = pathinfo($url);//这个name是拿来存入数据库作为资源链接;
                $fi = array();
                $fi['savename'] = $snp['filename'];
                $fi['savepath'] = substr($snp['dirname'], strrpos($snp['dirname'],'/' ) + 1);
                $fi['name'] = $snp['basename'];
                $fi['ext'] = $snp['extension'];
                $fi['size'] = $si['size'];
               // $fi['mime'] = $info->getMime();
                $fi['create_time'] = time();
                $fi['md5'] = $info->hash('md5');
                $fi['sha1'] = $info->hash('sha1');
                $fi['url'] = $url;
                $fi['guid'] = $this->guid;//session('guid');
                $fi['gid'] = session('gid');
                //设置这个客户关联的url字符集合,判断是否已经上传过这个文件
            }else{
                return ['id'=>-1,'url'=>'','msg'=>'格式错误'];
            }
//            $f = model('file');
//            $f->save($fi);
//            $id = $f->id;
            $id=Db::name('file')->insert($fi);
            $fi['id']=$id;
            $d=$fi;
        }
        return $d;
    }
}

<?php
/**
 * Copyright (c) 山东六牛网络科技有限公司 https://liuniukeji.com
 *
 * @Description     转账 Controller
 * @Author         (wangzhenyu/byzhenyu@qq.com)
 * @Copyright      Copyright (c) 山东六牛网络科技有限公司 保留所有版权(https://www.liuniukeji.com)
 * @Date           2018/11/27 0027 14:05
 * @CreateBy       PhpStorm
 */

namespace Admin\Controller;
use Think\Controller;
class TransferAccountController extends CommonController {
    protected function _initialize()
    {
        $this->TransferAccount = D("Hr/TransferAccount");
    }
    /**
     * @desc  转账查看
     * @param
     * @return mixed
     */
    public function getMyAccounts(){
        $keyword = I('keyword', '');
        if ($keyword) {
            $where['s.bank_name'] = array('like','%'.$keyword.'%');
        }
        $field = 'u.*, t.*, s.bank_name, s.bank_no, s.bank_holder, s.bank_opening';
        $AccountsInfo = $this->TransferAccount->getAccounts($where, $field);
        $this->list = $AccountsInfo['list'];
        $this->page = $AccountsInfo['page'];
        $this->display();
    }
    /**
     * @desc  转账详情
     * @param  id
     * @return mixed
     */
    public function accountDetail(){
        $where['t.id'] = I('id', 0, 'intval');
        $field = 'u.nickname, u.head_pic, u.user_money, t.*, s.bank_name, s.bank_no, s.bank_holder, s.bank_opening';
        $AccountsInfo =  $this->TransferAccount->getAccounts($where, $field);
        if(IS_POST){
            $data = I('post.');
            if($data['audit_status'] == 0){
                $this->ajaxReturn(V(0, '请选择转账状态'));
            }else if($data['audit_status'] == 2){
                $result = $this->TransferAccount->where(array('id' => $data['id']))->save($data);
                if($result){
                    $this->ajaxReturn(V(1, '操作成功'));
                }
            }else{
                $trade_no  = 'Y' . date('YmdHis', time()) . '-' . $AccountsInfo['list'][0]['user_id']; //订单号
                $result = D('Common/PayRecharge')->paySuccess(fen_to_yuan($AccountsInfo['list'][0]['transfer_amount']), $AccountsInfo['list'][0]['user_id'], $trade_no, 3);
                if ($result['status'] == 1) {
                    $this->TransferAccount->where(array('id' => $data['id']))->save($data);
                    $this->ajaxReturn(V(1, '操作成功'));
                }
            }
            $this->ajaxReturn(V(0, $this->Invoice->getError()));
        }
        $this->info = $AccountsInfo['list'][0];
        $this->display();
    }
    /**
     * @desc  删除信息
     * @param  id
     * @return mixed
     */
    /*删除*/
    public function recycle() {
        $this->_recycle('TransferAccount','id');
    }
    public function del(){
        $this->_del('TransferAccount', 'id');
    }
}
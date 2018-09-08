<?php
/**
 * 职位模型类
 */
namespace Admin\Model;

use Think\Model;

class PositionModel extends Model {
    protected $insertFields = array('position_name', 'sort');
    protected $updateFields = array('position_name', 'sort');
    protected $_validate = array(
        array('position_name', 'require', '职位名称不能为空！', 1, 'regex', 3),
        array('sort', 'require', '排序不能为空！', 1, 'regex', 3),
        array('position_name', 'checkPositionLength', '职位名称不能超过30字！', 2, 'callback', 3),
    );

    /**
     * 验证职位名称长度
     */
    protected function checkPositionLength($data) {
        $length = mb_strlen($data, 'utf-8');
        if ($length > 30) {
            return false;
        }
        return true;
    }
    /**
     * 获取职位列表
     * @param $where array 条件
     * @param $isPage bool 是否返回分页数据
     * @param $order string 排序顺序
     * @return mixed
     */
    public function getPositionList($where, $order = 'sort', $isPage = true){
        if(!$isPage) return $this->where($where)->order($order)->select();
        $count = $this->where($where)->count();
        $page = get_web_page($count);
        $list = $this->where($where)->limit($page['limit'])->order($order)->select();

        return array('info' => $list, 'page' => $page['page']);
    }

    //添加操作前的钩子操作
    protected function _before_insert(&$data, $option){
    }
    //更新操作前的钩子操作
    protected function _before_update(&$data, $option){
    }

}
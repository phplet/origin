<?php if ( ! defined('BASEPATH')) exit();
/**
 * 机考-试题数据模块
 *
 * @author qcchen
 * @final 2013-12-07
 */
class Question_model extends CI_Model
{
	/**
	 * 表名
	 * @var string
	 */
	private static $_table_name = 'question';
	private static $_table_question_option = 'option';

	/**
	 * 构造函数，初始化
	 *
	 */
    public function __construct()
    {
        parent::__construct();

        //载入缓存类
        $this->load->driver('cache');
    }

    /**
     * 按ID读取一个试题信息
     *
     * @param   int     试题id
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public function get_question_by_id($ques_id = 0, $item = NULL)
    {
        if ($ques_id == 0)
        {
            return FALSE;
        }
        if ($item)
        {
            $this->db->select($item);
        }
        $query = $this->db->get_where(self::$_table_name, array('ques_id' => $ques_id), 1);
        $row =  $query->row_array();
        if ($item && isset($row[$item]))
            return $row[$item];
        else
            return $row;
    }

    /**
     * 读取试题详情。如果是题组，则包含题组子题列表
     *
     * @param   int    试题id
     * @return  array
     */
    public function get_question_detail($ques_id)
    {
        $this->load->driver('cache');

        if (!$ques_id) {
            return false;
            exit;
        }
        $domain=C('memcache_pre');
        $question = $this->cache->file->get($ques_id);

        /** 判断是否存在本题缓存 如果不存在，读取数据库 */
        if (!$question) {
            $ques_id && $question = $this->get_question_by_id($ques_id, 'ques_id, type, title, picture, parent_id,subject_id');

            if (empty($question) OR $question['parent_id']>0) return false;

            // 格式化试题内容
            $question['title'] && $this->_format_question_content($question['ques_id'], $question['title'], in_array($question['type'], array(3, 9)));
            if (in_array($question['type'],array(0,4,5,6,8))) {
                // 题组
                $question['children'] = $this->get_children($ques_id);
            } elseif (in_array($question['type'],array(1,2,7))) {
                // 选择题
                $question['options'] = $this->get_options($ques_id);
            }

            /** 缓存时间 单位second 默认缓存1年 */
            $cache_time = 365 * 24 * 60 * 60;
            /** 写入缓存 */
            $this->cache->file->save($question['ques_id'], $question, $cache_time);
        }

    	return $question;
    }

    /**
     * 读取试题选项列表
     *
     * @param   int    试题id
     * @return  array
     */
    public function get_options($ques_id)
    {
    	$list = array();
        $query = $this->db->get_where(self::$_table_question_option, array('ques_id'=>$ques_id));
        foreach($query->result_array() as $row)
        {
        	$list[$row['option_id']] = $row;
        }
        return $list;
    }

    /**
     * 读取题组子题列表
     *
     * @param   int    试题id
     * @return  array
     */
    public function get_children($ques_id)
    {
        if (empty($ques_id)) return false;

        $list = array();
        $query = $this->db->select('ques_id, type, title, picture, answer')->order_by('sort ASC,ques_id ASC')->get_where(self::$_table_name, array('parent_id'=>$ques_id, 'is_delete'=>0));
        foreach ($query->result_array() as $row)
        {

    			$row['title'] && $this->_format_question_content($row['ques_id'], $row['title'], $row['type']==3);
                if (in_array($row['type'],array(1,2))) {
                	$row['options'] = $this->get_options($row['ques_id']);
                }

                if (in_array($row['type'], array(3,5,6)))
                {
                    $row['answer'] = explode("\n", $row['answer']);
                }
                $list[$row['ques_id']] = $row;
        }
        return $list;
    }

    /**
     * 格式化试题内容
     *
     * @param   integer		试题ID
     * @param   string		试题内容
     * @param	boolean		是否转换填空项
     * @return  void
     */
    private function _format_question_content(&$ques_id, &$content, $replace_inputs = FALSE)
    {
    	$content = str_replace("\n", '<br/>', $content);
    	if ($replace_inputs) {
    		//过滤&nbsp;
    		//$content = preg_replace("/（(.*)）/e", 'str_replace("&nbsp;", "", "（\1）")', $content);

    		//$regex = "/（[\s\d|&nbsp;]*）/";
    		$regex = "/（[\s\d|&nbsp;]*）/";
    		$input = "&nbsp;<input{nbsp}type='text'{nbsp}ques_id='{$ques_id}'{nbsp}name='answer_{$ques_id}[]'{nbsp}class='input_answer{nbsp}sub_undo{nbsp}type_3'{nbsp}/>";
    		$content = preg_replace($regex, $input, $content);
    	}
    	//$content = str_replace(" ", '&nbsp;', $content);
    	$content = str_replace("{nbsp}", ' ', $content);

    	return $content;
    }

}
/* End of file question_model.php */
/* Location: ./application/models/exam/question_model.php */
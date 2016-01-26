<?php
/**
 * AJAX方法响应类,只能用在AJAX方法内(XxxController::yyyFunc()函数中)
 * @code
 * public function fn1Func($param1, $param2)
 * {
 *      $resp = new AjaxResponse();
 *      $resp->alert('error request');
 *      $resp->redirect('/your/new/url?aaa=1');
 *      $resp->redirect('http://www.newweb.com'/your/new/url?aa=1');
 *      $resp->refresh();
 *      $resp->attr('#id_username', 'value', 'new name');
 *      $resp->call('fnShowDialog', 'id_dialog1');
 *      $resp->call('window.alert', 'new alert info');
 *      $resp->script('alert("new alert info");');
 *      return $resp;
 * }
 * @endcode
 */
final class AjaxResponse
{
    /**
     * 保存AJAX返回命令及参数
     */
    private $_data = array();

    /**
     * 清除所有已添加命令
     * @return  void
     */
    public function clear()
    {
        $this->_data = array();
    }

    /**
     * 添加alert命令,会调用JS函数alert来显示参数内容
     * @param   string  $info   要在客户端显示的内容
     * @return  void
     */
    public function alert($info)
    {
        $this->_data[] = array('alert', $info);
    }

    /**
     * 添加重定向(网址跳转)命令
     * @param   string  $url    要跳转的URL网址
     * @return  void
     */
    public function redirect($url)
    {
        $this->_data[] = array('redirect', $url);
    }

    /**
     * 添加刷新当前网页端命令
     * @return  void
     */
    public function refresh()
    {
        $this->_data[] = array('refresh');
    }

    /**
     * 添加修改jquery表达式所指对象属性值命令
     *      实质是调用$(jq_expr).attr(property, value);
     * @param   string  $jq_expr    jQuery表达式
     * @param   string  $property   属性名称
     * @param   string  $value      属性值
     * @return  void
     */
    public function attr($jq_expr, $property, $value)
    {
        $this->_data[] = array('attr', $jq_expr, $property, $value);
    }

    /**
     * 添加调用JS对象方法命令
     * @param   string  js函数或对象方法(如fnMyCheck1, location.reload)
     * @param   mixed   [参数一]
     * @param   mixed   [参数二]
     * @param   mixed   [参数三]
     * ......
     * @return  void
     * @code
     *      $resp->call('alert', 'new info');
     *      $resp->call('location.reload');
     * @endcode
     */
    public function call()
    {
        $args = func_get_args();
        if (!empty($args))
        {
            $func = array_shift($args);
            $this->_data[] = array('call', $func, $args);
        }
    }

    /**
     * 让客户端执行一段JS脚本
     * @param   string  $src    JS脚本内容
     * @return  void
     */
    public function script($src)
    {
        $this->_data[] = array('script', $src);
    }

    /**
     * 将$this->data命令转化成json字符串
     * @return  json_string
     */
    public function __toString()
    {
        return json_encode($this->_data);
    }
}

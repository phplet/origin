# 新步伐在线测评学生虚拟币转账功能开发说明
学生账号虚拟币转账功能,主要用于与新步伐所开发的其它外部系统进行账号转账之用,请求参数为GET参数,返回json格式数据

## 1. 相关配置项
配置文件为app/config/app/setting.php,配置项如下:

```php
// 外部调用登录用户验证配置项
$config['loginverify'] = array(
    'zmcat' => array(
        'name' => '新步伐在线学习',
        'hashcode' => 'zemingtest', 
        'urlprefix' => 'http://zmcat.zeming',
        'fromid' => 1),
    'zmexam' => array(
        name' => '新步伐在线机考',
        'hashcode' => 'examtest', 
        'urlprefix' => 'http://zmexam.zeming:8080',
        'fromid' => 'zmte'),
);
```
每一个外部系统,对应一个条记录,key为外部系统标识,值为该外部系统配置项:
1. name为外部系统名称
2. hashcode为与外部系统约定的数据加密密码
3. urlprefix为外部系统的网址前缀(后面加外部系统路径,以'/'开头)
4. fromid为测评系统本身在外部系统中的来源标识

另一个配置项是交易类型,增加了一个"择明通宝转账"类型(4),
```php
//交易类型
$config['trade_type'] = array(
    '1' => '支付宝充值',
    '2' => '系统充值',
    '3' => '购买产品',
    '4' => '择明通宝转账',
);
```
## 2. 学生转账接口
### 2.1 准备请求数据
学生转账接口请求前,先要准备请求的加密数据,请使用openssl_encrypt进行加密(方法为DES-CBC,加密密码由双方约定,既hashcode),下面是一部分PHP的示例代码,以生成加密数据:
```php
$data = array('ukey' => 1321313,    // 用户标识
            'pass' => '3232323',    // 密码,转账时必须
            'amount' => 10);        // 转账金额,不可为0,转账时必须
$enc_data = openssl_encrypt(json_encode($data), 'DES-CBC', $hashcode);

```
上面的加密请求对象,包含ukey、pass、amount三个字段,说明如下:
1. ukey为string类型,表示要验证的用户标识,必填
2. pass为string类型,表示用户密码,若无该字段及auth字段,则表示获取ukey指定学生的账号余额
3. amount为int类型,表示转账金额,若有该字段,则auth或pass字段也必须有,表示转账,不可为0,为正数则表示转入,为负数则表示转出

在没有pass字段的情况下，使用auth来代替,代码如下:
```php
$data = array('ukey' => 1321313,    // 用户标识
            'amount' => 10);        // 转账金额,不可为0,转账时必须
$data['auth'] = Func::encrypt($data, $hashcode);
$enc_data = openssl_encrypt(json_encode($data), 'DES-CBC', $hashcode);

```
上面的加密请求对象,包含ukey、auth、amount三个字段,说明如下:
1. ukey为string类型,表示要验证的用户标识,必填
2. auth为string类型,表示用户标识、转账金额的加密值,若无该字段及pass字段则表示获取ukey指定学生的账号余额
3. amount为int类型,表示转账金额,若有该字段,则auth或pass段也必须有,表示转账,不可为0,为正数则表示转入,为负数则表示转出


### 2.2. 进行GET请求,及处理返回数据
学生登录验证功能对应的网址为student/index/paytrans,外部系统调用该功能时使用普通GET请求即可,错误时返回报错信息或成功时返回学生余额信息,调用方式如下：
```php
    $data = array('ukey' => '111111',
        'pass' => '323232', // 无该字段及auth字段表示只获取余额
        //'auth' => 'xxxxx', // 无该字段且无pass字段表示只取余额
        'amount' => 10);    // 无该字段表示只获取余额

    $enc_data = Func::encrypt($data, $hashcode);
    $param3 = array('from' => $from, 'data' => $enc_data);
    $req_url = 'http://student.zeming/student/index/paytrans?' . http_build_query($param3);

    $curl = curl_init($req_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data2 = curl_exec($curl);                  // 执行GET请求并返回数据
    $data2 = json_decode($data2, true);         // 将json数据转化为php数组
    if (isset($data2['error']))
    {
        throw new Exception($data2['error']);
    }
    if (!isset($data2['data']))
    {
        throw new Exception('转账操作失败');
    }
    $data2['data'] = Func::decrypt($data2['data'], $hashcode);  // 解密返回数据data
    if ($data2['data'] === false)
    {
        throw new Exception('转账操作失败,非法的返回数据');
    }
    print_r($data2['data']);
    //array('ukey' => '3232323', 'account' => 32323);
```
在上面的代码中,重要参数如下:
1. $from,即外部系统名,取测评中对应配置即$config['loginverify'][$from]
2. $hash,即双方约定的加密密码,即测评中对应配置$config['loginverify'][$from]['hashcode']
3. 返回的数据为json格式,若有error属性,则表示错误信息,若无错误时有data属性,则表示返回的数据,data则包含ukey和account两个字段,分别表示用户标识和现在的余额
4. 通过该接口在测评系统中产生的交易记录,交易类型为4,即$config['trade_type']['4'],择明通宝转账
上面的请求，最终会拼成如下形式的GET请求网址:
```html
http://student.zeming/student/index/paytrans?from={$from}&data={$enc_data}
```

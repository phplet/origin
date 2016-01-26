# 新步伐在线测评学生登录验证功能开发说明
学生登录验证功能,主要用于外部系统进行测评系统学生账号登录验证过程,为解决跨域问题使用jQuery ajax json方式来处理

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
        'name' => '新步伐在线机考',
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

## 2. 学生登录验证
### 2.1 准备请求数据
学生登录验证功能请求前,先要准备请求的加密数据,请使用openssl_encrypt进行加密(方法为DES-CBC,加密密码由双方约定,既hashcode),下面是一部分PHP端的示例代码,以生成加密数据:
```php
$data = array('ukey' => 1321313,    // 用户标识
            'pass' => '3232323',    // 密码
            'autologin' => 1);      // 是否要求自动登录
$enc_data = openssl_encrypt(json_encode($data), 'DES-CBC', $hashcode);

```
上面的加密请求对象,包含ukey、pass、autologin三个字段,说明如下:
1. ukey为string类型,表示要验证的用户标识,必填
2. pass为string类型,表示用户密码,若无该字段,则表示验证当前测评系统登录学生是否与ukey所指一致,若有该字段则表示对ukey所指用户进行密码验证
3. autologin为int类型,在进行密码验证时方有效(即pass有效),0表示不自动登录,1表示自动登录

### 2.2. 进行jQuery ajax jsonp请求
学生登录验证功能对应的网址为student/index/loginverify,外部系统调用该功能时使用jQuery ajax jsonp跨域调用进行请求,错误时返回报错信息或成功时返回学生信息,调用方式如下：
```javascript
    jQuery.ajax({
        type: 'GET',
        url: 'http://student.zeming/student/index/loginverify',
        data: {from: 'zmexam', data:'enc_data'},
        dataType: "jsonp",
        jsonp : "callbackparam",
        jsonpCallback : "fnOnLoginVerifyResponse"
    });

```
在上面的代码中,重要参数如下:
1. url,即请求网址
2. data,即请求数据,请求数据包含两部分, from填入外部系统标识, data填入加密后的数据(即enc_data代指)
3. jsonp,为回调JS函数字段名(这里请保持不变)
4. jsonpCallback为回调函数
上面的请求，最终会拼成如下形式的GET请求网址:
```html
http://student.zeming/student/index/loginverify?callbackparam=fnOnLoginVerifyResponse&from=zmexam&data=enc_data
```

### 2.3 JS接收请求返回数据
loginverify请求执行后,会返回一个对象(error或data)表示错误信息或加密数据,需要实现JS回调函数:
```javascript
function fnOnLoginVerifyResponse(response_data)
{
    if (response_data['error'])
    {
        alert(response_data['error']);
    }
    if (response_data['data'])
    {
        alert('这是加密数据:' + response_data['data']);
    }
}
```
### 2.4 解密请求返回数据
若返回的是加密数据,则加密数据解密后是验证通过的用户信息(以PHP示例):
```php
$json_str = openssl_decrypt($response_data['data'], 'DES-CBC', $hashcode);
if ($json_str === false)
{
    die('解密失败');
}
$param = json_decode($json_str, true);
```
上面的代码中将加密字符串$data解密成$param关联数组,里面包含字段如下:
1. ukey,string类型,表示返回的用户标识
2. username,string类型,表示用户名
3. fullname,string类型,表示全称
4. email,string类型,表示电子邮箱
5. grade,int类型,表示年级
6. gender,int类型,表示性别0未对知,1男,2女

```php
$param = array('ukey' => 3232323,
            'username' => '3232323',
            'fullname' => 'fsssss',
            'email' => 'myemail@111.com',
            'grade' => 9,
            'gender' => 1);
```

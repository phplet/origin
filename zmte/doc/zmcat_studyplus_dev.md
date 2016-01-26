# 启用学习网继续学习功能

## 1. 开启与关闭继续学习跳转功能
在配置文件settings.php里有下面的配置项,判断该选项是否为真即跳转:
```
// 是否开启学习网继续学习功能
$config['zmcat_studyplus_enabled'] = false;
```

## 2. 跳转网址参数说明
统一跳转网址为student/index/studyplus
参数都加成GET参数,比如

    http://student.zeming/student/index/studyplus?k_zmtekid=12&kp_name=xxxx

下面是三种参数组合:
1. 传递知识点
    - k_zmtekid     知识点ID,与k_zmtekpid至少有一个
    - k_zmtekpid    知识点父ID,与k_zmtekid至少有一个
    - kp_name       认知过程名,多个用英文逗号分隔开，可选

2. 传递方法策略/信息提取方式
    - subject_name  学科名称，必须，只能有一个
    - ms_name       方法策略/信息提取方式，必须，只能有一个

3. 传递题型
    - subject_name    学科名称，必须，只能有一个
    - questype_name   题型名称，必须,只能有一个

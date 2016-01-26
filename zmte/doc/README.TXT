布署注意:
1. 原html/admin/js/common/address.js文件移到了cache/address.js,所以linux下需要
   建软链接:
   $ln -s /xxx/zmte/cache/address.js /xxx/zmte/html/admin/js/common/address.js
   windows下则自己将zmte/cache/address.js拷贝一份放到zmte/html/admin/js/common
   目录下即可
2. 配置文件修改:
   app/config目录下的config.db.php config.php redis.php memcache.php,都从原各自
   的sample文件复制改名过来修改即可
3. 需修改权限的cache目录,在linux下则建zmte_cache（与zmte在同一目录下），然后建软
   链接zmte/cache到zmte_cache，并将zmte_cache目录修改为777
4. 修改app/config/app/目录下的settings.php webconfig.php权限为666
5. 需要建软链接zmte/html/admin/source指向zmstatic目录，因为百度编辑器上传图片
   要用到这个目录
   

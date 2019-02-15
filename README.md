# swagger-php to yapi
## 介绍
- 根据代码中的[swagger-php](http://zircote.com/swagger-php/)注释,生成接口文档, 导入[yapi](https://yapi.ymfe.org/)中

## 安装

```
composer require zircote/swagger-php
```

## 使用

php 7.0+  
运行 index.php  


## 说明
### !! 需正确填写配置项 !! 参考代码注释
> index.php
```
class SwaggerToYapi
{
    const LOG_PATH = '/home/work/logs/swagger-php/logs/';//日志路径
    const YAPI_DOMAIN = 'http://127.0.0.1';//yapi域名
    const IMPORT_API_URL = '/api/open/import_data';//导入文档接口地址 wiki https://yapi.ymfe.org/openapi.html
    const PROJECT_PATH = '/home/work/www/swagger-php/projects/';//项目仓库路径

    //项目列表
    const PROJECT_LIST = [
        'projectName' => ['token' => '2c1cc61593f66101e13f', 'path' => self::PROJECT_PATH . 'projectName/home/controllers']
    ];
    
```

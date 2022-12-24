http任务读取服务

需将 config.example.php 作变量替换并覆盖至 config.php

配置说明：

```bash
@RABBITMQ_HOST@ : RabbitMQ 地址
@RABBITMQ_PORT@ : RabbitMQ 端口
@RABBITMQ_USERNAME@ : RabbitMQ 用户名
@RABBITMQ_PASSWORD@ : RabbitMQ 密码
@RABBITMQ_HTTPPORT@ : RabbitMQ Http端口
```

启动服务:

php httpclient.php
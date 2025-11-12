# 定制化的流程解析器
> 根据公司的业务封装的一个流程图解析以及推荐流程的一个解析器

# 计划列表
- [x] 支持自定义的流程节点
- [x] 支持自定义的流程变量
- [x] 支持自定义的流程任务
- [x] 支持自定义的流程配置以及任务配置
- [x] 管理端流程模型 api接口
- [x] 管理端流程实例 api接口
- [x] 管理端流程任务 api接口
- [x] 管理端流程变量 api接口
- [x] 非管理端 相关流程模型 api接口

实现方式： 管理端 api控制器和 非管理端 Service
用户信息： 发起人信息、表单输入信息、流程相关操作

流程图：
``` mermaid
graph TD

B --> C{Let me think}A[Christmas] -->|Get money| B(Go shopping)
C -->|One| D[Laptop]
C -->|Two| E[iPhone]
C -->|Three| F[fa:fa-car Car]
```

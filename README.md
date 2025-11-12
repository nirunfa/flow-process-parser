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

该扩展是结合公司业务定制化的一个 [jsonFlow](https://gitee.com/nirunfa/smart-flow-design) 的 json 解析器以及 jsonFlow 流程便利工具
内置了一些相关的数据表：
1. 存储 json 及相关的模型表（名称包含Design）
2. 模型关联的分类表 (category)
3. 模型关联的分组表 (group)
4. flow 的 json 解析生成的节点及相关其他（如：表单）表 (node)
5. flow 流程实例 (instance)
6. flow 流程任务 (task)


流程图：
``` mermaid
graph TD

B --> C{Let me think}A[Christmas] -->|Get money| B(Go shopping)
C -->|One| D[Laptop]
C -->|Two| E[iPhone]
C -->|Three| F[fa:fa-car Car]
```

# 自定义 Job 类扩展指南

本文档说明如何在引用 `FlowProcessParser` 组件的项目中自定义 Job 类的逻辑。

## 扩展方式

组件提供了三种扩展方式：

### 方式一：继承并重写方法（推荐）

继承 `JsonNodeParserJob` 类并重写 `protected` 方法来自定义逻辑。

**示例：**

```php
<?php

namespace App\Jobs;

use Nirunfa\FlowProcessParser\Jobs\JsonNodeParserJob;
use Nirunfa\FlowProcessParser\Models\NProcessNode;

class CustomJsonNodeParserJob extends JsonNodeParserJob
{
    /**
     * 重写节点解析方法，添加自定义逻辑
     */
    protected function combineChildNode($orgNodeData, $preProcessNode, $path, $isBranchChild = 0)
    {
        // 调用父类方法
        parent::combineChildNode($orgNodeData, $preProcessNode, $path, $isBranchChild);
        
        // 添加自定义逻辑
        // 例如：记录日志、发送通知等
    }
    
    /**
     * 重写关联数据保存方法
     */
    protected function flushRelationData()
    {
        // 自定义保存逻辑
        // 或调用父类方法
        parent::flushRelationData();
    }
    
    /**
     * 重写批量更新方法
     */
    protected function flushNextNodeUpdates()
    {
        // 自定义更新逻辑
        parent::flushNextNodeUpdates();
    }
    
    /**
     * 重写 null 节点更新方法
     */
    protected function batchUpdateNullNodes($nullNextNodeNodes, $branchNodes)
    {
        // 自定义 null 节点处理逻辑
        parent::batchUpdateNullNodes($nullNextNodeNodes, $branchNodes);
    }
}
```

**在配置文件中注册：**

```php
// config/process_parser.php
return [
    'json_parser' => [
        'custom_job' => \App\Jobs\CustomJsonNodeParserJob::class,
    ],
];
```

### 方式二：监听事件

组件在关键节点触发了事件，可以通过监听这些事件来自定义行为。

**可用事件：**

- `NodeParsingStarted` - 节点解析开始
- `NodeParsingCompleted` - 节点解析完成
- `NodeCreating` - 节点创建前（可修改节点数据）
- `NodeCreated` - 节点创建后
- `RelationDataSaving` - 关联数据保存前（可修改数据）

**示例：**

```php
<?php

namespace App\Listeners;

use Nirunfa\FlowProcessParser\Events\NodeParsing\NodeCreated;
use Nirunfa\FlowProcessParser\Events\NodeParsing\NodeParsingCompleted;
use Illuminate\Support\Facades\Log;

class NodeParsingListener
{
    /**
     * 节点创建后处理
     */
    public function handleNodeCreated(NodeCreated $event)
    {
        $node = $event->processNode;
        Log::info("节点已创建: {$node->name} (ID: {$node->id})");
        
        // 添加自定义逻辑
        // 例如：发送通知、更新缓存等
    }
    
    /**
     * 解析完成后处理
     */
    public function handleParsingCompleted(NodeParsingCompleted $event)
    {
        Log::info("流程解析完成: design_id={$event->designId}, ver={$event->ver}");
        
        // 添加自定义逻辑
    }
}
```

**在 EventServiceProvider 中注册：**

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Nirunfa\FlowProcessParser\Events\NodeParsing\NodeCreated;
use Nirunfa\FlowProcessParser\Events\NodeParsing\NodeParsingCompleted;
use App\Listeners\NodeParsingListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        NodeCreated::class => [
            NodeParsingListener::class . '@handleNodeCreated',
        ],
        NodeParsingCompleted::class => [
            NodeParsingListener::class . '@handleParsingCompleted',
        ],
    ];
}
```

### 方式三：实现接口

实现 `JsonNodeParserJobInterface` 接口，完全自定义 Job 逻辑。

**示例：**

```php
<?php

namespace App\Jobs;

use Nirunfa\FlowProcessParser\Contracts\JsonNodeParserJobInterface;

class FullyCustomJsonNodeParserJob implements JsonNodeParserJobInterface
{
    private $designId;
    private $ver;
    private $isPublish;
    
    public function __construct($designId, $ver,$isPublish)
    {
        $this->designId = $designId;
        $this->ver = $ver;
        $this->isPublish = $isPublish;
    }
    
    public function handle()
    {
        // 完全自定义的解析逻辑
    }
}
```

**在配置文件中注册：**

```php
// config/process_parser.php
return [
    'json_parser' => [
        'custom_job' => \App\Jobs\FullyCustomJsonNodeParserJob::class,
    ],
];
```

## 可重写的方法

以下 `protected` 方法可以在子类中重写：

- `combineChildNode()` - 节点解析核心方法
- `flushRelationData()` - 批量保存关联数据
- `flushNextNodeUpdates()` - 批量更新 next_node_id
- `batchUpdateNullNodes()` - 批量更新 null 节点

## 事件说明

### NodeParsingStarted
节点解析开始事件

```php
use Nirunfa\FlowProcessParser\Events\NodeParsing\NodeParsingStarted;

event(new NodeParsingStarted($designId, $ver, $orgNodeData));
```

### NodeParsingCompleted
节点解析完成事件

```php
use Nirunfa\FlowProcessParser\Events\NodeParsing\NodeParsingCompleted;

event(new NodeParsingCompleted($designId, $ver, $orgNodeData));
```

### NodeCreating
节点创建前事件，可以修改 `$initNode` 数据

```php
use Nirunfa\FlowProcessParser\Events\NodeParsing\NodeCreating;

event(new NodeCreating($designId, $ver, $orgNodeData, $initNode));
```

### NodeCreated
节点创建后事件

```php
use Nirunfa\FlowProcessParser\Events\NodeParsing\NodeCreated;

event(new NodeCreated($designId, $ver, $processNode));
```

### RelationDataSaving
关联数据保存前事件，可以修改 `$relationDataQueue`

```php
use Nirunfa\FlowProcessParser\Events\NodeParsing\RelationDataSaving;

event(new RelationDataSaving($designId, $ver, $relationDataQueue));
```

## 最佳实践

1. **优先使用事件**：对于简单的扩展（如日志、通知），使用事件监听器
2. **继承重写**：对于需要修改核心逻辑的场景，继承并重写方法
3. **完全自定义**：只有在需要完全不同的解析逻辑时，才实现接口

## TaskDirectionJob 扩展

`TaskDirectionJob` 用于处理任务走向，同样支持三种扩展方式。

### 方式一：继承并重写方法（推荐）

**示例：**

```php
<?php

namespace App\Jobs;

use Nirunfa\FlowProcessParser\Jobs\TaskDirectionJob;
use Nirunfa\FlowProcessParser\Models\NProcessNode;

class CustomTaskDirectionJob extends TaskDirectionJob
{
    /**
     * 重写节点检查方法，添加自定义逻辑
     */
    protected function nodeCheck($nextNode, $taskVariables, $instanceVariables)
    {
        // 调用父类方法
        $result = parent::nodeCheck($nextNode, $taskVariables, $instanceVariables);
        
        // 添加自定义逻辑
        // 例如：记录日志、特殊条件判断等
        
        return $result;
    }
    
    /**
     * 重写分支节点检查方法
     */
    protected function branchNodeCheck($branchNode, $taskVariables, $instanceVariables)
    {
        // 自定义分支检查逻辑
        return parent::branchNodeCheck($branchNode, $taskVariables, $instanceVariables);
    }
    
    /**
     * 重写条件节点检查方法
     */
    protected function conditionNodeCheck($conditionNode, $taskVariables, $instanceVariables)
    {
        // 自定义条件检查逻辑
        return parent::conditionNodeCheck($conditionNode, $taskVariables, $instanceVariables);
    }
}
```

**在配置文件中注册：**

```php
// config/process_parser.php
return [
    'json_parser' => [
        'custom_task_direction_job' => \App\Jobs\CustomTaskDirectionJob::class,
    ],
];
```

### 方式二：监听事件

**可用事件：**

- `TaskDirectionStarted` - 任务走向处理开始
- `TaskDirectionCompleted` - 任务走向处理完成
- `NodeChecking` - 节点检查前
- `NodeChecked` - 节点检查后
- `NewTaskCreating` - 新任务创建前（可修改任务数据）
- `NewTaskCreated` - 新任务创建后

**示例：**

```php
<?php

namespace App\Listeners;

use Nirunfa\FlowProcessParser\Events\TaskDirection\NewTaskCreated;
use Nirunfa\FlowProcessParser\Events\TaskDirection\TaskDirectionCompleted;
use Illuminate\Support\Facades\Log;

class TaskDirectionListener
{
    /**
     * 新任务创建后处理
     */
    public function handleNewTaskCreated(NewTaskCreated $event)
    {
        $task = $event->newTask;
        Log::info("新任务已创建: {$task->name} (ID: {$task->id})");
        
        // 添加自定义逻辑
        // 例如：发送通知、更新缓存等
    }
    
    /**
     * 任务走向处理完成后处理
     */
    public function handleTaskDirectionCompleted(TaskDirectionCompleted $event)
    {
        Log::info("任务走向处理完成: task_id={$event->taskId}");
        
        // 添加自定义逻辑
    }
}
```

**在 EventServiceProvider 中注册：**

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Nirunfa\FlowProcessParser\Events\TaskDirection\NewTaskCreated;
use Nirunfa\FlowProcessParser\Events\TaskDirection\TaskDirectionCompleted;
use App\Listeners\TaskDirectionListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        NewTaskCreated::class => [
            TaskDirectionListener::class . '@handleNewTaskCreated',
        ],
        TaskDirectionCompleted::class => [
            TaskDirectionListener::class . '@handleTaskDirectionCompleted',
        ],
    ];
}
```

### 方式三：实现接口

**示例：**

```php
<?php

namespace App\Jobs;

use Nirunfa\FlowProcessParser\Contracts\TaskDirectionJobInterface;

class FullyCustomTaskDirectionJob implements TaskDirectionJobInterface
{
    private $taskId;
    
    public function __construct($taskId)
    {
        $this->taskId = $taskId;
    }
    
    public function handle()
    {
        // 完全自定义的任务走向逻辑
    }
}
```

**在配置文件中注册：**

```php
// config/process_parser.php
return [
    'json_parser' => [
        'custom_task_direction_job' => \App\Jobs\FullyCustomTaskDirectionJob::class,
    ],
];
```

### TaskDirectionJob 可重写的方法

以下 `protected` 方法可以在子类中重写：

- `nodeCheck()` - 节点检查核心方法
- `branchNodeCheck()` - 分支节点检查
- `conditionNodeCheck()` - 条件节点检查

### TaskDirectionJob 事件说明

#### TaskDirectionStarted
任务走向处理开始事件

```php
use Nirunfa\FlowProcessParser\Events\TaskDirection\TaskDirectionStarted;

event(new TaskDirectionStarted($taskId, $task));
```

#### TaskDirectionCompleted
任务走向处理完成事件

```php
use Nirunfa\FlowProcessParser\Events\TaskDirection\TaskDirectionCompleted;

event(new TaskDirectionCompleted($taskId, $task, $nextNode, $newTask));
```

#### NodeChecking
节点检查前事件

```php
use Nirunfa\FlowProcessParser\Events\TaskDirection\NodeChecking;

event(new NodeChecking($taskId, $task, $nextNode, $taskVariables, $instanceVariables));
```

#### NodeChecked
节点检查后事件

```php
use Nirunfa\FlowProcessParser\Events\TaskDirection\NodeChecked;

event(new NodeChecked($taskId, $task, $nextNode, $result));
```

#### NewTaskCreating
新任务创建前事件，可以修改 `$taskData`

```php
use Nirunfa\FlowProcessParser\Events\TaskDirection\NewTaskCreating;

event(new NewTaskCreating($taskId, $task, $nextNode, $taskData));
```

#### NewTaskCreated
新任务创建后事件

```php
use Nirunfa\FlowProcessParser\Events\TaskDirection\NewTaskCreated;

event(new NewTaskCreated($taskId, $task, $newTask));
```

## 注意事项

1. 自定义 Job 类必须实现对应的接口（`JsonNodeParserJobInterface` 或 `TaskDirectionJobInterface`）
2. 如果继承 Job 类，确保调用 `parent::` 方法以保持核心功能
3. 事件监听器中的修改会影响后续处理，请谨慎操作
4. 自定义类需要与组件版本兼容，升级时注意检查
5. 使用 `createJsonNodeParserJob()` 和 `createTaskDirectionJob()` 辅助函数创建 Job 实例，而不是直接 `new`


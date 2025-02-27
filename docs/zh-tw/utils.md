# 輔助類

Hyperf 提供了大量便捷的輔助類，這裡會列出一些常用的好用的，不會列舉所有，可自行檢視 [hyperf/utils](https://github.com/hyperf/utils) 元件的程式碼獲得更多資訊。

## 協程輔助類

### Hyperf\Utils\Coroutine

該輔助類用於協助進行協程相關的判斷或操作。

#### id(): int

通過靜態方法 `id()` 獲得當前所處的 `協程 ID`，如當前不處於協程環境下，則返回 `-1`。 

#### create(callable $callable): int

通過靜態方法 `create(callable $callable)` 可建立一個協程，還可以通過全域性函式 `co(callable $callable)` 或 `go(callable $callable)` 達到同樣的目的，該方法是對 `Swoole` 建立協程方法的一個封裝，區別在於不會丟擲未捕獲的異常，未捕獲的異常會通過 `Hyperf\Contract\StdoutLoggerInterface` 以 `warning` 等級輸出。

#### inCoroutine(): bool

通過靜態方法 `inCoroutine()` 判斷當前是否處於協程環境下。

### Hyperf\Utils\Context

用於處理協程上下文，本質上是對 `Swoole\Coroutine::getContext()` 方法的一個封裝，但區別在於這裡相容了非協程環境下的執行。

### Hyperf\Utils\Coordinator\CoordinatorManager

該輔助類用於指揮協程等待事件發生。

```php
<?php
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coordinator\Constants;
use Hyperf\Utils\Coroutine;

Coroutine::create(function() {
    // 所有OnWorkerStart事件回撥完成後喚醒
    CoordinatorManager::until(Constants::WORKER_START)->yield();
    echo 'worker started';
    // 分配資源
    // 所有OnWorkerExit事件回撥完成後喚醒
    CoordinatorManager::until(Constants::WORKER_EXIT)->yield();
    echo 'worker exited';
    // 回收資源
});
```

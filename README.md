# Timer
### Таймеры для PHP 8.0.0+<br><br>

Советую открыть **`Timer.php`** и почитать описания методов

<br><br>
## Ограничения PHP
1. **ВСЕГДА используйте `declare(ticks = 1)`, иначе таймеры не будут выполняться**
2. Вам придётся использовать `Timer::wait()` для ожидания ещё не выполненных таймеров, иначе они будут удалены при уничтожении объекта таймера

<br><br>
## Пример использования
```php
// подключение Timer
require('Timer/Timer.php');

function timer1(): void
{
    echo('timer1: '.microtime(true).PHP_EOL);
}

function timer2(Timer $t): void
{
    echo('timer2: '.microtime(true).PHP_EOL);
    echo('таймеров в очереди: '.$t->count().PHP_EOL);
}

declare(ticks = 1)
{

// создание объекта таймера
$t = new Timer();

// добавление выполнения callback функции через 1 секунду
$t->add(1, 'timer');

// добавление выполнения callback функции через 0.5 секунды
$t->add(0.5, 'timer2', $t);

// ожидание выполнения всех callback функций
Timer::wait($t);

}
```

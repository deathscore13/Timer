<?php

/**
 * Timer
 * 
 * Таймеры для PHP 8.0.0+
 * https://github.com/deathscore13/Timer
 */

class Timer
{
    private int $scale = 6;
    private int $hashLen = 8;

    private array $times = [];
    private int $count = 0;

    private array $callbacks = [];
    private array $args = [];

    private array $hashes = [];
    private array $seconds = [];

    public function __construct()
    {
        register_tick_function([$this, 'check']);
    }
    
    /**
     * Вернёт предупреждение, если таймеры не были завершены
     * Используйте @$timer = null для игнорирования
     */
    public function __destruct()
    {
        if ($this->count)
            trigger_error('Some timers did not expire ('.$this->count.') and were removed', E_USER_WARNING);
        
        unregister_tick_function([$this, 'check']);
    }

    /**
     * Установка максимального количества знаков после запятой
     * По умолчанию 6
     * 
     * @param ?int $scale               Новое количество знаков после запятой или null чтобы вернуть текущее
     * 
     * @return int                      Текущее значение
     */
    public function scale(?int $scale = null): int
    {
        if ($scale !== null)
            $this->scale = $scale;
        
        return $this->scale;
    }

    /**
     * Установка размера хеша (1 и больше)
     * По умолчанию 8
     * 
     * @param ?int $hashLen             Новый размер хеша или null чтобы вернуть текущее
     * 
     * @return int                      Текущее значение
     */
    public function hashLen(?int $hashLen = null): int
    {
        if ($hashLen !== null)
            $this->hashLen = $hashLen;
        
        return $this->hashLen;
    }

    /**
     * Вызов callback функций у которых истекло время
     */
    public function check(): void
    {
        if (!$this->count)
            return;
        
        $i = -1;
        $time = microtime(true);
        while (++$i < $this->count && bccomp($this->times[$i], $time, $this->scale) < 1)
            $this->callbacks[$i](...$this->args[$i]);
        
        if ($i)
        {
            array_splice($this->times, 0, $i);
            array_splice($this->callbacks, 0, $i);
            array_splice($this->args, 0, $i);
            array_splice($this->hashes, 0, $i);
            array_splice($this->seconds, 0, $i);
            
            $this->count -= $i;
        }
    }

    /**
     * Добавление callback функции в очередь
     * 
     * @param float $seconds            Время в секундах, через которое будет вызвана функция
     * @param callable $callback        Callback функция
     * @param mixed &...$args           Передаваемые параметры (можно использовать ссылки)
     * 
     * @return ?string                  Уникальный хеш таймера для текущего объекта или null если $seconds <= 0
     */
    public function add(float $seconds, callable $callback, mixed &...$args): ?string
    {
        if ($seconds <= 0)
        {
            $callback(...$args);
            return null;
        }

        $i = -1;
        $time = bcadd(microtime(true), $seconds, $this->scale);
        while (isset($this->times[++$i]) && bccomp($this->times[$i], $time, $this->scale) < 1)
            continue;
        
        array_splice($this->times, $i, 0, [$time]);
        array_splice($this->callbacks, $i, 0, [$callback]);
        array_splice($this->args, $i, 0, [$args]);

        while (in_array(($hash = random_bytes($this->hashLen)), $this->hashes))
            continue;
        
        array_splice($this->hashes, $i, 0, [$hash]);
        array_splice($this->seconds, $i, 0, [$seconds]);

        $this->count++;
        return $hash;
    }

    /**
     * Удаление callback функции из очереди
     * 
     * @param string $hash              Хеш таймера
     */
    private function remove(string $hash): void
    {
        $i = -1;
        while (isset($this->hashes[++$i]))
        {
            if ($this->hashes[$i] === $hash)
            {
                array_splice($this->times, $i, 1);
                array_splice($this->callbacks, $i, 1);
                array_splice($this->args, $i, 1);
                array_splice($this->hashes, $i, 1);
                array_splice($this->seconds, $i, 1);

                $this->count--;
                return;
            }
        }
    }

    /**
     * Возвращает данные о задержке и времени выполнения таймера
     * Формат: ['seconds' => 'задержка', 'time' => 'время выполнения']
     * 
     * @param string $hash              Хеш таймера
     * 
     * @return array|false              Массив с данными или false если таймер не найден
     */
    public function seconds(string $hash): array|false
    {
        $i = -1;
        while (isset($this->hashes[++$i]))
        {
            if ($this->hashes[$i] === $hash)
            {
                return [
                    'seconds' => $this->seconds[$i],
                    'time' => $this->times[$i]
                ];
            }
        }

        return false;
    }

    /**
     * Возвращает статус выполнения callback функции
     * 
     * @param string $hash              Хеш таймера
     * 
     * @param bool                      true если не в очереди, false если ожидает выполнения
     */
    public function status(string $hash): bool
    {
        $i = -1;
        while (isset($this->hashes[++$i]))
        {
            if ($this->hashes[$i] === $hash)
                return false;
        }

        return true;
    }

    /**
     * Возвращает количество callback функций в очереди
     * 
     * @param int                       Количество callback функций в очереди
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Выполнение callback функции сейчас
     * 
     * @param string $hash              Хеш таймера
     */
    public function force(string $hash): void
    {
        $i = -1;
        while (isset($this->hashes[++$i]))
        {
            if ($this->hashes[$i] === $hash)
            {
                $this->callbacks[$i](...$this->args[$i]);

                array_splice($this->times, $i, 1);
                array_splice($this->callbacks, $i, 1);
                array_splice($this->args, $i, 1);
                array_splice($this->hashes, $i, 1);
                array_splice($this->seconds, $i, 1);
            }
        }
    }

    /**
     * Ожидание выполнения всех callback функций
     * НЕ ИСПОЛЬЗУЙТЕ ЭТО ПРИ declare(ticks = 0)
     * 
     * @param Timer $timer              Объект таймера
     */
    public static function wait(Timer $timer): void
    {
        $i = PHP_INT_MIN;
        while ($timer->count())
        {
            if ($i === PHP_INT_MAX)
                $i = PHP_INT_MIN;
            
            $i += 1;
        }
    }

    /**
     * Ожидание выполнения callback функции
     * НЕ ИСПОЛЬЗУЙТЕ ЭТО ПРИ declare(ticks = 0)
     * 
     * @param Timer $timer              Объект таймера
     * @param string $hash              Хеш таймера
     */
    public static function waitEx(Timer $timer, string $hash): void
    {
        $i = PHP_INT_MIN;
        while (!$timer->status($hash))
        {
            if ($i === PHP_INT_MAX)
                $i = PHP_INT_MIN;
            
            $i += 1;
        }
    }
}

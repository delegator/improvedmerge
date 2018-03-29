<?php

class Delegator_Improvedmerge_Model_Bench
{
    protected $startTime;
    protected $endTime;

    public function start()
    {
        $this->startTime = microtime(true);
    }

    public function end()
    {
        $this->endTime = microtime(true);
    }

    public function getTime()
    {
        $diff = $this->endTime - $this->startTime;

        // Seconds
        if ($diff >= 1) {
            $time = round($diff, 3);
            return sprintf('%.3f%s', $time, 's');
        }

        // Milliseconds
        $time = round($diff * 1000);
        return sprintf('%d%s', $time, 'ms');
    }
}

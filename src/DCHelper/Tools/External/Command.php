<?php

namespace DCHelper\Tools\External;

use Symfony\Component\Process\Process;

abstract class Command
{
    protected $mustRun  = true;
    protected $passthru = false;
    protected $tty      = false;
    public    $exit;
    public    $output;

    abstract public function run(...$arguments): string;

    public function mustRun($status = true): self
    {
        $this->mustRun = $status;
        return $this;
    }

    public function passthru($status = true): self
    {
        $this->passthru = $status;
        return $this;
    }

    public function tty($status = true): self
    {
        $this->tty = $status;
        return $this;
    }

    protected function _execute($commandLine): string
    {
        $process = new Process($commandLine);

        // If needed, request a TTY
        $process->setTty($this->tty);
        // Disable timeout
        $process->setTimeout(0);

        $callback = $this->passthru ? function($type, $data) {
            echo $data;
        } : null;

        $this->mustRun ? $process->mustRun($callback) : $process->run($callback);

        $this->exit   = $process->getExitCode();
        $this->output = $process->getOutput();
        return $this->output;
    }
}
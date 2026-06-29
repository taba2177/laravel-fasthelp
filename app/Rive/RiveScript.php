<?php

namespace App\Rive;

class RiveScript
{
    private $replies = [];

    public function load(string $path): void
    {
        if (is_dir($path)) {
            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            $files = [];
            foreach ($rii as $file) {
                if ($file->isDir()) {
                    continue;
                }
                if (pathinfo($file->getPathname(), PATHINFO_EXTENSION) === 'rive') {
                    $files[] = $file->getPathname();
                }
            }
            foreach ($files as $file) {
                $this->parseFile($file);
            }
        } elseif (file_exists($path)) {
            $this->parseFile($path);
        }
    }

    private function parseFile(string $path): void
    {
        $content = file_get_contents($path);
        $lines = explode("\n", $content);

        $currentTrigger = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if (strpos($line, '+') === 0) {
                $currentTrigger = trim(substr($line, 1));
                $this->replies[$currentTrigger] = [];
            } elseif (strpos($line, '-') === 0) {
                if ($currentTrigger) {
                    $this->replies[$currentTrigger][] = trim(substr($line, 1));
                }
            }
        }
    }

    public function reply(string $message): string
    {
        $message = strtolower(trim($message));

        if (isset($this->replies[$message])) {
            $responses = $this->replies[$message];
            return $responses[array_rand($responses)];
        }

        return 'I don\'t have a reply for that.';
    }
}

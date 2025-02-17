<?php
namespace Fawaz\Services;

class FileReaderService
{
  public function getFileContents(string $path): string|false {
    return file_get_contents($path);
}

}

<?php

namespace Fawaz\Config\ResponseCodes;

interface ResponseCodesSection {
    public function message(): string;
    public function code(): int;
}
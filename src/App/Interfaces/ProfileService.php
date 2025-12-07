<?php

namespace Fawaz\App\Interfaces;

use Fawaz\App\Profile;
use Fawaz\GraphQL\Context;
use Fawaz\Utils\ErrorResponse;

interface ProfileService
{
    public function profile(array $args, Context $ctx): Profile | ErrorResponse;
    public function listUsers(array $args, Context $ctx): array | ErrorResponse;
    public function listUsersAdmin(array $args, Context $ctx): array | ErrorResponse;
    public function searchUser(array $args, Context $ctx): array;
    public function userReferralList(array $args, Context $ctx): array;
}
